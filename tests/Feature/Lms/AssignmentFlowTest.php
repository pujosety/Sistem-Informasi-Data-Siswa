<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LmsAssignment;
use App\Models\LmsAssignmentSubmission;
use App\Models\Student;
use App\Models\User;
use App\Services\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssignmentFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_student_can_submit_and_teacher_can_grade_an_assignment(): void
    {
        app(RoleSeeder::class)->run();
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $teacher->assignRole('wali_kelas');
        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $studentUser->assignRole('siswa');
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $course = $this->courseFor($teacher, $student);
        CourseEnrollment::create([
            'school_id' => $course->school_id, 'course_id' => $course->id, 'student_id' => $student->id,
            'status' => CourseEnrollment::ACTIVE, 'enrolled_at' => now(),
        ]);

        $this->actingAs($teacher)
            ->post(route('lms.teacher.assignments.store', $course), [
                'title' => 'Latihan Persamaan Linear',
                'description' => 'Kerjakan dengan langkah yang jelas.',
                'max_score' => 100,
                'submission_type' => 'text',
                'max_attempts' => 1,
                'allow_late' => 0,
            ])
            ->assertRedirect();

        $assignment = LmsAssignment::firstOrFail();
        $this->actingAs($teacher)
            ->post(route('lms.teacher.assignments.publish', [$course, $assignment]))
            ->assertRedirect();

        $this->actingAs($studentUser)
            ->post(route('lms.student.assignments.submit', [$course, $assignment]), [
                'body' => 'Jawaban siswa.',
            ])
            ->assertRedirect();

        $submission = LmsAssignmentSubmission::firstOrFail();
        $this->actingAs($teacher)
            ->put(route('lms.teacher.submissions.grade', [$course, $assignment, $submission]), [
                'score' => 88,
                'feedback' => 'Langkah sudah benar.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lms_assignment_submissions', [
            'id' => $submission->id,
            'score' => 88,
            'status' => LmsAssignmentSubmission::GRADED,
            'feedback' => 'Langkah sudah benar.',
        ]);
    }

    private function courseFor(User $teacher, Student $student): Course
    {
        $schoolId = DB::table('schools')->where('is_default', true)->value('id') ?? DB::table('schools')->value('id');
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => 'ASSIGN-'.$student->id, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $semesterId = DB::table('semesters')->insertGetId([
            'academic_year_id' => $yearId, 'name' => '1', 'label' => 'Ganjil', 'is_current' => true,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'IPA Assignment '.$student->id, 'code' => 'IPA-A-'.$student->id, 'grade_level' => 'VII',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Assignment '.$student->id, 'code' => 'DEP-A-'.$student->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'academic_year_id' => $yearId, 'department_id' => $departmentId, 'name' => 'VII A '.$student->id,
            'level' => 'VII', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Course::create([
            'school_id' => $schoolId, 'academic_year_id' => $yearId, 'semester_id' => $semesterId,
            'subject_id' => $subjectId, 'classroom_id' => $classroomId, 'teacher_id' => $teacher->id,
            'code' => 'IPA-A-'.$student->id, 'title' => 'IPA Assignment '.$student->id, 'status' => Course::PUBLISHED,
        ]);
    }
}
