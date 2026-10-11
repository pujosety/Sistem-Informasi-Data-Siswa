<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Services\ModuleService;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->setModuleEnabled('cms', true);
        $this->setModuleEnabled('lms', true);
    }

    public function test_admin_sees_lyfla_learn_and_opens_the_authorized_monitoring_surface(): void
    {
        $admin = $this->makeUser('admin');
        $this->assertTrue($admin->can('lms.monitor.view'));

        $this->actingAs($admin)->get(route('cms.workspace'))
            ->assertOk()
            ->assertSee('LYFLA Learn')
            ->assertSee('href="'.route('lms.monitoring').'"', false);

        $learn = collect(app(WorkspaceService::class)->forUser($admin))
            ->firstWhere('key', 'learn');
        $this->assertSame('lms.monitoring', $learn['route']);
    }

    public function test_student_learn_workspace_uses_student_dashboard_and_permission(): void
    {
        $student = $this->makeUser('siswa');
        $this->assertTrue($student->can('lms.student.view'));

        $learn = collect(app(WorkspaceService::class)->forUser($student))
            ->firstWhere('key', 'learn');

        $this->assertNotNull($learn);
        $this->assertSame('lms.student.dashboard', $learn['route']);
    }

    public function test_user_without_lms_permission_has_no_learn_workspace(): void
    {
        $operator = $this->makeUser('operator');
        $this->assertFalse($operator->can('lms.course.view'));
        $this->assertFalse($operator->can('lms.student.view'));
        $this->assertFalse($operator->can('lms.monitor.view'));

        $this->assertNotContains('learn', collect(app(WorkspaceService::class)->forUser($operator))
            ->pluck('key')->all());
    }

    public function test_disabled_lms_module_hides_learn_workspace(): void
    {
        $this->setModuleEnabled('lms', false);
        $admin = $this->makeUser('admin');

        $this->assertNotContains('learn', collect(app(WorkspaceService::class)->forUser($admin))
            ->pluck('key')->all());
        $this->actingAs($admin)->get(route('lms.monitoring'))->assertNotFound();
    }

    private function setModuleEnabled(string $key, bool $enabled): void
    {
        Module::query()->where('key', $key)->update(['is_enabled' => $enabled]);
        app(ModuleService::class)->forget();
    }
}
