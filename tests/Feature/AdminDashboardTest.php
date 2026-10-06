<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin dashboard renders, and renders the brief's widgets.
 *
 * WHY A DASHBOARD NEEDS ITS OWN TEST
 *
 * A dashboard is the one page that is never linked to from anywhere, so nothing
 * else in the suite exercises it. It is also where a controller change is most
 * likely to break something quietly: a new `view()` variable that the Blade
 * never reads is invisible, and a variable the Blade reads that the controller
 * stopped passing is a 500 on the first page an admin opens each morning.
 *
 * The brief names the widgets explicitly (students, teachers, classes,
 * attendance, registrations, verification, alerts), so each one is asserted
 * here by what it renders rather than by what it was called.
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    private function seedSchool(int $students = 3): void
    {
        $year = $this->seedAcademicYear();

        $class = SchoolClass::create([
            'name' => 'XII IPA 1',
            'level' => 'XII',
            'academic_year_id' => $year->id,
            'department_id' => Department::firstOrCreate(['name' => 'IPA'], ['code' => 'IPA'])->id,
        ]);

        Employee::create([
            'user_id' => $this->admin->id,
            'employee_number' => 'EMP-001',
            'employment_type' => 'teacher',
            'status' => 'active',
        ]);

        foreach (range(1, $students) as $n) {
            $user = User::factory()->create(['name' => "Siswa {$n}"]);

            $student = Student::create([
                'user_id' => $user->id,
                'full_name' => "Siswa {$n}",
                'nisn' => '3200'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
                'gender' => $n % 2 === 0 ? 'P' : 'L',
                'class_id' => $class->id,
                'academic_year_id' => $year->id,
            ]);

            Registration::create([
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'status' => $n === 1 ? Registration::STATUS_PENDING : Registration::STATUS_VERIFIED,
            ]);
        }
    }

    /** @test */
    public function the_admin_dashboard_renders_for_an_admin(): void
    {
        $this->seedSchool();

        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk();
    }

    /** @test */
    public function the_dashboard_shows_the_school_headline_counts(): void
    {
        // Brief §9 names these three explicitly. They were absent entirely:
        // StatsService had registration aggregates and nothing else.
        $this->seedSchool();

        $html = $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Siswa Aktif', $html);
        $this->assertStringContainsString('Tenaga Pendidikan', $html);
        $this->assertStringContainsString('Kelas Aktif', $html);
    }

    /** @test */
    public function the_dashboard_explains_school_pulse_and_actionable_analytics(): void
    {
        $this->seedSchool();

        $html = $this->actingAs($this->admin)
            ->get('/admin/dashboard?range=30d&attendance_threshold=75')
            ->assertOk()
            ->getContent();

        foreach ([
            'School Pulse', 'Attendance Analytics', 'Academic Performance',
            'Student Attention Signals', 'PPDB Funnel', 'LMS Analytics',
            'Needs Attention', 'Target', 'Review Students',
        ] as $marker) {
            $this->assertStringContainsString($marker, $html, "Dashboard marker missing: {$marker}");
        }
    }

    /** @test */
    public function the_dashboard_does_not_invent_metrics_for_unavailable_modules(): void
    {
        $this->seedSchool();

        $html = $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Data belum tersedia', $html);
        $this->assertStringNotContainsString('Rp 1.000.000', $html);
    }

    /** @test */
    public function the_dashboard_keeps_the_actionable_counters_above_the_widget_grid(): void
    {
        // The pending-verification count is the reason an admin opens this page.
        // A user who can drag a widget must not be able to push it below the
        // fold, which is why those cards sit outside the reorderable area.
        $this->seedSchool();

        $html = $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $counterPosition = strpos($html, 'Perlu Tindakan Anda');
        $gridPosition = strpos($html, 'dashboardGrid');

        $this->assertNotFalse($counterPosition, 'The actionable counter is gone.');
        $this->assertNotFalse($gridPosition, 'The widget grid is gone.');
        $this->assertLessThan(
            $gridPosition,
            $counterPosition,
            'The actionable counter ended up inside or after the reorderable grid.'
        );
    }

    /** @test */
    public function the_dashboard_renders_the_gender_donut_when_gender_data_exists(): void
    {
        $this->seedSchool(4);

        $html = $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Distribusi Jenis Kelamin', $html);
        $this->assertStringContainsString('stroke-dasharray', $html, 'The donut rendered no segments.');
    }

    /**
     * @test
     */
    public function the_dashboard_renders_no_empty_panel_when_a_widget_has_no_data(): void
    {
        // A bordered box with nothing in it reads as something failed to load.
        $this->seedSchool();

        ActivityLog::query()->delete();

        $html = $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'Aktivitas Terakhir',
            $html,
            'An activity widget with no entries rendered an empty panel instead of being omitted.'
        );
    }

    /** @test */
    public function the_dashboard_shows_the_verification_queue(): void
    {
        $this->seedSchool();

        $html = $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Antrean Verifikasi', $html);
        $this->assertStringContainsString('Siswa 1', $html, 'The pending student is missing from the queue.');
    }

    /** @test */
    public function the_dashboard_is_not_reachable_by_a_student(): void
    {
        $student = User::factory()->create();
        $student->assignRole('siswa');

        $this->actingAs($student)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    /** @test */
    public function the_headline_counts_follow_the_selected_academic_year(): void
    {
        $this->seedSchool(2);

        // A count that ignores the year filter reports archived years as if
        // they were running, which is a number no school wants on a dashboard.
        $html = $this->actingAs($this->admin)
            ->get('/admin/dashboard?academic_year_id='.AcademicYear::first()->id)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Tahun ajaran terpilih', $html);
    }
}