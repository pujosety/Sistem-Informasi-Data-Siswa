<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\RoleSeeder;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the public reading side of the CMS.
 *
 * The publication gate itself is tested in CmsPublicationTest. What matters
 * HERE is that the news routes cannot serve a private post — because this is
 * where a scope could be dropped and a draft would go live, and a news index
 * that quietly included unpublished posts is exactly the failure §10 is about.
 *
 * The 404-not-redirect assertion is deliberate and is the reason the tests do
 * not just check for absence from a listing.
 */
class PublicNewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        app(SettingsService::class)->setMany(['school.name' => 'SMA Negeri 1 Bogor']);
    }

    private function makePost(array $attributes = []): Post
    {
        return Post::create(array_merge([
            'kind' => Post::KIND_POST,
            'title' => 'Pengumuman sekolah',
            'slug' => 'pengumuman',
            'excerpt' => 'Ringkasan berita.',
            'body' => 'Isi berita.',
            'status' => Post::PUBLISHED,
            'author_id' => User::factory()->create()->id,
            'published_at' => now()->subDay(),
            'is_public' => true,
        ], $attributes));
    }

    /**
     * @test
     */
    public function test_a_public_post_appears_in_the_index(): void
    {
        $this->makePost();

        $response = $this->get('/berita');

        $response->assertOk();
        $this->assertStringContainsString('Pengumuman', $response->getContent());
    }

    /**
     * @test
     */
    public function test_the_index_never_contains_an_unpublished_post(): void
    {
        $this->makePost(['title' => 'Boleh tayang', 'slug' => 'boleh-tayang']);

        $this->makePost([
            'title' => 'Belum selesai',
            'slug' => 'belum-selesai',
            'status' => Post::DRAFT,
        ]);

        $this->makePost([
            'title' => 'Selesai tapi privat',
            'slug' => 'selesai-privat',
            'is_public' => false,
        ]);

        $body = $this->get('/berita')->getContent();

        $this->assertStringContainsString('Boleh tayang', $body);
        $this->assertStringNotContainsString('Belum selesai', $body);
        $this->assertStringNotContainsString('Selesai tapi privat', $body);
    }

    /**
     * @test
     */
    public function test_a_private_post_is_a_404_not_a_redirect(): void
    {
        $this->makePost(['status' => Post::DRAFT, 'slug' => 'rahasia']);

        // 404, not a redirect. Distinguishing "exists but private" from "does
        // not exist" tells an anonymous visitor which slugs a school has
        // drafted.
        $this->get('/berita/rahasia')->assertNotFound();
    }

    /**
     * @test
     */
    public function test_a_public_post_can_be_read(): void
    {
        $post = $this->makePost();

        $response = $this->get('/berita/pengumuman');

        $response->assertOk();
        $this->assertStringContainsString($post->title, $response->getContent());
    }

    /**
     * @test
     */
    public function test_a_script_tag_never_reaches_the_rendered_page(): void
    {
        // This used to assert that the body is ESCAPED at render time, which is
        // what the view did before CMS sanitisation existed. It now renders
        // with {!! !!} — only safe because the stored value is already
        // cleaned — so the guarantee is that nothing executable appears, not
        // that the text is entity-encoded.
        //
        // The two are different promises. Escaping at render time means an
        // editor cannot use a heading or a link. Sanitising on write means the
        // article is readable AND safe. What must never happen is the payload
        // running.
        //
        // Written through the service and then published, because that is the
        // only path an editor has: a draft is correctly invisible, and a test
        // against a draft would be asserting on a 404.
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin = $admin->refresh();

        $cms = app(\App\Services\CmsPostService::class);
        $post = $cms->create($admin, [
            'title' => 'XSS',
            'body' => '<p>Galat.</p><script>alert(1)</script>',
        ]);
        $post = $cms->publish($post, $admin);

        $body = (string) $this->get('/berita/'.$post->slug)->getContent();

        // Asserted on the PAYLOAD, not on "<script": every page in this
        // application includes the Vite bundle, so the literal string "<script"
        // is present in a correctly sanitised response. That assertion could
        // never pass, and the version of this test that used it was green only
        // because the body rendered as a 404 error page with no scripts.
        $this->assertStringNotContainsString('alert(1)', $body);

        // The surrounding article is still rendered, and readable — which is
        // the point of sanitising on write instead of escaping on render.
        $this->assertStringContainsString('Galat.', $body);

        // The meta description comes from the same text and is published to
        // search engines, so a payload reaching it would outlive the page. This
        // is where it leaked the first time: strip_tags removed the <script>
        // tag but kept its text.
        $this->assertStringNotContainsString('alert(1)', $body);
    }

    /**
     * @test
     */
    public function test_an_empty_news_page_says_so_rather_than_breaking(): void
    {
        // The local testing schema may contain showcase rows from a previous
        // migration run. Hide them explicitly so this test exercises the empty
        // public state rather than inheriting the operator's seed data.
        Post::query()->get()->each->delete();

        $body = $this->get('/berita')->getContent();

        $this->assertStringContainsString('Belum ada berita', $body);
    }

    /** @test */
    public function test_the_public_search_page_is_reachable_without_a_session(): void
    {
        $this->get('/cari')->assertOk()->assertSee('Cari informasi sekolah');
    }

    /** @test */
    public function test_public_search_returns_published_posts_but_not_drafts(): void
    {
        $this->makePost([
            'title' => 'Siswa LYFLA Menang Lomba',
            'slug' => 'siswa-lyfla-menang-lomba',
            'excerpt' => 'Kabar prestasi siswa.',
        ]);
        $this->makePost([
            'title' => 'Draft Prestasi Internal',
            'slug' => 'draft-prestasi-internal',
            'status' => Post::DRAFT,
        ]);

        $response = $this->get('/cari?q=prestasi');

        $response->assertOk()->assertSee('Siswa LYFLA Menang Lomba');
        $response->assertDontSee('Draft Prestasi Internal');
    }

    /** @test */
    public function test_public_search_handles_an_empty_query_without_breaking(): void
    {
        $this->get('/cari?q=')->assertOk()->assertSee('Mulai dari satu kata.');
    }

    /** @test */
    public function test_news_can_be_filtered_by_category(): void
    {
        $category = Category::create(['name' => 'Prestasi', 'slug' => 'prestasi', 'sort_order' => 1]);
        $this->makePost([
            'title' => 'Prestasi Siswa',
            'slug' => 'prestasi-siswa',
            'category_id' => $category->id,
        ]);
        $this->makePost([
            'title' => 'Agenda Sekolah',
            'slug' => 'agenda-sekolah',
        ]);

        $this->get('/berita?category=prestasi')
            ->assertOk()
            ->assertSee('Prestasi Siswa')
            ->assertDontSee('Agenda Sekolah');
    }

    /** @test */
    public function test_the_news_page_needs_no_session(): void
    {
        $this->makePost(['slug' => 'tanpa-sesi']);

        $this->assertGuest();
        $this->get('/berita')->assertOk();
        $this->get('/berita/tanpa-sesi')->assertOk();
    }

    /** @test */
    public function test_a_published_public_page_can_be_read(): void
    {
        $page = $this->makePost([
            'kind' => Post::KIND_PAGE,
            'title' => 'Tentang Ekosistem LYFLA',
            'slug' => 'ekosistem-lyfla',
        ]);

        $this->get($page->url())
            ->assertOk()
            ->assertSee('Tentang Ekosistem LYFLA');
    }

    /** @test */
    public function test_a_private_public_page_is_not_readable(): void
    {
        $page = $this->makePost([
            'kind' => Post::KIND_PAGE,
            'title' => 'Halaman Internal',
            'slug' => 'halaman-internal',
            'is_public' => false,
        ]);

        $this->get($page->url())->assertNotFound();
    }
}
