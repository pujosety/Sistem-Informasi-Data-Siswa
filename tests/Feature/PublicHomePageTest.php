<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\RoleSeeder;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the public school website.
 *
 * THE PROPERTY THAT MATTERS
 *
 * This page is reachable without a session, so anything it renders is
 * published. §10 requires that internal data never becomes public by
 * accident and that student data is private by default. That makes "what does
 * the anonymous response body contain" a security question, not a
 * presentation one — which is why the assertions below check the RESPONSE
 * TEXT for real student values rather than checking that a list component is
 * absent from the template.
 *
 * Checking the view source would pass while a view variable leaked the same
 * value into a data attribute or a script tag. Reading what the server actually
 * sent does not have that gap.
 */
class PublicHomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seedSettings();
    }

    private function seedSettings(): void
    {
        app(SettingsService::class)->setMany([
            'school.name' => 'SMA Negeri 1 Bogor',
            'school.npsn' => '20219876',
            'school.city' => 'Bogor',
            'school.email' => 'info@sma1bogor.sch.id',
        ]);
    }

    /**
     * @test
     */
    public function test_an_anonymous_visitor_gets_the_page_not_a_login_redirect(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('public.home');
        $this->assertStringContainsString('SMA Negeri 1 Bogor', $response->getContent());
    }

    /**
     * @test
     */
    public function test_it_never_renders_a_student_name_nisn_nik_or_guardian(): void
    {
        // Real records with unmistakable values. Anything echoed back in the
        // anonymous response is a disclosure, whatever the reason.
        $user = User::factory()->create(['name' => 'Budi Santoso']);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'Budi Santoso',
            'nisn' => '0091234567',
            'nik' => '3201234567890001',
            'phone' => '081298765432',
            'address' => 'Jl. Rahasia No. 7',
        ]);

        $body = $this->get('/')->getContent();

        foreach ([
            'Budi Santoso'   => $student->full_name,
            '0091234567'     => $student->nisn,
            '3201234567890001' => $student->nik,
            '081298765432'   => $student->phone,
            'Jl. Rahasia'    => 'the home address',
        ] as $label => $value) {
            $this->assertStringNotContainsString(
                (string) $value,
                $body,
                "The public page rendered {$label}."
            );
        }
    }

    /**
     * @test
     */
    public function test_it_publishes_counts_without_publishing_records(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01',
            'end_date' => '2027-06-30', 'is_active' => true, 'status' => AcademicYear::ACTIVE,
        ]);

        $department = Department::create(['name' => 'IPA', 'code' => 'IPA']);
        SchoolClass::create([
            'academic_year_id' => $year->id, 'department_id' => $department->id,
            'name' => 'X IPA 1', 'code' => 'X-IPA-1', 'level' => 'X',
            'capacity' => 36, 'status' => SchoolClass::ACTIVE,
        ]);
        Subject::create(['name' => 'Matematika', 'code' => 'MTK', 'grade_level' => 'X']);

        Student::factory()->count(3)->create();

        $response = $this->get('/');
        $response->assertOk();

        // A count is not a row: 3 discloses no name, no NIK, no guardian.
        $this->assertStringContainsString('3', $response->getContent());
    }

    /**
     * @test
     */
    public function test_it_needs_no_session_and_no_account(): void
    {
        $this->assertGuest();

        $this->get('/')->assertOk();
    }

    /**
     * @test
     */
    public function test_it_offers_the_portal_and_registration(): void
    {
        $body = $this->get('/')->getContent();

        // The two doors in. Registration is public; the portal needs a login.
        $this->assertStringContainsString(route('login'), $body);
        $this->assertStringContainsString(route('register'), $body);
    }

    /**
     * @test
     */
    public function test_it_degrades_rather_than_failing_on_an_empty_database(): void
    {
        // No academic years at all. A public page that 500s on a fresh install
        // is worse than one that shows a school with no figures.
        $this->assertSame(0, AcademicYear::count());

        $response = $this->get('/');

        $response->assertOk();
        $this->assertStringContainsString('SMA Negeri 1 Bogor', $response->getContent());
    }

    /**
     * @test
     */
    public function test_a_logged_in_user_still_sees_the_public_page_at_root(): void
    {
        // The root is public, so authentication must not change what it serves.
        // A staff member who typed the site URL should get the website, not a
        // surprise dashboard.
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user->refresh())->get('/');

        $response->assertOk();
        $response->assertViewIs('public.home');
    }

    /**
     * @test
     */
    public function test_the_login_page_is_untouched(): void
    {
        // The root used to redirect here. It still exists, and it still works —
        // the change added a page, it did not move anything.
        $this->get('/login')->assertOk();
    }
}
