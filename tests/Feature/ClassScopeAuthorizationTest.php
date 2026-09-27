<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\HomeroomAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\ClassScope;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Class-scoped authorization (spec PHASE 37).
 *
 * The point of these tests is that PERMISSION ALONE IS NOT ENOUGH: a Wali Kelas
 * holding classroom.student.view must still be confined to their assignment, so
 * editing an id in the URL opens nothing.
 */
class ClassScopeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private SchoolClass $ownClass;

    private SchoolClass $otherClass;

    private User $wali;

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

        $this->ownClass = SchoolClass::create([
            'academic_year_id' => $year->id,
            'department_id' => $department->id,
            'name' => 'X IPA 1',
            'level' => 'X',
            'capacity' => 36,
            'status' => SchoolClass::ACTIVE,
        ]);

        $this->otherClass = SchoolClass::create([
            'academic_year_id' => $year->id,
            'department_id' => $department->id,
            'name' => 'X IPA 2',
            'level' => 'X',
            'capacity' => 36,
            'status' => SchoolClass::ACTIVE,
        ]);

        $this->wali = $this->makeUser('wali_kelas');

        HomeroomAssignment::create([
            'user_id' => $this->wali->id,
            'classroom_id' => $this->ownClass->id,
            'academic_year_id' => $year->id,
            'started_at' => now()->toDateString(),
            'status' => HomeroomAssignment::ACTIVE,
        ]);
    }

    private function studentIn(SchoolClass $classroom): Student
    {
        $student = Student::factory()->create();
        app(EnrollmentService::class)->assign($student, $classroom, $this->wali, 'test');

        return $student->fresh();
    }

    // -------------------------------------------------- TEST A + B (own vs other)

    public function test_wali_kelas_can_open_their_own_classroom(): void
    {
        $this->actingAs($this->wali)
            ->get("/akademik/kelas/{$this->ownClass->id}")
            ->assertOk();
    }

    public function test_wali_kelas_is_denied_another_classroom_by_url(): void
    {
        // Even with a valid permission and a well-formed id, another teacher's
        // class must be refused.
        $this->actingAs($this->wali)
            ->get("/akademik/kelas/{$this->otherClass->id}")
            ->assertForbidden();
    }

    public function test_wali_kelas_is_denied_another_classrooms_attendance(): void
    {
        $this->actingAs($this->wali)
            ->get("/akademik/kelas/{$this->otherClass->id}/absensi")
            ->assertForbidden();
    }

    public function test_wali_kelas_is_denied_another_classrooms_announcements(): void
    {
        $this->actingAs($this->wali)
            ->get("/akademik/kelas/{$this->otherClass->id}/pengumuman")
            ->assertForbidden();
    }

    // ----------------------------------------------------------------- TEST C

    public function test_wali_kelas_cannot_move_a_student_from_another_class(): void
    {
        $foreign = $this->studentIn($this->otherClass);

        // TEST C: the id belongs to a student in a class the Wali Kelas does not
        // own, so the scope check on their current class must refuse it.
        $this->actingAs($this->wali)
            ->get("/akademik/pindah/{$foreign->id}")
            ->assertForbidden();

        $this->actingAs($this->wali)
            ->post("/akademik/pindah/{$foreign->id}", [
                'classroom_id' => $this->ownClass->id,
                'reason' => 'dipaksa dari kelas lain',
            ])
            ->assertForbidden();

        // And the enrollment is genuinely untouched.
        $this->assertSame(
            $this->otherClass->id,
            Enrollment::currentFor($foreign->id)?->classroom_id,
        );
    }

    // ----------------------------------------------------------------- TEST F

    public function test_student_cannot_open_the_academic_admin_area(): void
    {
        $student = $this->studentIn($this->ownClass);
        $user = $student->user;

        $this->actingAs($user)
            ->get("/akademik/kelas/{$this->ownClass->id}")
            ->assertForbidden();

        $this->actingAs($user)->get('/akademik/kelas')->assertForbidden();
        $this->actingAs($user)->get('/akademik/penempatan')->assertForbidden();
    }

    // ----------------------------------------------------------------- TEST G

    public function test_kesiswaan_with_school_wide_scope_reaches_any_class(): void
    {
        $staff = $this->makeUser('kesiswaan');

        $this->actingAs($staff)->get("/akademik/kelas/{$this->ownClass->id}")->assertOk();
        $this->actingAs($staff)->get("/akademik/kelas/{$this->otherClass->id}")->assertOk();
    }

    // ----------------------------------------------------------------- TEST H

    public function test_super_admin_has_full_classroom_access(): void
    {
        $super = $this->makeUser('super_admin');

        $this->actingAs($super)->get("/akademik/kelas/{$this->ownClass->id}")->assertOk();
        $this->actingAs($super)->get("/akademik/kelas/{$this->otherClass->id}")->assertOk();
        $this->actingAs($super)->get('/akademik/tahun-ajaran')->assertOk();
    }

    public function test_super_admin_permission_cannot_be_escalated_by_a_plain_admin(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get('/akademik/tahun-ajaran')->assertOk();
        // Admin may see classes school-wide, but not manage the role catalogue.
        $this->actingAs($admin)->get('/admin/role')->assertForbidden();
    }

    // ------------------------------------------------------- the scope service

    public function test_class_scope_reports_assignment_not_permission(): void
    {
        $scope = app(ClassScope::class);

        $this->assertTrue($scope->isHomeroom($this->wali, $this->ownClass));
        $this->assertFalse($scope->isHomeroom($this->wali, $this->otherClass));
        $this->assertTrue($scope->canView($this->wali, $this->ownClass));
        $this->assertFalse($scope->canView($this->wali, $this->otherClass));

        // The Wali Kelas holds classroom.view but NOT the school-wide bypass.
        $this->assertTrue($this->wali->can('classroom.view'));
        $this->assertFalse($this->wali->can('classroom.view.all'));
    }
}
