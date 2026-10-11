<?php

namespace Tests\Feature\Lms;

use App\Models\User;
use App\Services\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseConsistencyTest extends LmsTestCase
{
    use RefreshDatabase;

    public function test_course_creation_rejects_semester_and_classroom_from_another_academic_year(): void
    {
        app(RoleSeeder::class)->run();
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $teacher->assignRole('wali_kelas');
        $schoolId = DB::table('schools')->where('is_default', true)->value('id');
        $yearOne = DB::table('academic_years')->insertGetId([
            'name' => 'CONSIST-1', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $yearTwo = DB::table('academic_years')->insertGetId([
            'name' => 'CONSIST-2', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $semesterTwo = DB::table('semesters')->insertGetId([
            'academic_year_id' => $yearTwo, 'name' => '1', 'label' => 'Ganjil', 'is_current' => true,
            'start_date' => '2027-07-01', 'end_date' => '2027-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Consistency Subject', 'code' => 'CONSIST-SUBJ', 'grade_level' => 'VII',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Consistency Department', 'code' => 'CONSIST-DEPT', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $classroomTwo = DB::table('classes')->insertGetId([
            'academic_year_id' => $yearTwo, 'department_id' => $departmentId,
            'name' => 'VII Consistency', 'level' => 'VII', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($teacher)
            ->from(route('lms.teacher.courses.create'))
            ->post(route('lms.teacher.courses.store'), [
                'academic_year_id' => $yearOne,
                'semester_id' => $semesterTwo,
                'subject_id' => $subjectId,
                'classroom_id' => $classroomTwo,
                'code' => 'CONSIST-COURSE',
                'title' => 'Course Invalid Context',
            ])
            ->assertRedirect(route('lms.teacher.courses.create'))
            ->assertSessionHasErrors(['semester_id', 'classroom_id']);

        $this->assertDatabaseMissing('lms_courses', ['code' => 'CONSIST-COURSE']);
    }
}
