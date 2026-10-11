<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentLearningFlowTest extends LmsTestCase
{
    use RefreshDatabase;

    /** @test */
    public function an_enrolled_student_can_open_their_course_and_complete_a_published_lesson(): void
    {
        app(RoleSeeder::class)->run();
        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $studentUser->assignRole('siswa');
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $course = $this->courseFor($student);
        $lesson = $course->lessons()->create([
            'title' => 'Bab 1',
            'body' => 'Materi pertama.',
            'position' => 1,
            'status' => Lesson::PUBLISHED,
            'school_id' => $course->school_id,
        ]);
        CourseEnrollment::create([
            'school_id' => $course->school_id,
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => CourseEnrollment::ACTIVE,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($studentUser)
            ->get(route('lms.student.dashboard'))
            ->assertOk()
            ->assertSee($course->title);

        $this->actingAs($studentUser)
            ->get(route('lms.student.courses.show', $course))
            ->assertOk()
            ->assertSee($lesson->title);

        $this->actingAs($studentUser)
            ->post(route('lms.student.lessons.complete', [$course, $lesson]))
            ->assertRedirect(route('lms.student.courses.show', $course));

        $this->assertDatabaseHas('lms_lesson_progress', [
            'school_id' => $course->school_id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
        ]);
    }

    /** @test */
    public function a_student_cannot_open_an_unenrolled_course(): void
    {
        app(RoleSeeder::class)->run();
        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $studentUser->assignRole('siswa');
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $course = $this->courseFor($student);

        $this->actingAs($studentUser)
            ->get(route('lms.student.courses.show', $course))
            ->assertForbidden();
    }

    private function courseFor(Student $student): Course
    {
        $schoolId = School::withoutGlobalScopes()->where('is_default', true)->value('id')
            ?? School::withoutGlobalScopes()->value('id');
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => 'LMS-'.strtolower(substr(md5((string) $student->id), 0, 8)),
            'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $semesterId = DB::table('semesters')->insertGetId([
            'academic_year_id' => $yearId, 'name' => '1', 'label' => 'Ganjil', 'is_current' => true,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Matematika LMS', 'code' => 'MAT-LMS-'.$student->id, 'grade_level' => 'VII',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Umum LMS '.$student->id, 'code' => 'UMUM-LMS-'.$student->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'academic_year_id' => $yearId, 'department_id' => $departmentId,
            'name' => 'VII LMS '.$student->id, 'level' => 'VII', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Course::create([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId, 'semester_id' => $semesterId, 'subject_id' => $subjectId,
            'classroom_id' => $classroomId, 'teacher_id' => User::factory()->create()->id,
            'code' => 'MAT-LMS-'.$student->id, 'title' => 'Matematika LMS '.$student->id,
            'status' => Course::PUBLISHED,
        ]);
    }
}
