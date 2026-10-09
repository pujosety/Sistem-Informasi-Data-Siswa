<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_navigation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('label', 80);
            $table->string('url', 500);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('open_in_new_tab')->default(false);
            $table->timestamps();
            $table->index(['school_id', 'is_enabled', 'position'], 'cms_navigation_school_order_idx');
        });

        $schoolId = DB::table('schools')->where('is_default', true)->value('id');
        if ($schoolId) {
            foreach ([
                ['Beranda', '/', 10],
                ['Profil', '/tentang', 20],
                ['Program', '/program', 30],
                ['Berita', '/berita', 40],
                ['Kontak', '/kontak', 50],
            ] as [$label, $url, $position]) {
                DB::table('cms_navigation_items')->insert([
                    'school_id' => $schoolId,
                    'label' => $label,
                    'url' => $url,
                    'position' => $position,
                    'is_enabled' => true,
                    'open_in_new_tab' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_navigation_items');
    }
};
