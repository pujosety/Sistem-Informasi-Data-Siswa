<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\User;
use App\Services\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeacherModuleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_teacher_can_create_a_module_and_assign_a_lesson_to_it(): void
    {
        app(RoleSeeder::class)->run();
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $teacher->assignRole('wali_kelas');
        $course = $this->courseFor($teacher);

        $this->actingAs($teacher)
            ->post(route('lms.teacher.modules.store', $course), [
                'title' => 'Bab 1: Algoritma',
                'description' => 'Konsep dasar algoritma.',
                'position' => 1,
            ])
            ->assertRedirect(route('lms.teacher.courses.show', $course));

        $moduleId = DB::table('lms_modules')->where('course_id', $course->id)->value('id');

        $this->actingAs($teacher)
            ->post(route('lms.teacher.lessons.store', $course), [
                'module_id' => $moduleId,
                'title' => 'Pengenalan Algoritma',
                'body' => 'Materi dasar.',
                'position' => 1,
            ])
            ->assertRedirect(route('lms.teacher.courses.show', $course));

        $this->assertDatabaseHas('lms_lessons', [
            'course_id' => $course->id,
            'module_id' => $moduleId,
            'title' => 'Pengenalan Algoritma',
        ]);
    }

    public function test_a_student_cannot_create_a_module(): void
    {
        app(RoleSeeder::class)->run();
        $student = User::factory()->create(['email_verified_at' => now()]);
        $student->assignRole('siswa');
        $course = $this->courseFor($student);

        $this->actingAs($student)
            ->post(route('lms.teacher.modules.store', $course), [
                'title' => 'Tidak boleh',
                'position' => 1,
            ])
            ->assertForbidden();
    }

    private function courseFor(User $teacher): Course
    {
        $schoolId = DB::table('schools')->where('is_default', true)->value('id');
        $suffix = strtolower(substr(md5((string) $teacher->id), 0, 8));
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => 'MODULE-'.$suffix, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $semesterId = DB::table('semesters')->insertGetId([
            'academic_year_id' => $yearId, 'name' => '1', 'label' => 'Ganjil', 'is_current' => true,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Informatika '.$suffix, 'code' => 'MOD-'.$suffix, 'grade_level' => 'VII',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Umum '.$suffix, 'code' => 'DPT-'.$suffix, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'academic_year_id' => $yearId, 'department_id' => $departmentId,
            'name' => 'VII '.$suffix, 'level' => 'VII', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Course::create([
            'school_id' => $schoolId, 'academic_year_id' => $yearId, 'semester_id' => $semesterId,
            'subject_id' => $subjectId, 'classroom_id' => $classroomId, 'teacher_id' => $teacher->id,
            'code' => 'COURSE-'.$suffix, 'title' => 'Course '.$suffix, 'status' => Course::DRAFT,
        ]);
    }
}
