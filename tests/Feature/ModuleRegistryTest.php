<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ModuleService;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards §50: disabling a module must leave no broken navigation behind.
 *
 * THE FAILURE THIS PREVENTS
 *
 * A module switch that only hides the sidebar is not a switch. The mobile dock
 * and the overflow menu are built from the same data by the same service, so
 * hiding one surface while the other keeps a link is the likely outcome — and
 * the link leads to a route whose module is off, which is exactly the broken
 * navigation the requirement forbids.
 *
 * Hence the assertions below check every surface at once: sidebar items, their
 * children, the dock, and the overflow list.
 */
class ModuleRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user->refresh();
    }

    /**
     * super_admin, for assertions about screens `admin` cannot open.
     *
     * No role is granted user.* or role.*, so user management is reachable
     * only through Gate::before. Using `admin` here would assert against an
     * actor that never sees those items, and the test would be wrong rather
     * than the navigation.
     */
    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user->refresh();
    }

    /**
     * Every navigation entry across every surface, as a flat list of labels.
     *
     * @return array<int, string>
     */
    private function allLabels(NavigationService $nav, User $user): array
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

    /**
     * @test
     */
    public function test_the_registry_seeds_every_named_module(): void
    {
        $keys = \App\Models\Module::pluck('key')->all();

        foreach ([
            'students', 'academic', 'attendance', 'documents', 'ppdb', 'parent',
            'cms', 'lms', 'hris', 'assets',
            'payroll', 'finance', 'procurement',
        ] as $key) {
            $this->assertContains($key, $keys, "Module [{$key}] is missing from the registry.");
        }
    }

    /**
     * @test
     */
    public function test_required_modules_are_the_platform_ones(): void
    {
        // Switching off students or academic would leave no way to record a
        // student or a grade, so those must be required.
        $this->assertTrue(\App\Models\Module::where('key', 'students')->firstOrFail()->is_required);
        $this->assertTrue(\App\Models\Module::where('key', 'academic')->firstOrFail()->is_required);

        $this->assertFalse(\App\Models\Module::where('key', 'lms')->firstOrFail()->is_required);
    }

    /**
     * @test
     */
    public function test_unbuilt_modules_are_seeded_disabled(): void
    {
        // The registry lists the whole platform, but a navigation filter that
        // consults it must not surface a section that leads nowhere.
        //
        // cms and hris were in this list until their UIs landed. Both were
        // seeded OFF because the tables existed with no screen to reach them,
        // and a section that 403s is worse than an absent one. Now that the
        // editor and the staff list exist, they ship ON — see
        // test_built_modules_are_seeded_enabled.
        foreach (['payroll', 'finance', 'procurement'] as $key) {
            $this->assertFalse(
                \App\Models\Module::where('key', $key)->firstOrFail()->is_enabled,
                "Module [{$key}] has no implementation and must be seeded disabled."
            );
        }
    }

    /**
     * @test
     */
    public function test_built_modules_are_seeded_enabled(): void
    {
        // A module whose screen is built must not ship hidden. A registry that
        // hides finished work is not honesty, it is a switch nobody flipped —
        // and the operator has no way to know it exists.
        foreach (['cms', 'hris', 'lms'] as $key) {
            $this->assertTrue(
                \App\Models\Module::where('key', $key)->firstOrFail()->is_enabled,
                "Module [{$key}] has a screen and must be seeded enabled."
            );
        }
    }

    /**
     * @test
     */
    public function test_lms_navigation_is_visible_for_authorized_teacher_and_student_roles(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('kesiswaan');
        $student = User::factory()->create();
        $student->assignRole('siswa');

        $teacherLabels = $this->allLabels(app(NavigationService::class), $teacher->refresh());
        $studentLabels = $this->allLabels(app(NavigationService::class), $student->refresh());

        $this->assertContains('Pembelajaran', $teacherLabels);
        $this->assertContains('Ruang Guru', $teacherLabels);
        $this->assertContains('Pembelajaran', $studentLabels);
    }

    /**
     * @test
     */
    public function test_workspace_tools_are_discoverable_for_each_authenticated_tier(): void
    {
        foreach (['super_admin', 'admin', 'kesiswaan', 'operator', 'verifikator', 'wali_kelas', 'siswa'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $labels = $this->allLabels(app(NavigationService::class), $user->refresh());

            $this->assertContains('Workspace', $labels, "Workspace group missing for [{$role}].");
            $this->assertContains('Drive & Office', $labels, "Drive/Office link missing for [{$role}].");
            $this->assertContains('Design Studio', $labels, "Design link missing for [{$role}].");
            $this->assertContains('Dibagikan kepada Saya', $labels, "Shared files link missing for [{$role}].");
        }

        $guardian = User::factory()->create();
        $child = \App\Models\Student::factory()->create();
        \App\Models\GuardianRelationship::create([
            'student_id' => $child->id,
            'guardian_user_id' => $guardian->id,
            'relationship' => 'wali',
            'status' => 'active',
        ]);
        $guardianLabels = $this->allLabels(app(NavigationService::class), $guardian->refresh());
        $this->assertContains('Drive & Office', $guardianLabels, 'Drive/Office link missing for Orang Tua/Wali.');
        $this->assertContains('Design Studio', $guardianLabels, 'Design link missing for Orang Tua/Wali.');
    }

    /**
     * @test
     */
    public function test_staff_sidebar_lands_on_their_own_authorized_workspace(): void
    {
        foreach ([
            'super_admin' => 'workspace.admin',
            'admin' => 'workspace.admin',
            'kesiswaan' => 'workspace.kesiswaan',
            'operator' => 'workspace.operator',
            'verifikator' => 'workspace.verifikator',
        ] as $role => $expectedRoute) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $items = app(NavigationService::class)->forUser($user->refresh())['items'];
            $routes = array_values(array_filter(array_map(fn ($item) => $item['route'] ?? null, $items)));

            $this->assertContains($expectedRoute, $routes, "Wrong or missing workspace entry for [{$role}].");
        }
    }

    /**
     * @test
     */
    public function test_operator_sees_registration_entry_instead_of_a_verification_menu(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $labels = $this->allLabels(app(NavigationService::class), $operator->refresh());

        $this->assertContains('Pendaftaran', $labels);
        $this->assertNotContains('Verifikasi', $labels);
        $this->assertContains('Dashboard Operator', $labels);
    }

    /**
     * @test
     */
    public function test_disabling_lms_hides_teacher_and_student_entry_points(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('kesiswaan');
        $student = User::factory()->create();
        $student->assignRole('siswa');

        app(ModuleService::class)->setEnabled('lms', false);

        $this->assertNotContains('Ruang Guru', $this->allLabels(app(NavigationService::class), $teacher->refresh()));
        $this->assertNotContains('Pembelajaran', $this->allLabels(app(NavigationService::class), $student->refresh()));
    }

    /**
     * @test
     */
    public function test_a_required_module_cannot_be_disabled(): void
    {
        $this->expectException(\LogicException::class);

        app(ModuleService::class)->setEnabled('students', false);
    }

    /**
     * @test
     */
    public function test_disabling_a_module_removes_it_from_every_navigation_surface(): void
    {
        $user = $this->admin();

        $before = $this->allLabels(app(NavigationService::class), $user);
        $this->assertContains(
            'Verifikasi', $before,
            'PPDB should be visible while enabled. Got: '.implode(', ', $before)
        );

        app(ModuleService::class)->setEnabled('ppdb', false);

        $after = $this->allLabels(app(NavigationService::class), $user);

        $this->assertNotContains('Verifikasi', $after, 'PPDB leaf survived in some surface.');
        $this->assertNotContains('PPDB', $after, 'PPDB group header survived as a stray label.');
    }

    /**
     * @test
     */
    public function test_a_group_whose_module_is_off_leaves_no_heading_behind(): void
    {
        $user = $this->admin();

        $labels = $this->allLabels(app(NavigationService::class), $user);
        $this->assertContains('Verifikasi', $labels, 'Got: '.implode(', ', $labels));
        $this->assertContains('PPDB', $labels, 'Got: '.implode(', ', $labels));

        app(ModuleService::class)->setEnabled('ppdb', false);

        $after = $this->allLabels(app(NavigationService::class), $user);

        // The heading is a container, not a leaf. Leaving "PPDB" behind with
        // nothing inside it is the dead navigation §50 forbids.
        $this->assertNotContains('PPDB', $after, 'A group with no openable leaves must not linger.');
    }

    /**
     * @test
     */
    public function test_disabling_a_required_module_is_refused_and_changes_nothing(): void
    {
        $user = $this->admin();

        $before = $this->allLabels(app(NavigationService::class), $user);

        try {
            app(ModuleService::class)->setEnabled('academic', false);
            $this->fail('Disabling a required module should be refused.');
        } catch (\LogicException) {
            // expected
        }

        $after = $this->allLabels(app(NavigationService::class), $user);

        $this->assertSame($before, $after, 'A refused toggle must not alter navigation.');
    }

    /**
     * @test
     */
    public function test_an_item_without_a_module_key_is_never_hidden(): void
    {
        // Identity screens (users, roles) predate the registry and carry no
        // module key. If the filter removed everything unannotated, an upgrade
        // would silently take the user-management menu away from the one role
        // that can currently reach it.
        $user = $this->superAdmin();

        $labels = $this->allLabels(app(NavigationService::class), $user);

        $this->assertContains(
            'Pengguna', $labels,
            'An unannotated item must stay visible. Got: '.implode(', ', $labels)
        );
    }
}
