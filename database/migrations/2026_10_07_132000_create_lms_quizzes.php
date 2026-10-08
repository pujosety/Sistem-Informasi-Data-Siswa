<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_quizzes')) {
            Schema::create('lms_quizzes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->unsignedSmallInteger('duration_minutes')->nullable();
                $table->unsignedSmallInteger('attempt_limit')->default(1);
                $table->decimal('passing_score', 6, 2)->default(70);
                $table->boolean('shuffle_questions')->default(false);
                $table->boolean('show_results')->default(true);
                $table->string('status', 20)->default('draft');
                $table->timestamps();
                $table->index(['school_id', 'course_id', 'status', 'due_at'], 'lms_quiz_scope_due_index');
            });
        }

        if (! Schema::hasTable('lms_quiz_questions')) {
            Schema::create('lms_quiz_questions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('quiz_id')->constrained('lms_quizzes')->cascadeOnDelete();
                $table->string('type', 30);
                $table->longText('prompt');
                $table->json('options')->nullable();
                $table->text('correct_answer')->nullable();
                $table->decimal('points', 6, 2)->default(1);
                $table->unsignedInteger('position')->default(1);
                $table->timestamps();
                $table->index(['school_id', 'quiz_id', 'position'], 'lms_question_scope_order_index');
            });
        }

        if (! Schema::hasTable('lms_quiz_attempts')) {
            Schema::create('lms_quiz_attempts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('quiz_id')->constrained('lms_quizzes')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->decimal('score', 6, 2)->nullable();
                $table->json('answers')->nullable();
                $table->string('status', 20)->default('in_progress');
                $table->timestamps();
                $table->index(['school_id', 'quiz_id', 'student_id'], 'lms_attempt_scope_student_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_quiz_attempts');
        Schema::dropIfExists('lms_quiz_questions');
        Schema::dropIfExists('lms_quizzes');
    }
};
