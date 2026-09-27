<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\ClassScope;
use App\Services\EnrollmentService;
use App\Services\HomeroomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Enrollment invariants (spec PHASE 38).
 *
 * Every operation that could silently corrupt academic history must fail loudly
 * instead: duplicate active enrollment, moving twice, promoting twice,
 * graduating twice, assigning into an archived year.
 */
class EnrollmentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    private SchoolClass $classA;

    private SchoolClass $classB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => AcademicYear::ACTIVE,
            'is_active' => true,
            'is_default' => true,
        ]);

        $department = Department::create(['name' => 'IPA', 'code' => 'IPA']);

        $this->classA = SchoolClass::create([
            'academic_year_id' => $this->year->id,
            'department_id' => $department->id,
            'name' => 'X IPA 1',
            'level' => 'X',
            'capacity' => 2,
            'status' => SchoolClass::ACTIVE,
        ]);

        $this->classB = SchoolClass::create([
            'academic_year_id' => $this->year->id,
            'department_id' => $department->id,
            'name' => 'X IPA 2',
            'level' => 'X',
            'capacity' => 36,
            'status' => SchoolClass::ACTIVE,
        ]);
    }

    public function test_a_student_cannot_hold_two_active_enrollments_in_one_year(): void
    {
        $service = app(EnrollmentService::class);
        $student = Student::factory()->create();

        $service->assign($student, $this->classA);

        $this->expectException(ValidationException::class);

        try {
            $service->assign($student, $this->classB);
        } finally {
            $this->assertSame(1, Enrollment::activeFor($student->id, $this->year->id) ? 1 : 0);
            $this->assertSame(
                $this->classA->id,
                Enrollment::activeFor($student->id, $this->year->id)->classroom_id,
                'The first enrollment must be preserved, not overwritten.',
            );
        }
    }

    public function test_assigning_the_same_class_twice_is_refused(): void
    {
        $service = app(EnrollmentService::class);
        $student = Student::factory()->create();

        $service->assign($student, $this->classA);

        $this->expectException(ValidationException::class);
        $service->assign($student, $this->classA);
    }

    public function test_moving_closes_the_previous_enrollment_and_keeps_history(): void
    {
        $service = app(EnrollmentService::class);
        $actor = $this->makeUser('admin');
        $student = Student::factory()->create();

        $service->assign($student, $this->classA);

        $service->move($student, $this->classB, $actor, now()->toDateString(), 'Keterbatasan kapasitas');

        $all = Enrollment::where('student_id', $student->id)->get();

        $this->assertCount(2, $all, 'The old enrollment must survive the move.');
        $this->assertSame('transferred', $all->firstWhere('classroom_id', $this->classA->id)->status);
        $this->assertSame('active', $all->firstWhere('classroom_id', $this->classB->id)->status);
        $this->assertNotNull($all->firstWhere('classroom_id', $this->classA->id)->ended_at);
    }

    public function test_moving_a_student_twice_still_ends_with_one_active_enrollment(): void
    {
        $service = app(EnrollmentService::class);
        $actor = $this->makeUser('admin');
        $student = Student::factory()->create();

        $service->assign($student, $this->classA);
        $service->move($student, $this->classB, $actor, null, 'pindah sekali');

        $this->assertSame(1, Enrollment::activeFor($student->id, $this->year->id) ? 1 : 0);

        // Moving back is legal and produces a third, still-single active row.
        $service->move($student, $this->classA, $actor, null, 'pindah balik');

        $this->assertSame(1, Enrollment::where('student_id', $student->id)->active()->count());
        $this->assertSame(3, Enrollment::where('student_id', $student->id)->count());
    }

    public function test_moving_a_student_to_the_class_they_are_already_in_is_refused(): void
    {
        $service = app(EnrollmentService::class);
        $student = Student::factory()->create();
        $service->assign($student, $this->classA);

        $this->expectException(ValidationException::class);
        $service->move($student, $this->classA, $this->makeUser('admin'), null, 'sia-sia');
    }

    public function test_an_archived_classroom_refuses_new_students(): void
    {
        $service = app(EnrollmentService::class);
        $this->classA->update(['status' => SchoolClass::ARCHIVED]);

        $this->expectException(ValidationException::class);
        $service->assign(Student::factory()->create(), $this->classA);
    }

    public function test_an_archived_academic_year_refuses_new_students(): void
    {
        $service = app(EnrollmentService::class);
        $this->year->update(['status' => AcademicYear::ARCHIVED, 'is_active' => false, 'is_default' => false]);

        $this->expectException(ValidationException::class);
        $service->assign(Student::factory()->create(), $this->classA);
    }

    public function test_closing_an_enrollment_twice_is_refused(): void
    {
        $service = app(EnrollmentService::class);
        $actor = $this->makeUser('admin');
        $student = Student::factory()->create();

        $enrollment = $service->assign($student, $this->classA);
        $service->close($enrollment, 'graduated', $actor, 'lulus');

        $this->expectException(ValidationException::class);
        $service->close($enrollment, 'graduated', $actor, 'lulus lagi');
    }

    public function test_capacity_is_surfaced_but_not_silently_enforced(): void
    {
        $service = app(EnrollmentService::class);

        $service->assign(Student::factory()->create(), $this->classA);
        $service->assign(Student::factory()->create(), $this->classA);

        $this->assertSame(2, $this->classA->fresh()->studentCount());
        $this->assertFalse($this->classA->fresh()->hasCapacity());
        $this->assertSame(0, $this->classA->fresh()->remainingCapacity());

        // A third student is still accepted — capacity warns, it does not block.
        $service->assign(Student::factory()->create(), $this->classA);
        $this->assertSame(3, $this->classA->fresh()->studentCount());
    }

    public function test_bulk_assignment_reports_per_student_outcomes(): void
    {
        $service = app(EnrollmentService::class);

        $enrolled = Student::factory()->create();
        $fresh = Student::factory()->count(2)->create();

        $service->assign($enrolled, $this->classA);

        $result = $service->assignMany(
            [$enrolled->id, $fresh->pluck('id')->all()],
            $this->classA,
        );

        $this->assertSame(2, $result['assigned']);
        $this->assertCount(1, $result['skipped']);
        $this->assertArrayHasKey($enrolled->id, $result['skipped']);
    }

    public function test_replacing_a_homeroom_keeps_the_previous_assignment(): void
    {
        $service = app(HomeroomService::class);
        $first = $this->makeUser('wali_kelas');
        $second = $this->makeUser('wali_kelas');

        $service->assign($this->classA, $first);
        $service->assign($this->classA, $second);

        $history = $service->history($this->classA);

        $this->assertCount(2, $history);
        $this->assertSame(1, $history->where('status', 'active')->count());
        $this->assertSame($second->id, $this->classA->fresh()->homeroom()->id);
    }

    public function test_the_legacy_student_columns_mirror_the_current_enrollment(): void
    {
        $service = app(EnrollmentService::class);
        $student = Student::factory()->create(['class_id' => null, 'academic_year_id' => null]);

        $service->assign($student, $this->classA);

        $fresh = $student->fresh();

        $this->assertSame($this->classA->id, $fresh->class_id);
        $this->assertSame($this->year->id, $fresh->academic_year_id);
    }

    public function test_no_duplicate_active_enrollment_exists_in_the_database(): void
    {
        $service = app(EnrollmentService::class);
        $service->assign(Student::factory()->create(), $this->classA);
        $service->assign(Student::factory()->create(), $this->classB);

        $duplicates = DB::table('enrollments')
            ->select('student_id', 'academic_year_id', DB::raw('COUNT(*) c'))
            ->where('status', 'active')
            ->groupBy('student_id', 'academic_year_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->assertSame(0, $duplicates);
    }
}
