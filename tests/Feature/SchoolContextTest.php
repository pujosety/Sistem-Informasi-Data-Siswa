<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\School;
use App\Services\SchoolContext;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** @test */
    public function settings_and_cms_content_are_isolated_per_school(): void
    {
        $default = School::query()->where('is_default', true)->firstOrFail();
        $second = School::create([
            'slug' => 'smp-negeri-4-metro',
            'name' => 'SMP Negeri 4 Metro',
            'short_name' => 'SMPN 4 Metro',
            'level' => 'SMP',
            'status' => 'Negeri',
            'is_active' => true,
            'is_default' => false,
        ]);

        $context = app(SchoolContext::class);
        $this->assertSame($context, app(SchoolContext::class));
        $context->use($second);
        $this->assertSame($second->id, $context->id());
        app(SettingsService::class)->set('app.name', 'SMP Negeri 4 Metro');
        $this->assertDatabaseHas('settings', ['school_id' => $second->id, 'key' => 'app.name', 'value' => 'SMP Negeri 4 Metro']);

        $category = Category::create([
            'name' => 'Prestasi',
            'slug' => 'prestasi-smp4',
            'description' => 'Prestasi SMP Negeri 4 Metro',
            'sort_order' => 1,
        ]);
        Post::create([
            'kind' => Post::KIND_POST,
            'title' => 'Berita SMP Negeri 4 Metro',
            'slug' => 'berita-smp4',
            'category_id' => $category->id,
            'status' => Post::PUBLISHED,
            'is_public' => true,
            'published_at' => now(),
        ]);

        $context->use($default);
        $this->assertNotSame('SMP Negeri 4 Metro', app(SettingsService::class)->get('app.name'));
        $this->assertDatabaseMissing('cms_posts', ['school_id' => $default->id, 'slug' => 'berita-smp4']);

        $context->use($second);
        $this->assertSame('SMP Negeri 4 Metro', app(SettingsService::class)->get('app.name'));
        $this->assertSame(1, Post::query()->where('slug', 'berita-smp4')->count());
    }

    /** @test */
    public function super_admin_can_switch_the_active_school_from_settings(): void
    {
        $second = School::create([
            'slug' => 'smp-negeri-4-metro',
            'name' => 'SMP Negeri 4 Metro',
            'short_name' => 'SMPN 4 Metro',
            'level' => 'SMP',
            'status' => 'Negeri',
            'is_active' => true,
            'is_default' => false,
        ]);
        $user = \App\Models\User::factory()->create();
        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->post(route('settings.school.switch', $second))
            ->assertRedirect()
            ->assertSessionHas('active_school_slug', 'smp-negeri-4-metro');

        $this->assertSame('smp-negeri-4-metro', session('active_school_slug'));
    }

    /** @test */
    public function public_school_query_selector_is_recomputed_for_each_request(): void
    {
        $second = School::create([
            'slug' => 'smp-negeri-4-metro',
            'name' => 'SMP Negeri 4 Metro',
            'short_name' => 'SMPN 4 Metro',
            'level' => 'SMP',
            'status' => 'Negeri',
            'is_active' => true,
            'is_default' => false,
        ]);

        app(SchoolContext::class)->use($second);
        app(SettingsService::class)->set('school.name', 'SMP Negeri 4 Metro');
        app(SchoolContext::class)->reset();

        $this->get('/?school=smp-negeri-4-metro')
            ->assertOk()
            ->assertSee('SMP Negeri 4 Metro');
    }
}
