<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * One block on a landing page.
 *
 * WHY content IS JSON AND NOT COLUMNS
 *
 * The section types do not share a shape. A stats block is a list of
 * label/value pairs, a testimonial is a quote with an attribution, an FAQ is
 * question/answer rows, a program card is a title with a description and a
 * link. A column per field would give a wide table that is mostly NULL and
 * that every new block type would force a migration for.
 *
 * WHY position IS A FLOAT
 *
 * So an administrator can insert a section between two others without
 * renumbering the page. Integers make "put this one third" mean "find the
 * current third, then increment everything below it", and the first person to
 * reorder by hand gets it wrong.
 */
class LandingSection extends Model
{
    public const TYPE_HERO = 'hero';
    public const TYPE_TRUST = 'trust';
    public const TYPE_STATS = 'stats';
    public const TYPE_ABOUT = 'about';
    public const TYPE_FEATURES = 'features';
    public const TYPE_PROGRAMS = 'programs';
    public const TYPE_EXPERIENCE = 'experience';
    public const TYPE_ACHIEVEMENTS = 'achievements';
    public const TYPE_FACILITIES = 'facilities';
    public const TYPE_TESTIMONIALS = 'testimonials';
    public const TYPE_PPDB = 'ppdb';
    public const TYPE_NEWS = 'news';
    public const TYPE_JOURNEY = 'journey';
    public const TYPE_ALUMNI = 'alumni';
    public const TYPE_FAQ = 'faq';
    public const TYPE_CTA = 'cta';

    /**
     * Every block the landing page knows how to render.
     *
     * This list is the contract between CMS and front end: an administrator
     * may store any type, but only these render. A type outside the list is
     * skipped rather than output — an unknown block must not blank the page,
     * because a typo in one row should not take the school website offline.
     */
    public const TYPES = [
        self::TYPE_HERO,
        self::TYPE_TRUST,
        self::TYPE_STATS,
        self::TYPE_ABOUT,
        self::TYPE_FEATURES,
        self::TYPE_PROGRAMS,
        self::TYPE_EXPERIENCE,
        self::TYPE_ACHIEVEMENTS,
        self::TYPE_FACILITIES,
        self::TYPE_TESTIMONIALS,
        self::TYPE_PPDB,
        self::TYPE_NEWS,
        self::TYPE_JOURNEY,
        self::TYPE_ALUMNI,
        self::TYPE_FAQ,
        self::TYPE_CTA,
    ];

    protected $fillable = [
        'page_key',
        'type',
        'title',
        'subtitle',
        'body',
        'media_id',
        'content',
        'is_enabled',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_enabled' => 'boolean',
            'position' => 'float',
        ];
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /**
     * Only types the front end can actually render.
     *
     * Scoped here rather than at the query site so there is exactly one
     * definition of "renderable" — a check repeated in a controller and a
     * view is a check that eventually disagrees with itself.
     */
    public function scopeRenderable($query)
    {
        return $query->whereIn('type', self::TYPES);
    }

    public function scopeForPage($query, string $pageKey = 'home')
    {
        return $query->where('page_key', $pageKey);
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * The blocks of a page, in the order they should render.
     */
    public static function forPage(string $pageKey = 'home'): Collection
    {
        // A deploy can briefly run the new code before its migration has been
        // applied. The public homepage must remain renderable in that window;
        // an empty collection produces the honest no-sections state while the
        // deployment health check still reports the missing schema.
        if (! Schema::hasTable('landing_sections')) {
            return new Collection;
        }

        return static::query()
            ->forPage($pageKey)
            ->enabled()
            ->renderable()
            ->orderBy('position')
            ->get();
    }

    /**
     * A content value with a fallback, so a template never branches on a
     * missing key.
     *
     * `content` is JSON an administrator typed by hand, so every read is a
     * possible miss. Returning the fallback keeps that possibility in one
     * place instead of at every call site.
     */
    public function value(string $key, mixed $default = null): mixed
    {
        return data_get($this->content, $key, $default);
    }

    /**
     * A content value that is always a list.
     *
     * Returns [] rather than null so `@foreach` is always safe, which matters
     * because a malformed row would otherwise throw inside a template on a
     * page every visitor sees.
     */
    public function items(string $key): array
    {
        $value = $this->value($key, []);

        return is_array($value) ? array_values($value) : [];
    }
}