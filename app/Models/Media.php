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

    /**
     * A public URL for this file.
     *
     * Added because every block on the landing page needs one, and the
     * alternative is `Storage::disk($media->disk)->url($media->path)` written
     * into sixteen Blade templates. A disk name lives on the row, so a template
     * cannot know which disk to ask — it has to come from the model.
     *
     * Returns null rather than throwing when the file is gone, because the
     * landing page is public and a missing image must degrade to a placeholder
     * rather than take the page down for every visitor.
     */
    /**
     * The public URL for this file.
     *
     * Called through the `url` cast rather than a legacy accessor, because
     * `url` is also a Builder method and an unresolved `$media->url()` gets
     * forwarded there and throws.
     */
    public function url(): ?string
    {
        if (! $this->path) {
            return null;
        }

        // Two kinds of file live in the media library, and they resolve
        // differently:
        //
        //   uploads/documents/a.pdf   → served by the storage disk at
        //                                /storage/uploads/documents/a.pdf
        //   images/school/hero.webp   → served STATICALLY from public/, so the
        //                                disk URL would 404
        //
        // The landing photographs are the second kind, so they are checked on
        // disk first. Guessing wrong here produces a section with a broken
        // image, which is exactly the placeholder look this replaces.
        if (str_starts_with($this->path, 'images/') || str_starts_with($this->path, 'branding/')) {
            if (is_file(public_path($this->path))) {
                return asset($this->path);
            }
        }

        try {
            return Storage::disk($this->disk ?: 'public')->url($this->path);
        } catch (\Throwable) {
            return null;
        }
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
        if (str_starts_with($this->path ?? '', 'images/') || str_starts_with($this->path ?? '', 'branding/')) {
            return ! is_file(public_path($this->path));
        }

        try {
            return ! Storage::disk($this->disk ?: 'public')->exists($this->path);
        } catch (\Throwable) {
            return true;
        }
    }
}
