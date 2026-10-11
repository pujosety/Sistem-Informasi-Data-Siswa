<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Post;
use App\Models\School;
use App\Services\ModuleService;
use App\Services\SchoolContext;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->setCmsEnabled(true);
    }

    public function test_admin_opens_cms_studio_and_sees_the_workspace_switcher_entry(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get('/cms')
            ->assertOk()
            ->assertSee('LYFLA Studio')
            ->assertSee('Kelola Artikel')
            ->assertSee('href="'.route('cms.workspace').'"', false);

        $this->assertContains('cms', collect(app(WorkspaceService::class)->forUser($admin))
            ->pluck('key')->all());
    }

    public function test_kesiswaan_can_open_the_cms_workspace_without_publishing_permission(): void
    {
        $writer = $this->makeUser('kesiswaan');

        $this->assertTrue($writer->can('cms.view'));
        $this->assertFalse($writer->can('cms.posts.publish'));

        $this->actingAs($writer)->get('/cms')
            ->assertOk()
            ->assertSee('LYFLA Studio')
            ->assertSee('href="'.route('cms.workspace').'"', false)
            ->assertDontSee('Terbitkan');
    }

    public function test_users_without_cms_permission_cannot_open_or_switch_to_cms(): void
    {
        $operator = $this->makeUser('operator');

        $this->actingAs($operator)->get('/cms')->assertForbidden();
        $this->assertNotContains('cms', collect(app(WorkspaceService::class)->forUser($operator))
            ->pluck('key')->all());
    }

    public function test_disabled_cms_module_hides_the_workspace_and_returns_not_found(): void
    {
        $this->setCmsEnabled(false);
        $admin = $this->makeUser('admin');

        $this->assertNotContains('cms', collect(app(WorkspaceService::class)->forUser($admin))
            ->pluck('key')->all());
        $this->actingAs($admin)->get('/cms')->assertNotFound();
        $this->actingAs($admin)->get(route('admin.cms.index'))->assertNotFound();
    }

    public function test_cms_toggle_leaves_the_hris_state_unchanged(): void
    {
        $this->setCmsEnabled(false);
        $cms = Module::query()->where('key', 'cms')->firstOrFail();
        $hris = Module::query()->where('key', 'hris')->firstOrFail();
        $hrisWasEnabled = (bool) $hris->is_enabled;

        $this->actingAs($this->makeUser('admin'))
            ->put(route('admin.modules.update', $cms), ['is_enabled' => true])
            ->assertRedirect();

        $this->assertTrue($cms->fresh()->is_enabled);
        $this->assertSame($hrisWasEnabled, (bool) $hris->fresh()->is_enabled);
    }

    public function test_dashboard_content_counts_are_scoped_to_the_active_school(): void
    {
        $admin = $this->makeUser('admin');
        $activeSchool = School::query()->where('is_default', true)->firstOrFail();
        app(SchoolContext::class)->use($activeSchool);
        Post::create([
            'kind' => Post::KIND_POST,
            'title' => 'Konten sekolah aktif',
            'slug' => 'konten-sekolah-aktif',
            'status' => Post::DRAFT,
            'author_id' => $admin->id,
        ]);

        $otherSchool = School::create([
            'slug' => 'cms-workspace-other-school',
            'name' => 'Sekolah lain',
            'is_active' => true,
            'is_default' => false,
        ]);
        $otherPost = Post::create([
            'school_id' => $otherSchool->id,
            'kind' => Post::KIND_POST,
            'title' => 'Konten sekolah lain',
            'slug' => 'konten-sekolah-lain',
            'status' => Post::DRAFT,
            'author_id' => $admin->id,
        ]);
        $this->assertNotSame($activeSchool->id, $otherSchool->id);
        $this->assertSame($otherSchool->id, $otherPost->school_id);
        $this->assertSame($activeSchool->id, app(SchoolContext::class)->id());
        $this->assertStringContainsString('school_id', Post::query()->toSql());
        $expectedPostCount = Post::query()->posts()->count();
        $this->assertFalse(Post::query()->whereKey($otherPost->id)->exists());

        $response = $this->actingAs($admin)->get('/cms')->assertOk();
        $response->assertSee('data-cms-metric="posts" data-cms-value="'.$expectedPostCount.'"', false);
        $response->assertDontSee('Konten sekolah lain');
    }

    private function setCmsEnabled(bool $enabled): void
    {
        Module::query()->where('key', 'cms')->update(['is_enabled' => $enabled]);
        app(ModuleService::class)->forget();
    }
}
