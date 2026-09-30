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
    public function test_the_body_is_escaped_not_rendered_as_html(): void
    {
        $this->makePost([
            'slug' => 'xss',
            'body' => '<script>alert(1)</script>',
        ]);

        $body = $this->get('/berita/xss')->getContent();

        // Rendered raw, a school editor is one keystroke away from stored XSS on
        // a page every parent and student loads.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    /**
     * @test
     */
    public function test_an_empty_news_page_says_so_rather_than_breaking(): void
    {
        $body = $this->get('/berita')->getContent();

        $this->assertStringContainsString('Belum ada berita', $body);
    }

    /**
     * @test
     */
    public function test_the_news_page_needs_no_session(): void
    {
        $this->makePost(['slug' => 'tanpa-sesi']);

        $this->assertGuest();
        $this->get('/berita')->assertOk();
        $this->get('/berita/tanpa-sesi')->assertOk();
    }
}
