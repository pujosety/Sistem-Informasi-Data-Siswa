<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GuardianRelationship;
use App\Models\HomeroomAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Workspace isolation (spec PHASE 17, 89).
 *
 * A hidden menu is not authorization. Every workspace below is also attempted
 * by direct URL from a role that should not be there, and a parent is checked
 * against a child that is not theirs.
 */
class WorkspaceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => AcademicYear::ACTIVE,
            'is_active' => true,
            'is_default' => true,
        ]);

        $department = Department::create(['name' => 'IPA', 'code' => 'IPA']);

        $this->class = SchoolClass::create([
            'academic_year_id' => $year->id,
            'department_id' => $department->id,
            'name' => 'X IPA 1',
            'level' => 'X',
            'capacity' => 36,
            'status' => SchoolClass::ACTIVE,
        ]);
    }

    private function enrolledStudent(): Student
    {
        $student = Student::factory()->create();
        app(EnrollmentService::class)->assign($student, $this->class);

        return $student->fresh();
    }

    // ------------------------------------------------------- workspace isolation

    public function test_admin_owns_the_admin_workspace(): void
    {
        // Admin holds user.view, so the management workspace is theirs. The
        // verifier queue is a separate surface they may also open, but the
        // boundary that matters is that Operator and Verifikator cannot.
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get('/ruang-kerja/admin')->assertOk();
        $this->actingAs($admin)->get('/ruang-kerja/verifikator')->assertOk();
    }

    public function test_verifikator_lands_on_the_verification_queue(): void
    {
        $verifier = $this->makeUser('verifikator');

        $this->actingAs($verifier)
            ->get('/dashboard')
            ->assertRedirect('/ruang-kerja/verifikator');

        $this->actingAs($verifier)->get('/ruang-kerja/verifikator')->assertOk();
    }

    public function test_verifikator_cannot_manage_users_or_settings(): void
    {
        $verifier = $this->makeUser('verifikator');

        $this->actingAs($verifier)->get('/admin/pengguna')->assertForbidden();
        $this->actingAs($verifier)->get('/admin/role')->assertForbidden();
        $this->actingAs($verifier)->get('/pengaturan')->assertForbidden();
        $this->actingAs($verifier)->get('/ruang-kerja/admin')->assertForbidden();
    }

    public function test_operator_cannot_reach_the_admin_workspace(): void
    {
        $operator = $this->makeUser('operator');

        // Operator holds dashboard.admin.view but is not an administrator, so
        // the management workspace must stay closed to it.
        $this->actingAs($operator)->get('/ruang-kerja/admin')->assertForbidden();
    }

    public function test_student_cannot_open_any_staff_workspace(): void
    {
        $student = $this->enrolledStudent();

        foreach (['/ruang-kerja/admin', '/ruang-kerja/kesiswaan', '/ruang-kerja/operator', '/ruang-kerja/verifikator'] as $uri) {
            $this->actingAs($student->user)->get($uri)->assertForbidden();
        }
    }

    public function test_operator_cannot_open_student_or_parent_portals(): void
    {
        $operator = $this->makeUser('operator');

        $this->actingAs($operator)->get('/siswa/dashboard')->assertForbidden();
        $this->actingAs($operator)->get('/orang-tua')->assertForbidden();
    }

    public function test_homeroom_teacher_cannot_open_user_management(): void
    {
        $wali = $this->makeUser('wali_kelas');

        HomeroomAssignment::create([
            'user_id' => $wali->id,
            'classroom_id' => $this->class->id,
            'academic_year_id' => $this->class->academic_year_id,
            'started_at' => now()->toDateString(),
            'status' => HomeroomAssignment::ACTIVE,
        ]);

        $this->actingAs($wali)->get('/kelas-saya')->assertOk();
        $this->actingAs($wali)->get('/admin/pengguna')->assertForbidden();
        $this->actingAs($wali)->get('/admin/role')->assertForbidden();
    }

    // ------------------------------------------------------------- parent portal

    public function test_parent_sees_their_own_child(): void
    {
        $parent = $this->makeUser('operator');
        $child = $this->enrolledStudent();

        GuardianRelationship::create([
            'student_id' => $child->id,
            'guardian_user_id' => $parent->id,
            'relationship' => 'ayah',
            'status' => 'active',
        ]);

        $this->actingAs($parent)->get('/orang-tua')->assertOk()->assertSee($child->full_name);
        $this->actingAs($parent)->get("/orang-tua/anak/{$child->id}/absensi")->assertOk();
    }

    public function test_parent_cannot_reach_an_unlinked_student(): void
    {
        $parent = $this->makeUser('operator');
        $linked = $this->enrolledStudent();
        $stranger = $this->enrolledStudent();

        GuardianRelationship::create([
            'student_id' => $linked->id,
            'guardian_user_id' => $parent->id,
            'relationship' => 'ayah',
            'status' => 'active',
        ]);

        // 404, not 403: a 403 would confirm the stranger exists.
        $this->actingAs($parent)->get("/orang-tua/anak/{$stranger->id}/absensi")->assertNotFound();
        $this->actingAs($parent)->get("/orang-tua/anak/{$stranger->id}/akademik")->assertNotFound();
    }

    public function test_internal_user_without_a_guardian_link_cannot_open_the_parent_portal(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get('/orang-tua')->assertForbidden();
    }

    public function test_parent_never_sees_draft_grades(): void
    {
        $parent = $this->makeUser('operator');
        $child = $this->enrolledStudent();
        $enrollment = Enrollment::currentFor($child->id);

        GuardianRelationship::create([
            'student_id' => $child->id,
            'guardian_user_id' => $parent->id,
            'relationship' => 'ayah',
            'status' => 'active',
        ]);

        $subject = Subject::create(['name' => 'Fisika', 'code' => 'FIS-DRAFT']);

        Grade::create([
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'term' => '1',
            'score' => 74,
            'status' => Grade::DRAFT,
        ]);

        $response = $this->actingAs($parent)->get("/orang-tua/anak/{$child->id}/akademik");

        $response->assertOk();
        $response->assertDontSee('FIS-DRAFT');
        $response->assertSee('Belum ada nilai terbit');
    }

    // -------------------------------------------------------------- login landing

    public function test_login_lands_each_role_on_its_own_workspace(): void
    {
        $expectations = [
            'admin' => '/ruang-kerja/admin',
            'kesiswaan' => '/ruang-kerja/kesiswaan',
        ];

        foreach ($expectations as $role => $expected) {
            $user = $this->makeUser($role);

            $this->actingAs($user)
                ->get('/dashboard')
                ->assertRedirect($expected);
        }
    }

    public function test_a_homeroom_teacher_lands_on_kelas_saya(): void
    {
        $wali = $this->makeUser('wali_kelas');

        HomeroomAssignment::create([
            'user_id' => $wali->id,
            'classroom_id' => $this->class->id,
            'academic_year_id' => $this->class->academic_year_id,
            'started_at' => now()->toDateString(),
            'status' => HomeroomAssignment::ACTIVE,
        ]);

        $this->actingAs($wali)->get('/dashboard')->assertRedirect('/kelas-saya');
    }

    public function test_a_parent_lands_on_the_parent_portal(): void
    {
        $parent = $this->makeUser('operator');
        $child = $this->enrolledStudent();

        GuardianRelationship::create([
            'student_id' => $child->id,
            'guardian_user_id' => $parent->id,
            'relationship' => 'ayah',
            'status' => 'active',
        ]);

        $this->actingAs($parent)->get('/dashboard')->assertRedirect('/orang-tua');
    }
}
