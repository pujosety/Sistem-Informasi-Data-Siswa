<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Module;
use App\Models\User;
use App\Services\ModuleService;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the two operator surfaces: switching a module on, and reading your own
 * employment record.
 *
 * WHY THE TOGGLE MATTERS MORE THAN IT LOOKS
 *
 * §50 requires that disabling a module leaves no broken navigation. The
 * registry seeds `lms`, `payroll`, `finance` and `procurement` DISABLED
 * precisely so no link leads to a section that does not exist. But a registry
 * that can only be changed from a shell is not a feature — it is a deployment
 * step wearing a data table. This suite pins the screen.
 *
 * The required-module refusal is the part that must not become a 500.
 * `ModuleService::setEnabled()` throws `LogicException` for a required module
 * and `InvalidArgumentException` for an unknown key. A CLI throwing is
 * correct. A web screen throwing is a crash an operator caused by clicking a
 * button, and the whole point of the screen is to show them WHY.
 *
 * And the employee screen exists because Phase 3 deliberately did not backfill
 * employment records — "staff with no HR record" is a NORMAL state during
 * onboarding, and a screen that errors on it would make the no-backfill
 * decision look like a bug.
 */
class ModuleToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    private function operator(): User
    {
        return $this->makeUser('admin')->refresh();
    }

    // ---------------------------------------------------------------- the list

    /** @test */
    public function a_permitted_operator_sees_every_module_and_its_state(): void
    {
        $this->actingAs($this->operator())
            ->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSee('students')
            ->assertSee('lms');
    }

    /** @test */
    public function a_role_without_the_permission_is_refused(): void
    {
        foreach (['operator', 'verifikator', 'kesiswaan', 'siswa'] as $role) {
            $this->actingAs($this->makeUser($role))
                ->get(route('admin.modules.index'))
                ->assertForbidden();
        }
    }

    /** @test */
    public function a_guest_is_sent_to_login(): void
    {
        $this->get(route('admin.modules.index'))->assertRedirect(route('login'));
    }

    /**
     * The policy reads `module.*`, not `system.*`.
     *
     * It used to ask for system.view and system.update while the catalogue
     * defines module.view and module.toggle, so the screen was unreachable for
     * every role the matrix showed as able to use it — and `system.*` is host
     * configuration, a much more dangerous thing to sit next to "turn the CMS
     * on" than the same hand.
     *
     * @test
     */
    public function the_policy_uses_the_module_domain_not_the_system_domain(): void
    {
        $operator = $this->operator();

        $this->assertTrue($operator->can('module.view'));
        $this->assertTrue($operator->can('module.toggle'));

        $this->assertTrue(\Gate::forUser($operator)->allows('viewAny', Module::class));
    }

    // -------------------------------------------------------------- the switch

    /** @test */
    public function an_optional_module_can_be_switched_on(): void
    {
        $lms = Module::where('key', 'lms')->firstOrFail();

        $this->assertFalse($lms->is_enabled, 'lms ships disabled — it has no tables.');

        $this->actingAs($this->operator())
            ->put(route('admin.modules.update', $lms), ['is_enabled' => true])
            ->assertRedirect();

        $this->assertTrue($lms->refresh()->is_enabled);
    }

    /**
     * @test
     */
    public function a_required_module_is_refused_with_a_visible_reason_not_a_500(): void
    {
        $students = Module::where('key', 'students')->firstOrFail();

        $this->assertTrue($students->is_required);

        // A CLI throwing is fine. A web screen throwing means an operator
        // clicked a button and got a stack trace, with no idea whether the
        // switch moved.
        $response = $this->actingAs($this->operator())
            ->put(route('admin.modules.update', $students), ['is_enabled' => false]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertTrue(
            $students->refresh()->is_enabled,
            'A refused toggle must not change the module.'
        );
    }

    /**
     * The unknown-key guard, tested where it can actually be reached.
     *
     * It CANNOT be reached over HTTP: route-model binding and
     * `ModuleService::find()` query the same `modules` table, so any id that
     * binds is a key the service already knows. The controller's
     * InvalidArgumentException catch is therefore defensive — it protects a
     * future code path (a console caller, an API, a key renamed mid-deploy),
     * not this screen. Driving it over HTTP produced a test that could only
     * fail, so it is pinned on the service, where the contract lives.
     *
     * @test
     */
    public function the_service_refuses_a_key_the_registry_does_not_know(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ModuleService::class)->setEnabled('no_such_module', true);
    }

    /**
     * @test
     */
    public function the_service_still_refuses_a_required_module(): void
    {
        // The service is the guarantee; the screen is the courtesy. Both must
        // hold, and this pins the service so a future caller cannot skip it.
        $this->expectException(\LogicException::class);

        app(ModuleService::class)->setEnabled('academic', false);
    }

    // ----------------------------------------------------- navigation follows

    /**
     * @test
     */
    public function switching_a_module_off_removes_it_from_every_navigation_surface(): void
    {
        $nav = app(NavigationService::class);

        $before = $this->labels($nav, $this->operator());
        $this->assertContains('Kepegawaian', $before, 'hris ships enabled.');

        $this->actingAs($this->operator())->put(
            route('admin.modules.update', Module::where('key', 'hris')->firstOrFail()),
            ['is_enabled' => false]
        );

        $this->assertNotContains(
            'Kepegawaian',
            $this->labels($nav, $this->operator()),
            'The link must disappear from sidebar, dock AND overflow at once.'
        );
    }

    // ------------------------------------------------------- employee record

    /** @test */
    public function a_staff_member_with_a_record_can_read_it(): void
    {
        $staff = $this->operator();
        Employee::create([
            'user_id' => $staff->id,
            'employee_number' => 'PG-2026-001',
            'position' => 'Kepala Sekolah',
            'employment_status' => Employee::ACTIVE,
            'employment_type' => 'permanent',
            'created_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get(route('profile.employment'))
            ->assertOk()
            ->assertSee('Kepala Sekolah')
            ->assertSee('PG-2026-001');
    }

    /**
     * @test
     */
    public function a_staff_member_with_no_record_sees_a_clear_state_not_an_error(): void
    {
        // Phase 3 deliberately refused to backfill employment, because guessing
        // which of the staff accounts is a teacher and which is a clerk writes
        // false HR records. So this is the DEFAULT state for most staff, and a
        // screen that 500s on it makes the correct decision look like a bug.
        $this->actingAs($this->operator())
            ->get(route('profile.employment'))
            ->assertOk();
    }

    /** @test */
    public function a_guest_is_sent_to_login_from_the_employment_screen(): void
    {
        $this->get(route('profile.employment'))->assertRedirect(route('login'));
    }

    /** @test */
    public function a_student_sees_no_staff_employment_record(): void
    {
        $this->actingAs($this->makeUser('siswa'))
            ->get(route('profile.employment'))
            ->assertOk();
    }

    // ------------------------------------------------------------------ helper

    /**
     * Every label across every navigation surface, as a flat list.
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
