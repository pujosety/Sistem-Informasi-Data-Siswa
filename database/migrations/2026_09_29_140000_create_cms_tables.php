<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posts: the content a public page can actually be built from.
 *
 * WHY THIS IS THE FIRST CMS TABLE
 *
 * §6 asks for posts and §7 for pages, and the difference matters. A post is
 * dated content with an author, a category, tags and a publish date; a page is
 * undated standing material. Modelling both as one table is the usual shortcut
 * and it is why most CMSes can only do one of them properly.
 *
 * `kind` separates them, and it is set once and never changed. Everything else
 * about the split is derived: a page has no published_at, no author, and no
 * category required.
 *
 * WHY NOT JSON
 *
 * §54 allows JSON for flexible BLOCK settings, not for relational data. An
 * author, a category and a tag are relations with their own lifecycles — a
 * renamed author must be renamed once, in one place, and that is not something
 * a string inside a JSON blob can do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('cms_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('cms_posts', function (Blueprint $table) {
            $table->id();

            // post | page. See the docblock.
            $table->string('kind', 10)->default('post');

            $table->string('title', 200);
            $table->string('slug', 220);

            // Markdown in, HTML out. Stored as written so a revision can be
            // diffed against what the author actually typed.
            $table->longText('body')->nullable();
            $table->text('excerpt')->nullable();

            /*
             * Presentation is a small fixed set of choices, not a free string.
             * An ENUM would need a migration to add one, and a plain string
             * would accept a typo that silently renders as draft.
             */
            // draft | pending | scheduled | published | archived
            $table->string('status', 20)->default('draft');

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('cms_categories')->nullOnDelete();

            // A page is not a post, so it has no date. Both stay nullable and
            // the model treats null as undated.
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();

            /*
             * Page hierarchy. Self-referential, and the index is on
             * (parent_id, sort_order) because a page tree is always read as a
             * whole branch at once.
             */
            $table->foreignId('parent_id')->nullable()->constrained('cms_posts')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Blocks for the site builder, PHASE 4. JSON is the right shape
            // here: block settings are configuration, not relations, and §54
            // allows exactly this.
            $table->json('blocks')->nullable();

            /*
             * SEO, as its own columns rather than a JSON blob, because the
             * brief names a dedicated SEO screen and because these are queried
             * for a meta tag on every request.
             */
            $table->string('meta_title', 200)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_image', 255)->nullable();

            /*
             * §10 made concrete. `is_public` is the explicit publication
             * control the brief asks for, and it defaults to FALSE: a post is
             * private until somebody decides otherwise. There is no path by
             * which saving a draft publishes it.
             */
            $table->boolean('is_public')->default(false);
            $table->timestamp('public_from')->nullable();
            $table->timestamp('public_until')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // The public listing: published, public, in date order.
            $table->index(
                ['is_public', 'status', 'published_at'],
                'cms_posts_public_index'
            );
            $table->index(['kind', 'parent_id', 'sort_order'], 'cms_posts_tree_index');
            $table->unique(['kind', 'slug'], 'cms_posts_kind_slug_unique');
        });

        Schema::create('cms_post_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cms_tag_id')->constrained()->cascadeOnDelete();

            // A tag cannot be applied to the same post twice; without this a
            // double-submitted form silently doubles the tag on the article.
            $table->unique(['cms_post_id', 'cms_tag_id'], 'cms_post_tag_unique');
        });

        Schema::create('cms_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            /*
             * A full snapshot, not a diff. §14 wants compare AND restore, and a
             * snapshot makes restore a single write; a diff makes it a replay
             * that has to be replayed correctly against whatever else changed.
             * Storage is cheap here — these are articles, not a ledger.
             */
            $table->json('payload');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['cms_post_id', 'created_at'], 'cms_revisions_post_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_revisions');
        Schema::dropIfExists('cms_post_tag');
        Schema::dropIfExists('cms_posts');
        Schema::dropIfExists('cms_tags');
        Schema::dropIfExists('cms_categories');
    }
};
