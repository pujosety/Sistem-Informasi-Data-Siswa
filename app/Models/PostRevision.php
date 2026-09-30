<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A snapshot of a post at a point in time.
 *
 * §14 wants compare AND restore. A full snapshot makes restore one write; a diff
 * would make it a replay that has to be replayed correctly against everything
 * that changed since. Articles are small, so the storage is not worth the
 * complexity of a diff.
 */
class PostRevision extends Model
{
    protected $table = 'cms_revisions';

    protected $fillable = ['cms_post_id', 'author_id', 'payload', 'note'];

    protected $casts = ['payload' => 'array'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'cms_post_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Which fields changed since the previous revision.
     *
     * For a summary line only. Comparing the real thing is a diff view, and
     * the snapshot is authoritative for restore regardless of what this says.
     */
    public function changedFields(): array
    {
        $previous = PostRevision::query()
            ->where('cms_post_id', $this->cms_post_id)
            ->where('id', '<', $this->id)
            ->orderByDesc('id')
            ->first();

        if (! $previous) {
            return array_keys($this->payload);
        }

        $changed = [];

        foreach ($this->payload as $key => $value) {
            if (! array_key_exists($key, $previous->payload) || $previous->payload[$key] !== $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }
}
