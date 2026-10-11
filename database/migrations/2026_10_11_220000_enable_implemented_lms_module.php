<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // LMS now has authenticated teacher and student routes, surfaces, and
        // workflows. The original registry seed predates that implementation
        // and intentionally inserted it disabled; make the shipped module visible.
        DB::table('modules')->where('key', 'lms')->update([
            'is_enabled' => true,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('modules')->where('key', 'lms')->update([
            'is_enabled' => false,
            'updated_at' => now(),
        ]);
    }
};
