<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's standard database-notification table.
 *
 * Added when the notification centre was introduced — the original schema
 * (2026_01_01_000010) only declared `notifications` in the spec, not in the
 * database, so the first notification read blew up with a missing-table error.
 *
 * This is the canonical Laravel 12 shape and is safe to add to an existing
 * database: it only creates a new table and never touches existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The notification centre lists newest-first and filters unread rows.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_unread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
