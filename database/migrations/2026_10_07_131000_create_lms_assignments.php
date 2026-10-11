<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_assignments')) {
            Schema::create('lms_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
                $table->foreignId('module_id')->nullable()->constrained('lms_modules')->nullOnDelete();
                $table->string('title', 180);
                $table->longText('description')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->decimal('max_score', 6, 2)->default(100);
                $table->string('submission_type', 20)->default('text_file');
                $table->unsignedSmallInteger('max_attempts')->default(1);
                $table->boolean('allow_late')->default(false);
                $table->string('status', 20)->default('draft');
                $table->timestamps();
                $table->index(['school_id', 'course_id', 'status', 'due_at'], 'lms_assignment_scope_due_index');
            });
        }

        if (! Schema::hasTable('lms_assignment_submissions')) {
            Schema::create('lms_assignment_submissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('assignment_id')->constrained('lms_assignments')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->longText('body')->nullable();
                $table->string('file_path')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->boolean('is_late')->default(false);
                $table->decimal('score', 6, 2)->nullable();
                $table->text('feedback')->nullable();
                $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('graded_at')->nullable();
                $table->string('status', 20)->default('submitted');
                $table->timestamps();
                $table->index(['school_id', 'assignment_id', 'status'], 'lms_submission_scope_assignment_index');
                $table->index(['student_id', 'assignment_id'], 'lms_submission_student_assignment_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_assignment_submissions');
        Schema::dropIfExists('lms_assignments');
    }
};
