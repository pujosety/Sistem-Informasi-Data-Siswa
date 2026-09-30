<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Who may write and publish CMS content.
 *
 * THE RULE THIS ENCODES
 *
 * Writing and publishing are separate abilities, and the reason is a school,
 * not a principle. A teacher who drafts the school news must not be able to put
 * it on the public site; a head who approves it must not have to write it. One
 * permission makes the first impossible and the second pointless.
 *
 * The service enforces the same split — `CmsPostService::publish()` re-checks
 * `cms.posts.publish` itself — because the service is reachable from more than
 * this policy. Two checks, on purpose: the policy is what the route and the UI
 * ask, and the service is what actually stops a write.
 */
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cms.view');
    }

    public function view(User $user, Post $post): bool
    {
        return $user->can('cms.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cms.posts.create');
    }

    /**
     * Edit, including editing a published article.
     *
     * Editing live content is not the same as publishing it, and a school that
     * lets a teacher correct a typo on a live article while reserving the
     * decision to go live for a head would find that unworkable. The split is
     * between "make a change" and "make it appear", not between "touch it" and
     * "leave it alone".
     *
     * The consequence is that a published article can be edited in place. What
     * protects the reader is `is_public`: unpublishing is one click, and
     * CmsPostService::unpublish() does not destroy the content.
     */
    public function update(User $user, Post $post): bool
    {
        return $user->can('cms.posts.edit');
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->can('cms.posts.publish');
    }

    /**
     * Take it off the public site.
     *
     * Same permission as publishing, deliberately. Withdrawing is the more
     * urgent half of the same decision, and gating it behind a separate
     * permission means a school can end up unable to pull an article down
     * during an incident — which is the one moment the permission matters.
     */
    public function unpublish(User $user, Post $post): bool
    {
        return $user->can('cms.posts.publish');
    }

    public function delete(User $user, Post $post): bool
    {
        // No delete. A school that removes a correction needs the original to
        // still be there, and the schema has no soft-delete on cms_posts.
        return false;
    }

    /**
     * Publishing everything that is currently due.
     *
     * `viewAny`-shaped rather than post-shaped, because it acts on a query
     * rather than a record.
     */
    public function publishAny(User $user): bool
    {
        return $user->can('cms.posts.publish');
    }
}
