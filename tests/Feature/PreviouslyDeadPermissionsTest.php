<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Alumni;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\HomeroomAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards three permissions that were granted and reached nothing.
 *
 * Each of these appeared in the role matrix as a capability an administrator
 * could hand out, and did nothing when they did. That is worse than an absent
 * permission: a school grants `alumni.view` believing someone can now find its
 * graduates, and discovers the capability does not exist.
 *
 *   alumni.view        the table and the model existed; the screen did not
 *   student.export     /laporan exports the same data behind `report.export`
 *   homeroom.change    one method did appoint AND replace behind `homeroom.assign`
 */
class PreviouslyDeadPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    // ------------------------------------------------------------------ alumni

    private int $seq = 0;

    private function graduate(string $name, int $year = 2025): Alumni
    {
        // firstOrCreate, not create: two graduates in the same year is the
        // normal case, and `name` is unique on academic_years.
        $ay = AcademicYear::firstOrCreate(
            ['name' => $year.'/'.($year + 1)],
            [
                'start_date' => $year.'-07-01',
                'end_date' => ($year + 1).'-06-30',
            ]
        );

        // One class per graduate: `classes.name` is unique, and the class is
        // what makes the history on the detail page worth looking at.
        $class = SchoolClass::firstOrCreate(
            ['name' => 'XII RPL '.(++$this->seq)],
            [
                'level' => 'XII',
                'academic_year_id' => $ay->id,
                'department_id' => Department::firstOrCreate(['name' => 'RPL'], ['code' => 'RPL'])->id,
            ]
        );

        $account = $this->makeUser('siswa');
        $account->update(['name' => $name]);

        $student = Student::create([
            'user_id' => $account->id,
            'full_name' => $name,
            'nisn' => (string) (930000 + (++$this->seq) * 7),
            'gender' => 'L',
            'class_id' => $class->id,
            'academic_year_id' => $ay->id,
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $ay->id,
            'classroom_id' => $class->id,
            'status' => Enrollment::ACTIVE,
        ]);

        return Alumni::create([
            'student_id' => $student->id,
            'graduation_year' => $year,
            'graduation_date' => $year.'-06-20',
            'last_classroom_id' => $class->id,
            'department_id' => $class->department_id,
        ]);
    }

    /** @test */
    public function a_permitted_role_can_list_alumni(): void
    {
        $this->graduate('Budi Santoso');

        $this->actingAs($this->makeUser('kesiswaan'))
            ->get(route('alumni.index'))
            ->assertOk()
            ->assertSee('Budi Santoso');
    }

    /** @test */
    public function the_alumni_search_finds_by_name(): void
    {
        $this->graduate('Budi Santoso');
        $this->graduate('Siti Aminah');

        $this->actingAs($this->makeUser('kesiswaan'))
            ->get(route('alumni.index', ['q' => 'Siti']))
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertDontSee('Budi Santoso');
    }

    /** @test */
    public function a_role_without_the_permission_is_refused(): void
    {
        foreach (['operator', 'verifikator', 'wali_kelas', 'siswa'] as $role) {
            $this->actingAs($this->makeUser($role))
                ->get(route('alumni.index'))
                ->assertForbidden();
        }
    }

    /** @test */
    public function a_guest_is_sent_to_login(): void
    {
        $this->get(route('alumni.index'))->assertRedirect(route('login'));
    }

    /**
     * @test
     */
    public function an_alumni_record_is_never_editable_or_deletable(): void
    {
        $row = $this->graduate('Budi Santoso');
        $actor = $this->makeUser('admin');

        // Even Super Admin. A graduation is written by the academic flow that
        // closed the enrollment; editing the row afterwards would leave a record
        // claiming someone graduated with no enrollment behind it.
        $this->assertFalse(\Gate::forUser($actor)->allows('update', $row));
        $this->assertFalse(\Gate::forUser($actor)->allows('delete', $row));
    }

    /** @test */
    public function the_alumni_detail_page_shows_the_enrollment_history(): void
    {
        $row = $this->graduate('Budi Santoso');

        $this->actingAs($this->makeUser('kesiswaan'))
            ->get(route('alumni.show', $row))
            ->assertOk()
            ->assertSee('Budi Santoso')
            // The history is the point of the screen: the alumni row says they
            // graduated, this says what they actually took.
            ->assertSee('XII RPL');
    }

    // ------------------------------------------------------------ student export

    /** @test */
    public function a_permitted_role_can_export_the_roster(): void
    {
        $this->graduate('Budi Santoso');

        $this->actingAs($this->makeUser('kesiswaan'))
            ->get(route('kesiswaan.students.export', ['format' => 'csv']))
            ->assertOk();
    }

    /**
     * @test
     */
    public function a_role_without_the_export_permission_is_refused(): void
    {
        foreach (['operator', 'verifikator', 'wali_kelas', 'siswa'] as $role) {
            $this->actingAs($this->makeUser($role))
                ->get(route('kesiswaan.students.export', ['format' => 'csv']))
                ->assertForbidden();
        }
    }

    /** @test */
    public function the_export_format_is_restricted_to_what_is_offered(): void
    {
        // `whereIn` on the route, so an unlisted format is a 404 rather than a
        // file with a misleading extension.
        $this->actingAs($this->makeUser('kesiswaan'))
            ->get('/kesiswaan/data-siswa/php/export')
            ->assertNotFound();
    }

    // ------------------------------------------------------------ homeroom change

    private function classWithHomeroom(): SchoolClass
    {
        $ay = $this->seedAcademicYear();

        $class = SchoolClass::create([
            'name' => 'XII IPA 1',
            'level' => 'XII',
            'academic_year_id' => $ay->id,
        ]);

        $teacher = $this->makeUser('wali_kelas');

        HomeroomAssignment::create([
            'user_id' => $teacher->id,
            'classroom_id' => $class->id,
            'academic_year_id' => $ay->id,
            'started_at' => now()->toDateString(),
            'status' => HomeroomAssignment::ACTIVE,
        ]);

        return $class;
    }

    /**
     * THE CENTRAL ASSERTION
     *
     * Appointing a Wali Kelas where there is none, and REPLACING one who is
     * already in place, are different decisions. Replacing ends somebody's
     * assignment: their "Kelas Saya" workspace empties and what they can reach
     * changes. `homeroom.change` existed for exactly that and was consulted by
     * nothing, because one method did both behind `homeroom.assign`.
     *
     * @test
     */
    public function replacing_a_homeroom_teacher_needs_the_change_permission(): void
    {
        $class = $this->classWithHomeroom();

        $admin = $this->makeUser('admin')->fresh();
        $this->assertTrue($admin->can('homeroom.assign'));
        $this->assertTrue($admin->can('homeroom.change'));

        // Strip only the CHANGE half, through the role, and forget the cache so
        // can() is not answering from a stale list. Restored afterwards.
        $role = \Spatie\Permission\Models\Role::findByName('admin');
        $role->revokePermissionTo('homeroom.change');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        try {
            $admin = $admin->fresh();
            $this->assertTrue($admin->can('homeroom.assign'));
            $this->assertFalse($admin->can('homeroom.change'));

            $replacement = $this->makeUser('wali_kelas');

            // There IS a homeroom teacher, so this is a REPLACEMENT and the
            // stronger permission applies.
            $this->actingAs($admin)
                ->post("/akademik/kelas/{$class->id}/wali-kelas", [
                    'user_id' => $replacement->id,
                ])
                ->assertForbidden();

            // The assignment is untouched.
            $this->assertSame(
                1,
                HomeroomAssignment::where('classroom_id', $class->id)
                    ->where('status', HomeroomAssignment::ACTIVE)
                    ->count()
            );
        } finally {
            $role->givePermissionTo('homeroom.change');
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * @test
     */
    public function appointing_a_vacant_homeroom_only_needs_the_assign_permission(): void
    {
        $ay = $this->seedAcademicYear();

        $class = SchoolClass::create([
            'name' => 'XII IPA 2',
            'level' => 'XII',
            'academic_year_id' => $ay->id,
        ]);

        $role = \Spatie\Permission\Models\Role::findByName('admin');
        $role->revokePermissionTo('homeroom.change');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        try {
            $admin = $this->makeUser('admin')->fresh();
            $teacher = $this->makeUser('wali_kelas');

            // No current holder, so this is an APPOINTMENT and the weaker
            // permission is the right one.
            $this->actingAs($admin)
                ->post("/akademik/kelas/{$class->id}/wali-kelas", [
                    'user_id' => $teacher->id,
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('homeroom_assignments', [
                'classroom_id' => $class->id,
                'user_id' => $teacher->id,
                'status' => HomeroomAssignment::ACTIVE,
            ]);
        } finally {
            $role->givePermissionTo('homeroom.change');
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
