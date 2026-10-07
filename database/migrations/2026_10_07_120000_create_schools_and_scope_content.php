<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schools')) {
            Schema::create('schools', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 120)->unique();
                $table->string('name', 190);
                $table->string('short_name', 80)->nullable();
                $table->string('npsn', 30)->nullable();
                $table->string('level', 40)->nullable();
                $table->string('status', 40)->nullable();
                $table->string('accreditation', 40)->nullable();
                $table->json('profile')->nullable();
                $table->json('contact')->nullable();
                $table->json('principal')->nullable();
                $table->json('branding')->nullable();
                $table->json('homepage')->nullable();
                $table->json('social')->nullable();
                $table->json('seo')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->index(['is_active', 'is_default']);
            });
        }

        $legacyName = Schema::hasTable('settings')
            ? DB::table('settings')->where('key', 'school.name')->value('value')
            : null;
        $legacyNpsn = Schema::hasTable('settings')
            ? DB::table('settings')->where('key', 'school.npsn')->value('value')
            : null;

        $default = DB::table('schools')->where('is_default', true)->first();
        if (! $default) {
            $id = DB::table('schools')->insertGetId([
                'slug' => 'smp-negeri-1-metro',
                'name' => $legacyName ?: 'SMP 1 LYFLA',
                'short_name' => $legacyName ?: 'SMP 1 LYFLA',
                'npsn' => $legacyNpsn ?: null,
                'level' => 'SMP',
                'status' => 'Negeri',
                'is_active' => true,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $default = DB::table('schools')->where('id', $id)->first();
        }

        $this->scopeTable('settings', $default->id, true);
        foreach (['cms_categories', 'cms_tags', 'cms_posts', 'cms_media', 'landing_sections'] as $table) {
            $this->scopeTable($table, $default->id);
        }

        if (Schema::hasTable('cms_media') && ! Schema::hasColumn('cms_media', 'source_name')) {
            Schema::table('cms_media', function (Blueprint $table): void {
                $table->string('source_name', 190)->nullable()->after('caption');
                $table->text('source_url')->nullable()->after('source_name');
            });
        }

        if (Schema::hasTable('cms_posts') && ! Schema::hasColumn('cms_posts', 'source_url')) {
            Schema::table('cms_posts', function (Blueprint $table): void {
                $table->text('source_url')->nullable()->after('meta_image');
                $table->string('source_name', 190)->nullable()->after('source_url');
            });
        }
    }

    private function scopeTable(string $tableName, int $defaultSchoolId, bool $settings = false): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'school_id')) {
            Schema::table($tableName, function (Blueprint $table) use ($settings): void {
                $table->foreignId('school_id')->nullable()->after($settings ? 'id' : 'id');
                $table->index('school_id');
            });
        }

        DB::table($tableName)->whereNull('school_id')->update(['school_id' => $defaultSchoolId]);

        if ($settings) {
            try {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropUnique('settings_key_unique');
                    $table->unique(['school_id', 'key'], 'settings_school_key_unique');
                });
            } catch (\Throwable) {
                // A partially applied deployment can already have the new key.
            }
        }
    }

    public function down(): void
    {
        // Tenant data is deliberately retained on rollback; removing school_id
        // would merge separate school content and destroy isolation.
    }
};
