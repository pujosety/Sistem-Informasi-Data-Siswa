<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('classroom_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->string('code', 80)->unique();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['academic_year_id', 'semester_id']);
            $table->index(['teacher_id', 'status']);
            $table->index(['classroom_id', 'semester_id']);
        });

        Schema::create('lms_course_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('enrolled_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['course_id', 'student_id'], 'lms_course_student_unique');
            $table->index(['student_id', 'status']);
        });

        Schema::create('lms_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
            $table->string('title', 180);
            $table->longText('body')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['course_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_lessons');
        Schema::dropIfExists('lms_course_enrollments');
        Schema::dropIfExists('lms_courses');
    }
};
