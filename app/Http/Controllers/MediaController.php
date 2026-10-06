<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Post;
use App\Services\CmsMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ADMIN → KONTEN → MEDIA
 *
 * The library an article's images are chosen from. `cms.media.manage` was in
 * the permission catalogue from the start, granted to a real role, and reached
 * nothing — a permission with no route is a lie told in the role matrix.
 *
 * WHAT IS CHECKED WHERE
 *
 * The permission for the *screen* is on the route, in routes/media.php. The
 * permission for the *destructive action* is on the route too, and again in
 * MediaPolicy — the same double-check CmsPostService does for publishing,
 * because a policy is what the UI asks and the service is what stops the write.
 *
 * The rules about the file itself are in CmsMediaService. This class deals with
 * HTTP, not with what a valid image is.
 */
class MediaController extends BaseController
{
    public function __construct(
        \App\Services\AuditService $audit,
        \App\Services\CompletenessService $completeness,
        private readonly CmsMediaService $media,
    ) {
        parent::__construct($audit, $completeness);
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Media::class);

        $items = Media::query()
            ->with('uploader')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(function ($w) use ($term) {
                $w->where('original_name', 'like', '%'.$term.'%')
                    ->orWhere('alt_text', 'like', '%'.$term.'%');
            }))
            ->latest('id')
            ->paginate(24)
            ->withQueryString();

        return view('admin.media.index', [
            'items' => $items,
            'q' => $request->string('q')->toString(),
            'maxKb' => (int) config('cms.media.max_kb'),
        ]);
    }

    /**
     * Upload one image.
     *
     * The `image` rule in the validator is a first cheap filter on the
     * extension only. It is NOT the security control — a PHP file named .jpg
     * passes it. CmsMediaService re-reads the MIME from the bytes and is the
     * check that actually rejects it.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Media::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'max:'.((int) config('cms.media.max_kb'))],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = $this->media->store(
            $request->file('file'),
            $request->user(),
            $validated['alt_text'] ?? null,
            $validated['caption'] ?? null,
        );

        $this->audit->log('cms.media.uploaded', $item, "Mengunggah gambar: {$item->displayName()}", [
            'mime' => $item->mime,
            'size' => $item->size,
        ]);

        return $this->backWith("Gambar diunggah: {$item->displayName()} ({$item->humanSize()}).");
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $this->authorize('update', $media);

        $data = $request->validate([
            'file' => ['nullable', 'file', 'image', 'max:'.((int) config('cms.media.max_kb'))],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->hasFile('file')) {
            $this->media->replace($media, $request->file('file'));
        }

        $media->update(collect($data)->except('file')->all());

        $this->audit->log('cms.media.updated', $media, "Mengubah gambar: {$media->displayName()}");

        return $this->backWith('Gambar dan keterangan berhasil diperbarui.');
    }

    /**
     * Remove from the library. Soft — the bytes stay, so a live article that
     * still references the path keeps rendering.
     */
    public function destroy(Media $media): RedirectResponse
    {
        $this->authorize('delete', $media);

        $name = $media->displayName();
        $inUse = $media->posts()->count();

        $this->media->delete($media);

        $this->audit->log('cms.media.deleted', $media, "Menghapus gambar dari pustaka: {$name}", [
            'used_by_posts' => $inUse,
        ]);

        $message = $inUse > 0
            ? "Gambar \"{$name}\" dihapus dari pustaka. {$inUse} artikel masih memakainya dan tetap tampil."
            : "Gambar \"{$name}\" dihapus dari pustaka.";

        return $this->backWith($message);
    }

    public function restore(int $media): RedirectResponse
    {
        $item = Media::withTrashed()->findOrFail($media);

        $this->authorize('restore', $item);

        $this->media->restore($item);

        $this->audit->log('cms.media.restored', $item, "Mengembalikan gambar: {$item->displayName()}");

        return $this->backWith('Gambar dikembalikan ke pustaka.');
    }

    /**
     * Attach images to an article.
     *
     * The post is the route-bound model, so the id in the payload is never
     * trusted. `cms.posts.edit` here because this edits the ARTICLE's images,
     * not the library.
     */
    public function attachToPost(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $data = $request->validate([
            'media_ids' => ['nullable', 'array', 'max:50'],
            'media_ids.*' => ['integer', 'exists:cms_media,id'],
        ]);

        $this->media->syncPostMedia($post, $data['media_ids'] ?? []);

        $this->audit->log('cms.post.media_synced', $post, "Mengganti gambar artikel: {$post->title}", [
            'count' => count($data['media_ids'] ?? []),
        ]);

        return $this->backWith('Gambar artikel diperbarui.');
    }
}
