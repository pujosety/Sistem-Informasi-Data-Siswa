<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Models\Post;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The public reading side of the CMS.
 *
 * THE ONLY WAY TO READ A POST HERE
 *
 * Every query in this controller goes through publishedAndPublic(). That scope
 * is not a convenience — it is the boundary. §10 requires that internal data
 * never becomes public by accident, and a controller that filtered on
 * `status = published` alone would publish every finished draft in the school
 * the moment somebody ticked a status.
 *
 * So there is deliberately no code path here that builds a Post query
 * without that scope, and no per-post re-check afterwards. A second check
 * would be the place a future change forgets one of the two conditions, and it
 * would be invisible: the page would simply look empty.
 *
 * A post that is not visible 404s rather than redirecting, for the same reason
 * as any other absent record: distinguishing "exists but private" from "does
 * not exist" tells an anonymous visitor which slugs the school has drafted.
 */
class PublicCmsController extends Controller
{
    private const PER_PAGE = 12;

    public function __construct(private readonly SettingsService $settings) {}

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
            ->with(['category', 'author'])
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

        return view('public.page', [
            'school' => $this->school(),
            'page' => $page,
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
        return [
            'name' => $this->settings->get('school.name'),
            'npsn' => $this->settings->get('school.npsn'),
            'address' => $this->settings->get('school.address'),
            'city' => $this->settings->get('school.city'),
            'province' => $this->settings->get('school.province'),
            'email' => $this->settings->get('school.email'),
            'phone' => $this->settings->get('school.phone'),
            'website' => $this->settings->get('school.website'),
            'headmaster' => $this->settings->get('school.headmaster'),
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
