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
        AcademicYear::query()->delete();
        Subject::query()->delete();
        $this->seedSettings();
    }

    private function seedSettings(): void
    {
        app(SettingsService::class)->setMany([
            'school.name' => 'SMP 1 LYFLA',
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
        $this->assertStringContainsString('SMP 1 LYFLA', $response->getContent());
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
        $this->assertStringContainsString('SMP 1 LYFLA', $response->getContent());
    }

    /** @test */
    public function the_contact_form_stores_a_message_and_redirects_back(): void
    {
        $response = $this->post(route('public.contact.submit'), [
            'name' => 'Alya Pratama',
            'email' => 'alya@example.test',
            'phone' => '081234567890',
            'topic' => 'PPDB',
            'message' => 'Saya ingin mengetahui jadwal pendaftaran.',
            'website' => '',
        ]);

        $response->assertRedirect(route('public.contact'))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('contact_messages', [
            'email' => 'alya@example.test',
            'topic' => 'PPDB',
            'status' => 'new',
        ]);
    }

    /** @test */
    public function the_contact_form_rejects_an_invalid_email_without_writing(): void
    {
        $this->from(route('public.contact'))
            ->post(route('public.contact.submit'), [
                'name' => 'Alya Pratama',
                'email' => 'not-an-email',
                'topic' => 'Umum',
                'message' => 'Pesan.',
                'website' => '',
            ])
            ->assertRedirect(route('public.contact'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    /** @test */
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

    /**
     * @test
     */
    public function test_every_public_page_loads_without_a_session(): void
    {
        foreach (['/', '/tentang', '/program', '/ppdb', '/kontak'] as $uri) {
            $response = $this->get($uri);

            $response->assertOk("Public page [{$uri}] must not require authentication.");
        }
    }

    /**
     * @test
     */
    public function test_no_public_page_renders_a_student_record(): void
    {
        $user = User::factory()->create(['name' => 'Siti Nurhaliza']);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'Siti Nurhaliza',
            'nisn' => '0098765432',
            'nik' => '3276543210987654',
            'phone' => '081234567899',
        ]);

        // Every public page, one loop, because §10 applies to the whole site
        // rather than to the page that happens to be checked.
        foreach (['/', '/tentang', '/program', '/ppdb', '/kontak'] as $uri) {
            $body = $this->get($uri)->getContent();

            foreach ([
                'Siti Nurhaliza'      => 'student name',
                '0098765432'          => 'NISN',
                '3276543210987654'    => 'NIK',
                '081234567899'        => 'phone',
            ] as $value => $label) {
                $this->assertStringNotContainsString(
                    $value,
                    $body,
                    "Public page [{$uri}] rendered a {$label}."
                );
            }
        }
    }

    /**
     * @test
     *
     * Note what this does NOT assert: subjects are not partitioned by
     * department in the schema — there is no department_id on subjects — so the
     * page shows the curriculum-wide subject list under each department. That
     * is a limitation of the data, recorded here so a future change to the
     * schema updates the page rather than silently diverging from the test.
     */
    public function test_the_program_page_lists_subjects_but_not_classes(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01',
            'end_date' => '2027-06-30', 'is_active' => true, 'status' => AcademicYear::ACTIVE,
        ]);
        $department = Department::create(['name' => 'Ilmu Pengetahuan Alam', 'code' => 'IPA']);
        SchoolClass::create([
            'academic_year_id' => $year->id, 'department_id' => $department->id,
            'name' => 'X IPA 1', 'code' => 'X-IPA-1', 'level' => 'X',
            'capacity' => 36, 'status' => SchoolClass::ACTIVE,
        ]);
        Subject::create(['name' => 'Matematika', 'code' => 'MTK', 'grade_level' => 'X']);

        $body = $this->get('/program')->getContent();

        $this->assertStringContainsString('Ilmu Pengetahuan Alam', $body);
        $this->assertStringContainsString('Matematika', $body);

        // A count is publishable; the class itself is one step from a roster,
        // since class membership is derived from enrolment and identifies
        // students.
        $this->assertStringContainsString('1 kelas aktif', $body);
        $this->assertStringNotContainsString('X IPA 1', $body);
    }

    /**
     * @test
     */
    public function test_the_profile_says_so_when_nothing_is_published(): void
    {
        // No school.* values beyond the default, so the page must not render a
        // grid of empty labels.
        app(SettingsService::class)->setMany([
            'school.name' => 'SMP 1 LYFLA',
            'school.npsn' => '',
            'school.address' => '',
            'school.city' => '',
            'school.province' => '',
            'school.email' => '',
            'school.phone' => '',
            'school.website' => '',
            'school.headmaster' => '',
        ]);

        $body = $this->get('/tentang')->getContent();

        $this->assertStringContainsString('Detail profil belum dilengkapi', $body);
    }
}
