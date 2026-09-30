<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

/**
 * Who may touch the media library.
 *
 * WHY THIS IS NOT `cms.posts.edit`
 *
 * Because the blast radius is different. `cms.posts.edit` says "you may change
 * articles" — a bounded surface, and mistakes in it cost a typo. The media
 * library is a shared resource: the school logo, the headmaster's photo, the
 * banner on the enrolment page. An editor who can delete the logo because they
 * can fix a comma in a headline has been given far more than the permission
 * they hold suggests, and the role matrix would not show it.
 *
 * So deleting is a separate ability, and a writer without
 * `cms.media.manage` can still upload and still attach — the write path stays
 * open, the destructive path does not.
 */
class MediaPolicy
{
    /** See the library at all. */
    public function viewAny(User $user): bool
    {
        return $user->can('cms.view') || $user->can('cms.media.manage');
    }

    public function view(User $user, Media $media): bool
    {
        return $this->viewAny($user);
    }

    /** Upload. Writers need this or they cannot put an image in their article. */
    public function create(User $user): bool
    {
        return $user->can('cms.media.manage') || $user->can('cms.posts.edit');
    }

    /** Edit alt text and caption. Same reasoning as upload. */
    public function update(User $user, Media $media): bool
    {
        return $this->create($user);
    }

    /**
     * Remove from the library. Destructive, and therefore its own permission.
     */
    public function delete(User $user, Media $media): bool
    {
        return $user->can('cms.media.manage');
    }

    /** Bring a removed image back. Same blast radius as removing it. */
    public function restore(User $user, Media $media): bool
    {
        return $user->can('cms.media.manage');
    }
}
