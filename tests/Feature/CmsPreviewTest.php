<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Services\CmsPostService;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(ModuleService::class)->setEnabled('cms', true);
    }

    public function test_editor_can_open_a_temporary_noindex_preview_of_an_unpublished_page(): void
    {
        $editor = $this->makeUser('kesiswaan');
        $page = app(CmsPostService::class)->create($editor, [
            'kind' => Post::KIND_PAGE,
            'title' => 'Pratinjau Draf',
            'blocks' => [['type' => 'heading', 'level' => 2, 'text' => 'Belum Terbit']],
        ]);

        $this->actingAs($editor)
            ->get(route('admin.cms.show', ['post' => $page->id]))
            ->assertOk()
            ->assertSee('Pratinjau Sementara');

        $redirect = $this->actingAs($editor)
            ->post(route('admin.cms.preview', ['post' => $page->id]))
            ->assertRedirect();

        $previewUrl = $redirect->headers->get('Location');
        $this->assertIsString($previewUrl);
        $this->assertStringContainsString('signature=', $previewUrl);
        $this->assertStringContainsString('expires=', $previewUrl);

        $this->app['auth']->logout();
        $preview = $this->get($previewUrl)->assertOk();

        $preview->assertSee('Belum Terbit')
            ->assertSee('Pratinjau sementara')
            ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false)
            ->assertDontSee('<link rel="canonical"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', (string) $preview->headers->get('Cache-Control'));
        $this->assertSame(Post::DRAFT, $page->fresh()->status);
        $this->assertFalse($page->fresh()->is_public);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'cms.post.preview_link_generated',
            'subject_type' => 'Post',
            'subject_id' => $page->id,
            'user_id' => $editor->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'cms.post.previewed',
            'subject_type' => 'Post',
            'subject_id' => $page->id,
            'user_id' => null,
        ]);
    }

    public function test_signed_preview_expires_and_rejects_tampered_school_scope(): void
    {
        $editor = $this->makeUser('kesiswaan');
        $page = app(CmsPostService::class)->create($editor, [
            'kind' => Post::KIND_PAGE,
            'title' => 'Pratinjau Aman',
        ]);

        $redirect = $this->actingAs($editor)
            ->post(route('admin.cms.preview', ['post' => $page->id]))
            ->assertRedirect();
        $previewUrl = $redirect->headers->get('Location');

        $parts = parse_url($previewUrl);
        parse_str($parts['query'] ?? '', $query);
        $query['school'] = 'sekolah-lain';
        $tamperedUrl = $parts['scheme'].'://'.$parts['host'].$parts['path'].'?'.http_build_query($query);
        $this->get($tamperedUrl)->assertForbidden();

        $this->travel(16)->minutes();
        $this->get($previewUrl)->assertForbidden();
    }

    public function test_signed_preview_uses_the_signed_tenant_without_changing_session(): void
    {
        $school = \App\Models\School::firstOrCreate(
            ['slug' => 'smp-negeri-4-metro'],
            [
                'name' => 'SMP Negeri 4 Metro',
                'short_name' => 'SMPN 4 Metro',
                'level' => 'SMP',
                'status' => 'Negeri',
                'is_active' => true,
                'is_default' => false,
            ],
        );
        $superAdmin = \App\Models\User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->post(route('settings.school.switch', $school))
            ->assertRedirect();
        $page = app(CmsPostService::class)->create($superAdmin, [
            'kind' => Post::KIND_PAGE,
            'title' => 'Halaman SMPN 4',
            'blocks' => [['type' => 'paragraph', 'text' => 'Konten sekolah kedua']],
        ]);
        $redirect = $this->post(route('admin.cms.preview', ['post' => $page->id]))
            ->assertRedirect();
        $previewUrl = $redirect->headers->get('Location');

        session(['active_school_slug' => 'smp-negeri-1-metro']);
        $this->app['auth']->logout();
        $preview = $this->get($previewUrl)->assertOk();

        $preview->assertSee('SMP Negeri 4 Metro')
            ->assertSee('Konten sekolah kedua');
        $this->assertSame('smp-negeri-1-metro', session('active_school_slug'));
    }

    public function test_news_draft_preview_uses_the_article_layout(): void
    {
        $editor = $this->makeUser('kesiswaan');
        $article = app(CmsPostService::class)->create($editor, [
            'kind' => Post::KIND_POST,
            'title' => 'Berita Belum Terbit',
            'body' => '<p>Isi berita privat untuk pratinjau.</p>',
        ]);

        $redirect = $this->actingAs($editor)
            ->post(route('admin.cms.preview', ['post' => $article->id]))
            ->assertRedirect();
        $preview = $this->get($redirect->headers->get('Location'))
            ->assertOk()
            ->assertSee('Berita Belum Terbit')
            ->assertSee('Isi berita privat untuk pratinjau.')
            ->assertSee('Pratinjau sementara');

        $preview->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertSame(Post::DRAFT, $article->fresh()->status);
        $this->assertFalse($article->fresh()->is_public);
    }

    public function test_only_editors_can_generate_preview_links_and_unsigned_access_is_denied(): void
    {
        $editor = $this->makeUser('kesiswaan');
        $page = app(CmsPostService::class)->create($editor, [
            'kind' => Post::KIND_PAGE,
            'title' => 'Halaman Privat',
        ]);
        $student = $this->makeUser('siswa');

        $this->actingAs($student)
            ->post(route('admin.cms.preview', ['post' => $page->id]))
            ->assertForbidden();

        $unsignedUrl = route('public.cms.preview', [
            'postId' => $page->id,
            'school' => app(\App\Services\SchoolContext::class)->slug(),
        ]);
        $this->get($unsignedUrl)->assertForbidden();
    }
}
