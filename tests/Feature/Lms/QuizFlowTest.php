<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LmsQuiz;
use App\Models\LmsQuizAttempt;
use App\Models\LmsQuizQuestion;
use App\Models\Student;
use App\Models\User;
use App\Services\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function an_enrolled_student_can_take_a_published_quiz_and_receive_a_score(): void
    {
        app(RoleSeeder::class)->run();
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $teacher->assignRole('wali_kelas');
        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $studentUser->assignRole('siswa');
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $course = $this->courseFor($teacher, $student);
        CourseEnrollment::create(['school_id' => $course->school_id, 'course_id' => $course->id, 'student_id' => $student->id, 'status' => 'active', 'enrolled_at' => now()]);

        $this->actingAs($teacher)->post(route('lms.teacher.quizzes.store', $course), [
            'title' => 'Kuis Bab 1', 'description' => 'Pilih jawaban yang tepat.', 'attempt_limit' => 1, 'passing_score' => 70,
        ])->assertRedirect();
        $quiz = LmsQuiz::firstOrFail();
        $this->actingAs($teacher)->post(route('lms.teacher.quizzes.questions.store', [$course, $quiz]), [
            'type' => 'multiple_choice', 'prompt' => '2 + 2 = ?', 'options' => ['3', '4', '5'], 'correct_answer' => '4', 'points' => 100,
        ])->assertRedirect();
        $this->actingAs($teacher)->post(route('lms.teacher.quizzes.publish', [$course, $quiz]))->assertRedirect();

        $this->actingAs($studentUser)->get(route('lms.student.quizzes.show', [$course, $quiz]))->assertOk()->assertSee('2 + 2');
        $this->actingAs($studentUser)->post(route('lms.student.quizzes.submit', [$course, $quiz]), [
            'answers' => ['1' => '4'],
        ])->assertRedirect();

        $this->assertDatabaseHas('lms_quiz_attempts', ['quiz_id' => $quiz->id, 'student_id' => $student->id, 'score' => 100, 'status' => 'submitted']);
    }

    private function courseFor(User $teacher, Student $student): Course
    {
        $schoolId = DB::table('schools')->where('is_default', true)->value('id') ?? DB::table('schools')->value('id');
        $yearId = DB::table('academic_years')->insertGetId(['name' => 'QUIZ-'.$student->id, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $semesterId = DB::table('semesters')->insertGetId(['academic_year_id' => $yearId, 'name' => '1', 'label' => 'Ganjil', 'is_current' => true, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now()]);
        $subjectId = DB::table('subjects')->insertGetId(['name' => 'Quiz Subject '.$student->id, 'code' => 'QUIZ-'.$student->id, 'grade_level' => 'VII', 'created_at' => now(), 'updated_at' => now()]);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Quiz Department '.$student->id, 'code' => 'QUIZD-'.$student->id, 'created_at' => now(), 'updated_at' => now()]);
        $classroomId = DB::table('classes')->insertGetId(['academic_year_id' => $yearId, 'department_id' => $departmentId, 'name' => 'VII Q '.$student->id, 'level' => 'VII', 'created_at' => now(), 'updated_at' => now()]);
        return Course::create(['school_id' => $schoolId, 'academic_year_id' => $yearId, 'semester_id' => $semesterId, 'subject_id' => $subjectId, 'classroom_id' => $classroomId, 'teacher_id' => $teacher->id, 'code' => 'QUIZ-'.$student->id, 'title' => 'Quiz Course '.$student->id, 'status' => Course::PUBLISHED]);
    }
}
