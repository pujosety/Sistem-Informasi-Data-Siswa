<?php

namespace Tests\Feature\Lms;

use App\Services\RoleSeeder;
use App\Models\User;
use Tests\TestCase;

class TeacherCourseWorkspaceTest extends TestCase
{
    /** @test */
    public function a_staff_lms_user_can_open_the_teacher_course_workspace(): void
    {
        app(RoleSeeder::class)->run();
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $teacher->assignRole('wali_kelas');

        $this->actingAs($teacher)
            ->get(route('lms.teacher.courses.index'))
            ->assertOk()
            ->assertSee('Ruang Pembelajaran');
    }

    /** @test */
    public function a_student_cannot_open_the_teacher_course_workspace(): void
    {
        app(RoleSeeder::class)->run();
        $student = User::factory()->create(['email_verified_at' => now()]);
        $student->assignRole('siswa');

        $this->actingAs($student)
            ->get(route('lms.teacher.courses.index'))
            ->assertForbidden();
    }
}
