<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance is recorded against a classroom SESSION, and each record points at
 * an ENROLLMENT — not a raw student id — so historical attendance stays attached
 * to the academic context it happened in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            // One session per classroom per day.
            $table->unique(['classroom_id', 'date'], 'attendance_session_unique');
            $table->index(['academic_year_id', 'date'], 'attendance_session_year_date_index');
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            // present | late | sick | excused | absent
            $table->string('status', 20)->default('present');
            $table->text('notes')->nullable();

            // Correction trail, per the audit requirement.
            $table->string('previous_status', 20)->nullable();
            $table->text('correction_reason')->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();

            $table->timestamps();

            $table->unique(['attendance_session_id', 'enrollment_id'], 'attendance_record_unique');
            $table->index(['enrollment_id'], 'attendance_record_enrollment_index');
        });

        Schema::create('classroom_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('body');
            // students | parents | both
            $table->string('audience', 20)->default('both');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['classroom_id', 'published_at'], 'announcement_classroom_index');
            $table->index(['academic_year_id'], 'announcement_year_index');
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 30)->unique();
            $table->string('grade_level', 20)->nullable();   // X / XI / XII
            $table->timestamps();
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('term', 20)->default('1');       // 1 | 2
            $table->decimal('score', 5, 2)->nullable();
            // draft | published
            $table->string('status', 20)->default('draft');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'subject_id', 'term'], 'grade_unique');
            $table->index(['status'], 'grade_status_index');
        });

        Schema::create('alumni', function (Blueprint $table) {
            $table->id();
            // One alumni row per student — no duplicate identity.
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->year('graduation_year');
            $table->date('graduation_date')->nullable();
            $table->foreignId('last_classroom_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['graduation_year'], 'alumni_year_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('classroom_announcements');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
    }
};
