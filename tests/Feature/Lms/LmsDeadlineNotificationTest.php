<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LmsAssignment;
use App\Models\Student;
use App\Models\User;
use App\Services\RoleSeeder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LmsDeadlineNotificationTest extends LmsTestCase
{
    use RefreshDatabase;

    public function test_deadline_command_notifies_enrolled_students_once(): void
    {
        app(RoleSeeder::class)->run();
        Carbon::setTestNow(Carbon::parse('2026-10-08 09:00:00'));
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $teacher->assignRole('wali_kelas');
        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $studentUser->assignRole('siswa');
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        [$course, $classroomId, $yearId] = $this->courseFor($teacher);
        CourseEnrollment::create([
            'school_id' => $course->school_id, 'course_id' => $course->id,
            'student_id' => $student->id, 'status' => CourseEnrollment::ACTIVE, 'enrolled_at' => now(),
        ]);
        $assignment = $course->assignments()->create([
            'school_id' => $course->school_id, 'title' => 'Deadline dekat', 'due_at' => now()->addHours(6),
            'max_score' => 100, 'submission_type' => 'text', 'max_attempts' => 1,
            'status' => LmsAssignment::PUBLISHED,
        ]);

        Artisan::call('lms:notify-deadlines', ['--hours' => 24]);
        Artisan::call('lms:notify-deadlines', ['--hours' => 24]);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $studentUser->id]);
        Carbon::setTestNow();
    }

    private function courseFor(User $teacher): array
    {
        $schoolId = DB::table('schools')->where('is_default', true)->value('id');
        $suffix = strtolower(substr(md5((string) $teacher->id), 0, 8));
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => 'DEAD-'.$suffix, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $semesterId = DB::table('semesters')->insertGetId([
            'academic_year_id' => $yearId, 'name' => '1', 'label' => 'Ganjil', 'is_current' => true,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Deadline '.$suffix, 'code' => 'DED-'.$suffix, 'grade_level' => 'VII',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Deadline '.$suffix, 'code' => 'DEP-D-'.$suffix, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'academic_year_id' => $yearId, 'department_id' => $departmentId,
            'name' => 'VII D '.$suffix, 'level' => 'VII', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [Course::create([
            'school_id' => $schoolId, 'academic_year_id' => $yearId, 'semester_id' => $semesterId,
            'subject_id' => $subjectId, 'classroom_id' => $classroomId, 'teacher_id' => $teacher->id,
            'code' => 'COURSE-DEAD-'.$suffix, 'title' => 'Course Deadline '.$suffix, 'status' => Course::PUBLISHED,
        ]), $classroomId, $yearId];
    }
}
