<?php

namespace Tests\Feature;

use App\Models\NavigationItem;
use App\Models\School;
use App\Services\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNavigationAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_admin_can_open_the_school_navigation_manager_but_other_staff_cannot(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->get('/admin/navigasi-publik')
            ->assertOk()
            ->assertSee('Navigasi Publik');

        $this->actingAs($this->makeUser('kesiswaan'))
            ->get('/admin/navigasi-publik')
            ->assertForbidden();
    }

    public function test_enabled_custom_navigation_is_used_on_the_public_site_and_disabled_items_are_omitted(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post('/admin/navigasi-publik', [
            'label' => 'Jalur PPDB',
            'url' => '/ppdb',
            'position' => 10,
            'is_enabled' => '1',
            'open_in_new_tab' => '0',
        ])->assertRedirect('/admin/navigasi-publik');

        $this->actingAs($admin)->post('/admin/navigasi-publik', [
            'label' => 'Tautan Rahasia',
            'url' => 'https://example.test/rahasia',
            'position' => 20,
            'is_enabled' => '0',
            'open_in_new_tab' => '1',
        ])->assertRedirect('/admin/navigasi-publik');

        $response = $this->get('/');
        $response->assertOk()
            ->assertSee('Jalur PPDB')
            ->assertSee('href="/ppdb"', false)
            ->assertDontSee('Tautan Rahasia');
    }

    public function test_navigation_rejects_script_and_protocol_relative_urls(): void
    {
        $admin = $this->makeUser('admin');

        foreach (['javascript:alert(1)', '//evil.example/path'] as $url) {
            $this->actingAs($admin)->from('/admin/navigasi-publik')->post('/admin/navigasi-publik', [
                'label' => 'Unsafe',
                'url' => $url,
                'position' => 10,
                'is_enabled' => '1',
            ])->assertSessionHasErrors('url');
        }
    }

    public function test_admin_can_edit_and_delete_a_navigation_item(): void
    {
        $item = NavigationItem::create([
            'label' => 'Profil lama',
            'url' => '/lama',
            'position' => 70,
            'is_enabled' => true,
        ]);
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->put('/admin/navigasi-publik/'.$item->id, [
            'label' => 'Profil sekolah',
            'url' => '/tentang',
            'position' => 5,
            'is_enabled' => '1',
            'open_in_new_tab' => '0',
        ])->assertRedirect('/admin/navigasi-publik');

        $this->assertDatabaseHas('cms_navigation_items', [
            'id' => $item->id,
            'label' => 'Profil sekolah',
            'position' => 5,
        ]);

        $this->actingAs($admin)->delete('/admin/navigasi-publik/'.$item->id)
            ->assertRedirect('/admin/navigasi-publik');
        $this->assertDatabaseMissing('cms_navigation_items', ['id' => $item->id]);
    }

    public function test_navigation_items_are_scoped_to_the_active_school(): void
    {
        $defaultItem = NavigationItem::create([
            'label' => 'Default only', 'url' => '/default-only', 'position' => 60, 'is_enabled' => true,
        ]);
        $secondSchool = School::create([
            'slug' => 'school-navigation-test',
            'name' => 'School Navigation Test',
            'is_active' => true,
            'is_default' => false,
        ]);

        app(SchoolContext::class)->use($secondSchool);
        $secondItem = NavigationItem::create([
            'label' => 'Second only', 'url' => '/second-only', 'position' => 60, 'is_enabled' => true,
        ]);

        $this->assertSame($secondSchool->id, $secondItem->school_id);
        $this->assertCount(1, NavigationItem::query()->get());
        $this->assertSame('Second only', NavigationItem::query()->first()->label);

        app(SchoolContext::class)->reset();
        $this->assertTrue(NavigationItem::query()->whereKey($defaultItem->id)->exists());
        $this->assertFalse(NavigationItem::query()->whereKey($secondItem->id)->exists());
    }

    public function test_external_links_open_safely_in_a_new_tab(): void
    {
        $this->actingAs($this->makeUser('admin'))->post('/admin/navigasi-publik', [
            'label' => 'Portal Eksternal',
            'url' => 'https://example.test/portal',
            'position' => 90,
            'is_enabled' => '1',
            'open_in_new_tab' => '1',
        ])->assertRedirect('/admin/navigasi-publik');

        $this->get('/')->assertOk()
            ->assertSee('href="https://example.test/portal"', false)
            ->assertSee('target="_blank" rel="noopener noreferrer"', false);
    }
}
