<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions table for SESSION_DRIVER=database.
 *
 * Required in production: hosted filesystems (Wasmer included) are frequently
 * ephemeral or per-instance, so file-based sessions would log users out
 * whenever a request lands on a different instance, and a rebuilt container
 * would drop every open session.
 *
 * The cache and jobs tables already ship with Laravel's defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
