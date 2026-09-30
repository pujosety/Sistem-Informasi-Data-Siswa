<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the CMS publication gate.
 *
 * THE PROPERTY THAT MATTERS
 *
 * §10 requires that internal data never becomes public by accident. In a CMS
 * that means two things must be true independently: a post is FINISHED
 * (`status = published`) and it is CHOSEN to be public (`is_public`).
 *
 * Conflating them is the failure this test exists to prevent. If publication
 * meant only `status = published`, then a single autsave that set the status
 * would put an unfinished article on the internet, and the school would have
 * no way to know. If it meant only `is_public`, a school would have to
 * remember to also set a status and would see "nothing is published" with no
 * indication why.
 *
 * Every test below therefore asserts on the PAIR, because that is the only
 * combination that is actually safe.
 */
class CmsPublicationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Named makePost rather than post: Illuminate\Foundation\Testing\TestCase
     * already declares a public post() HTTP helper, and a private method of
     * the same name is a fatal error, not a shadowing warning.
     */
    private function makePost(array $attributes = []): Post
    {
        $author = User::factory()->create();

        return Post::create(array_merge([
            'kind' => Post::KIND_POST,
            'title' => 'Pengumuman Ujian',
            'slug' => 'pengumuman-ujian',
            'body' => 'Ujian dimulai bulan depan.',
            'status' => Post::PUBLISHED,
            'author_id' => $author->id,
            'published_at' => now()->subDay(),
            'is_public' => true,
        ], $attributes));
    }

    /**
     * @test
     */
    public function test_a_published_and_public_post_is_visible(): void
    {
        $post = $this->makePost();

        $this->assertTrue($post->isVisibleToPublic());
        $this->assertTrue(Post::publishedAndPublic()->whereKey($post->id)->exists());
    }

    /**
     * @test
     */
    public function test_a_finished_post_that_was_not_marked_public_stays_private(): void
    {
        // The whole point. The author finished it; nobody chose to publish it.
        $post = $this->makePost(['is_public' => false]);

        $this->assertFalse(
            $post->isVisibleToPublic(),
            'Finishing a post must not publish it.'
        );
        $this->assertFalse(Post::publishedAndPublic()->whereKey($post->id)->exists());
    }

    /**
     * @test
     */
    public function test_a_post_marked_public_but_still_a_draft_stays_private(): void
    {
        // And the mirror of the above: ticking the box on a draft is not
        // enough either, so an autosave cannot leak.
        $post = $this->makePost(['status' => Post::DRAFT]);

        $this->assertFalse($post->isVisibleToPublic());
        $this->assertFalse(Post::publishedAndPublic()->whereKey($post->id)->exists());
    }

    /**
     * @test
     */
    public function test_every_non_final_status_is_private(): void
    {
        foreach ([Post::DRAFT, Post::PENDING, Post::SCHEDULED, Post::ARCHIVED] as $status) {
            $post = $this->makePost(['status' => $status, 'slug' => 's-'.strtolower($status)]);

            $this->assertFalse(
                $post->isVisibleToPublic(),
                "Status [{$status}] must not be publicly visible."
            );
        }
    }

    /**
     * @test
     */
    public function test_a_future_publication_date_holds_it_back(): void
    {
        $post = $this->makePost(['published_at' => now()->addWeek()]);

        $this->assertFalse($post->isVisibleToPublic());
        $this->assertFalse(Post::publishedAndPublic()->whereKey($post->id)->exists());
    }

    /**
     * @test
     */
    public function test_a_publication_window_is_enforced(): void
    {
        $notYet = $this->makePost(['slug' => 'not-yet', 'public_from' => now()->addWeek()]);
        $expired = $this->makePost(['slug' => 'expired', 'public_until' => now()->subDay()]);
        $current = $this->makePost(['slug' => 'current']);

        $this->assertFalse($notYet->isVisibleToPublic(), 'public_from in the future must hold it back.');
        $this->assertFalse($expired->isVisibleToPublic(), 'public_until in the past must hide it.');
        $this->assertTrue($current->isVisibleToPublic());

        $visible = Post::publishedAndPublic()->pluck('slug')->all();
        $this->assertSame(['current'], $visible);
    }

    /**
     * @test
     */
    public function test_the_default_is_private(): void
    {
        // Not just isVisibleToPublic() — the column default, so a row inserted
        // by anything that forgets the field is private.
        $author = User::factory()->create();

        $post = Post::create([
            'kind' => Post::KIND_POST,
            'title' => 'Tanpa status',
            'slug' => 'tanpa-status',
            'author_id' => $author->id,
        ]);

        $this->assertFalse($post->is_public, 'A post created without a publication decision must be private.');
        $this->assertFalse($post->isVisibleToPublic());
    }

    /**
     * @test
     */
    public function test_scheduling_is_not_publication(): void
    {
        // A school that schedules a draft and forgets the checkbox must not
        // wake up to a public article.
        $post = $this->makePost([
            'status' => Post::SCHEDULED,
            'scheduled_for' => now()->subHour(),
            'is_public' => false,
            'slug' => 'terjadwal',
        ]);

        $this->assertTrue($post->isDueToPublish(), 'The schedule has arrived.');
        $this->assertFalse($post->isVisibleToPublic(), 'But it was never made public.');
    }

    /**
     * @test
     */
    public function test_a_soft_deleted_post_leaves_the_public_listing(): void
    {
        $post = $this->makePost();
        $this->assertTrue(Post::publishedAndPublic()->whereKey($post->id)->exists());

        $post->delete();

        $this->assertFalse(
            Post::publishedAndPublic()->whereKey($post->id)->exists(),
            'A deleted post must not remain in the public listing.'
        );
    }

    /**
     * @test
     */
    public function test_a_page_and_a_post_cannot_share_a_slug(): void
    {
        // The unique index is on (kind, slug) precisely so a page may reuse a
        // post's slug without the two colliding in a URL namespace.
        $this->makePost(['slug' => 'tentang']);

        $page = Post::create([
            'kind' => Post::KIND_PAGE,
            'title' => 'Tentang',
            'slug' => 'tentang',
        ]);

        $this->assertTrue($page->isPage());
        $this->assertNotSame('tentang', Post::posts()->whereKey($page->id)->value('slug'));
    }

    /**
     * @test
     */
    public function test_categories_and_tags_attach_to_a_post(): void
    {
        $post = $this->makePost();

        $post->category()->associate(Category::create(['name' => 'Berita', 'slug' => 'berita']));
        $post->save();

        $post->tags()->attach([
            Tag::create(['name' => 'Ujian', 'slug' => Tag::slugify('Ujian')])->id,
            Tag::create(['name' => 'Kelas XII', 'slug' => Tag::slugify('Kelas XII')])->id,
        ]);

        $this->assertSame('Berita', $post->fresh()->category->name);
        $this->assertCount(2, $post->fresh()->tags);
    }

    /**
     * @test
     */
    public function test_a_page_builds_its_url_from_its_parents(): void
    {
        // §7 hierarchy: Profile / History / Vision. The path is derived, not a
        // named route, because the depth is arbitrary.
        $profile = Post::create(['kind' => Post::KIND_PAGE, 'title' => 'Profil', 'slug' => 'profil']);
        $history = Post::create([
            'kind' => Post::KIND_PAGE,
            'title' => 'Sejarah',
            'slug' => 'sejarah',
            'parent_id' => $profile->id,
        ]);

        $this->assertStringEndsWith('/tentang/profil', $profile->url());
        $this->assertStringEndsWith('/tentang/profil/sejarah', $history->url());
    }
}
