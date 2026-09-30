<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * One image in the CMS media library.
 *
 * The row is the catalogue entry; the bytes live on the disk. That split is
 * why `disk` and `path` are stored rather than a URL — see the migration.
 */
class Media extends Model
{
    use SoftDeletes;

    protected $table = 'cms_media';

    protected $fillable = [
        'disk', 'path', 'original_name', 'mime',
        'size', 'width', 'height',
        'alt_text', 'caption', 'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function posts(): BelongsToMany
    {
        // Stated rather than inferred — same reason as Post::tags().
        return $this->belongsToMany(
            Post::class,
            'cms_post_media',
            'cms_media_id',
            'cms_post_id'
        )->withPivot('sort_order');
    }

    /**
     * A human-readable size for the library listing.
     *
     * KB with no decimals below 1 MB, because "847 KB" is what a school needs
     * to decide whether to delete something and "864128 bytes" is not.
     */
    public function humanSize(): string
    {
        if ($this->size < 1024 * 1024) {
            return max(1, (int) round($this->size / 1024)).' KB';
        }

        return round($this->size / (1024 * 1024), 1).' MB';
    }

    /** Name to show when the uploader's original name is unavailable. */
    public function displayName(): string
    {
        return $this->original_name ?: basename($this->path);
    }

    /**
     * True when the file is no longer on disk.
     *
     * A soft delete removes the row from the library but leaves the bytes, so
     * a live article keeps rendering. That also means "still there" and "row
     * exists" are different questions, and an operator needs to see the gap.
     */
    public function isMissingOnDisk(): bool
    {
        return ! Storage::disk($this->disk)->exists($this->path);
    }
}
