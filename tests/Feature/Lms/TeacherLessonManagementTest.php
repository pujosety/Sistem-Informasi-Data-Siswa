<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\User;
use App\Services\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeacherLessonManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_teacher_can_create_a_draft_lesson_for_their_course(): void
    {
        app(RoleSeeder::class)->run();
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $teacher->assignRole('wali_kelas');
        $course = $this->courseFor($teacher);

        $this->actingAs($teacher)
            ->post(route('lms.teacher.lessons.store', $course), [
                'title' => 'Pengenalan Algoritma',
                'body' => 'Materi dasar algoritma dan langkah penyelesaian masalah.',
                'position' => 1,
            ])
            ->assertRedirect(route('lms.teacher.courses.show', $course))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lms_lessons', [
            'course_id' => $course->id,
            'title' => 'Pengenalan Algoritma',
            'status' => 'draft',
            'position' => 1,
        ]);
    }

    /** @test */
    public function a_teacher_can_publish_a_lesson_after_review(): void
    {
        app(RoleSeeder::class)->run();
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $teacher->assignRole('wali_kelas');
        $course = $this->courseFor($teacher);
        $lessonId = DB::table('lms_lessons')->insertGetId([
            'course_id' => $course->id,
            'title' => 'Latihan Mandiri',
            'body' => 'Kerjakan latihan berikut.',
            'position' => 1,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($teacher)
            ->post(route('lms.teacher.lessons.publish', [$course, $lessonId]))
            ->assertRedirect(route('lms.teacher.courses.show', $course));

        $this->assertDatabaseHas('lms_lessons', [
            'id' => $lessonId,
            'status' => 'published',
        ]);
    }

    private function courseFor(User $teacher): Course
    {
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $semesterId = DB::table('semesters')->insertGetId([
            'academic_year_id' => $yearId,
            'name' => '1',
            'label' => 'Ganjil',
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Informatika',
            'code' => 'IF-TEST',
            'grade_level' => 'VII',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Umum',
            'code' => 'UMUM-TEST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'academic_year_id' => $yearId,
            'department_id' => $departmentId,
            'name' => 'VII A',
            'level' => 'VII',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Course::create([
            'academic_year_id' => $yearId,
            'semester_id' => $semesterId,
            'subject_id' => $subjectId,
            'classroom_id' => $classroomId,
            'teacher_id' => $teacher->id,
            'code' => 'IF-VII-TEST',
            'title' => 'Informatika VII',
            'status' => Course::DRAFT,
        ]);
    }
}
