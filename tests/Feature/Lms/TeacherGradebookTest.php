<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Enrollment;
use App\Models\LmsAssignment;
use App\Models\LmsQuiz;
use App\Models\Student;
use App\Models\User;
use App\Services\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeacherGradebookTest extends LmsTestCase
{
    use RefreshDatabase;

    public function test_a_teacher_can_view_assignment_and_quiz_scores_for_active_course_students(): void
    {
        app(RoleSeeder::class)->run();
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $teacher->assignRole('wali_kelas');
        [$course, $classroomId, $yearId] = $this->courseFor($teacher);

        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $student = Student::factory()->create(['user_id' => $studentUser->id, 'full_name' => 'Siswa Gradebook']);
        Enrollment::create([
            'student_id' => $student->id, 'academic_year_id' => $yearId, 'classroom_id' => $classroomId,
            'status' => Enrollment::ACTIVE, 'started_at' => now()->toDateString(),
        ]);
        CourseEnrollment::create([
            'school_id' => $course->school_id, 'course_id' => $course->id, 'student_id' => $student->id,
            'status' => CourseEnrollment::ACTIVE, 'enrolled_at' => now(),
        ]);

        $assignment = $course->assignments()->create([
            'school_id' => $course->school_id, 'title' => 'Tugas Algoritma', 'max_score' => 100,
            'submission_type' => 'text', 'max_attempts' => 1, 'status' => LmsAssignment::PUBLISHED,
        ]);
        $assignment->submissions()->create([
            'school_id' => $course->school_id, 'student_id' => $student->id, 'body' => 'Jawaban',
            'submitted_at' => now(), 'score' => 85, 'graded_by' => $teacher->id,
            'graded_at' => now(), 'status' => 'graded',
        ]);

        $quiz = $course->quizzes()->create([
            'school_id' => $course->school_id, 'title' => 'Kuis Bab 1', 'attempt_limit' => 1,
            'passing_score' => 70, 'status' => LmsQuiz::PUBLISHED,
        ]);
        $quiz->attempts()->create([
            'school_id' => $course->school_id, 'student_id' => $student->id,
            'started_at' => now(), 'submitted_at' => now(), 'score' => 90,
            'answers' => [], 'status' => 'submitted',
        ]);

        $this->actingAs($teacher)
            ->get(route('lms.teacher.gradebook.index', $course))
            ->assertOk()
            ->assertSee('Siswa Gradebook')
            ->assertSee('85')
            ->assertSee('90');
    }

    public function test_a_student_cannot_view_the_teacher_gradebook(): void
    {
        app(RoleSeeder::class)->run();
        $student = User::factory()->create(['email_verified_at' => now()]);
        $student->assignRole('siswa');
        [$course] = $this->courseFor($student);

        $this->actingAs($student)
            ->get(route('lms.teacher.gradebook.index', $course))
            ->assertForbidden();
    }

    private function courseFor(User $teacher): array
    {
        $schoolId = DB::table('schools')->where('is_default', true)->value('id');
        $suffix = strtolower(substr(md5((string) $teacher->id), 0, 8));
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => 'GRADE-'.$suffix, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $semesterId = DB::table('semesters')->insertGetId([
            'academic_year_id' => $yearId, 'name' => '1', 'label' => 'Ganjil', 'is_current' => true,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Informatika '.$suffix, 'code' => 'GRD-'.$suffix, 'grade_level' => 'VII',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Umum '.$suffix, 'code' => 'GRD-D-'.$suffix, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'academic_year_id' => $yearId, 'department_id' => $departmentId,
            'name' => 'VII '.$suffix, 'level' => 'VII', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [Course::create([
            'school_id' => $schoolId, 'academic_year_id' => $yearId, 'semester_id' => $semesterId,
            'subject_id' => $subjectId, 'classroom_id' => $classroomId, 'teacher_id' => $teacher->id,
            'code' => 'COURSE-GRD-'.$suffix, 'title' => 'Course Gradebook '.$suffix, 'status' => Course::PUBLISHED,
        ]), $classroomId, $yearId];
    }
}
