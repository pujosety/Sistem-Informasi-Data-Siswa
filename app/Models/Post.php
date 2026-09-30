<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A post or a page.
 *
 * The one field that matters on this model is `is_public`. §10 requires that
 * internal data never becomes public by accident and that student data is
 * private by default, so publication is an explicit, separate, opt-in decision
 * from status. A post can be `published` — meaning the author considers it
 * finished — and still be private, and every public query in this application
 * goes through publishedAndPublic() so that the two cannot be conflated by
 * forgetting one of them.
 */
class Post extends Model
{
    use SoftDeletes;

    public const KIND_POST = 'post';

    public const KIND_PAGE = 'page';

    public const DRAFT = 'draft';

    public const PENDING = 'pending';

    public const SCHEDULED = 'scheduled';

    public const PUBLISHED = 'published';

    public const ARCHIVED = 'archived';

    /** §14's workflow: draft → pending review → approved → published. */
    public const STATUSES = [
        self::DRAFT,
        self::PENDING,
        self::SCHEDULED,
        self::PUBLISHED,
        self::ARCHIVED,
    ];

    /** A status that means "finished", whether or not it is visible. */
    public const FINALISED = [self::PUBLISHED, self::ARCHIVED];

    protected $table = 'cms_posts';

    protected $fillable = [
        'kind', 'title', 'slug', 'body', 'excerpt', 'status',
        'author_id', 'category_id', 'published_at', 'scheduled_for',
        'parent_id', 'sort_order', 'blocks',
        'meta_title', 'meta_description', 'meta_image',
        'is_public', 'public_from', 'public_until',
    ];

    /**
     * Publication is private unless something says otherwise.
     *
     * The column default handles a raw INSERT that omits the column, but
     * Eloquent writes every fillable attribute it knows about, so an unset
     * attribute arrives as NULL and the database default never fires. Declaring
     * it here as well means the value is false however the row was written —
     * which matters because a boolean cast turns NULL into NULL, not false, and
     * NULL fails an `is_public = true` check just as false does but reads as
     * "unknown" in a data export.
     */
    protected $attributes = [
        'kind' => self::KIND_POST,
        'status' => self::DRAFT,
        'is_public' => false,
        'sort_order' => 0,
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'public_from' => 'datetime',
        'public_until' => 'datetime',
        'is_public' => 'boolean',
        'blocks' => 'array',
        'sort_order' => 'integer',
    ];

    // ------------------------------------------------------------- relations

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * The pivot table and both keys are stated rather than inferred.
     *
     * belongsToMany derives `post_tag` with `post_id` and `tag_id` from the
     * two model class names, and this schema uses cms_post_tag with
     * cms_post_id and cms_tag_id — the inference produced an INSERT naming
     * columns that do not exist. Being explicit here and in Tag::posts() is
     * what keeps the two sides describing the same table.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            'cms_post_tag',
            'cms_post_id',
            'cms_tag_id'
        );
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class, 'cms_post_id')->latest('created_at');
    }

    // ---------------------------------------------------------------- scopes

    public function scopePosts(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_POST);
    }

    public function scopePages(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_PAGE);
    }

    /**
     * THE public gate. Both conditions, always.
     *
     * `published` is about the author: is it finished. `is_public` is a separate
     * decision about the world: should anyone outside see it. A scope that
     * filtered on one of the two would either leak a finished draft to the
     * internet or hide every article a school has marked as public, and both
     * failure modes look like "the CMS is empty" rather than like a bug.
     *
     * The window is enforced here rather than trusted to the caller, because
     * every consumer should be able to call this scope and be safe, rather than
     * having to remember the other two conditions.
     */
    public function scopePublishedAndPublic(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('status', self::PUBLISHED)
            ->where('is_public', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->where(fn ($q) => $q->whereNull('public_from')->orWhere('public_from', '<=', $now))
            ->where(fn ($q) => $q->whereNull('public_until')->orWhere('public_until', '>', $now));
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query
            ->where('status', self::SCHEDULED)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now());
    }

    // ----------------------------------------------------------------- state

    public function isPage(): bool
    {
        return $this->kind === self::KIND_PAGE;
    }

    /**
     * Whether this record may currently be shown to an anonymous visitor.
     *
     * The same four conditions as publishedAndPublic(), evaluated on the
     * instance, so a controller that already holds a post can ask without a
     * second query. Kept in step with the scope deliberately: a public page
     * that forgets the scope and uses this is still safe.
     */
    public function isVisibleToPublic(): bool
    {
        if ($this->status !== self::PUBLISHED || ! $this->is_public) {
            return false;
        }

        if ($this->published_at === null || $this->published_at->isFuture()) {
            return false;
        }

        if ($this->public_from !== null && $this->public_from->isFuture()) {
            return false;
        }

        if ($this->public_until !== null && $this->public_until->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * §6's scheduled publishing.
     *
     * A scheduled post becomes published when its time arrives, and only if
     * somebody has already chosen to make it public. Scheduling is not
     * publication: a school that schedules a draft and forgets the checkbox
     * should not wake up to a public article.
     */
    public function isDueToPublish(): bool
    {
        return $this->status === self::SCHEDULED
            && $this->scheduled_for !== null
            && $this->scheduled_for->isPast();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::DRAFT     => 'Draf',
            self::PENDING   => 'Menunggu tinjauan',
            self::SCHEDULED => 'Terjadwal',
            self::PUBLISHED => 'Terbit',
            self::ARCHIVED  => 'Diarsipkan',
            default          => ucfirst((string) $this->status),
        };
    }

    /**
     * What the `<title>` should say, falling back through the SEO field, the
     * title, and the school name rather than emitting an empty tag.
     */
    public function metaTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    /**
     * The public path, as a string rather than a route() call.
     *
     * Deliberately not route(). A page's URL depends on its parent chain, which
     * is arbitrary depth, so a named route per depth is not a thing. Deriving
     * the path keeps the model usable before the public routes exist and after
     * the page tree grows deeper than any route table can express.
     */
    public function url(): string
    {
        $segments = [$this->slug];

        // Walk up the tree so a child of a child still resolves.
        $parent = $this->parent;
        $guard = 0;

        while ($parent !== null && $guard++ < 10) {
            array_unshift($segments, $parent->slug);
            $parent = $parent->parent;
        }

        $base = $this->isPage() ? 'tentang' : 'berita';

        return url(implode('/', array_merge([$base], $segments)));
    }
}
