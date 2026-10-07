<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceUnique('cms_categories', 'cms_categories_slug_unique', ['school_id', 'slug'], 'cms_categories_school_slug_unique');
        $this->replaceUnique('cms_tags', 'cms_tags_slug_unique', ['school_id', 'slug'], 'cms_tags_school_slug_unique');
        $this->replaceUnique('cms_posts', 'cms_posts_kind_slug_unique', ['school_id', 'kind', 'slug'], 'cms_posts_school_kind_slug_unique');
    }

    private function replaceUnique(string $tableName, string $old, array $columns, string $new): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($old, $columns, $new): void {
                $table->dropUnique($old);
                $table->unique($columns, $new);
            });
        } catch (\Throwable) {
            // A retry-safe migration may already have applied the replacement.
            try {
                Schema::table($tableName, function (Blueprint $table) use ($columns, $new): void {
                    $table->unique($columns, $new);
                });
            } catch (\Throwable) {
                // Leave an already-correct index untouched.
            }
        }
    }

    public function down(): void
    {
        // Keep tenant-safe unique keys on rollback; restoring global uniqueness
        // would make separate school records collide.
    }
};
