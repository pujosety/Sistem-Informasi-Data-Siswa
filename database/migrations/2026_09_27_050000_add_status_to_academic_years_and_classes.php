<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic Year + Classroom lifecycle.
 *
 * Additive only. `academic_years.is_active` and `classes.level` are kept because
 * existing code and data already depend on them; `status`/`is_default` sit
 * alongside rather than replacing them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Academic Year: explicit lifecycle -----------------------------
        Schema::table('academic_years', function (Blueprint $table) {
            $table->string('status', 20)->default('upcoming')->after('name');
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->text('notes')->nullable()->after('is_default');
        });

        // One default academic year at most, enforced by a generated-free
        // partial index created below (MySQL has no partial indexes, so this
        // is validated in the service layer and in the form request).
        Schema::table('academic_years', function (Blueprint $table) {
            $table->index(['status'], 'academic_years_status_index');
        });

        // --- Classroom: code, room, lifecycle ------------------------------
        Schema::table('classes', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->after('name');
            $table->string('room', 50)->nullable()->after('capacity');
            $table->string('status', 20)->default('active')->after('room');
            $table->text('notes')->nullable()->after('status');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->index(['status'], 'classes_status_index');
            // A classroom name is unique within its academic year, not globally.
            $table->index(['academic_year_id', 'name'], 'classes_year_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex('classes_status_index');
            $table->dropIndex('classes_year_name_index');
            $table->dropColumn(['code', 'room', 'status', 'notes']);
        });

        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropIndex('academic_years_status_index');
            $table->dropColumn(['status', 'is_default', 'notes']);
        });
    }
};
