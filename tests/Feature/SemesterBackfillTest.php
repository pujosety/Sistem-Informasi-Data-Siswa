<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\SemesterBackfill;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests the semester backfill against data shaped to catch it being wrong.
 *
 * THE FAILURE THIS GUARDS
 *
 * `grades.term` is a bare string that repeats in every academic year. '1' in
 * 2026/2027 and '1' in 2025/2026 are different periods, and only the enrolment
 * knows which year a grade belongs to. A backfill that joined on term alone
 * would attach a 2025 score to the 2026 semester, which is silent — every row
 * still resolves, every report still renders, and the history is quietly wrong.
 *
 * The fixtures below therefore span two academic years and both terms, and the
 * assertions check the YEAR rather than only the term name.
 *
 * WHY THE BACKFILL IS CALLED DIRECTLY
 *
 * A migration runs against whatever the database holds at deploy time, so its
 * backfill cannot be tested by a test that inserts rows afterwards —
 * RefreshDatabase migrates an empty schema first, and grades created after the
 * backfill has already run are legitimately unattached. Seeding and then calling
 * SemesterBackfill puts the test in the same order production is in.
 */
class SemesterBackfillTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year2026;

    private AcademicYear $year2025;

    private SubjectFixture $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year2026 = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => AcademicYear::ACTIVE,
            'is_active' => true,
        ]);

        $this->year2025 = AcademicYear::create([
            'name' => '2025/2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => AcademicYear::ARCHIVED,
            'is_active' => false,
        ]);

        $this->subject = new SubjectFixture(
            \App\Models\Subject::create(['name' => 'Matematika', 'code' => 'MTK', 'grade_level' => 'X'])
        );

        // 2026 uses both terms; 2025 uses only term 1.
        $this->grade($this->year2026, 1, '1', 88.00);
        $this->grade($this->year2026, 1, '2', 91.50);
        $this->grade($this->year2026, 2, '1', 75.25);
        $this->grade($this->year2025, 3, '1', 80.00);
    }

    private function grade(AcademicYear $year, int $studentSeq, string $term, float $score): Grade
    {
        $user = User::factory()->create();
        $department = Department::firstOrCreate(['code' => 'IPA'], ['name' => 'IPA']);

        $class = SchoolClass::create([
            'academic_year_id' => $year->id,
            'department_id' => $department->id,
            'name' => $year->name.'-'.$studentSeq,
            'code' => 'X-'.$year->id.'-'.$studentSeq,
            'level' => 'X',
            'status' => SchoolClass::ACTIVE,
        ]);

        $student = Student::factory()->create(['user_id' => $user->id]);

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'classroom_id' => $class->id,
            'department_id' => $department->id,
            'status' => Enrollment::ACTIVE,
            'started_at' => $year->start_date->toDateString(),
            'notes' => '',
            'source' => 'manual',
        ]);

        return Grade::create([
            'enrollment_id' => $enrollment->id,
            'subject_id' => $this->subject->model->id,
            'term' => $term,
            'score' => $score,
            'status' => 'draft',
        ]);
    }

    private function applyBackfill(): void
    {
        $backfill = new SemesterBackfill();
        $backfill->createCalendar();
        $backfill->attachGrades();
    }

    /**
     * @test
     */
    public function test_every_grade_is_attached_to_a_semester(): void
    {
        $this->applyBackfill();

        $this->assertSame(0, Grade::whereNull('semester_id')->count());
    }

    /**
     * @test
     */
    public function test_a_grade_is_attached_to_a_semester_in_its_own_academic_year(): void
    {
        $this->applyBackfill();

        // The point of the fixture. '1' exists in both years, so a join on term
        // alone would attach the 2025 score to the 2026 semester.
        $grade2025 = Grade::where('score', 80.00)->firstOrFail();
        $grade2026 = Grade::where('score', 88.00)->firstOrFail();

        $this->assertSame($this->year2025->id, $grade2025->semester->academic_year_id);
        $this->assertSame($this->year2026->id, $grade2026->semester->academic_year_id);
        $this->assertNotSame($grade2025->semester_id, $grade2026->semester_id);
    }

    /**
     * @test
     */
    public function test_only_semesters_that_have_grades_are_created(): void
    {
        $this->applyBackfill();

        // 2026 uses terms 1 and 2; 2025 only term 1. Creating both semesters for
        // every year would invent a schedule the school never set.
        $this->assertSame(2, $this->year2026->semesters()->count());
        $this->assertSame(1, $this->year2025->semesters()->count());
        $this->assertSame(0, $this->year2025->semesters()->where('name', '2')->count());
    }

    /**
     * @test
     */
    public function test_the_term_string_is_left_intact(): void
    {
        $this->applyBackfill();

        // grades.term stays authoritative for the existing gradebook and its
        // unique constraint. The calendar is additive.
        $grade = Grade::where('score', 91.50)->firstOrFail();

        $this->assertSame('2', $grade->term);
        $this->assertSame('2', $grade->semester->name);
    }

    /**
     * @test
     */
    public function test_every_grade_is_attached_to_a_final_category(): void
    {
        $this->applyBackfill();

        $this->assertSame(0, Grade::whereNull('category_id')->count());
        $this->assertSame('final', Grade::firstOrFail()->category->key);
    }

    /**
     * @test
     */
    public function test_running_the_backfill_twice_changes_nothing(): void
    {
        $this->applyBackfill();
        $this->applyBackfill();

        // A re-run happens whenever a second deployment follows the first, so
        // neither the categories nor the semesters may double. Each year gets
        // exactly ONE category (the seeded 'final'); the semester count differs
        // per year because 2026 uses both terms and 2025 only term 1.
        $this->assertSame(1, $this->year2026->gradeCategories()->count());
        $this->assertSame(1, $this->year2025->gradeCategories()->count());
        $this->assertSame(2, $this->year2026->semesters()->count());
        $this->assertSame(1, $this->year2025->semesters()->count());
    }

    /**
     * @test
     */
    public function test_the_existing_unique_constraint_on_grades_still_holds(): void
    {
        $this->applyBackfill();

        // (enrollment_id, subject_id, term) must stay unique. A semester column
        // added without noticing this would allow two scores for the same period
        // and silently average them in reports.
        $grade = Grade::where('score', 88.00)->firstOrFail();

        $this->expectException(QueryException::class);

        Grade::create([
            'enrollment_id' => $grade->enrollment_id,
            'subject_id' => $this->subject->model->id,
            'term' => $grade->term,
            'semester_id' => $grade->semester_id,
            'score' => 99.00,
            'status' => 'draft',
        ]);
    }
}

/** Small holder so setUp stays readable. */
class SubjectFixture
{
    public function __construct(public readonly \App\Models\Subject $model) {}
}
