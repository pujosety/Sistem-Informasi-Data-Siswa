<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceFileShare;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Read-only sharing is intentionally separate from Drive ownership and Gates. */
class WorkspaceFileShareService
{
    public const MAX_EXPIRY_DAYS = 90;

    public function __construct(private readonly SchoolContext $schools, private readonly AuditService $audit) {}

    private function schoolId(User $actor): int
    {
        $school = $this->schools->current();
        $active = User::query()->whereKey($actor->id)->where('is_active', true)->exists();
        abort_unless($active && $school && $school->is_active, 404);

        return (int) $school->id;
    }

    /**
     * Users, employees, classes and guardian links have no tenant column.
     * Only explicit LMS school links prove membership: assigned course teacher,
     * or active student enrollment (both enrollment AND course school must match).
     * Roles, active-school selection and owning a Drive file do NOT prove membership.
     */
    public function eligibleUsers(int $schoolId): Builder
    {
        return User::query()->where('is_active', true)->where(function (Builder $users) use ($schoolId): void {
            $users->whereExists(function ($query) use ($schoolId): void {
                $query->selectRaw('1')->from('lms_courses as member_courses')
                    ->whereColumn('member_courses.teacher_id', 'users.id')
                    ->where('member_courses.school_id', $schoolId)
                    ->whereIn('member_courses.status', ['draft', 'published']);
            })->orWhereExists(function ($query) use ($schoolId): void {
                $query->selectRaw('1')->from('students as member_students')
                    ->join('lms_course_enrollments as member_enrollments', 'member_enrollments.student_id', '=', 'member_students.id')
                    ->join('lms_courses as student_courses', 'student_courses.id', '=', 'member_enrollments.course_id')
                    ->whereColumn('member_students.user_id', 'users.id')
                    ->where('member_enrollments.school_id', $schoolId)
                    ->where('student_courses.school_id', $schoolId)
                    ->where('member_enrollments.status', 'active')
                    ->whereIn('student_courses.status', ['draft', 'published']);
            });
        });
    }

    public function ownedFile(User $owner, string $publicId, bool $includeTrash = false): WorkspaceFile
    {
        $schoolId = $this->schoolId($owner);
        $query = WorkspaceFile::query()->where('school_id', $schoolId)->where('owner_id', $owner->id)->where('public_id', $publicId);
        if ($includeTrash) {
            $query->withTrashed();
        }

        return $query->firstOrFail();
    }

    public function sharesFor(User $owner, string $fileId)
    {
        $file = $this->ownedFile($owner, $fileId);

        return WorkspaceFileShare::query()->where('school_id', $file->school_id)
            ->where('workspace_file_id', $file->id)->where('owner_id', $owner->id)
            ->with('recipient')->latest('id')->paginate(30);
    }

    public function grant(User $owner, string $fileId, string $recipientEmail, ?CarbonImmutable $expiry): WorkspaceFileShare
    {
        $schoolId = $this->schoolId($owner);
        if ($expiry && ($expiry->lessThanOrEqualTo(now()) || $expiry->greaterThan(now()->addDays(self::MAX_EXPIRY_DAYS)))) {
            throw ValidationException::withMessages(['expires_at' => 'Kedaluwarsa harus di masa depan, maksimal 90 hari.']);
        }

        return DB::transaction(function () use ($owner, $fileId, $recipientEmail, $expiry, $schoolId): WorkspaceFileShare {
            $file = $this->ownedFile($owner, $fileId);
            $file = WorkspaceFile::query()->whereKey($file->id)->where('school_id', $schoolId)
                ->where('owner_id', $owner->id)->lockForUpdate()->firstOrFail();
            // SQL collations may ignore case/trailing spaces. Require literal account identity.
            $candidates = $this->eligibleUsers($schoolId)->where('email', $recipientEmail)->get();
            $exact = $candidates->filter(fn (User $user) => $user->email === $recipientEmail);
            if ($exact->count() !== 1) {
                throw ValidationException::withMessages(['recipient_email' => 'Penerima harus akun lain yang aktif di sekolah ini.']);
            }
            $recipient = $exact->first();
            if ((int) $recipient->id === (int) $owner->id) {
                throw ValidationException::withMessages(['recipient_email' => 'Penerima harus akun lain yang aktif di sekolah ini.']);
            }
            // Serialize grants on the file row; preserve revoked UUIDs forever.
            $prior = WorkspaceFileShare::query()->where('school_id', $schoolId)->where('workspace_file_id', $file->id)
                ->where('recipient_id', $recipient->id)->whereNull('revoked_at')->lockForUpdate()->get();
            foreach ($prior as $share) {
                $share->update(['revoked_at' => now()]);
                $this->audit->logAs($owner->id, 'workspace.share.revoked', $share, 'Mengganti izin baca sebelumnya.');
            }
            $share = WorkspaceFileShare::create([
                'public_id' => (string) Str::uuid(), 'school_id' => $schoolId,
                'workspace_file_id' => $file->id, 'owner_id' => $owner->id,
                'recipient_id' => $recipient->id, 'expires_at' => $expiry, 'revoked_at' => null,
            ]);
            $this->audit->logAs($owner->id, 'workspace.share.granted', $share, 'Memberikan izin baca berkas.', [
                'file_public_id' => $file->public_id, 'recipient_id' => $recipient->id,
                'expires_at' => $expiry?->toIso8601String(), 'rights' => 'read-only',
            ]);

            return $share;
        });
    }

