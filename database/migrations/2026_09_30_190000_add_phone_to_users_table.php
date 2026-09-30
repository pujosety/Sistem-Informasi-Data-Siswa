<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `phone` to `users`.
 *
 * WHY THIS MIGRATION EXISTS
 *
 * `ProfileController::update()` validates and writes `name`, `email` and
 * `phone` — and has done since the profile screen was built. `users` never had
 * a `phone` column, so EVERY profile save ended in
 * `SQLSTATE[42S22] Unknown column 'phone' in 'field list'`. The password form
 * on the same page worked, which is why the page looked alive.
 *
 * The confusion was institutional rather than accidental: the employees
 * migration documents `name`, `email`, `phone` and `is_active` as "already
 * live on `users`", and that was true of three of the four. `phone` was the
 * odd one out, and the false comment has since been corrected to name this
 * migration.
 *
 * `phone` already exists on `students` (string 25, nullable) — created in the
 * core-table migration — and this column deliberately matches it, because a
 * student's account phone and their profile phone are the same number and two
 * definitions of it would drift.
 *
 * Backward-safe: nullable, so every existing row is valid and no default is
 * invented. Nothing is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'phone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 25)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'phone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });
    }
};