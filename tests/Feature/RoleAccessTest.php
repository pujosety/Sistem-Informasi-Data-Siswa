<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles AND their permissions must exist: routes are guarded by
        // `can:<permission>`, and Gate::before only short-circuits for Super
        // Admin. These tests used to pass only because AppServiceProvider had
        // lost its namespace, so no gate callback ran at all and every
        // permission check silently resolved to "allowed".
        $this->seedRoles();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/siswa/dashboard')->assertRedirect('/login');
    }

    public function test_siswa_can_open_own_dashboard(): void
    {
        $user = $this->userWithRole('siswa');
        Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/siswa/dashboard')->assertOk();
    }

    public function test_siswa_cannot_open_admin_area(): void
    {
        $user = $this->userWithRole('siswa');
        Student::factory()->create(['user_id' => $user->id]);

        // The RBAC wall must hold on a real URL, not just hidden buttons.
        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/admin/pendaftaran')->assertForbidden();
    }

    public function test_admin_opens_admin_area_and_is_blocked_from_siswa_portal(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $this->actingAs($admin)->get('/admin/pendaftaran')->assertOk();
        $this->actingAs($admin)->get('/siswa/dashboard')->assertForbidden();
    }

    public function test_kesiswaan_reads_data_but_cannot_verify(): void
    {
        $staff = $this->userWithRole('kesiswaan');

        $this->actingAs($staff)->get('/kesiswaan/dashboard')->assertOk();
        $this->actingAs($staff)->get('/kesiswaan/data-siswa')->assertOk();

        // Verification is admin-only, per the role table in the spec.
        $this->actingAs($staff)->get('/admin/pendaftaran')->assertForbidden();
    }

    public function test_reports_are_available_to_staff_only(): void
    {
        $staff = $this->userWithRole('kesiswaan');
        $student = $this->userWithRole('siswa');

        $this->actingAs($staff)->get('/laporan')->assertOk();
        $this->actingAs($student)->get('/laporan')->assertForbidden();
    }

    public function test_reports_export_runs_and_returns_a_file(): void
    {
        $staff = $this->userWithRole('kesiswaan');
        $student = Student::factory()->create();

        $year = \App\Models\AcademicYear::firstOrCreate(
            ['name' => now()->year.'/'.(now()->year + 1)],
            ['start_date' => now()->startOfYear(), 'end_date' => now()->addYear()->endOfYear(), 'is_active' => true]
        );

        // The factory already provisions a draft registration; flip it instead
        // of inserting a second one (registrations.student_id is unique).
        $student->registration()->update([
            'academic_year_id' => $year->id,
            'status' => Registration::STATUS_VERIFIED,
        ]);

        $this->actingAs($staff)->get('/laporan/excel')
            ->assertOk();
    }

    public function test_pdf_report_renders(): void
    {
        $staff = $this->userWithRole('kesiswaan');

        $this->actingAs($staff)->get('/laporan/pdf')->assertOk();
    }
}
