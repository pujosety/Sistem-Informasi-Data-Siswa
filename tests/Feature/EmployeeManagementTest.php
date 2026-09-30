<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\ModuleService;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the HRIS surface that Phase 3 left without a UI.
 *
 * THE FAILURES THESE PREVENT
 *
 * 1. A resignation reachable by `employee.update`. Ending someone's
 *    employment is the one write in this module with no undo and a visible
 *    consequence, and it is the thing a careless or disgruntled administrator
 *    reaches for first. So it has its own permission, its own route and its own
 *    policy ability, and the tests below prove that holding `update` does not
 *    reach it — including via a crafted POST to the update route.
 *
 * 2. Identity leaking into the employment row. The form offers no name or
 *    email field, and the controller's write list does not include them, so
 *    there is no path that copies them. A test that posts a name and asserts
 *    it was ignored is the only way to keep that true when someone later adds
 *    a convenient "quick edit" field.
 *
 * 3. Re-attribution. Changing `user_id` on an existing record would move a
 *    contract and a hire date onto a different person, silently rewriting who
 *    the employment belonged to.
 *
 * 4. A hard delete. A resignation cascades the grade history and homeroom
 *    assignments the person created out of existence, which is why `resign()`
 *    sets a status and keeps the row.
 */
class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] permissions this test revoked from a role */
    private array $restorePermissions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    /**
     * Put back anything adminWithout() took away.
     *
     * RefreshDatabase rolls the SCHEMA back, not Spatie's in-process permission
     * cache. A revoked grant therefore survives into the next test file, and the
     * failure lands somewhere unrelated — `can()` true, ClassScope empty, a
     * screen 403 that no test in that file would explain.
     */
    protected function tearDown(): void
    {
        foreach ($this->restorePermissions as $permission) {
            foreach (['admin'] as $roleName) {
                $role = \Spatie\Permission\Models\Role::findByName($roleName);

                if ($role && ! $role->permissions->contains($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    // ------------------------------------------------------------------ actors

    private function admin(): User
    {
        $user = $this->makeUser('admin');

        // The registry ships `hris` ENABLED now that the UI exists, so nothing
        // is toggled here. ModuleRegistryTest owns the seed assertion, and the
        // navigation tests below flip it off and on themselves.
        return $user;
    }

    /**
     * An admin with every employee.* permission EXCEPT the one named.
     *
     * Built by stripping a single permission off the real catalogue grant
     * rather than by hand-listing permissions, so the fixture cannot drift
     * away from what the catalogue actually issues.
     */
    /**
     * An admin who does NOT hold one permission, for the duration of a test.
     *
     * The revoke is undone by TestCase::withoutPermission()'s counterpart in
     * tearDown — see setUpBelow() — because Spatie's permission cache lives
     * for the whole PHP process, so a bare revokePermissionTo() would leave
     * that grant missing from every test that runs afterwards.
     */
    private function adminWithout(string $permission): User
    {
        $admin = $this->admin();

        $admin->roles->each(function ($role) use ($permission) {
            $role->revokePermissionTo($permission);
        });

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->restorePermissions[] = $permission;

        return $admin->refresh();
    }

    private function staffUser(string $name = 'Guru Matematika'): User
    {
        $user = $this->makeUser('wali_kelas');
        $user->update(['name' => $name]);

        return $user->refresh();
    }

    private function employeeFor(User $user, array $attributes = []): Employee
    {
        $department = Department::firstOrCreate(
            ['name' => 'Matematika'],
            // `code` is NOT NULL in the schema, so firstOrCreate needs it
            // supplied or the insert fails with a raw 1364.
            ['code' => 'MTK']
        );

        return Employee::create($attributes + [
            'user_id' => $user->id,
            'employee_number' => 'PG-'.($user->id + 100),
            'department_id' => $department->id,
            'position' => 'Guru',
            'employment_status' => Employee::ACTIVE,
            'employment_type' => 'permanent',
            'created_by' => $this->admin()->id,
        ]);
    }

    // ----------------------------------------------------------- the happy path

    /** @test */
    public function an_admin_can_open_the_staff_list(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.employees'))
            ->assertOk();
    }

    /** @test */
    public function an_admin_can_record_an_employment_against_an_existing_account(): void
    {
        $staff = $this->staffUser();

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), [
                'user_id' => $staff->id,
                'employee_number' => 'PG-2026-001',
                'department_id' => Department::firstOrCreate(['name' => 'IPA'], ['code' => 'IPA'])->id,
                'position' => 'Guru IPA',
                'employment_status' => Employee::ACTIVE,
                'employment_type' => 'permanent',
                'hire_date' => '2026-07-01',
            ])
            ->assertRedirect();

        $employee = Employee::where('user_id', $staff->id)->firstOrFail();

        $this->assertSame('PG-2026-001', $employee->employee_number);
        $this->assertSame('Guru IPA', $employee->position);
        $this->assertSame('2026-07-01', $employee->hire_date->format('Y-m-d'));
    }

    /** @test */
    public function the_record_is_audited(): void
    {
        $staff = $this->staffUser();

        $this->actingAs($this->admin())
            ->post(route('admin.employees.store'), [
                'user_id' => $staff->id,
                'employment_status' => Employee::ACTIVE,
                'employment_type' => 'permanent',
            ]);

        $employee = Employee::where('user_id', $staff->id)->firstOrFail();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'employee.created',
            'subject_type' => 'employee',
            'subject_id' => $employee->id,
        ]);
    }

    /**
     * @test
     */
    public function a_second_record_for_the_same_account_updates_rather_than_duplicates(): void
    {
        $staff = $this->staffUser();

        $this->actingAs($this->admin())->post(route('admin.employees.store'), [
            'user_id' => $staff->id,
            'position' => 'Guru Baru',
            'employment_status' => Employee::ACTIVE,
            'employment_type' => 'permanent',
        ]);

        $this->actingAs($this->admin())->post(route('admin.employees.store'), [
            'user_id' => $staff->id,
            'position' => 'Guru Senior',
            'employment_status' => Employee::ACTIVE,
            'employment_type' => 'permanent',
        ]);

        // One person, one employment. Two rows would mean two contracts.
        $this->assertSame(1, Employee::where('user_id', $staff->id)->count());
        $this->assertSame('Guru Senior', Employee::where('user_id', $staff->id)->firstOrFail()->position);
    }

    /**
     * @test
     */
    public function searching_reaches_the_name_on_the_linked_account(): void
    {
        $staff = $this->staffUser('Siti Aminah');
        $this->employeeFor($staff);

        // The name lives on `users`. A search that queried `employees` alone
        // would return an empty list for every real employee, which reads as
        // "search is broken" rather than "search looked in the wrong table".
        $this->actingAs($this->admin())
            ->get(route('admin.employees', ['q' => 'Siti']))
            ->assertOk()
            ->assertSee('Siti Aminah');
    }

    /**
     * @test
     */
    public function the_list_reports_how_many_staff_have_no_employment_record(): void
    {
        $this->employeeFor($this->staffUser());

        $this->actingAs($this->admin())
            ->get(route('admin.employees'))
            ->assertOk()
            ->assertSee('belum punya catatan kepegawaian');
    }

    // ------------------------------------------------------------- identity

    /**
     * @test
     */
    public function a_posted_name_and_email_are_ignored_rather_than_copied(): void
    {
        $staff = $this->staffUser('Nama Asli');
        $employee = $this->employeeFor($staff);

        $this->actingAs($this->admin())
            ->put(route('admin.employees.update', $employee), [
                'user_id' => $staff->id,
                'name' => 'Nama Palsu',
                'email' => 'palsu@example.test',
                'position' => 'Guru Besar',
                'employment_status' => Employee::ACTIVE,
                'employment_type' => 'permanent',
            ]);

        $staff->refresh();

        $this->assertSame('Nama Asli', $staff->name, 'Identity must not be written from the HR form.');
        $this->assertNotSame('palsu@example.test', $staff->email);
        $this->assertSame('Guru Besar', $employee->refresh()->position);
    }

    /**
     * @test
     */
    public function the_linked_account_cannot_be_swapped_for_another_person(): void
    {
        $employee = $this->employeeFor($this->staffUser('A'));
        $other = $this->staffUser('B');

        $this->actingAs($this->admin())
            ->put(route('admin.employees.update', $employee), [
                'user_id' => $other->id,
                'employment_status' => Employee::ACTIVE,
                'employment_type' => 'permanent',
            ]);

        $this->assertSame($employee->user_id, $employee->refresh()->user_id);
    }

    // ---------------------------------------------------------- the resignation

    /**
     * @test
     */
    public function a_resignation_sets_a_status_and_keeps_the_row(): void
    {
        $employee = $this->employeeFor($this->staffUser());

        $this->actingAs($this->admin())
            ->post(route('admin.employees.resign', $employee), [
                'resignation_reason' => 'Pensiun',
            ]);

        $employee->refresh();

        $this->assertSame(Employee::RESIGNED, $employee->employment_status);
        $this->assertNotNull($employee->resigned_at);
        $this->assertSame('Pensiun', $employee->resignation_reason);
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
    }

    /**
     * @test
     */
    public function holding_update_alone_cannot_end_an_employment(): void
    {
        $admin = $this->adminWithout('employee.resign');
        $employee = $this->employeeFor($this->staffUser());

        $this->assertFalse($admin->fresh()->can('employee.resign'));

        $this->actingAs($admin)
            ->post(route('admin.employees.resign', $employee))
            ->assertForbidden();

        $this->assertSame(Employee::ACTIVE, $employee->refresh()->employment_status);
    }

    /**
     * @test
     */
    public function an_ordinary_save_cannot_rewrite_a_termination(): void
    {
        $admin = $this->adminWithout('employee.resign');
        $employee = $this->employeeFor($this->staffUser());

        // resigned_at is not in the controller's write list, so a crafted
        // payload carrying it is dropped rather than applied.
        $this->actingAs($admin)
            ->put(route('admin.employees.update', $employee), [
                'user_id' => $employee->user_id,
                'employment_status' => Employee::ACTIVE,
                'employment_type' => 'permanent',
                'resigned_at' => '2020-01-01 00:00:00',
                'resignation_reason' => 'dismissed',
            ]);

        $employee->refresh();

        $this->assertNull($employee->resigned_at);
        $this->assertNull($employee->resignation_reason);
    }

    /**
     * @test
     */
    public function a_person_can_be_reinstated_after_resigning(): void
    {
        $employee = $this->employeeFor($this->staffUser());

        $this->actingAs($this->admin())->post(route('admin.employees.resign', $employee), [
            'resignation_reason' => 'Kontrak habis',
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.employees.reinstate', $employee));

        $employee->refresh();

        $this->assertSame(Employee::ACTIVE, $employee->employment_status);
        $this->assertNull($employee->resigned_at);
        $this->assertNull($employee->resignation_reason);
    }

    /**
     * @test
     */
    public function resigning_twice_is_refused_rather_than_stamping_a_second_date(): void
    {
        $employee = $this->employeeFor($this->staffUser());

        $this->actingAs($this->admin())->post(route('admin.employees.resign', $employee));
        $first = $employee->refresh()->resigned_at;

        $this->actingAs($this->admin())
            ->post(route('admin.employees.resign', $employee))
            ->assertSessionHas('error');

        $this->assertEquals($first, $employee->refresh()->resigned_at);
    }

    // ------------------------------------------------------------ authorization

    /**
     * @test
     */
    public function kesiswaan_cannot_reach_the_staff_list(): void
    {
        // kesiswaan holds a lot — student.*, document.*, report.*, cms draft —
        // and none of it is HRIS. A prefix match that pulled `employee` in
        // through some shared parent would be the obvious way for this to leak.
        $this->actingAs($this->makeUser('kesiswaan'))
            ->get(route('admin.employees'))
            ->assertForbidden();
    }

    /** @test */
    public function a_student_cannot_reach_the_staff_list(): void
    {
        $this->actingAs($this->makeUser('siswa'))
            ->get(route('admin.employees'))
            ->assertForbidden();
    }

    /** @test */
    public function a_guest_is_sent_to_login(): void
    {
        $this->get(route('admin.employees'))->assertRedirect(route('login'));
    }

    /** @test */
    public function a_disabled_admin_is_denied_every_employee_screen(): void
    {
        $admin = $this->admin();
        $admin->update(['is_active' => false]);

        $employee = $this->employeeFor($this->staffUser());

        $this->actingAs($admin->refresh())->get(route('admin.employees'))->assertRedirect(route('login'));
        $this->actingAs($admin->refresh())->get(route('admin.employees.show', $employee))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------- navigation

    /** @test */
    public function the_hris_module_is_seeded_enabled_now_that_it_has_a_ui(): void
    {
        // It shipped disabled while the table existed with no screen. The
        // employment UI landed, so the section is reachable. MySQL hands back
        // 0/1 rather than a PHP bool, hence the cast.
        $this->assertTrue(
            (bool) DB::table('modules')->where('key', 'hris')->value('is_enabled'),
            'HRIS has an employment UI and must be seeded enabled.'
        );
    }

    /**
     * @test
     */
    public function the_section_appears_once_the_module_is_switched_on_and_held(): void
    {
        $admin = $this->admin();
        $nav = app(NavigationService::class);

        $this->assertContains('Kepegawaian', $this->labels($nav, $admin));

        // Switched off, the link disappears from every surface at once — the
        // §50 promise, and the reason the route is permission-gated rather
        // than module-gated at the middleware level.
        app(ModuleService::class)->setEnabled('hris', false);

        $this->assertNotContains('Kepegawaian', $this->labels($nav, $admin));
    }

    /**
     * @test
     */
    public function the_section_stays_hidden_for_a_role_that_lacks_the_permission(): void
    {
        // Module on, permission absent: the item must still be filtered out,
        // or it becomes a link that 403s. The module ships enabled, so this
        // exercises the permission gate on its own.
        $kesiswaan = $this->makeUser('kesiswaan');

        $this->assertNotContains('Kepegawaian', $this->labels(app(NavigationService::class), $kesiswaan));
    }

    /**
     * Every label across every navigation surface.
     *
     * @return array<int, string>
     */
    private function labels(NavigationService $nav, User $user): array
    {
        $data = $nav->forUser($user);
        $labels = [];

        $walk = function (array $items) use (&$walk, &$labels): void {
            foreach ($items as $item) {
                if (isset($item['label'])) {
                    $labels[] = $item['label'];
                }
                if (! empty($item['children'])) {
                    $walk($item['children']);
                }
            }
        };

        $walk($data['items']);
        $walk($data['dock']);
        $walk($data['more']);

        return $labels;
    }

}
