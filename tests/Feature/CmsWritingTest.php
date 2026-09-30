<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\CmsPostService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Guards the rules that protect a school from publishing by accident.
 *
 * The publication gate is tested in CmsPublicationTest. What is tested here is
 * that the WRITING side cannot bypass it — and a CMS is exactly where that
 * gets violated, because the failure is a convenience: a quick-publish button, a
 * form that posts status directly, a second controller that saves without
 * thinking. Each of those looks like a feature and leaks a draft.
 *
 * So the rules live in the service and a controller has to call them, and every
 * rule below is one somebody will eventually want to skip.
 */
class CmsWritingTest extends TestCase
{
    use RefreshDatabase;

    private CmsPostService $cms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->cms = app(CmsPostService::class);
    }

    private function editor(string $role = 'kesiswaan'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->refresh();
    }

    /**
     * @test
     */
    public function test_creating_a_post_never_publishes_it(): void
    {
        $post = $this->cms->create($this->editor(), ['title' => 'Berita Baru']);

        // There is deliberately no "create and publish". §14's workflow starts
        // at draft, and a form that submits straight to published removes the
        // review step the school was told it had.
        $this->assertSame(Post::DRAFT, $post->status);
        $this->assertFalse($post->is_public);
        $this->assertFalse($post->isVisibleToPublic());
    }

    /**
     * @test
     */
    public function test_updating_cannot_publish_by_setting_the_status(): void
    {
        $post = $this->cms->create($this->editor(), ['title' => 'Berita']);
        $author = $this->editor();

        // The shortcut somebody will eventually try: write the field directly.
        $this->cms->update($post, $author, ['status' => Post::PUBLISHED]);

        $post->refresh();

        $this->assertSame(Post::PUBLISHED, $post->status);
        $this->assertFalse(
            $post->is_public,
            'Setting status through update() must not reach the public; only publish() does that.'
        );
        $this->assertFalse($post->isVisibleToPublic());
    }

    /**
     * @test
     */
    public function test_publishing_requires_the_publish_permission(): void
    {
        $post = $this->cms->create($this->editor(), ['title' => 'Berita']);
        $writer = $this->editor('kesiswaan');

        // kesiswaan may draft the news and may not release it. That separation
        // is the whole reason cms.posts.publish is not part of the write grant.
        $this->assertFalse($writer->can('cms.posts.publish'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->cms->publish($post, $writer);
    }

    /**
     * @test
     */
    public function test_publishing_with_the_right_permission_publishes(): void
    {
        $post = $this->cms->create($this->editor(), ['title' => 'Berita']);
        $publisher = $this->editor('admin');

        $published = $this->cms->publish($post, $publisher);

        $this->assertSame(Post::PUBLISHED, $published->status);
        $this->assertTrue($published->is_public);
        $this->assertTrue($published->isVisibleToPublic());
    }

    /**
     * @test
     */
    public function test_a_writer_can_draft_and_submit_for_review(): void
    {
        $post = $this->cms->create($this->editor(), ['title' => 'Berita']);
        $writer = $this->editor('kesiswaan');

        $this->assertTrue($writer->can('cms.posts.create'));
        $this->assertFalse($writer->can('cms.posts.publish'));

        $pending = $this->cms->submitForReview($post, $writer);

        $this->assertSame(Post::PENDING, $pending->status);
        $this->assertFalse($pending->isVisibleToPublic());
    }

    /**
     * @test
     */
    public function test_every_save_writes_a_revision_of_the_state_before_it(): void
    {
        $post = $this->cms->create($this->editor(), ['title' => 'Judul lama', 'body' => 'Isi lama.']);
        $author = $this->editor();

        $this->cms->update($post, $author, ['title' => 'Judul baru', 'body' => 'Isi baru.']);

        $revision = $post->revisions()->latest('id')->firstOrFail();

        // The snapshot holds what it WAS, which is what makes restore mean
        // "put it back" rather than "work out how to undo it".
        $this->assertSame('Judul lama', $revision->payload['title']);
        $this->assertSame('Isi lama.', $revision->payload['body']);
    }

    /**
     * @test
     */
    public function test_restoring_a_revision_does_not_republish(): void
    {
        $post = $this->cms->create($this->editor(), ['title' => 'Asli']);
        $admin = $this->editor('admin');

        $this->cms->publish($post, $admin);
        $this->assertTrue($post->refresh()->isVisibleToPublic());

        $revision = $post->revisions()->latest('id')->firstOrFail();
        $restored = $this->cms->restoreRevision($post, $revision, $admin);

        // A school that pulled a page during an incident must not put it back on
        // the internet by undoing an edit. Content returns; publication does not.
        $this->assertSame('Asli', $restored->title);
        $this->assertFalse($restored->is_public);
        $this->assertSame(Post::DRAFT, $restored->status);
        $this->assertFalse($restored->isVisibleToPublic());
    }

    /**
     * @test
     */
    public function test_a_live_article_cannot_change_its_own_url_silently(): void
    {
        $post = $this->cms->create($this->editor(), ['title' => 'Promosekolah']);
        $admin = $this->editor('admin');
        $this->cms->publish($post, $admin);

        $before = $post->slug;
        $this->cms->update($post, $admin, ['title' => 'Judul baru', 'slug' => 'url-baru']);

        $post->refresh();

        // Anything may already have linked to the old URL. A live article
        // keeps its slug; a draft may change.
        $this->assertSame($before, $post->slug);
    }

    /**
     * @test
     */
    public function test_a_draft_may_change_its_slug(): void
    {
        $post = $this->cms->create($this->editor(), ['title' => 'Awal']);

        $this->cms->update($post, $this->editor(), ['title' => 'Akhir', 'slug' => 'akhir']);

        $this->assertSame('akhir', $post->refresh()->slug);
    }

    /**
     * @test
     */
    public function test_slugs_are_made_unique(): void
    {
        $first = $this->cms->create($this->editor(), ['title' => 'Berita Sama']);
        $second = $this->cms->create($this->editor(), ['title' => 'Berita Sama']);

        $this->assertSame('berita-sama', $first->slug);
        $this->assertSame('berita-sama-2', $second->slug);
    }

    /**
     * @test
     */
    public function test_unpublishing_removes_it_from_the_site_without_losing_the_decision(): void
    {
        $post = $this->cms->create($this->editor(), ['title' => 'Berita']);
        $admin = $this->editor('admin');
        $this->cms->publish($post, $admin);

        $after = $this->cms->unpublish($post->refresh(), $admin);

        $this->assertSame(Post::DRAFT, $after->status);
        $this->assertFalse($after->isVisibleToPublic());

        // is_public is deliberately NOT cleared. It records that this was
        // published once, which is a fact about the school, not a setting.
        $this->assertTrue(
            $after->is_public,
            'Unpublishing should stop exposure now and stay reversible, not erase that it was public.'
        );
    }

    /**
     * @test
     */
    public function test_a_scheduled_post_publishes_when_due_without_a_permission(): void
    {
        $post = $this->cms->create($this->editor(), ['title' => 'Terjadwal']);
        $admin = $this->editor('admin');
        $this->cms->publish($post, $admin);
        $post->update([
            'status' => Post::SCHEDULED,
            'scheduled_for' => now()->subHour(),
        ]);

        $count = $this->cms->publishDue();

        $this->assertSame(1, $count);
        $this->assertSame(Post::PUBLISHED, $post->refresh()->status);

        // It was already public when scheduled, so it stays public — a scheduler
        // is not the author making a new publication decision.
        $this->assertTrue($post->refresh()->isVisibleToPublic());
    }

    /**
     * @test
     */
    public function test_tags_are_created_once_and_reused(): void
    {
        $post = $this->cms->create($this->editor(), ['title' => 'Berita']);
        $author = $this->editor();

        // 'Ujian' and 'ujian' are the same tag, so the first call creates two
        // tag rows, not three.
        $this->cms->syncTags($post, ['Ujian', 'Kelas XII', 'ujian']);

        $this->assertSame(2, \App\Models\Tag::count());
        $this->assertCount(2, $post->fresh()->tags);

        // The second call replaces the set rather than adding to it. Kelas XII
        // is no longer attached, but the tag itself still exists — a tag is
        // reusable content, and deleting it would break other posts.
        $this->cms->syncTags($post->fresh(), ['Ujian', 'Prestasi']);

        $this->assertCount(2, $post->fresh()->tags);
        $this->assertSame(3, \App\Models\Tag::count());
    }
}
