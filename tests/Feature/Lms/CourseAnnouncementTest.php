<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SidaNotification;
use App\Services\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseAnnouncementTest extends LmsTestCase
{
    use RefreshDatabase;

    public function test_teacher_can_publish_a_course_announcement_and_student_can_read_it(): void
    {
        app(RoleSeeder::class)->run();
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

        $this->actingAs($teacher)
            ->post(route('lms.teacher.announcements.store', $course), [
                'title' => 'Jadwal belajar minggu ini',
                'body' => 'Silakan selesaikan Bab 1 sebelum Jumat.',
            ])
            ->assertRedirect(route('lms.teacher.courses.show', $course));

        $announcementId = DB::table('lms_announcements')->where('course_id', $course->id)->value('id');
        $this->actingAs($teacher)
            ->post(route('lms.teacher.announcements.publish', [$course, $announcementId]))
            ->assertRedirect(route('lms.teacher.courses.show', $course));

        $this->assertDatabaseHas('lms_announcements', [
            'id' => $announcementId, 'status' => 'published', 'title' => 'Jadwal belajar minggu ini',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $studentUser->id,
            'notifiable_type' => User::class,
            'type' => SidaNotification::class,
        ]);

        $this->actingAs($studentUser)
            ->get(route('lms.student.courses.show', $course))
            ->assertOk()
            ->assertSee('Jadwal belajar minggu ini')
            ->assertSee('Silakan selesaikan Bab 1 sebelum Jumat.');
    }

    public function test_student_cannot_publish_a_course_announcement(): void
    {
        app(RoleSeeder::class)->run();
        $student = User::factory()->create(['email_verified_at' => now()]);
        $student->assignRole('siswa');
        [$course] = $this->courseFor($student);

        $this->actingAs($student)
            ->post(route('lms.teacher.announcements.store', $course), [
                'title' => 'Tidak boleh', 'body' => 'Tidak boleh.',
            ])
            ->assertForbidden();
    }

    private function courseFor(User $teacher): array
    {
        $schoolId = DB::table('schools')->where('is_default', true)->value('id');
        $suffix = strtolower(substr(md5((string) $teacher->id), 0, 8));
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => 'ANN-'.$suffix, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $semesterId = DB::table('semesters')->insertGetId([
            'academic_year_id' => $yearId, 'name' => '1', 'label' => 'Ganjil', 'is_current' => true,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Announcement '.$suffix, 'code' => 'ANN-'.$suffix, 'grade_level' => 'VII',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Announcement '.$suffix, 'code' => 'ANN-D-'.$suffix,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $classroomId = DB::table('classes')->insertGetId([
            'academic_year_id' => $yearId, 'department_id' => $departmentId,
            'name' => 'VII ANN '.$suffix, 'level' => 'VII', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [Course::create([
            'school_id' => $schoolId, 'academic_year_id' => $yearId, 'semester_id' => $semesterId,
            'subject_id' => $subjectId, 'classroom_id' => $classroomId, 'teacher_id' => $teacher->id,
            'code' => 'COURSE-ANN-'.$suffix, 'title' => 'Course Announcement '.$suffix, 'status' => Course::PUBLISHED,
        ]), $classroomId, $yearId];
    }
}
