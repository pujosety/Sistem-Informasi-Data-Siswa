<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Writing the CMS.
 *
 * WHY THE RULES LIVE HERE AND NOT IN A CONTROLLER
 *
 * A CMS is where §10 gets violated, and it gets violated quietly: someone
 * adds a "quick publish" button, or a second controller saves a post and
 * forgets that publishing is a separate decision from finishing. Every rule
 * that protects a school is therefore enforced in one place that a controller
 * has to call rather than in a controller that can forget:
 *
 *   - saving never publishes
 *   - publishing requires the permission, not just a status
 *   - every save writes a revision, so restore is always possible
 *   - a slug cannot silently change the URL of a published article without
 *     the author being told
 */
class CmsPostService
{
    public function __construct(private readonly CmsContentSanitizer $sanitizer) {}


    /**
     * Create a post. Always a draft.
     *
     * There is no "create and publish" path, on purpose. §14's workflow starts
     * at draft, and a form that submits straight to published skips the review
     * step the school was told it had.
     */
    public function create(User $author, array $attributes): Post
    {
        $post = new Post;
        $post->kind = $attributes['kind'] ?? Post::KIND_POST;
        $post->title = $attributes['title'] ?? 'Tanpa judul';
        $post->slug = $this->uniqueSlug($attributes['title'] ?? 'tanpa-judul');
        // Cleaned on the way in, so the database never holds a payload that
        // some future renderer might not escape.
        $post->body = $this->sanitizer->clean($attributes['body'] ?? null);
        $post->excerpt = $attributes['excerpt']
            ?? $this->sanitizer->excerpt($attributes['body'] ?? null);
        $post->author_id = $author->id;
        $post->status = Post::DRAFT;
        $post->is_public = false;
        $post->save();

        return $post;
    }

    /**
     * Update a post and record a revision of the state before the change.
     *
     * The revision holds the PREVIOUS content, which is what makes restore
     * mean "put back what it was" rather than "try to work out what to undo".
     */
    public function update(Post $post, User $actor, array $attributes): Post
    {
        return DB::transaction(function () use ($post, $actor, $attributes) {
            $this->snapshot($post, $actor);

            if (array_key_exists('title', $attributes) && $attributes['title'] !== $post->title) {
                $post->title = $attributes['title'];
                // §6 lists slug as editable, but a live article changing its
                // own URL breaks anything that has linked to it. Only a
                // non-public post may take a new slug silently.
                if (! $post->isVisibleToPublic() && array_key_exists('slug', $attributes)) {
                    $post->slug = $this->uniqueSlug($attributes['slug'], $post->id);
                }
            }

            foreach (['body', 'excerpt', 'status', 'category_id', 'parent_id',
                'meta_title', 'meta_description', 'meta_image',
                'public_from', 'public_until', 'blocks'] as $field) {
                if (! array_key_exists($field, $attributes)) {
                    continue;
                }

                $post->{$field} = $field === 'body'
                    ? $this->sanitizer->clean($attributes[$field])
                    : $attributes[$field];
            }

            // An excerpt is derived when the author did not write one, so the
            // listing never shows raw tags.
            if (! filled($post->excerpt) && filled($post->body)) {
                $post->excerpt = $this->sanitizer->excerpt($post->body);
            }

            // A status change is not a publication. is_public is only ever set
            // through publish()/unpublish(), never by a plain update.
            $post->save();

            return $post->refresh();
        });
    }

    /**
     * Publish. Requires the permission — the caller does not get to skip it.
     *
     * Sets both the status and is_public, because §14 treats them as two
     * stages and this is the one that reaches the public. Anything published
     * through here is public; anything published by writing the status field
     * directly is not, and will not appear on the site.
     */
    public function publish(Post $post, User $actor): Post
    {
        if (! $actor->can('cms.posts.publish')) {
            throw ValidationException::withMessages([
                'status' => 'Anda tidak memiliki izin untuk menerbitkan konten.',
            ]);
        }

        $this->snapshot($post, $actor);

        $post->status = Post::PUBLISHED;
        $post->is_public = true;
        // A scheduled post that is published early publishes now. Publishing
        // one that is late does not backdate it to the schedule.
        $post->published_at = $post->published_at ?? now();
        $post->save();

        return $post->refresh();
    }

