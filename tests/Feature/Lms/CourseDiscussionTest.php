<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseDiscussionTest extends LmsTestCase
{
    use RefreshDatabase;

    public function test_teacher_and_enrolled_student_can_discuss_inside_a_course(): void
    {
        app(RoleSeeder::class)->run();
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $teacher->assignRole('wali_kelas');
        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $studentUser->assignRole('siswa');
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $course = $this->courseFor($teacher);
        CourseEnrollment::create([
            'school_id' => $course->school_id, 'course_id' => $course->id,
            'student_id' => $student->id, 'status' => CourseEnrollment::ACTIVE, 'enrolled_at' => now(),
        ]);

        $this->actingAs($teacher)
            ->post(route('lms.teacher.courses.discussions.store', $course), [
                'body' => 'Silakan diskusikan materi Bab 1.',
            ])
            ->assertRedirect();
        $discussionId = DB::table('lms_discussions')->where('course_id', $course->id)->value('id');

        $this->actingAs($studentUser)
            ->post(route('lms.student.courses.discussions.store', $course), [
                'parent_id' => $discussionId,
                'body' => 'Saya sudah membaca materinya.',
            ])
            ->assertRedirect();

        $this->actingAs($studentUser)
            ->get(route('lms.student.courses.discussions.index', $course))
            ->assertOk()
            ->assertSee('Silakan diskusikan materi Bab 1.')
            ->assertSee('Saya sudah membaca materinya.');
        $this->assertDatabaseCount('lms_discussions', 2);
    }

    public function test_non_enrolled_student_cannot_view_course_discussion(): void
    {
        app(RoleSeeder::class)->run();
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $teacher->assignRole('wali_kelas');
        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $studentUser->assignRole('siswa');
        Student::factory()->create(['user_id' => $studentUser->id]);
        $course = $this->courseFor($teacher);

        $this->actingAs($studentUser)
            ->get(route('lms.student.courses.discussions.index', $course))
            ->assertNotFound();
    }

    private function courseFor(User $teacher): Course
    {
        $schoolId = DB::table('schools')->where('is_default', true)->value('id');
        $suffix = strtolower(substr(md5((string) $teacher->id), 0, 8));
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => 'DISC-'.$suffix, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $semesterId = DB::table('semesters')->insertGetId([
            'academic_year_id' => $yearId, 'name' => '1', 'label' => 'Ganjil', 'is_current' => true,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Discussion '.$suffix, 'code' => 'DISC-'.$suffix, 'grade_level' => 'VII',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Discussion '.$suffix, 'code' => 'DISC-D-'.$suffix,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'academic_year_id' => $yearId, 'department_id' => $departmentId,
            'name' => 'VII DISC '.$suffix, 'level' => 'VII', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Course::create([
            'school_id' => $schoolId, 'academic_year_id' => $yearId, 'semester_id' => $semesterId,
            'subject_id' => $subjectId, 'classroom_id' => $classroomId, 'teacher_id' => $teacher->id,
            'code' => 'COURSE-DISC-'.$suffix, 'title' => 'Course Discussion '.$suffix, 'status' => Course::PUBLISHED,
        ]);
    }
}
