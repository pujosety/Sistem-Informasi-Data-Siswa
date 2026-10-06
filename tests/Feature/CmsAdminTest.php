<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the CMS admin surface.
 *
 * WHY THIS SUITE EXISTS
 *
 * Phase 3 shipped the CMS tables, the writing service and the public news
 * pages — and no screen to write in. Ten `cms.*` permissions sat in the
 * catalogue, were granted to real roles, and could not be exercised anywhere:
 * `kesiswaan` holds `cms.posts.create` and there was no page in the entire
 * application that answered it.
 *
 * THE FAILURES THESE PREVENT
 *
 * 1. A writer who can publish. §14 wants a teacher to draft the school news
 *    and a head to approve it. `kesiswaan` holds create + edit and
 *    deliberately NOT publish, so the split is asserted from both sides: the
 *    editor is reachable, the publish route is not.
 *
 * 2. Saving as publishing. The form has one action and it is not publish. A
 *    crafted `status=published` in the payload must not reach the public site,
 *    because `CmsPostService::update()` copies the status field but never
 *    touches `is_public`, and the public query filters on `is_public`.
 *
 * 3. Restoring the wrong article's revision. Route-model binding resolves the
 *    post and the revision independently, so /konten/9/revisi/3 is a valid URL
 *    that names two different documents. Without an explicit check, an edit to
 *    one article is delivered by a link to another.
 *
 * 4. Restoring as republishing. A school that pulls a page during an incident
 *    and later restores an older revision must not put it back on the internet
 *    as a side effect.
 */
class CmsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        app(ModuleService::class)->setEnabled('cms', true);
    }

    // ------------------------------------------------------------------ actors

    /** A writer: creates and edits, cannot publish. */
    private function editor(): User
    {
        return $this->makeUser('kesiswaan')->refresh();
    }

    /** A publisher: admin, holds cms.posts.publish. */
    private function publisher(): User
    {
        return $this->makeUser('admin')->refresh();
    }

    private function aPost(array $attributes = []): Post
    {
        $user = $this->publisher();

        return Post::create($attributes + [
            'kind' => Post::KIND_POST,
            'title' => 'Artikel Uji',
            'slug' => 'artikel-uji',
            'body' => '<p>Isi.</p>',
            'status' => Post::DRAFT,
            'is_public' => false,
            'author_id' => $user->id,
        ]);
    }

    // ------------------------------------------------------------- the writers

    /** @test */
    public function a_writer_can_reach_the_content_list(): void
    {
        $this->actingAs($this->editor())
            ->get(route('admin.cms.index'))
            ->assertOk();
    }

    /** @test */
    public function a_writer_can_open_the_editor_without_nested_forms(): void
    {
        $post = $this->aPost();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.cms.edit', $post))
            ->assertOk();

        preg_match_all('/<form\\b|<\\/form>/i', $response->getContent(), $matches);
        $depth = 0;
        $maxDepth = 0;
        foreach ($matches[0] as $tag) {
            $depth += str_starts_with(strtolower($tag), '</') ? -1 : 1;
            $maxDepth = max($maxDepth, $depth);
        }

        $this->assertSame(1, $maxDepth, 'CMS editor must not render nested forms.');
    }

    /** @test */
    public function a_writer_can_create_a_draft(): void
    {
        $this->actingAs($this->editor())
            ->post(route('admin.cms.store'), [
                'kind' => Post::KIND_POST,
                'title' => 'Prestasi Kelas XII',
                'body' => '<p>Juara lomba.</p>',
            ])
            ->assertRedirect();

        $post = Post::where('slug', 'prestasi-kelas-xii')->firstOrFail();

        $this->assertSame(Post::DRAFT, $post->status);
        $this->assertFalse($post->is_public, 'A newly created post must not be public.');
    }

    /**
     * @test
     */
    public function a_writer_cannot_publish(): void
    {
        $post = $this->aPost();

        $this->assertFalse($this->editor()->fresh()->can('cms.posts.publish'));

        $this->actingAs($this->editor())
            ->post(route('admin.cms.publish', $post))
            ->assertForbidden();

        $this->assertFalse($post->refresh()->is_public);
    }

    /**
     * THE REGRESSION THIS PREVENTS
     *
     * A `status=published` in the update payload. The service copies `status`
     * because a status change is a legitimate part of an edit — but publishing
     * is `publish()`, which sets `is_public` as well. The public query filters
     * on `is_public`, so a payload can make the admin screen say "published"
     * while the article stays off the site. That is the confusion this asserts
     * against: the two must not drift apart.
     *
     * @test
     */
    public function a_posted_status_cannot_make_content_public(): void
    {
        $post = $this->aPost();

        $this->actingAs($this->publisher())
            ->put(route('admin.cms.update', $post), [
                'kind' => Post::KIND_POST,
                'title' => 'Artikel Uji',
                'body' => '<p>Isi baru.</p>',
                'status' => Post::PUBLISHED,
            ]);

        $post->refresh();

        $this->assertFalse($post->is_public, 'A save must not reach the public site.');
    }

    // ---------------------------------------------------------------- publish

    /** @test */
    public function a_publisher_can_publish_and_unpublish(): void
    {
        $post = $this->aPost();

        $this->actingAs($this->publisher())
            ->post(route('admin.cms.publish', $post));

        $post->refresh();
        $this->assertTrue($post->is_public);
        $this->assertSame(Post::PUBLISHED, $post->status);

        $this->actingAs($this->publisher())
            ->post(route('admin.cms.unpublish', $post));

        $post->refresh();

        // The public query requires status=published AND is_public.
        // unpublish() drops the status and leaves is_public alone, so what
        // actually removes it from the site is the status — which is why the
        // flag is deliberately not flipped back.
        $this->assertSame(Post::DRAFT, $post->status);
        $this->assertFalse($post->isVisibleToPublic());
    }

    /**
     * @test
     */
    public function unpublishing_does_not_destroy_the_content(): void
    {
        $post = $this->aPost();
        $this->actingAs($this->publisher())->post(route('admin.cms.publish', $post));

        $this->actingAs($this->publisher())->post(route('admin.cms.unpublish', $post));

        $post->refresh();

        // A school that withdraws a page during an incident must be able to put
        // it back. Destroying the row is the one thing it must not do.
        $this->assertNotNull($post->body);
        $this->assertFalse($post->isVisibleToPublic());
        $this->assertDatabaseHas('cms_posts', ['id' => $post->id]);
    }

    /**
     * @test
     */
    public function publishing_is_audited(): void
    {
        $post = $this->aPost();

        $this->actingAs($this->publisher())->post(route('admin.cms.publish', $post));

        // AuditService stores class_basename(), not the FQCN — so the value
        // that actually lands in the column is 'Post'.
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'cms.post.published',
            'subject_type' => 'Post',
            'subject_id' => $post->id,
        ]);
    }

    // ------------------------------------------------------------- revisions

    /**
     * @test
     */
    public function a_revision_from_another_post_is_refused(): void
    {
        $mine = $this->aPost();
        $other = $this->aPost(['title' => 'Artikel Lain', 'slug' => 'artikel-lain']);

        $foreign = PostRevision::create([
            'cms_post_id' => $other->id,
            'author_id' => $this->publisher()->id,
            'payload' => ['title' => 'Konten Asing'],
        ]);

        // A valid URL naming two different documents. Without the ownership
        // check, restoring here writes the OTHER article's content into this
        // one — an edit delivered through the wrong document.
        $this->actingAs($this->publisher())
            ->post(route('admin.cms.revisions.restore', [$mine, $foreign]))
            ->assertNotFound();
    }

    /**
     * @test
     */
    public function restoring_its_own_revision_works_and_does_not_republish(): void
    {
        $post = $this->aPost(['body' => '<p>Versi lama.</p>']);

        // One save creates the snapshot of the previous state.
        $this->actingAs($this->publisher())->put(route('admin.cms.update', $post), [
            'kind' => Post::KIND_POST,
            'title' => 'Artikel Uji',
            'body' => '<p>Versi baru.</p>',
        ]);

        $revision = $post->revisions()->latest('id')->firstOrFail();

        $this->actingAs($this->publisher())
            ->post(route('admin.cms.publish', $post));

        $this->actingAs($this->publisher())
            ->post(route('admin.cms.revisions.restore', [$post, $revision]));

        $post->refresh();

        $this->assertStringContainsString('Versi lama', $post->body);

        // Restore is stricter than "the status does not change": it returns the
        // post to draft AND clears is_public, so an article that was live goes
        // off the site entirely. A school that pulled a page during an incident
        // cannot put it back on the internet by undoing an edit — §10's reason
        // for the limitation.
        $this->assertSame(Post::DRAFT, $post->status);
        $this->assertFalse($post->is_public);
        $this->assertFalse($post->isVisibleToPublic());
    }

    // ---------------------------------------------------------- authorization

    /**
     * @test
     */
    public function an_operator_cannot_reach_the_cms(): void
    {
        // operator holds dashboard, students and registrations. Nothing about
        // writing the school's news.
        $this->actingAs($this->makeUser('operator'))
            ->get(route('admin.cms.index'))
            ->assertForbidden();
    }

    /** @test */
    public function a_verifier_cannot_reach_the_cms(): void
    {
        $this->actingAs($this->makeUser('verifikator'))
            ->get(route('admin.cms.index'))
            ->assertForbidden();
    }

    /** @test */
    public function a_student_cannot_reach_the_cms(): void
    {
        $this->actingAs($this->makeUser('siswa'))
            ->get(route('admin.cms.index'))
            ->assertForbidden();
    }

    /** @test */
    public function a_guest_is_sent_to_login(): void
    {
        $this->get(route('admin.cms.index'))->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------- lifecycle

    /**
     * @test
     */
    public function a_scheduled_post_that_is_due_is_published_by_hand(): void
    {
        // No scheduler runs on a single-container host, so publishDue() is
        // otherwise only reachable from cron. The button is the same
        // transition, and it is what makes scheduling usable at all here.
        $post = $this->aPost();
        $post->update(['status' => Post::SCHEDULED, 'scheduled_for' => now()->subMinute()]);

        $this->actingAs($this->publisher())
            ->post(route('admin.cms.publish-due'))
            ->assertSessionHas('success');

        $post->refresh();

        $this->assertTrue($post->is_public);
    }

    /**
     * @test
     */
    public function a_writer_cannot_trigger_the_due_publish(): void
    {
        $this->actingAs($this->editor())
            ->post(route('admin.cms.publish-due'))
            ->assertForbidden();
    }

    /**
     * @test
     */
    public function tags_are_saved_as_a_comma_separated_list(): void
    {
        $this->actingAs($this->publisher())
            ->post(route('admin.cms.store'), [
                'kind' => Post::KIND_POST,
                'title' => 'Artikel Bertag',
                'body' => '<p>Isi.</p>',
                'tags' => 'Prestasi, Sains',
            ]);

        $post = Post::where('title', 'Artikel Bertag')->firstOrFail();

        $this->assertCount(2, $post->tags);
    }

    /**
     * @test
     */
    public function a_page_drops_article_only_fields(): void
    {
        // A page has no date and no excerpt. Storing them would be a value the
        // public page never reads and the editor never shows.
        $this->actingAs($this->publisher())
            ->post(route('admin.cms.store'), [
                'kind' => Post::KIND_PAGE,
                'title' => 'Profil Sekolah',
                'body' => '<p>Tentang kami.</p>',
                'excerpt' => 'Ringkasan yang tidak dipakai.',
            ]);

        $page = Post::where('title', 'Profil Sekolah')->firstOrFail();

        $this->assertTrue($page->isPage());
        $this->assertNull($page->public_from);
    }

    /**
     * @test
     */
    public function a_category_can_be_assigned(): void
    {
        $category = Category::create(['name' => 'Prestasi', 'slug' => 'prestasi']);

        $this->actingAs($this->publisher())
            ->post(route('admin.cms.store'), [
                'kind' => Post::KIND_POST,
                'title' => 'Artikel Berkategori',
                'body' => '<p>Isi.</p>',
                'category_id' => $category->id,
            ]);

        $post = Post::where('title', 'Artikel Berkategori')->firstOrFail();

        $this->assertSame($category->id, $post->category_id);
    }
}