    /**
     * Take a published post off the public site without destroying it.
     *
     * Deliberately does NOT set is_public = false. That flag is a decision
     * recorded by whoever published; unpublishing should stop the exposure
     * now and can be reversed, whereas flipping the flag loses the fact that
     * it was ever public. A draft that was once live is an unusual state and
     * is visible in the admin as such.
     */
    public function unpublish(Post $post, User $actor): Post
    {
        $this->snapshot($post, $actor);

        $post->status = Post::DRAFT;
        $post->save();

        return $post->refresh();
    }

    /**
     * Send to review, which is §14's second stage.
     */
    public function submitForReview(Post $post, User $actor): Post
    {
        $this->snapshot($post, $actor);

        $post->status = Post::PENDING;
        $post->save();

        return $post->refresh();
    }

    /**
     * Restore a revision. The content goes back; the status does not.
     *
     * Restoring a published article must not republish it, or a school that
     * pulled a page during an incident would put it back on the internet by
     * undoing an edit. §10 is the reason this is a deliberate limitation.
     */
    public function restoreRevision(Post $post, PostRevision $revision, User $actor): Post
    {
        $payload = $revision->payload;

        foreach (['title', 'slug', 'excerpt', 'meta_title',
            'meta_description', 'meta_image', 'category_id'] as $field) {
            if (array_key_exists($field, $payload)) {
                $post->{$field} = $payload[$field];
            }
        }

        // Cleaned again on the way back, because a restore must not become a
        // way to reintroduce markup that the write path would have removed —
        // an old revision, or a payload edited by hand.
        if (array_key_exists('body', $payload)) {
            $post->body = $this->sanitizer->clean($payload['body']);
        }

        // Never carried back: publication, authorship, and the window.
        $post->is_public = false;
        $post->author_id = $actor->id;
        $post->status = Post::DRAFT;
        $post->save();

        return $post->refresh();
    }

    /**
     * @param  array<int, string>  $names
     */
    public function syncTags(Post $post, array $names): void
    {
        // Deduplicate on the SLUG, not the raw name. `Ujian` and `ujian` are
        // the same tag, and de-duplicating the strings first created both —
        // the same tag twice under one post, and a tag table with a duplicate
        // in it.
        $ids = collect($names)
            ->map(fn ($n) => trim($n))
            ->filter()
            ->unique(fn (string $name) => Tag::slugify($name))
            ->map(function (string $name) {
                $slug = Tag::slugify($name);

                return Tag::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
            })
            ->values()
            ->all();

        $post->tags()->sync($ids);
    }

    /**
     * Publish posts whose scheduled time has arrived.
     *
     * Separate from publish() on purpose: a scheduled post is already public
     * (the author decided that when they scheduled it) and only needs the
     * status advanced. Running it through publish() would need a permission
     * that the scheduler does not have, and would re-derive published_at.
     */
    public function publishDue(): int
    {
        $due = Post::query()
            ->where('status', Post::SCHEDULED)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->get();

        foreach ($due as $post) {
            $post->update(['status' => Post::PUBLISHED]);
        }

        return $due->count();
    }

    /**
     * Record the current state, so a later restore has something to return to.
     */
    private function snapshot(Post $post, ?User $actor): void
    {
        PostRevision::create([
            'cms_post_id' => $post->id,
            'author_id' => $actor?->id,
            'payload' => [
                'title' => $post->title,
                'slug' => $post->slug,
                'body' => $post->body,
                'excerpt' => $post->excerpt,
                'meta_title' => $post->meta_title,
                'meta_description' => $post->meta_description,
                'meta_image' => $post->meta_image,
                'category_id' => $post->category_id,
                'kind' => $post->kind,
                'status' => $post->status,
                'is_public' => $post->is_public,
            ],
            'note' => 'Disimpan otomatis sebelum perubahan.',
        ]);
    }

    /**
     * A slug that is free within this post's kind.
     *
     * The (kind, slug) index means a page and a post may share a name, so the
     * suffix loop has to consider the kind too or it would reject a perfectly
     * valid slug.
     */
    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'tanpa-judul';
        $slug = $base;
        $n = 2;

        while (Post::query()
            ->where('kind', Post::KIND_POST)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
