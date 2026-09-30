<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The CMS media library.
 *
 * WHY THE RULES ARE HERE AND NOT IN THE CONTROLLER
 *
 * Upload validation is the one part of this application that is a security
 * control rather than a UI convenience, and it is reachable from a console
 * command, a seeder, or a future API exactly as it is from the admin screen.
 * If the rule lives in a controller, the second caller re-invents it and the
 * re-invention is the one with the hole in it.
 *
 * THE THREE THINGS THIS ENFORCES
 *
 * 1. THE MIME IS READ FROM THE BYTES, NEVER FROM THE BROWSER.
 *
 *    A multipart request's Content-Type is a string the client typed. Validating
 *    `mimes:jpg` or `$file->getClientMimeType()` means validating the attacker.
 *    The only trustworthy statement about what a file is comes from reading its
 *    first bytes, which is what finfo does behind `getMimeType()`. A PHP
 *    webshell renamed to `foto.jpg` arrives as `text/x-php` and is rejected here
 *    regardless of the extension or the Content-Type it carries.
 *
 * 2. THE STORED FILENAME IS GENERATED, NOT RECEIVED.
 *
 *    Everything hostile about an upload is usually in the name: `../`, a null
 *    byte, a double extension, a 300-character path, or a name that collides
 *    with an existing file and overwrites it. Using a random name and an
 *    extension derived from the sniffed MIME makes the whole class unreachable
 *    rather than filtered. The original name is kept in a database column for
 *    display, where it is inert.
 *
 * 3. THE STORED PATH IS RE-READ AND CHECKED BEFORE USE.
 *
 *    Layers 1 and 2 make a traversal path impossible to construct, so a third
 *    check would be dead code. It is written anyway: it is four lines, it turns
 *    a silent regression in either layer above from a stored-file incident into
 *    a thrown exception, and the cost of a control you never needed is zero
 *    while the cost of one you needed is a school website rewrite.
 *
 * WHAT IS NOT ENFORCED, AND WHY IT IS NOT NEEDED
 *
 * There is no "is this a real photo" check. A school uploading a photo of a
 * certificate is doing the normal thing, and content moderation is not a
 * validation rule. What matters is that whatever the pixels are, they are
 * pixels — an image decoder parses them, and a browser renders them in an
 * <img>, where script does not run.
 */
class CmsMediaService
{
    /**
     * Validate and store one uploaded image, returning its catalogue row.
     *
     * @throws ValidationException
     */
    public function store(UploadedFile $file, User $uploader, ?string $altText = null, ?string $caption = null): Media
    {
        $this->assertUploadable($file);

        $mime = $this->sniffedMime($file);

        $extension = config('cms.media.allowed_mimes')[$mime]
            ?? throw ValidationException::withMessages(['file' => 'Format gambar tidak didukung.']);

        /*
         * The stored name shares NOTHING with the incoming name but the
         * extension — and that extension came from the bytes. 40 random
         * characters is a 190-bit space; two uploads in the same millisecond
         * colliding is not a thing that happens.
         */
        $directory = trim((string) config('cms.media.directory'), '/').'/'.now()->format('Y/m');

        $filename = Str::random(40).'.'.$extension;
        $path = $this->safePath($directory, $filename);

        $dimensions = $this->dimensions($file);

        $stored = $file->storeAs($directory, $filename, ['disk' => $this->disk()]);

        if ($stored === false) {
            throw ValidationException::withMessages(['file' => 'Gambar gagal disimpan.']);
        }

        /*
         * storeAs() may hash the name for a store that dislikes collisions, so
         * the path is re-read from the result rather than assumed.
         */
        $stored = $this->safePath($directory, basename($stored));

        return Media::create([
            'disk' => $this->disk(),
            'path' => $stored,
            'original_name' => $this->safeDisplayName($file),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'alt_text' => $altText !== null && trim($altText) !== '' ? Str::limit(trim($altText), 255, '') : null,
            'caption' => $caption !== null && trim($caption) !== '' ? trim($caption) : null,
            'uploaded_by' => $uploader->id,
        ]);
    }

    /**
     * Remove an image from the library.
     *
     * SOFT, ALWAYS. The bytes stay on disk.
     *
     * A published article carries the path inside its body, and a hard delete
     * would turn an operator's routine tidy-up into a 404 on the public school
     * website with no way back. The file is now unreferenced-but-present, which
     * is recoverable; a deleted file is not.
     */
    public function delete(Media $media): void
    {
        $media->delete();
    }

    /**
     * Restore a soft-deleted image to the library.
     */
    public function restore(Media $media): void
    {
        $media->restore();
    }

