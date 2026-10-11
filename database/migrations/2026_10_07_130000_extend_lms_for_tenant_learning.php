<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['lms_courses', 'lms_course_enrollments', 'lms_lessons'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'school_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
                });
            }
        }

        $primarySchoolId = School::withoutGlobalScopes()
            ->where('is_default', true)
            ->value('id');

        if ($primarySchoolId) {
            DB::table('lms_courses')->whereNull('school_id')->update(['school_id' => $primarySchoolId]);
            DB::table('lms_course_enrollments')->whereNull('school_id')->update([
                'school_id' => DB::raw('(select school_id from lms_courses where lms_courses.id = lms_course_enrollments.course_id)'),
            ]);
            DB::table('lms_lessons')->whereNull('school_id')->update([
                'school_id' => DB::raw('(select school_id from lms_courses where lms_courses.id = lms_lessons.course_id)'),
            ]);
        }

        Schema::table('lms_courses', function (Blueprint $table): void {
            $table->dropUnique('lms_courses_code_unique');
            $table->unique(['school_id', 'code'], 'lms_courses_school_code_unique');
        });

        if (! Schema::hasTable('lms_modules')) {
            Schema::create('lms_modules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->unsignedInteger('position')->default(1);
                $table->string('status', 20)->default('draft');
                $table->timestamps();
                $table->index(['school_id', 'course_id', 'status', 'position'], 'lms_modules_scope_order_index');
            });
        }

        if (! Schema::hasColumn('lms_lessons', 'module_id')) {
            Schema::table('lms_lessons', function (Blueprint $table): void {
                $table->foreignId('module_id')->nullable()->after('course_id')->constrained('lms_modules')->nullOnDelete();
                $table->string('attachment_path')->nullable();
                $table->text('external_url')->nullable();
                $table->text('video_url')->nullable();
            });
        }

        if (! Schema::hasTable('lms_lesson_progress')) {
            Schema::create('lms_lesson_progress', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
                $table->foreignId('lesson_id')->constrained('lms_lessons')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['lesson_id', 'student_id'], 'lms_lesson_student_unique');
                $table->index(['school_id', 'student_id', 'course_id'], 'lms_progress_student_course_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_lesson_progress');
        Schema::table('lms_lessons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('module_id');
            $table->dropColumn(['attachment_path', 'external_url', 'video_url']);
        });
        Schema::dropIfExists('lms_modules');
        Schema::table('lms_courses', function (Blueprint $table): void {
            $table->dropUnique('lms_courses_school_code_unique');
            $table->unique('code');
        });
        foreach (['lms_courses', 'lms_course_enrollments', 'lms_lessons'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('school_id');
            });
        }
    }
};
