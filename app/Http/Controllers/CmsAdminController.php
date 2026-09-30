<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\Tag;
use App\Services\AuditService;
use App\Services\CmsPostService;
use App\Services\CompletenessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * ADMIN → KONTEN (CMS)
 *
 * Phase 3 built the tables, the models, CmsPostService and the public news
 * pages. It did not build the room where an article is actually written — so
 * ten `cms.*` permissions were granted, visible in the role matrix, and reached
 * nothing. `kesiswaan` holds `cms.posts.create`; before this controller there
 * was no page in the whole application where that permission did anything.
 *
 * The rules are not here. They are in CmsPostService, because they are the
 * rules a second surface (an API, a console command, an import) would otherwise
 * re-invent: saving never publishes, publishing requires the permission, every
 * save writes a revision.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO
 *
 * Media uploads, theme editing and navigation editing have permissions in the
 * catalogue and no implementation. They are not stubbed here. A permission with
 * no route is a lie told in the role matrix; adding a half-working screen and
 * calling it done would be the same lie wearing a UI.
 */
class CmsAdminController extends BaseController
{
    public function __construct(
        AuditService $audit,
        CompletenessService $completeness,
        private readonly CmsPostService $posts,
    ) {
        parent::__construct($audit, $completeness);
    }

    // ------------------------------------------------------------------ read

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Post::class);

        $posts = Post::query()
            ->with(['author', 'category'])
            ->when($request->string('kind')->toString(), fn ($q, $kind) => $q->where('kind', $kind))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('title', 'like', '%'.$term.'%')
                ->orWhere('slug', 'like', '%'.$term.'%')))
            // A scheduled post that is now due still reads as "scheduled" until
            // something runs publishDue(). Surfacing it as due here means the
            // operator can see that a cron job is missing.
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.cms.index', [
            'posts' => $posts,
            'kinds' => ['post' => 'Artikel', 'page' => 'Halaman'],
            'statuses' => Post::STATUSES,
            'q' => $request->string('q')->toString(),
            'filters' => $request->only(['kind', 'status']),
            'dueCount' => Post::query()->scheduled()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Post::class);

        return view('admin.cms.create', $this->formOptions([
            'post' => new Post(['kind' => $request->string('kind')->toString() ?: Post::KIND_POST]),
        ]));
    }

    public function edit(Post $post): View
    {
        $this->authorize('update', $post);

        return view('admin.cms.edit', $this->formOptions(['post' => $post->load('tags')]));
    }

    public function show(Post $post): View
    {
        $this->authorize('view', $post);

        return view('admin.cms.show', [
            'post' => $post->load(['author', 'category', 'tags', 'revisions.author']),
        ]);
    }

    public function revisions(Post $post): View
    {
        $this->authorize('update', $post);

        return view('admin.cms.revisions', [
            'post' => $post,
            'revisions' => $post->revisions()->with('author')->latest()->get(),
        ]);
    }

    // ----------------------------------------------------------------- write

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Post::class);

        $data = $this->validateContent($request);

        $post = $this->posts->create($request->user(), $data);

        $this->posts->syncTags($post, $this->tagNames($request));

        $this->audit->log('cms.post.created', $post, "Membuat konten: {$post->title}", [
            'kind' => $post->kind,
        ]);

        return redirect()
            ->route('admin.cms.show', $post)
            ->with('success', 'Konten tersimpan sebagai draft. Terbitkan saat sudah siap.');
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $data = $this->validateContent($request);

        $this->posts->update($post, $request->user(), $data);
        $this->posts->syncTags($post, $this->tagNames($request));

        $this->audit->log('cms.post.updated', $post, "Mengubah konten: {$post->title}");

        return $this->backWith('Konten diperbarui. Revisi sebelumnya tersimpan.');
    }

    /**
     * Publish. The permission is checked twice — here by the route, and again
     * inside the service, because the service is reachable from anywhere.
     */
    public function publish(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('publish', $post);

        $this->posts->publish($post, $request->user());

        $this->audit->log('cms.post.published', $post, "Menerbitkan konten: {$post->title}");

        return $this->backWith('Konten diterbitkan dan tampil di situs publik.');
    }

    public function unpublish(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('unpublish', $post);

        $this->posts->unpublish($post, $request->user());

        $this->audit->log('cms.post.unpublished', $post, "Menarik konten dari situs: {$post->title}");

        return $this->backWith('Konten ditarik dari situs publik. Riwayat revise tetap tersimpan.');
    }

    public function submitForReview(Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $this->posts->submitForReview($post, request()->user());

        $this->audit->log('cms.post.submitted', $post, "Mengirim konten untuk ditinjau: {$post->title}");

        return $this->backWith('Konten dikirim untuk ditinjau.');
    }

    public function restoreRevision(Post $post, PostRevision $revision): RedirectResponse
    {
        $this->authorize('update', $post);

        // Bound the revision to the post in the URL. Route-model binding
        // resolves both independently, so without this a crafted
        // /cms/posts/9/revisions/3 restores a snapshot belonging to a
        // different article — an edit delivered through the wrong document.
        abort_unless((int) $revision->cms_post_id === (int) $post->id, 404);

        $this->posts->restoreRevision($post, $revision, request()->user());

        $this->audit->log('cms.post.restored', $post, "Mengembalikan revisi konten: {$post->title}", [
            'revision_id' => $revision->id,
        ]);

        return $this->backWith('Konten dikembalikan ke revisi tersebut. Status tidak ikut berubah.');
    }

    /**
     * A scheduled post whose time has come, published by hand.
     *
     * publishDue() is what the scheduler calls. This is the same transition
     * triggered from a button, for a school whose scheduler is not running —
     * which is the normal case here, because QUEUE_CONNECTION is a database
     * driver but no worker is started on a single-container host.
     */
    public function publishDue(Request $request): RedirectResponse
    {
        $this->authorize('publishAny', Post::class);

        $count = $this->posts->publishDue();

        return $this->backWith(
            $count === 0
                ? 'Tidak ada konten terjadwal yang sudah jatuh tempo.'
                : "{$count} konten terjadwal diterbitkan."
        );
    }

    // ------------------------------------------------------------------ help

    private function validateContent(Request $request): array
    {
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', [Post::KIND_POST, Post::KIND_PAGE])],
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220', 'alpha_dash'],
            'body' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'integer', 'exists:cms_categories,id'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'string', 'max:255'],
            'public_from' => ['nullable', 'date'],
            'public_until' => ['nullable', 'date', 'after_or_equal:public_from'],
        ]);

        // A page has no date; a post without a body is an empty shell. Neither
        // is worth storing, and both are easier to catch here than in review.
        if ($data['kind'] === Post::KIND_PAGE) {
            unset($data['public_from'], $data['public_until'], $data['excerpt']);
        }

        return $data;
    }

    /**
     * Tag names from a comma-separated field.
     *
     * splitTags() in the service owns the parsing; this only normalises the
     * form field into the array it expects, so a blank field becomes an empty
     * list rather than a single empty tag.
     */
    private function tagNames(Request $request): array
    {
        $raw = (string) $request->input('tags', '');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function formOptions(array $extra = []): array
    {
        return $extra + [
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
            'kinds' => [Post::KIND_POST => 'Artikel', Post::KIND_PAGE => 'Halaman'],
        ];
    }
}
