<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrollment is the source of truth for a student's classroom membership.
 *
 * `students.class_id` / `students.academic_year_id` are NOT dropped — they stay
 * as a compatibility mirror of the current enrollment so existing screens keep
 * working during the transition.
 *
 * MySQL has no partial indexes, so "one ACTIVE enrollment per student per year"
 * is enforced in two places:
 *   - a composite index that keeps lookups cheap for the guard query
 *   - application validation in EnrollmentService
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();

            // active | promoted | retained | transferred | graduated | withdrawn | completed
            $table->string('status', 20)->default('active');

            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->text('notes')->nullable();

            // Provenance: how this enrollment came to exist.
            $table->string('source', 20)->default('manual');   // backfill | manual | import | promotion
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Hot path: "current enrollment for this student", and per-class rosters.
            $table->index(['student_id', 'status'], 'enrollments_student_status_index');
            $table->index(['classroom_id', 'status'], 'enrollments_classroom_status_index');
            $table->index(['academic_year_id', 'status'], 'enrollments_year_status_index');
            $table->index(['department_id'], 'enrollments_department_index');
        });

        Schema::create('homeroom_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            // active | ended | replaced
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'homeroom_user_status_index');
            $table->index(['classroom_id', 'status'], 'homeroom_classroom_status_index');
            $table->index(['academic_year_id'], 'homeroom_year_index');
        });

        // Wali Murid is a completely different concept from Wali Kelas. This table
        // links a parent LOGIN to a student; `parents` keeps the profile data and
        // gains a nullable user_id so an existing profile can be claimed.
        Schema::table('parents', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::create('guardian_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('parents')->nullOnDelete();

            // ayah | ibu | wali
            $table->string('relationship', 20)->default('wali');
            $table->boolean('is_primary')->default(false);
            // pending | active | revoked
            $table->string('status', 20)->default('active');

            $table->string('linked_via', 20)->default('admin');   // admin | invitation | token
            $table->string('invite_token_hash', 64)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status'], 'guardian_student_status_index');
            $table->index(['guardian_user_id', 'status'], 'guardian_user_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_relationships');
        Schema::table('parents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::dropIfExists('homeroom_assignments');
        Schema::dropIfExists('enrollments');
    }
};