    /**
     * Attach images to a post, replacing whatever was attached before.
     *
     * The post's id is taken from the bound model rather than from the payload,
     * so a crafted request cannot attach an image to an article the editor is
     * not allowed to see. Route-model binding resolves the two independently
     * — the same trap CmsAdminController::restoreRevision() guards against.
     *
     * @param  array<int>  $mediaIds
     */
    public function syncPostMedia(Post $post, array $mediaIds): void
    {
        // Only images that exist and are not deleted. An id that was removed
        // from the library is not a reason to fail the whole save — the editor
        // simply picked a stale checkbox.
        $existing = Media::query()
            ->whereIn('id', $mediaIds)
            ->pluck('id')
            ->all();

        $sync = [];

        foreach ($existing as $index => $id) {
            $sync[$id] = ['sort_order' => $index];
        }

        $post->media()->sync($sync);
    }

    // ------------------------------------------------------------- validation

    /**
     * Everything checkable before the bytes are read.
     *
     * @throws ValidationException
     */
    private function assertUploadable(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'Berkas gagal diunggah.']);
        }

        $maxBytes = ((int) config('cms.media.max_kb')) * 1024;

        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => 'Ukuran gambar maksimal '.(int) config('cms.media.max_kb').' KB.',
            ]);
        }

        /*
         * A zero-byte file is valid as a file and invalid as an image. finfo
         * reports `application/x-empty` for it, which is already outside the
         * allowlist, but saying so plainly beats letting the user work out what
         * "text/plain" means about their photo.
         */
        if ($file->getSize() === 0) {
            throw ValidationException::withMessages(['file' => 'Berkas gambar kosong.']);
        }

        $this->assertNameIsInert($file);
    }

    /**
     * The browser's filename must not contain anything structural.
     *
     * This is belt-and-braces: the stored name is random, so a traversal string
     * here could never reach the disk. It is rejected anyway, for a reason that
     * is not about traversal — a name like `../../../../.env` displayed in the
     * library list is a social-engineering payload aimed at the next person to
     * read the screen, and `strlen()` on a 300-character name is a nuisance.
     *
     * @throws ValidationException
     */
    private function assertNameIsInert(UploadedFile $file): void
    {
        $name = $file->getClientOriginalName();

        if (str_contains($name, "\0")
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || str_contains($name, '..')) {
            throw ValidationException::withMessages(['file' => 'Nama berkas tidak valid.']);
        }

        if (strlen($name) > 255) {
            throw ValidationException::withMessages(['file' => 'Nama berkas terlalu panjang.']);
        }

        /*
         * Checked as a filter only. The extension that is actually written is
         * derived from the sniffed MIME, so passing this proves nothing about
         * the contents — that is the point of the MIME check below.
         */
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension !== '' && ! in_array($extension, (array) config('cms.media.allowed_extensions'), true)) {
            throw ValidationException::withMessages([
                'file' => 'Format harus JPG, PNG, WEBP, atau GIF.',
            ]);
        }
    }

    /**
     * The real MIME, read from the file's bytes.
     *
     * @throws ValidationException
     */
    private function sniffedMime(UploadedFile $file): string
    {
        $allowed = (array) config('cms.media.allowed_mimes');

        $mime = (string) $file->getMimeType();

        if (! isset($allowed[$mime])) {
            throw ValidationException::withMessages([
                'file' => 'Berkas bukan gambar yang valid. Hanya JPG, PNG, WEBP, dan GIF.',
            ]);
        }

        return $mime;
    }

    /**
     * Width and height, or nulls when the file will not report them.
     *
     * A GIF with no dimensions is still a valid GIF as far as finfo and the
     * browser are concerned, so a null here is recorded rather than treated as
     * a rejection. The library UI has to cope with "unknown size" anyway,
     * because the column is nullable.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(UploadedFile $file): array
    {
        $info = @getimagesize($file->getRealPath());

        if ($info === false || empty($info[0]) || empty($info[1])) {
            return [null, null];
        }

        return [(int) $info[0], (int) $info[1]];
    }

    /**
     * Reject any path that does not sit inside the media directory.
     *
     * A defence that must never fire, written so that the day it does fire the
     * reason is obvious.
     *
     * @throws ValidationException
     */
    private function safePath(string $directory, string $filename): string
    {
        $path = $directory.'/'.$filename;

        if (str_contains($path, '..')
            || str_contains($path, "\0")
            || str_starts_with($path, '/')) {
            throw ValidationException::withMessages(['file' => 'Lokasi penyimpanan tidak valid.']);
        }

        return $path;
    }

    /** The original name, stripped of anything path-shaped, for display only. */
    private function safeDisplayName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));

        return Str::limit($name !== '' ? $name : 'gambar', 255, '');
    }

    private function disk(): string
    {
        return (string) config('cms.media.disk', 'public');
    }
}
