<?php

namespace Tests\Feature\Lms;

use App\Models\User;
use App\Services\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsMonitoringTest extends LmsTestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_lms_monitoring(): void
    {
        app(RoleSeeder::class)->run();
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('lms.monitoring'))
            ->assertOk()
            ->assertSee('Monitoring LMS')
            ->assertSee('Course aktif');
    }

    public function test_student_cannot_view_lms_monitoring(): void
    {
        app(RoleSeeder::class)->run();
        $student = User::factory()->create(['email_verified_at' => now()]);
        $student->assignRole('siswa');

        $this->actingAs($student)
            ->get(route('lms.monitoring'))
            ->assertForbidden();
    }
}
