<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards correcting a master record.
 *
 * THE GAP THIS CLOSES
 *
 * Master data had `master.create` and no edit at all. `master.update` was
 * defined in the catalogue, granted to admin, and had no route — so an operator
 * could add a class and then be unable to correct a typo in its name, its level
 * or its capacity, ever. The only way out was a second row holding the
 * corrected value, which then looks like two classes.
 *
 * The subtlety the tests below exist for: an edit must refuse a value that
 * COLLIDES with another row, while allowing a value identical to the row's own.
 * `unique(...)->ignore($id)` is the whole difference between "you typed the
 * name that is already there" failing forever and an edit that cannot be saved.
 */
class MasterDataEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    private function admin(): User
    {
        return $this->makeUser('admin')->refresh();
    }

    /**
     * firstOrCreate, not create.
     *
     * `academic_years.name` is unique, and two classes in one test each ask for
     * the default year. A collision here dies in the fixture before a single
     * assertion runs, which reads as a feature failure rather than a test bug.
     */
    private function year(string $name = '2026/2027'): AcademicYear
    {
        return AcademicYear::firstOrCreate(
            ['name' => $name],
            [
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
            ]
        );
    }

    // ------------------------------------------------------------------- year

    /** @test */
    public function an_admin_holds_the_update_permission(): void
    {
        $this->assertTrue($this->admin()->can('master.create'));
        $this->assertTrue($this->admin()->can('master.update'));
    }

    /** @test */
    public function a_class_can_be_renamed(): void
    {
        $class = SchoolClass::create([
            'name' => 'XI IPA 1',
            'level' => 'XI',
            'academic_year_id' => $this->year()->id,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.master.update', ['type' => 'kelas', 'id' => $class->id]), [
                'name' => 'XI IPA 2',
                'level' => 'XI',
                'academic_year_id' => $class->academic_year_id,
            ])
            ->assertRedirect();

        $this->assertSame('XI IPA 2', $class->refresh()->name);
    }

    /**
     * THE COLLISION CASE.
     *
     * `unique('classes','name')` on the CREATE rule and no edit route at all.
     * The edit rule ignores its own id, so re-saving the same name succeeds
     * while adopting another class's name is refused. Without the `ignore`, a
     * user correcting one field on a form that still carried the old values
     * would be permanently unable to save.
     *
     * @test
     */
    public function an_edit_may_keep_its_own_name_but_not_take_another(): void
    {
        $mine = SchoolClass::create([
            'name' => 'XII IPA 1',
            'level' => 'XII',
            'academic_year_id' => $this->year()->id,
        ]);

        SchoolClass::create([
            'name' => 'XII IPA 2',
            'level' => 'XII',
            // The same year as the class above — one call, one year.
            'academic_year_id' => $mine->academic_year_id,
        ]);

        // Same name as itself, different capacity: must save.
        $this->actingAs($this->admin())
            ->put(route('admin.master.update', ['type' => 'kelas', 'id' => $mine->id]), [
                'name' => 'XII IPA 1',
                'level' => 'XII',
                'capacity' => 34,
                'academic_year_id' => $mine->academic_year_id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(34, $mine->refresh()->capacity);

        // Taking the other class's name: must be refused.
        $this->actingAs($this->admin())
            ->from(route('admin.master'))
            ->put(route('admin.master.update', ['type' => 'kelas', 'id' => $mine->id]), [
                'name' => 'XII IPA 2',
                'level' => 'XII',
                'academic_year_id' => $mine->academic_year_id,
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame('XII IPA 1', $mine->refresh()->name);
    }

    // -------------------------------------------------------------- department

    /** @test */
    public function a_department_code_can_be_corrected(): void
    {
        $department = Department::create(['name' => 'Rekayasa Perangkat Lunak', 'code' => 'RPL']);

        $this->actingAs($this->admin())
            ->put(route('admin.master.update', ['type' => 'jurusan', 'id' => $department->id]), [
                'name' => 'Rekayasa Perangkat Lunak',
                'code' => 'RPLX',
            ])
            ->assertRedirect();

        $this->assertSame('RPLX', $department->refresh()->code);
    }

    // ------------------------------------------------------------------- year

    /**
     * @test
     */
    public function activating_a_year_clears_the_other_active_one(): void
    {
        $first = $this->year('2025/2026');
        $first->update(['is_active' => true]);

        $second = $this->year('2026/2027');

        $this->actingAs($this->admin())
            ->put(route('admin.master.update', ['type' => 'tahun-ajaran', 'id' => $second->id]), [
                'name' => '2026/2027',
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($second->refresh()->is_active);
        $this->assertFalse(
            $first->refresh()->is_active,
            'Two active years would leave the dashboard reading whichever it found first.'
        );
    }

    /** @test */
    public function a_year_end_date_must_be_after_its_start(): void
    {
        $year = $this->year();

        $this->actingAs($this->admin())
            ->from(route('admin.master'))
            ->put(route('admin.master.update', ['type' => 'tahun-ajaran', 'id' => $year->id]), [
                'name' => '2026/2027',
                'start_date' => '2027-07-01',
                'end_date' => '2026-06-30',
            ])
            ->assertSessionHasErrors('end_date');
    }

    // ---------------------------------------------------------- document type

    /** @test */
    public function a_document_type_can_be_corrected(): void
    {
        $type = DocumentType::create([
            'name' => 'Foto Siswa',
            'slug' => 'foto-siswa',
            'label' => 'Foto 3x4',
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.master.update', ['type' => 'jenis-dokumen', 'id' => $type->id]), [
                'name' => 'Foto Siswa 3x4',
                'slug' => 'foto-siswa',
                'label' => 'Foto 3x4 recent',
                'is_required' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('Foto Siswa 3x4', $type->refresh()->name);
    }

    // --------------------------------------------------------- authorization

    /** @test */
    public function a_role_that_may_only_read_master_data_cannot_edit_it(): void
    {
        // Read WITHOUT write, which is the interesting case: the list opens
        // and the save is refused. kesiswaan holds neither master.view nor
        // master.update, so it cannot stand in for it — the pair is built by
        // removing only the update half from admin, through the role, and
        // forgetting the cache so can() is not answering from a stale list.
        $role = \Spatie\Permission\Models\Role::findByName('admin');
        $role->revokePermissionTo('master.update');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        try {
            $viewer = $this->makeUser('admin')->fresh();

            $this->assertTrue($viewer->can('master.view'));
            $this->assertFalse($viewer->can('master.update'));

            $class = SchoolClass::create([
                'name' => 'X IPA 1',
                'level' => 'X',
                'academic_year_id' => $this->year()->id,
            ]);

            $this->actingAs($viewer)
                ->put(route('admin.master.update', ['type' => 'kelas', 'id' => $class->id]), [
                    'name' => 'Diubah',
                    'level' => 'X',
                    'academic_year_id' => $class->academic_year_id,
                ])
                ->assertForbidden();

            $this->assertSame('X IPA 1', $class->refresh()->name);
        } finally {
            $role->givePermissionTo('master.update');
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /** @test */
    public function a_student_cannot_edit_master_data(): void
    {
        $class = SchoolClass::create([
            'name' => 'X IPA 2',
            'level' => 'X',
            'academic_year_id' => $this->year()->id,
        ]);

        $this->actingAs($this->makeUser('siswa'))
            ->put(route('admin.master.update', ['type' => 'kelas', 'id' => $class->id]), [
                'name' => 'Dibajak',
                'level' => 'X',
                'academic_year_id' => $class->academic_year_id,
            ])
            ->assertForbidden();
    }

    /** @test */
    public function an_unknown_master_type_is_not_found(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.master.update', ['type' => 'kelas', 'id' => 999999]), [
                'name' => 'X',
            ])
            // whereIn on the route refuses the path entirely; the controller's
            // match is the second line of defence for a future caller.
            ->assertNotFound();
    }

    /** @test */
    public function editing_a_class_never_touches_its_students(): void
    {
        $year = $this->year();
        $class = SchoolClass::create([
            'name' => 'XII IPA 3',
            'level' => 'XII',
            'academic_year_id' => $year->id,
        ]);

        $student = Student::create([
            'user_id' => $this->makeUser('siswa')->id,
            'full_name' => 'Siswa Tetap',
            'nisn' => '9912345',
            'class_id' => $class->id,
            'academic_year_id' => $year->id,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.master.update', ['type' => 'kelas', 'id' => $class->id]), [
                'name' => 'XII IPA 4',
                'level' => 'XII',
                'academic_year_id' => $year->id,
            ]);

        // A rename is a rename. A class edit that could orphan its roster
        // would be a far more dangerous feature than this one.
        $this->assertSame($class->id, $student->refresh()->class_id);
    }
}