    public function revoke(User $owner, string $fileId, string $shareId): void
    {
        DB::transaction(function () use ($owner, $fileId, $shareId): void {
            $file = $this->ownedFile($owner, $fileId, true);
            WorkspaceFile::withTrashed()->whereKey($file->id)->lockForUpdate()->firstOrFail();
            $share = WorkspaceFileShare::query()->where('school_id', $file->school_id)
                ->where('workspace_file_id', $file->id)->where('owner_id', $owner->id)
                ->where('public_id', $shareId)->lockForUpdate()->firstOrFail();
            if ($share->revoked_at === null) {
                $share->update(['revoked_at' => now()]);
                $this->audit->logAs($owner->id, 'workspace.share.revoked', $share, 'Mencabut izin baca berkas.');
            }
        });
    }

    public function received(User $recipient)
    {
        $schoolId = $this->schoolId($recipient);
        abort_unless($this->eligibleUsers($schoolId)->whereKey($recipient->id)->exists(), 404);

        return WorkspaceFileShare::query()->where('school_id', $schoolId)->where('recipient_id', $recipient->id)
            ->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('file', fn ($q) => $q->where('school_id', $schoolId)->whereColumn('workspace_files.owner_id', 'workspace_file_shares.owner_id'))
            ->with(['file', 'owner'])->latest('id')->paginate(30);
    }

    /** Authorize freshly, open private current-version bytes, then audit approval. */
    public function openDownload(User $recipient, string $shareId): array
    {
        $stream = null;
        try {
            return DB::transaction(function () use ($recipient, $shareId, &$stream): array {
                $schoolId = $this->schoolId($recipient);
                abort_unless($this->eligibleUsers($schoolId)->whereKey($recipient->id)->exists(), 404);
                $share = WorkspaceFileShare::query()->where('school_id', $schoolId)
                    ->where('recipient_id', $recipient->id)->where('public_id', $shareId)->firstOrFail();
                // File-first locking agrees with grant/revoke and version/trash operations.
                $file = WorkspaceFile::query()->where('school_id', $schoolId)->where('owner_id', $share->owner_id)
                    ->whereKey($share->workspace_file_id)->lockForUpdate()->firstOrFail();
                $share = WorkspaceFileShare::query()->whereKey($share->id)->lockForUpdate()->firstOrFail();
                abort_unless($share->isActive() && (int) $share->recipient_id === (int) $recipient->id
                    && (int) $share->school_id === $schoolId && (int) $share->workspace_file_id === (int) $file->id
                    && (int) $share->owner_id === (int) $file->owner_id, 404);
                $version = $file->versions()->where('version_number', $file->current_version)->firstOrFail();
                abort_unless($version->disk === 'workspace', 404);
                app(WorkspaceMalwareScanService::class)->assertClean($version);
                $stream = Storage::disk('workspace')->readStream($version->path);
                abort_unless(is_resource($stream), 404);
                $this->audit->logAs($recipient->id, 'workspace.share.accessed', $share, 'Mengunduh versi aktif berkas bersama.', [
                    'file_public_id' => $file->public_id, 'version' => $version->version_number, 'rights' => 'read-only',
                ]);

                return [$file, $version, $stream];
            });
        } catch (\Throwable $error) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw $error;
        }
    }
}
