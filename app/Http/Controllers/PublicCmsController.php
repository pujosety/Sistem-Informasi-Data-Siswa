<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Models\Post;
use App\Services\CmsBlockService;
use App\Services\SchoolContext;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The public reading side of the CMS.
 *
 * PUBLIC READ BOUNDARY
 *
 * Public listings, search results, and slug pages go through
 * publishedAndPublic(), so a draft, unpublished post, future post, expired
 * post, or post outside the active school is excluded in the query.
 *
 * preview() is the single deliberate exception: its route requires a valid,
 * expiring signature; the signed school value selects a request-only tenant
 * scope; and the response is noindex/no-store. Do not add any other draft read
 * path here or weaken the public scope above.
 *
 * An unsigned request cannot distinguish "exists but private" from "does not
 * exist"; ordinary public routes 404 in either case.
 */
class PublicCmsController extends Controller
{
    private const PER_PAGE = 12;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly CmsBlockService $blockEditor,
    ) {}

    /**
     * The news index.
     *
     * Paginated rather than listed. A school with years of announcements is the
     * normal case, and an unbounded list is the thing that turns a slow query
     * into a timeout.
     */
    public function news(Request $request): View
    {
        $categorySlug = trim($request->string('category')->toString());

        $posts = Post::query()
            ->posts()
            ->publishedAndPublic()
            ->when($categorySlug !== '', fn ($query) => $query->whereHas(
                'category',
                fn ($category) => $category->where('slug', $categorySlug)
            ))
            ->with(['category', 'author', 'media'])
            ->orderByDesc('published_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('public.news.index', [
            'school' => $this->school(),
            'posts' => $posts,
            'categories' => $this->categoriesWithContent(),
            'categorySlug' => $categorySlug,
        ]);
    }

    public function post(Request $request, string $slug): View
    {
        $post = Post::query()
            ->posts()
            ->publishedAndPublic()
            ->with(['category', 'tags', 'author'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('public.news.show', [
            'school' => $this->school(),
            'post' => $post,
            'renderableBlocks' => $this->blockEditor->forPublicRender($post->blocks),
            // Related reading, drawn from the same gate. A post outside it is
            // simply not a candidate.
            'related' => Post::query()
                ->posts()
                ->publishedAndPublic()
                ->whereKeyNot($post->id)
                ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
                ->orderByDesc('published_at')
                ->limit(3)
                ->get(),
        ]);
    }

    public function preview(Request $request, int $postId): \Illuminate\Http\Response
    {
        $schoolSlug = trim($request->string('school')->toString());
        abort_if($schoolSlug === '', 404);

        $school = \App\Models\School::query()
            ->where('is_active', true)
            ->where('slug', $schoolSlug)
            ->firstOrFail();

        // The signed query binds the bearer token to one tenant. This override
        // lasts only for this request and never changes a visitor's session.
        app(SchoolContext::class)->useForRequest($school);

        $post = Post::query()
            ->with(['category', 'tags', 'author', 'media'])
            ->whereKey($postId)
            ->firstOrFail();

        app(\App\Services\AuditService::class)->log(
            'cms.post.previewed',
            $post,
            'Membuka pratinjau bertanda tangan.',
            ['school_id' => $school->id],
        );

        $data = [
            'school' => $this->school(),
            'isPreview' => true,
            'renderableBlocks' => $this->blockEditor->forPublicRender($post->blocks),
        ];

        if ($post->isPage()) {
            $data['page'] = $post;
            $view = 'public.page';
        } else {
            $data['post'] = $post;
            $data['related'] = Post::query()
                ->posts()
                ->publishedAndPublic()
                ->whereKeyNot($post->id)
                ->when($post->category_id, fn ($query) => $query->where('category_id', $post->category_id))
                ->orderByDesc('published_at')
                ->limit(3)
                ->get();
            $view = 'public.news.show';
        }

        return response()->view($view, $data)
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'private, no-store');
    }

    public function search(Request $request): View
    {
        $query = trim($request->string('q')->toString());
        $posts = collect();
        $programs = collect();

        if (mb_strlen($query) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';
            $posts = Post::query()
                ->posts()
                ->publishedAndPublic()
                ->with('category')
                ->where(function ($builder) use ($like) {
                    $builder->where('title', 'like', $like)
                        ->orWhere('excerpt', 'like', $like)
                        ->orWhere('body', 'like', $like);
                })
                ->orderByDesc('published_at')
                ->limit(12)
                ->get();

            $programs = Department::query()
                ->where(function ($builder) use ($like) {
                    $builder->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                })
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        return view('public.search', [
            'school' => $this->school(),
            'query' => $query,
            'posts' => $posts,
            'programs' => $programs,
        ]);
    }

    public function page(Request $request, string $slug): View
    {
        $page = Post::query()
            ->pages()
            ->publishedAndPublic()
            ->with(['author', 'media'])
            ->where('slug', $slug)
            ->firstOrFail();

        $renderableBlocks = $this->blockEditor->forPublicRender($page->blocks);

        return view('public.page', [
            'school' => $this->school(),
            'page' => $page,
            'renderableBlocks' => $renderableBlocks,
        ]);
    }

    /**
     * The same school.* set the other public controller supplies.
     *
     * Duplicated rather than extracted, for the same reason PublicHomeController
     * has its own: this is the public data contract, and a shared service for
     * four keys would be a place for a staff-facing value to drift in.
     */
    private function school(): array
    {
        $school = app(SchoolContext::class)->current();
        $contact = $school?->contact ?? [];
        $principal = $school?->principal ?? [];

        return [
            'name' => $school?->name ?: $this->settings->get('school.name'),
            'npsn' => $school?->npsn ?: $this->settings->get('school.npsn'),
            'address' => data_get($contact, 'address') ?: $this->settings->get('school.address'),
            'city' => data_get($contact, 'city') ?: $this->settings->get('school.city'),
            'province' => data_get($contact, 'province') ?: $this->settings->get('school.province'),
            'email' => data_get($contact, 'email') ?: $this->settings->get('school.email'),
            'phone' => data_get($contact, 'phone') ?: $this->settings->get('school.phone'),
            'website' => data_get($contact, 'website') ?: $this->settings->get('school.website'),
            'headmaster' => data_get($principal, 'name') ?: $this->settings->get('school.headmaster'),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Category>
     */
    private function categoriesWithContent()
    {
        try {
            return Category::query()
                ->whereHas('posts', fn ($q) => $q->publishedAndPublic())
                ->orderBy('sort_order')
                ->get();
        } catch (\Throwable $e) {
            // A CMS that is not migrated yet should not take the news page down
            // with it; it renders empty, which is honest.
            report($e);

            return collect();
        }
    }
}
