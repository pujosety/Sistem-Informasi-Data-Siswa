<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeacherEnrollmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_teacher_can_enroll_a_student_from_the_course_classroom(): void
    {
        app(RoleSeeder::class)->run();
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $teacher->assignRole('wali_kelas');
        [$course, $classroomId, $yearId] = $this->courseFor($teacher);
        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $student = Student::factory()->create(['user_id' => $studentUser->id, 'full_name' => 'Siswa Roster']);
        Enrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $yearId,
            'classroom_id' => $classroomId,
            'status' => Enrollment::ACTIVE,
            'started_at' => now()->toDateString(),
        ]);

        $this->actingAs($teacher)
            ->post(route('lms.teacher.enrollments.store', $course), ['student_id' => $student->id])
            ->assertRedirect(route('lms.teacher.enrollments.index', $course));

        $this->assertDatabaseHas('lms_course_enrollments', [
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => CourseEnrollment::ACTIVE,
        ]);
    }

    public function test_a_student_cannot_manage_course_enrollments(): void
    {
        app(RoleSeeder::class)->run();
        $student = User::factory()->create(['email_verified_at' => now()]);
        $student->assignRole('siswa');
        [$course] = $this->courseFor($student);

        $this->actingAs($student)
            ->get(route('lms.teacher.enrollments.index', $course))
            ->assertForbidden();
    }

    private function courseFor(User $teacher): array
    {
        $schoolId = DB::table('schools')->where('is_default', true)->value('id');
        $suffix = strtolower(substr(md5((string) $teacher->id), 0, 8));
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => 'ENROLL-'.$suffix, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $semesterId = DB::table('semesters')->insertGetId([
            'academic_year_id' => $yearId, 'name' => '1', 'label' => 'Ganjil', 'is_current' => true,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Informatika '.$suffix, 'code' => 'ENR-'.$suffix, 'grade_level' => 'VII',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Umum '.$suffix, 'code' => 'DEP-'.$suffix, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'academic_year_id' => $yearId, 'department_id' => $departmentId,
            'name' => 'VII '.$suffix, 'level' => 'VII', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $course = Course::create([
            'school_id' => $schoolId, 'academic_year_id' => $yearId, 'semester_id' => $semesterId,
            'subject_id' => $subjectId, 'classroom_id' => $classroomId, 'teacher_id' => $teacher->id,
            'code' => 'COURSE-ENR-'.$suffix, 'title' => 'Course Enrollment '.$suffix, 'status' => Course::DRAFT,
        ]);

        return [$course, $classroomId, $yearId];
    }
}
