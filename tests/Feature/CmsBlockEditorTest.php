<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\School;
use App\Models\User;
use App\Services\CmsPostService;
use App\Services\ModuleService;
use App\Services\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsBlockEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        app(ModuleService::class)->setEnabled('cms', true);
    }

    public function test_writer_can_save_typed_page_blocks_without_publishing(): void
    {
        $writer = $this->makeUser('kesiswaan');

        $this->actingAs($writer)->post(route('admin.cms.store'), [
            'kind' => Post::KIND_PAGE,
            'title' => 'Profil Sekolah',
            'blocks_present' => '1',
            'blocks' => [
                ['type' => 'heading', 'level' => '2', 'text' => 'Tentang Kami'],
                ['type' => 'paragraph', 'text' => 'Kami mendukung pembelajaran yang aman.'],
                ['type' => 'button', 'text' => 'Daftar', 'url' => '/ppdb'],
            ],
        ])->assertRedirect();

        $page = Post::query()
            ->where('kind', Post::KIND_PAGE)
            ->where('title', 'Profil Sekolah')
            ->firstOrFail();

        $this->assertEquals([
            ['type' => 'heading', 'version' => 1, 'level' => 2, 'text' => 'Tentang Kami'],
            ['type' => 'paragraph', 'version' => 1, 'text' => 'Kami mendukung pembelajaran yang aman.'],
            ['type' => 'button', 'version' => 1, 'text' => 'Daftar', 'url' => '/ppdb'],
        ], $page->blocks);
        $this->assertSame(Post::DRAFT, $page->status);
        $this->assertFalse($page->is_public);
        $this->get(route('public.page', ['slug' => $page->slug]))->assertNotFound();
    }

    public function test_revision_captures_the_previous_typed_blocks_before_update(): void
    {
        $editor = $this->makeUser('kesiswaan');
        $posts = app(CmsPostService::class);
        $original = [
            ['type' => 'heading', 'version' => 1, 'level' => 2, 'text' => 'Judul Lama'],
            ['type' => 'paragraph', 'version' => 1, 'text' => 'Isi versi lama.'],
        ];
        $post = $posts->create($editor, [
            'kind' => Post::KIND_PAGE,
            'title' => 'Halaman Revisi',
            'blocks' => $original,
        ]);

        $posts->update($post, $editor, [
            'blocks' => [['type' => 'paragraph', 'text' => 'Isi versi baru.']],
        ]);

        $revision = $post->revisions()->latest('id')->firstOrFail();

        $this->assertArrayHasKey('blocks', $revision->payload);
        $this->assertEquals($original, $revision->payload['blocks']);
    }

    public function test_restoring_a_block_revision_restores_blocks_without_republishing(): void
    {
        $editor = $this->makeUser('kesiswaan');
        $publisher = $this->makeUser('admin');
        $posts = app(CmsPostService::class);
        $original = [
            ['type' => 'heading', 'version' => 1, 'level' => 2, 'text' => 'Judul Lama'],
            ['type' => 'paragraph', 'version' => 1, 'text' => 'Isi versi lama.'],
        ];
        $post = $posts->create($editor, [
            'kind' => Post::KIND_PAGE,
            'title' => 'Halaman Restore Blok',
            'blocks' => $original,
        ]);
        $posts->update($post, $editor, [
            'blocks' => [['type' => 'paragraph', 'text' => 'Isi versi baru.']],
        ]);
        $originalRevision = $post->revisions()->oldest('id')->firstOrFail();
        $posts->publish($post->refresh(), $publisher);

        $restored = $posts->restoreRevision($post->refresh(), $originalRevision, $editor);

        $this->assertEquals($original, $restored->blocks);
        $this->assertSame(Post::DRAFT, $restored->status);
        $this->assertFalse($restored->is_public);
    }

    public function test_typed_blocks_and_legacy_payloads_normalize_to_schema_version_one(): void
    {
        $blocks = app(\App\Services\CmsBlockService::class)->normalize([
            ['type' => 'paragraph', 'text' => 'Paragraf versi satu.'],
        ]);
        $legacy = app(\App\Services\CmsBlockService::class)->forPublicRender([
            ['type' => 'paragraph', 'text' => 'Konten lama.'],
        ]);

        $this->assertEquals([
            ['type' => 'paragraph', 'version' => 1, 'text' => 'Paragraf versi satu.'],
        ], $blocks);
        $this->assertEquals([
            ['type' => 'paragraph', 'version' => 1, 'text' => 'Konten lama.'],
        ], $legacy);
    }

    public function test_unknown_typed_block_schema_versions_are_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(\App\Services\CmsBlockService::class)->normalize([
            ['type' => 'paragraph', 'version' => 2, 'text' => 'Konten versi masa depan.'],
        ]);
    }

    public function test_block_count_limit_is_enforced(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(\App\Services\CmsBlockService::class)->normalize(array_fill(0, 41, [
            'type' => 'paragraph',
            'text' => 'Blok.',
        ]));
    }

    public function test_paragraph_text_limit_is_enforced(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(\App\Services\CmsBlockService::class)->normalize([
            ['type' => 'paragraph', 'text' => str_repeat('a', 5001)],
        ]);
    }

    public function test_button_label_limit_is_enforced(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(\App\Services\CmsBlockService::class)->normalize([
            ['type' => 'button', 'text' => str_repeat('a', 121), 'url' => '/ppdb'],
        ]);
    }

    public function test_unsupported_block_types_are_rejected_before_saving(): void
    {
        $writer = $this->makeUser('kesiswaan');

        $this->actingAs($writer)->post(route('admin.cms.store'), [
            'kind' => Post::KIND_PAGE,
            'title' => 'Halaman Blok Tidak Didukung',
            'blocks_present' => '1',
            'blocks' => [
                ['type' => 'raw_html', 'text' => '<script>alert(1)</script>'],
            ],
        ])->assertSessionHasErrors('blocks.0.type');

        $this->assertDatabaseMissing('cms_posts', ['title' => 'Halaman Blok Tidak Didukung']);
    }

    public function test_unsafe_button_urls_are_rejected_before_saving(): void
    {
        $writer = $this->makeUser('kesiswaan');

        $this->actingAs($writer)->post(route('admin.cms.store'), [
            'kind' => Post::KIND_PAGE,
            'title' => 'Halaman Tautan Tidak Aman',
            'blocks_present' => '1',
            'blocks' => [
                ['type' => 'button', 'text' => 'Jangan klik', 'url' => 'javascript:alert(1)'],
            ],
        ])->assertSessionHasErrors('blocks.0.url');

        $this->assertDatabaseMissing('cms_posts', ['title' => 'Halaman Tautan Tidak Aman']);
    }

    public function test_submitting_an_empty_block_list_clears_existing_blocks(): void
    {
        $writer = $this->makeUser('kesiswaan');
        $page = app(CmsPostService::class)->create($writer, [
            'kind' => Post::KIND_PAGE,
            'title' => 'Halaman Dengan Blok',
            'blocks' => [
                ['type' => 'paragraph', 'text' => 'Isi yang akan dihapus.'],
            ],
        ]);

        $this->actingAs($writer)->put(route('admin.cms.update', ['post' => $page->id]), [
            'kind' => Post::KIND_PAGE,
            'title' => $page->title,
            'blocks_present' => '1',
        ])->assertRedirect();

        $this->assertSame([], $page->fresh()->blocks);
    }

    public function test_restoring_a_legacy_revision_without_blocks_clears_newer_blocks(): void
    {
        $editor = $this->makeUser('kesiswaan');
        $posts = app(CmsPostService::class);
        $post = $posts->create($editor, [
            'kind' => Post::KIND_PAGE,
            'title' => 'Halaman Revisi Lama',
            'blocks' => [['type' => 'paragraph', 'text' => 'Blok lama.']],
        ]);
        $posts->update($post, $editor, [
            'blocks' => [['type' => 'paragraph', 'text' => 'Blok baru.']],
        ]);
        $legacyRevision = $post->revisions()->oldest('id')->firstOrFail();
        $legacyPayload = $legacyRevision->payload;
        unset($legacyPayload['blocks']);
        $legacyRevision->update(['payload' => $legacyPayload]);

        $restored = $posts->restoreRevision($post->refresh(), $legacyRevision->fresh(), $editor);

        $this->assertSame([], $restored->blocks);
        $this->assertSame(Post::DRAFT, $restored->status);
        $this->assertFalse($restored->is_public);
    }

    public function test_block_editor_and_public_page_are_scoped_to_the_active_school(): void
    {
        $writer = $this->makeUser('kesiswaan');
        $publisher = $this->makeUser('admin');
        $posts = app(CmsPostService::class);
        $primary = School::query()->where('is_default', true)->firstOrFail();
        $other = School::create([
            'slug' => 'cms-block-other-school',
            'name' => 'Sekolah Blok Lain',
            'is_active' => true,
            'is_default' => false,
        ]);

        app(SchoolContext::class)->use($other);
        $page = $posts->create($writer, [
            'kind' => Post::KIND_PAGE,
            'title' => 'Halaman Sekolah Lain',
            'blocks' => [['type' => 'paragraph', 'text' => 'Rahasia sekolah lain.']],
        ]);
        $posts->publish($page, $publisher);
        app(SchoolContext::class)->use($primary);

        $this->actingAs($writer)->get(route('admin.cms.edit', ['post' => $page->id]))->assertNotFound();
        $this->get(route('public.page', ['slug' => $page->slug]))->assertNotFound();
    }

    public function test_published_page_renders_typed_blocks_as_escaped_content(): void
    {
        $writer = $this->makeUser('kesiswaan');
        $publisher = $this->makeUser('admin');
        $posts = app(CmsPostService::class);
        $page = $posts->create($writer, [
            'kind' => Post::KIND_PAGE,
            'title' => 'Profil Berblok',
            'blocks' => [
                ['type' => 'heading', 'level' => 2, 'text' => 'Tentang Kami'],
                ['type' => 'paragraph', 'text' => '<script>alert(1)</script>'],
                ['type' => 'button', 'text' => 'Daftar', 'url' => '/ppdb'],
            ],
        ]);
        $posts->publish($page, $publisher);

        $bufferLevel = ob_get_level();
        $response = $this->get(route('public.page', ['slug' => $page->slug]));
        $this->assertSame($bufferLevel, ob_get_level(), 'Public rendering must leave output buffers balanced.');

        $response->assertOk()
            ->assertSee('Tentang Kami')
            ->assertSee('Daftar')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_published_news_renders_typed_blocks_as_escaped_content(): void
    {
        $writer = $this->makeUser('kesiswaan');
        $publisher = $this->makeUser('admin');
        $post = app(CmsPostService::class)->create($writer, [
            'kind' => Post::KIND_POST,
            'title' => 'Kabar Sekolah Berblok',
            'blocks' => [
                ['type' => 'heading', 'level' => 3, 'text' => 'Kegiatan'],
                ['type' => 'paragraph', 'text' => '<script>alert(1)</script>'],
                ['type' => 'button', 'text' => 'Baca selengkapnya', 'url' => '/program'],
            ],
        ]);
        app(CmsPostService::class)->publish($post, $publisher);

        $bufferLevel = ob_get_level();
        $response = $this->get(route('public.news.show', ['slug' => $post->slug]));
        $this->assertSame($bufferLevel, ob_get_level(), 'Public news rendering must leave output buffers balanced.');

        $response->assertOk()
            ->assertSee('Kegiatan')
            ->assertSee('Baca selengkapnya')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_editor_exposes_keyboard_operable_block_controls(): void
    {
        $writer = $this->makeUser('kesiswaan');

        $this->actingAs($writer)->get(route('admin.cms.create'))
            ->assertOk()
            ->assertSee('Blok konten')
            ->assertSee('Tambah judul')
            ->assertSee('Tambah paragraf')
            ->assertSee('Tambah tombol')
            ->assertSee('Pindahkan blok ke atas', false)
            ->assertSee('Pindahkan blok ke bawah', false)
            ->assertSee('Hapus blok')
            ->assertSee('name="blocks_present"', false);
    }
}
