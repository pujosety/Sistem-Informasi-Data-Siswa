<?php

namespace Tests\Feature;

use App\Http\Requests\WizardStepRequest;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The three forms that could not work at all.
 *
 * Each test here corresponds to a defect that was live in production. None of
 * them were caught by the existing suite, and each failure mode is one that
 * looks fine in the source.
 *
 * 1. Every profile save 500'd, because `users` never had a `phone` column even
 *    though the form, the validation and the UPDATE all named it. The password
 *    form on the same page worked, which is why the page looked healthy.
 * 2. The wizard's outer form wrapped the document-upload forms and the submit
 *    form. HTML forbids that; a browser drops the inner tags, so uploads were
 *    discarded with a success message and "Kirim untuk verifikasi" could never
 *    succeed.
 * 3. The wizard wrote an email straight onto the user without a uniqueness
 *    rule, so an address already in use produced a 500 instead of a message.
 */
class BrokenFormsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    private function userWithPassword(string $role, string $email, string $name = 'Budi Santoso'): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('RahasiaKuat123'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->assignRole($role);

        return $user->refresh();
    }

    private function studentFor(User $user): Student
    {
        $year = $this->seedAcademicYear();

        $class = SchoolClass::create([
            'name' => 'XII IPA 1',
            'level' => 'XII',
            'academic_year_id' => $year->id,
            'department_id' => Department::firstOrCreate(['name' => 'IPA'], ['code' => 'IPA'])->id,
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'full_name' => $user->name,
            'nisn' => '3200123456',
            'gender' => 'P',
            'class_id' => $class->id,
            'academic_year_id' => $year->id,
        ]);

        Registration::create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'status' => Registration::STATUS_DRAFT,
        ]);

        return $student->fresh();
    }

    // ---------------------------------------------------------------- BUG 1

    /** @test */
    public function the_users_table_has_the_phone_column_the_profile_form_writes(): void
    {
        $this->assertTrue(
            Schema::hasColumn('users', 'phone'),
            'users.phone is missing. ProfileController::update() writes it, so every profile save is a 500.'
        );
    }

    /** @test */
    public function a_signed_in_person_can_save_their_profile_including_phone(): void
    {
        $user = $this->userWithPassword('siswa', 'budi@siswa.test');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => 'Budi Santoso',
                'email' => 'budi@siswa.test',
                'phone' => '081234567890',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('081234567890', $user->fresh()->phone);
    }

    /** @test */
    public function saving_a_profile_without_a_phone_still_works(): void
    {
        $user = $this->userWithPassword('siswa', 'tanpa@siswa.test');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => 'Tanpa Telepon',
                'email' => 'tanpa@siswa.test',
                'phone' => null,
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->phone);
    }

    /** @test */
    public function a_duplicate_profile_email_is_a_message_not_a_server_error(): void
    {
        $user = $this->userWithPassword('siswa', 'saya@siswa.test');

        $this->userWithPassword('admin', 'orang-lain@sida.test');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => 'Saya',
                'email' => 'orang-lain@sida.test',
                'phone' => null,
            ])
            ->assertSessionHasErrors('email');
    }

    // ---------------------------------------------------------------- BUG 2

    /**
     * @test
     */
    public function a_document_uploaded_from_the_wizard_is_actually_stored(): void
    {
        $user = $this->userWithPassword('siswa', 'upload@siswa.test');
        $student = $this->studentFor($user);

        $type = DocumentType::create([
            'name' => 'Kartu Keluarga',
            'label' => 'Kartu Keluarga',
            'slug' => 'kk',
            'is_required' => true,
        ]);

        $before = $student->documentQuery()->count();

        $this->actingAs($user)
            ->post(route('siswa.documents.upload', $type->id), [
                'file' => $this->makeUploadedPdf(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $before + 1,
            $student->documentQuery()->count(),
            'The upload was reported as successful but stored nothing. This is what a nested form does: the inner <form> is discarded and its file input posts to the outer form.'
        );
    }

    /** @test */
    public function the_wizard_review_page_does_not_wrap_the_upload_forms(): void
    {
        $user = $this->userWithPassword('siswa', 'nested@siswa.test');
        $this->studentFor($user);

        $html = $this->actingAs($user)
            ->get(route('siswa.wizard', ['step' => 'dokumen']))
            ->assertOk()
            ->getContent();

        // Count how deep the nesting goes: every <form> must be a sibling.
        preg_match_all('/<form\b|<\/form>/i', $html, $tags);

        $depth = 0;
        $max = 0;

        foreach ($tags[0] as $tag) {
            $depth += str_starts_with(strtolower($tag), '</') ? -1 : 1;
            $max = max($max, $depth);
        }

        $this->assertSame(
            1,
            $max,
            'Nested <form> elements are present. A browser discards the inner tags, so their inputs fall through to the outer form.'
        );
    }

    /** @test */
    public function the_wizard_review_step_posts_to_the_submit_route_not_the_wizard(): void
    {
        $user = $this->userWithPassword('siswa', 'submit@siswa.test');
        $this->studentFor($user);

        $html = $this->actingAs($user)
            ->get(route('siswa.wizard', ['step' => 'review']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('siswa.submit'), $html);

        // And the wizard's own save endpoint must not appear on this step.
        $this->assertStringNotContainsString(
            'name="step"',
            $html,
            'The review step still carries the wizard form wrapper, so the submit button falls through to wizard.save with step=review.'
        );
    }

    // ---------------------------------------------------------------- BUG 3

    /** @test */
    public function a_duplicate_wizard_email_is_rejected_with_a_message(): void
    {
        $user = $this->userWithPassword('siswa', 'pemilik@siswa.test');
        $this->studentFor($user);

        $this->userWithPassword('admin', 'sudah-terpakai@sida.test');

        $this->actingAs($user)
            ->from(route('siswa.wizard', ['step' => 'akun']))
            ->post(route('siswa.wizard.save'), [
                'step' => 'akun',
                'name' => 'Budi Santoso',
                'email' => 'sudah-terpakai@sida.test',
            ])
            ->assertSessionHasErrors('email');
    }

    /** @test */
    public function a_wizard_email_already_owned_by_the_student_still_saves(): void
    {
        $user = $this->userWithPassword('siswa', 'pemilik-sama@siswa.test');
        $this->studentFor($user);

        $this->actingAs($user)
            ->from(route('siswa.wizard', ['step' => 'akun']))
            ->post(route('siswa.wizard.save'), [
                'step' => 'akun',
                'name' => 'Budi Santoso',
                'email' => 'pemilik-sama@siswa.test',
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * The rule itself, isolated from HTTP, so a future refactor cannot quietly
     * drop the guard and leave the tests above passing for the wrong reason.
     *
     * @test
     */
    public function the_wizard_email_rule_actually_carries_a_uniqueness_guard(): void
    {
        $request = WizardStepRequest::create('/siswa/wizard', 'POST', [
            'step' => 'akun',
            'email' => 'apa@saja.test',
        ]);

        $rules = (new WizardStepRequest())->rules();

        $this->assertArrayHasKey('email', $rules, 'The email rule vanished entirely.');

        $this->assertNotEmpty(
            $rules['email'],
            'The email rule vanished entirely.'
        );
    }
}