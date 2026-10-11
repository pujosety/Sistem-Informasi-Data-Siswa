<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceFileShare;
use App\Models\WorkspaceVersionScan;
use App\Services\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Deferred acceptance suite: do not execute until the final platform test wave. */
class WorkspaceFileShareTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $recipient;

    private WorkspaceFile $file;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('workspace');
        $this->owner = $this->makeUser('admin');
        $this->recipient = $this->makeUser('siswa');
        $schoolId = app(SchoolContext::class)->id();
        $student = Student::factory()->create(['user_id' => $this->recipient->id]);
        $year = $this->seedAcademicYear();
        $semester = DB::table('semesters')->insertGetId([
            'academic_year_id' => $year->id, 'name' => '1', 'label' => 'Ganjil', 'is_current' => true,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subject = DB::table('subjects')->insertGetId(['name' => 'Share subject', 'code' => 'SHARE', 'grade_level' => 'VII', 'created_at' => now(), 'updated_at' => now()]);
        $department = DB::table('departments')->insertGetId(['name' => 'Share department', 'code' => 'SHARE', 'created_at' => now(), 'updated_at' => now()]);
        $classroom = DB::table('classes')->insertGetId(['academic_year_id' => $year->id, 'department_id' => $department, 'name' => 'VII Share', 'level' => 'VII', 'created_at' => now(), 'updated_at' => now()]);
        $this->course = Course::create([
            'school_id' => $schoolId, 'academic_year_id' => $year->id, 'semester_id' => $semester,
            'subject_id' => $subject, 'classroom_id' => $classroom, 'teacher_id' => $this->owner->id,
            'code' => 'SHARE', 'title' => 'Share course', 'status' => 'published',
        ]);
        CourseEnrollment::create(['school_id' => $schoolId, 'course_id' => $this->course->id, 'student_id' => $student->id, 'status' => 'active']);
        $this->file = WorkspaceFile::create([
            'public_id' => (string) Str::uuid(), 'school_id' => $schoolId, 'owner_id' => $this->owner->id,
            'name' => 'private.txt', 'mime_type' => 'text/plain', 'size_bytes' => 7,
            'source_application' => 'drive', 'current_version' => 1,
        ]);
        $this->addVersion(1, 'private', true);
    }

    private function addVersion(int $number, string $bytes, bool $clean): void
    {
        $path = 'schools/'.$this->file->school_id.'/users/'.$this->owner->id.'/'.$this->file->public_id.'-v'.$number.'.txt';
        Storage::disk('workspace')->put($path, $bytes);
        $version = $this->file->versions()->create([
            'version_number' => $number, 'disk' => 'workspace', 'path' => $path, 'mime_type' => 'text/plain',
            'size_bytes' => strlen($bytes), 'checksum_sha256' => hash('sha256', $bytes), 'created_by' => $this->owner->id,
        ]);
        if ($clean) {
            // Explicit test attestation, not a production bypass or fake scanner.
            WorkspaceVersionScan::create(['workspace_file_version_id' => $version->id, 'status' => 'clean', 'checksum_sha256' => hash('sha256', $bytes), 'scanned_bytes' => strlen($bytes), 'attempts' => 1, 'scanned_at' => now()]);
        }
    }

    private function grant(array $extra = []): WorkspaceFileShare
    {
        $this->actingAs($this->owner)->post(route('workspace.shares.grant', $this->file->public_id), array_merge(['recipient_email' => $this->recipient->email], $extra))
            ->assertSessionHasNoErrors()->assertRedirect();

        return WorkspaceFileShare::query()->latest('id')->firstOrFail();
    }

    public function test_owner_management_received_view_private_download_and_audit(): void
    {
        $share = $this->grant();
        $this->get(route('workspace.shares.manage', $this->file->public_id))->assertOk()->assertSeeText($this->recipient->email);
        $this->actingAs($this->recipient)->get(route('workspace.shares.received'))->assertOk()->assertSeeText('private.txt')->assertDontSeeText('Collabora');
        $response = $this->get(route('workspace.shares.download', $share->public_id))->assertOk();
        $this->assertSame('private', $response->streamedContent());
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'workspace.share.granted', 'user_id' => $this->owner->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'workspace.share.accessed', 'user_id' => $this->recipient->id]);
        $this->get(route('workspace.drive.files.download', $this->file))->assertNotFound();
        $this->post(route('workspace.drive.files.rename', $this->file), ['name' => 'stolen.txt'])->assertNotFound();
        $this->get(route('workspace.drive.files.collabora', $this->file))->assertNotFound();
    }

    public function test_revocation_is_idempotent_and_old_uuid_cannot_be_reactivated(): void
    {
        $share = $this->grant();
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($this->owner)->delete(route('workspace.shares.revoke', [$this->file->public_id, $share->public_id]))->assertRedirect();
        }
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'workspace.share.revoked')->count());
        $replacement = $this->grant();
        $this->assertNotSame($share->public_id, $replacement->public_id);
        $this->actingAs($this->recipient)->get(route('workspace.shares.download', $share->public_id))->assertNotFound();
        $this->get(route('workspace.shares.download', $replacement->public_id))->assertOk();
    }

    public function test_foreign_owner_including_super_admin_cannot_manage_grant_or_revoke(): void
    {
        $share = $this->grant();
        foreach (['admin', 'super_admin'] as $role) {
            $other = $this->makeUser($role);
            $this->actingAs($other)->get(route('workspace.shares.manage', $this->file->public_id))->assertNotFound();
            $this->post(route('workspace.shares.grant', $this->file->public_id), ['recipient_email' => $this->recipient->email])->assertNotFound();
            $this->delete(route('workspace.shares.revoke', [$this->file->public_id, $share->public_id]))->assertNotFound();
            $this->get(route('workspace.shares.download', $share->public_id))->assertNotFound();
        }
    }

    public function test_unknown_inactive_unscoped_and_nonexact_recipients_fail_generically(): void
    {
        $unknown = $this->makeUser('super_admin');
        foreach (['nobody@example.invalid', $unknown->email, strtoupper($this->recipient->email)] as $email) {
            $this->actingAs($this->owner)->post(route('workspace.shares.grant', $this->file->public_id), ['recipient_email' => $email])->assertSessionHasErrors('recipient_email');
        }
        $this->recipient->update(['is_active' => false]);
        $this->post(route('workspace.shares.grant', $this->file->public_id), ['recipient_email' => $this->recipient->email])->assertSessionHasErrors('recipient_email');
        $this->assertDatabaseCount('workspace_file_shares', 0);
    }

    public function test_cross_school_and_mismatched_enrollment_school_cannot_receive(): void
    {
        $other = School::create(['name' => 'Other', 'slug' => 'share-other', 'is_active' => true, 'is_default' => false]);
        DB::table('lms_course_enrollments')->update(['school_id' => $other->id]);
        $this->actingAs($this->owner)->post(route('workspace.shares.grant', $this->file->public_id), ['recipient_email' => $this->recipient->email])->assertSessionHasErrors('recipient_email');
        $this->course->update(['school_id' => $other->id]);
        $this->post(route('workspace.shares.grant', $this->file->public_id), ['recipient_email' => $this->recipient->email])->assertSessionHasErrors('recipient_email');
    }

    public function test_expiry_must_be_valid_future_and_at_most_ninety_days(): void
    {
        foreach (['bad', now()->subMinute()->format('Y-m-d\TH:i'), now()->addDays(91)->format('Y-m-d\TH:i')] as $expiry) {
            $this->actingAs($this->owner)->post(route('workspace.shares.grant', $this->file->public_id), ['recipient_email' => $this->recipient->email, 'expires_at' => $expiry])->assertSessionHasErrors('expires_at');
        }
        $share = $this->grant(['expires_at' => now()->addDay()->format('Y-m-d\TH:i')]);
        $this->travel(2)->days();
        $this->actingAs($this->recipient)->get(route('workspace.shares.download', $share->public_id))->assertNotFound();
        $this->get(route('workspace.shares.received'))->assertDontSeeText('private.txt');
    }

    public function test_trash_blocks_access_and_owner_can_still_revoke(): void
    {
        $share = $this->grant();
        $this->file->delete();
        $this->actingAs($this->recipient)->get(route('workspace.shares.download', $share->public_id))->assertNotFound();
        $this->get(route('workspace.shares.received'))->assertDontSeeText('private.txt');
        $this->actingAs($this->owner)->delete(route('workspace.shares.revoke', [$this->file->public_id, $share->public_id]))->assertRedirect();
    }

    public function test_recipient_deactivation_and_membership_loss_revoke_effective_access(): void
    {
        $share = $this->grant();
        $this->recipient->update(['is_active' => false]);
        $this->actingAs($this->recipient)->get(route('workspace.shares.download', $share->public_id))->assertNotFound();
        $this->recipient->update(['is_active' => true]);
        DB::table('lms_course_enrollments')->update(['status' => 'inactive']);
        $this->get(route('workspace.shares.download', $share->public_id))->assertNotFound();
    }

    public function test_new_current_version_requires_its_own_clean_scan(): void
    {
        $share = $this->grant();
        $this->addVersion(2, 'new bytes', false);
        $this->file->update(['current_version' => 2, 'size_bytes' => 9]);
        $this->actingAs($this->recipient)->get(route('workspace.shares.download', $share->public_id))->assertStatus(423);
        $version = $this->file->versions()->where('version_number', 2)->firstOrFail();
        WorkspaceVersionScan::create(['workspace_file_version_id' => $version->id, 'status' => 'clean', 'checksum_sha256' => $version->checksum_sha256, 'scanned_bytes' => 9, 'attempts' => 1]);
        $response = $this->get(route('workspace.shares.download', $share->public_id))->assertOk();
        $this->assertSame('new bytes', $response->streamedContent());
        $this->assertDatabaseCount('workspace_file_versions', 2);
    }

    public function test_all_nonclean_and_malformed_attestations_fail_closed(): void
    {
        $share = $this->grant();
        $scan = WorkspaceVersionScan::query()->firstOrFail();
        foreach (['infected', 'pending', 'unavailable'] as $status) {
            $scan->update(['status' => $status]);
            $this->actingAs($this->recipient)->get(route('workspace.shares.download', $share->public_id))->assertStatus(423);
        }
        $scan->update(['status' => 'clean', 'checksum_sha256' => str_repeat('0', 64)]);
        $this->get(route('workspace.shares.download', $share->public_id))->assertStatus(423);
        $scan->delete();
        $this->get(route('workspace.shares.download', $share->public_id))->assertStatus(423);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'workspace.share.accessed']);
    }

    public function test_school_switch_unknown_uuids_and_other_recipient_deny_download(): void
    {
        $share = $this->grant();
        $other = $this->makeUser('admin');
        $this->actingAs($other)->get(route('workspace.shares.download', $share->public_id))->assertNotFound();
        $this->actingAs($this->recipient)->get(route('workspace.shares.download', (string) Str::uuid()))->assertNotFound();
        $this->get('/workspace/shares/123/download')->assertNotFound();
        $school = School::create(['name' => 'Switch', 'slug' => 'share-switch', 'is_active' => true]);
        app(SchoolContext::class)->useForRequest($school);
        $this->get(route('workspace.shares.download', $share->public_id))->assertNotFound();
    }

    public function test_client_internal_ids_paths_and_write_rights_are_rejected(): void
    {
        foreach (['owner_id', 'recipient_id', 'school_id', 'workspace_file_id', 'path', 'disk', 'rights', 'permission'] as $field) {
            $this->actingAs($this->owner)->post(route('workspace.shares.grant', $this->file->public_id), ['recipient_email' => $this->recipient->email, $field => 'write'])->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('workspace_file_shares', 0);
    }

    public function test_guests_cannot_download_and_routes_keep_web_auth_csrf_and_throttle(): void
    {
        $share = $this->grant();
        auth()->logout();
        $this->get(route('workspace.shares.download', $share->public_id))->assertRedirect(route('login'));
        $route = app('router')->getRoutes()->getByName('workspace.shares.grant');
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('throttle:10,1', $route->gatherMiddleware());
    }
}
