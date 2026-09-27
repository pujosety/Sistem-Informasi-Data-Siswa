<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Internal notification centre.
 *
 * Uses Laravel's database channel against the `notifications` table, with a
 * typed payload so the UI can link straight to the relevant resource.
 */
class NotificationService
{
    public const REGISTRATION_SUBMITTED = 'registration.submitted';
    public const DOCUMENT_REJECTED = 'document.rejected';
    public const REVISION_REQUESTED = 'revision.requested';
    public const REGISTRATION_VERIFIED = 'registration.verified';
    public const DOCUMENT_VERIFIED = 'document.verified';

    // Academic lifecycle events
    public const CLASS_ANNOUNCEMENT = 'classroom.announcement';
    public const STUDENT_ASSIGNED = 'enrollment.assigned';
    public const STUDENT_MOVED = 'enrollment.moved';
    public const CLASS_PROMOTED = 'enrollment.promoted';
    public const GRADUATED = 'enrollment.graduated';

    // -----------------------------------------------------------------
    // Dispatch
    // -----------------------------------------------------------------

    public function notifyStudent(Student $student, string $type, string $title, string $body, array $context = []): void
    {
        $user = $student->user;

        if (! $user) {
            return;
        }

        $user->notify(new \App\Notifications\SidaNotification(
            $type,
            $title,
            $body,
            array_merge(['student_id' => $student->id, 'registration_id' => $student->registration?->id], $context)
        ));
    }

    /**
     * Notify a specific user by id.
     *
     * Used by classroom-scoped events (announcements, placement, promotion)
     * where the recipients are a known set of users rather than a student.
     */
    public function notifyUser(?int $userId, string $type, string $title, string $body, array $context = []): void
    {
        if (! $userId) {
            return;
        }

        $user = User::find($userId);

        if (! $user) {
            return;
        }

        $user->notify(new \App\Notifications\SidaNotification($type, $title, $body, $context));
    }

    /**
     * Fan out to many users at once.
     *
     * @param  array<int>  $userIds
     */
    public function notifyUsers(array $userIds, string $type, string $title, string $body, array $context = []): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if ($ids === []) {
            return;
        }

        Notification::send(
            User::whereIn('id', $ids)->get(),
            new \App\Notifications\SidaNotification($type, $title, $body, $context)
        );
    }

    /** Fan out to every staff member who acts on registrations. */
    public function notifyStaff(string $type, string $title, string $body, array $context = []): void
    {
        $users = User::role(['admin', 'kesiswaan'])->whereNotNull('email_verified_at')->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new \App\Notifications\SidaNotification($type, $title, $body, $context));
    }

    public function onRegistrationSubmitted(Registration $registration): void
    {
        $this->notifyStaff(
            self::REGISTRATION_SUBMITTED,
            'Pendaftaran baru masuk',
            ($registration->student?->full_name ?? 'Seorang siswa').' telah mengirim pendaftaran.',
            ['student_id' => $registration->student_id, 'registration_id' => $registration->id]
        );
    }

    public function onRevisionRequested(Registration $registration, string $reason): void
    {
        if (! $registration->student) {
            return;
        }

        $this->notifyStudent(
            $registration->student,
            self::REVISION_REQUESTED,
            'Pendaftaran perlu diperbaiki',
            $reason,
            ['registration_id' => $registration->id, 'reason' => $reason]
        );
    }

    public function onDocumentRejected(Registration $registration, string $documentName, string $reason): void
    {
        if (! $registration->student) {
            return;
        }

        $this->notifyStudent(
            $registration->student,
            self::DOCUMENT_REJECTED,
            $documentName.' ditolak',
            $reason,
            ['registration_id' => $registration->id, 'reason' => $reason]
        );
    }

    public function onRegistrationVerified(Registration $registration): void
    {
        if (! $registration->student) {
            return;
        }

        $this->notifyStudent(
            $registration->student,
            self::REGISTRATION_VERIFIED,
            'Pendaftaran terverifikasi',
            'Seluruh berkas Anda telah disetujui. Data siswa kini tercatat resmi.',
            ['registration_id' => $registration->id, 'status' => 'verified']
        );
    }

    public function onDocumentVerified(Registration $registration, string $documentName): void
    {
        if (! $registration->student) {
            return;
        }

        $this->notifyStudent(
            $registration->student,
            self::DOCUMENT_VERIFIED,
            $documentName.' terverifikasi',
            'Berkas '.$documentName.' telah diperiksa dan disetujui.',
            ['registration_id' => $registration->id]
        );
    }

    // -----------------------------------------------------------------
    // Read routing
    // -----------------------------------------------------------------

    /**
     * Where a notification should take its reader.
     *
     * Lives here (not in the Blade view) so the notification page, the
     * mark-as-read endpoint and any future API all agree.
     */
    public function destinationFor(array $data, User $user): string
    {
        $context = $data['context'] ?? [];

        if ($user->hasRole('admin')) {
            return isset($context['student_id'])
                ? route('admin.registrations.show', $context['student_id'])
                : route('admin.registrations');
        }

        if ($user->hasRole('kesiswaan')) {
            return route('kesiswaan.students');
        }

        return in_array($data['type'] ?? '', [self::DOCUMENT_REJECTED, self::REVISION_REQUESTED], true)
            ? route('siswa.documents')
            : route('siswa.dashboard');
    }

    /** Visual tone for the notification list. */
    public static function toneFor(?string $type): array
    {
        return match ($type) {
            self::REGISTRATION_VERIFIED, self::DOCUMENT_VERIFIED => ['success', 'check-circle'],
            self::DOCUMENT_REJECTED, self::REVISION_REQUESTED => ['danger', 'alert-triangle'],
            default => ['info', 'inbox'],
        };
    }
}
