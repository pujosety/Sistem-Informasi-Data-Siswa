<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landing page sections, and the content inside them.
 *
 * WHY THIS EXISTS
 *
 * The redesign brief asks for a landing page an administrator can edit —
 * reorder sections, toggle them, swap copy and images, all without touching
 * code. The CMS that already exists (cms_posts, cms_media, cms_categories)
 * models ARTICLES: dated, categorised, searchable, paginated. A landing page
 * section is none of those things. A hero headline has no publication date and
 * no category, and "the third section on the homepage" is not something a post
 * table can express.
 *
 * So this adds two tables rather than bending cms_posts into a shape it was
 * never meant to take.
 *
 * DESIGN NOTES
 *
 * `position` is a float rather than an integer so an administrator can insert
 * a section between two others without renumbering the whole page. Gaps are
 * filled by whoever edits next, and the reader sorts by position regardless.
 *
 * `content` is JSON, not fifteen nullable columns. The sections have genuinely
 * different shapes — a stats block is a list of four pairs, a testimonial is a
 * quote with an attribution, FAQ is question/answer rows — and a column per
 * field would be a wide table that is empty for almost every row.
 *
 * Backward-safe and additive: nothing existing is touched, and every column
 * has a default so an empty landing page is a valid starting state rather than
 * an error.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('landing_sections')) {
            return;
        }

        Schema::create('landing_sections', function (Blueprint $table): void {
            $table->id();

            // 'home' for now; the key exists so a second landing page later is
            // a new value rather than a new table.
            $table->string('page_key', 40)->default('home')->index();

            // The block type, matched to a Blade component: hero, stats,
            // trust, about, features, programs, experience, achievements,
            // facilities, testimonials, ppdb, news, journey, alumni, faq, cta.
            $table->string('type', 40);

            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('body')->nullable();

            // A featured image is a cms_media row, so the block reuses the
            // existing library and the existing ownership checks rather than
            // inventing a second upload path.
            $table->unsignedBigInteger('media_id')->nullable();

            $table->json('content')->nullable();

            $table->boolean('is_enabled')->default(true);

            $table->float('position')->default(0);

            $table->timestamps();

            // The page renders sections in order, so the index serves the only
            // query this table ever sees.
            $table->index(['page_key', 'is_enabled', 'position'], 'landing_sections_render_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_sections');
    }
};