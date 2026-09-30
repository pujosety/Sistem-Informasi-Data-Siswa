<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\GradeEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards grade entry, and above all the line between saving and publishing.
 *
 * THE FAILURE THIS EXISTS TO PREVENT
 *
 * A school grades a test; a parent reads a report card. Those must not be the
 * same action. `grades.status` stays `draft` on save, and only `grade.publish`
 * moves it to `published`. If a save published, a teacher correcting one cell
 * would silently release a half-marked term, and no test in the repository
 * would notice until a parent complained.
 *
 * The second thing guarded is scope: a Wali Kelas holding `grade.edit` must
 * not reach another teacher's class by editing the URL. Holding the permission
 * is not the same as holding the assignment.
 *
 * The third is the cleared cell. A blank means "not graded yet" — it must not
 * write a zero, and it must not delete a score that was already recorded.
 */
class GradeEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    // ----------------------------------------------------------------- fixtures

    /** NISN is UNIQUE, and two classes are created in the scope test. */
    private int $nisnSeq = 0;

    private function teacher(): User
    {
        return $this->makeUser('wali_kelas')->refresh();
    }

    private function classWith(int $students = 2): SchoolClass
    {
        $year = $this->seedAcademicYear();

        $class = SchoolClass::create([
            'name' => 'XII IPA 1',
            // `level` is NOT NULL in the schema with no default, so omitting it
            // fails the insert with a raw 1364 and every test in the file dies
            // at the fixture before reaching a single assertion.
            'level' => 'XII',
            'academic_year_id' => $year->id,
        ]);

        for ($i = 1; $i <= $students; $i++) {
            // A student is always an ACCOUNT first: `user_id` is NOT NULL and
            // unique, and the column is `full_name`, not `name`.
            $account = $this->makeUser('siswa');
            $account->update(['name' => "Siswa {$i}"]);

            $student = Student::create([
                'user_id' => $account->id,
                'full_name' => "Siswa {$i}",
                'nisn' => (string) (900000 + ++$this->nisnSeq),
                'gender' => 'L',
                'class_id' => $class->id,
                'academic_year_id' => $year->id,
            ]);

            Enrollment::create([
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'classroom_id' => $class->id,
                'status' => Enrollment::ACTIVE,
            ]);
        }

        return $class->refresh();
    }

    private function semester(SchoolClass $class): Semester
    {
        return Semester::create([
            'academic_year_id' => $class->academic_year_id,
            'name' => now()->year.'/'.(now()->year + 1).'-1',
            'label' => 'Semester 1',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->addMonths(6),
            'is_current' => true,
        ]);
    }

    private function subject(): Subject
    {
        return Subject::create(['name' => 'Matematika', 'code' => 'MTK']);
    }

    private function firstEnrollment(SchoolClass $class): Enrollment
    {
        return $class->liveEnrollments()->firstOrFail();
    }

    // ------------------------------------------------------------------ saving

    /** @test */
    public function a_teacher_with_edit_can_save_a_score_and_it_stays_draft(): void
    {
        $class = $this->classWith();
        $semester = $this->semester($class);
        $subject = $this->subject();
        $enrollment = $this->firstEnrollment($class);

        $written = app(GradeEntryService::class)->save(
            $class, $semester, $subject, [$enrollment->id => 87.5], $this->teacher()
        );

        $this->assertSame(1, $written);

        $grade = Grade::firstOrFail();

        $this->assertSame(87.5, (float) $grade->score);
        $this->assertSame(Grade::DRAFT, $grade->status);
        $this->assertNull($grade->published_at);
        $this->assertNull($grade->published_by);
    }

    /**
     * THE CENTRAL ASSERTION
     *
     * @test
     */
    public function a_saved_score_is_not_visible_to_the_parent_portal(): void
    {
        $class = $this->classWith();
        $semester = $this->semester($class);
        $subject = $this->subject();
        $enrollment = $this->firstEnrollment($class);

        app(GradeEntryService::class)->save(
            $class, $semester, $subject, [$enrollment->id => 91], $this->teacher()
        );

        $this->assertSame(Grade::DRAFT, Grade::firstOrFail()->status);

        // The parent portal is the consumer this protects. A draft score that
        // reached it would be a school publishing a term before it is graded.
        $this->assertSame(
            0,
            Grade::query()->where('status', Grade::PUBLISHED)->count()
        );
    }

    /** @test */
    public function publishing_moves_drafts_to_published_and_stamps_the_actor(): void
    {
        $class = $this->classWith();
        $semester = $this->semester($class);
        $subject = $this->subject();
        $enrollment = $this->firstEnrollment($class);

        $teacher = $this->teacher();
        $head = $this->makeUser('admin')->refresh();

        app(GradeEntryService::class)->save(
            $class, $semester, $subject, [$enrollment->id => 78], $teacher
        );

        $count = app(GradeEntryService::class)->publish(
            $class, $semester, [$subject->id], $head
        );

        $this->assertSame(1, $count);

        $grade = Grade::firstOrFail();

        $this->assertSame(Grade::PUBLISHED, $grade->status);
        $this->assertNotNull($grade->published_at);
        $this->assertSame($head->id, $grade->published_by);
    }

    /** @test */
    public function a_second_save_updates_rather_than_duplicating(): void
    {
        $class = $this->classWith();
        $semester = $this->semester($class);
        $subject = $this->subject();
        $enrollment = $this->firstEnrollment($class);
        $teacher = $this->teacher();
        $service = app(GradeEntryService::class);

        $service->save($class, $semester, $subject, [$enrollment->id => 70], $teacher);
        $service->save($class, $semester, $subject, [$enrollment->id => 75], $teacher);

        // The unique key is (enrollment_id, subject_id, term). A second insert
        // would either fail or create a second row for one student and one
        // subject, and a report aggregating both would double-count.
        $this->assertSame(1, Grade::query()->count());
        $this->assertSame(75.0, (float) Grade::firstOrFail()->score);
    }

    /**
     * @test
     */
    public function a_cleared_cell_does_not_delete_a_recorded_score(): void
    {
        $class = $this->classWith();
        $semester = $this->semester($class);
        $subject = $this->subject();
        $enrollment = $this->firstEnrollment($class);
        $teacher = $this->teacher();
        $service = app(GradeEntryService::class);

        $service->save($class, $semester, $subject, [$enrollment->id => 82], $teacher);

        // The teacher clears the cell. That means "not graded yet", which is
        // not the same as "delete what I entered": a gradebook auto-submits
        // every cell on every save, so an accidental clear would otherwise
        // silently erase a whole column.
        $written = $service->save($class, $semester, $subject, [$enrollment->id => ''], $teacher);

        $this->assertSame(0, $written);
        $this->assertSame(82.0, (float) Grade::firstOrFail()->score);
    }

    /**
     * @test
     */
    public function a_score_cannot_be_written_onto_a_student_outside_the_class(): void
    {
        $class = $this->classWith();
        $other = $this->classWith(1);
        $semester = $this->semester($class);
        $subject = $this->subject();

        $foreignEnrollment = $other->liveEnrollments()->firstOrFail();

        $written = app(GradeEntryService::class)->save(
            $class, $semester, $subject, [$foreignEnrollment->id => 100], $this->teacher()
        );

        $this->assertSame(0, $written);
        $this->assertSame(0, Grade::query()->count());
    }

    // ------------------------------------------------------------------- scope

    /**
     * @test
     */
    public function a_wali_kelas_cannot_open_a_class_they_do_not_homeroom(): void
    {
        $mine = $this->classWith();
        $theirs = $this->classWith(1);

        $wali = $this->teacher();
        \App\Models\HomeroomAssignment::create([
            'user_id' => $wali->id,
            'classroom_id' => $mine->id,
            'academic_year_id' => $mine->academic_year_id,
            'started_at' => now()->toDateString(),
            'status' => \App\Models\HomeroomAssignment::ACTIVE,
        ]);

        // Holding grade.edit is not holding the assignment. Without the scope
        // check, changing one id in the URL opens another teacher's class.
        $this->actingAs($wali)
            ->get("/akademik/kelas/{$mine->id}/nilai")
            ->assertOk();

        $this->actingAs($wali)
            ->get("/akademik/kelas/{$theirs->id}/nilai")
            ->assertForbidden();
    }

    // ----------------------------------------------------------------- the API

    /** @test */
    public function the_gradebook_screen_opens_for_a_permitted_teacher(): void
    {
        $class = $this->classWith();
        $wali = $this->teacher();

        \App\Models\HomeroomAssignment::create([
            'user_id' => $wali->id,
            'classroom_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'started_at' => now()->toDateString(),
            'status' => \App\Models\HomeroomAssignment::ACTIVE,
        ]);

        $this->actingAs($wali)
            ->get("/akademik/kelas/{$class->id}/nilai")
            ->assertOk();
    }

    /** @test */
    public function a_student_cannot_reach_the_gradebook(): void
    {
        $class = $this->classWith();

        $this->actingAs($this->makeUser('siswa'))
            ->get("/akademik/kelas/{$class->id}/nilai")
            ->assertForbidden();
    }

    /** @test */
    public function a_guest_is_sent_to_login(): void
    {
        $class = $this->classWith();

        $this->get("/akademik/kelas/{$class->id}/nilai")
            ->assertRedirect(route('login'));
    }
}
