<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('email', 'admin@sida.test')
            ->where('name', 'Administrator SIDA')
            ->update([
                'name' => 'Administrator LYFLA',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Do not restore the obsolete display name on rollback.
    }
};
