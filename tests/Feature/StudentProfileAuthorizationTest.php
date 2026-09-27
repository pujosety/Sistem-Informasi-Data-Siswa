<?php

namespace Tests\Feature;

use App\Http\Requests\StudentProfileRequest;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards a bug this suite actually hit: StudentProfileRequest::authorize()
 * silently returned false, so a valid biodata POST redirected with NO error
 * and nothing was saved. A user filling the form simply saw "no message, no
 * change" — the worst possible failure mode.
 */
class StudentProfileAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedAcademicYear();
        $this->seedDocumentTypes();
    }

    private function studentAt(string $status): Student
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        $student = Student::factory()->create(['user_id' => $user->id]);
        $student->registration->update(['status' => $status]);

        return $student->refresh();
    }

    public static function editableStatusProvider(): array
    {
        return [
            'draft' => [Registration::STATUS_DRAFT],
            'revision' => [Registration::STATUS_REVISION],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('editableStatusProvider')]
    public function test_student_may_edit_biodata_while_registration_is_open(string $status): void
    {
        $student = $this->studentAt($status);

        $this->actingAs($student->user);

        $request = StudentProfileRequest::createFrom(
            \Illuminate\Http\Request::create('/siswa/biodata', 'PUT'),
            // bind the acting user the way the container would
        );
        $request->setUserResolver(fn () => $student->user);

        $this->assertTrue(
            $request->authorize(),
            "A student with a '{$status}' registration must be allowed to edit biodata."
        );
    }

    public function test_student_may_not_edit_while_under_review(): void
    {
        $student = $this->studentAt(Registration::STATUS_PENDING);

        $request = StudentProfileRequest::createFrom(
            \Illuminate\Http\Request::create('/siswa/biodata', 'PUT')
        );
        $request->setUserResolver(fn () => $student->user);

        $this->assertFalse(
            $request->authorize(),
            'Biodata must be locked while an admin is verifying the registration.'
        );
    }

    public function test_a_valid_biodata_post_actually_persists_every_field(): void
    {
        $student = $this->studentAt(Registration::STATUS_DRAFT);

        $response = $this->actingAs($student->user)->put(route('siswa.biodata.update'), [
            'full_name' => 'Nur Aisyah Ramadhani',
            'nisn' => $student->nisn,
            'nik' => '3273014502900001',
            'gender' => 'P',
            'birth_place' => 'Bandung',
            'birth_date' => '2009-03-14',
            'religion' => 'Islam',
            'phone' => '081234567890',
            'address' => 'Jl. Cendana No. 45',
            'city' => 'Bandung',
            'previous_school' => 'SMP Negeri 12 Bandung',
            'graduation_year' => '2026',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $fresh = $student->fresh();

        // Every field below was silently dropped before authorize() was fixed.
        $this->assertSame('Nur Aisyah Ramadhani', $fresh->full_name);
        // Saving biodata also syncs the account name.
        $this->assertSame('Nur Aisyah Ramadhani', $fresh->user->name);
        $this->assertSame('P', $fresh->gender);
        $this->assertSame('Bandung', $fresh->birth_place);
        $this->assertSame('2009-03-14', $fresh->birth_date?->format('Y-m-d'));
        $this->assertSame('081234567890', $fresh->phone);
        $this->assertSame('SMP Negeri 12 Bandung', $fresh->previous_school);
        $this->assertSame('2026', $fresh->graduation_year);
    }

    public function test_graduation_year_is_a_range_check_not_a_string_length_check(): void
    {
        $student = $this->studentAt(Registration::STATUS_DRAFT);

        // A valid year must be ACCEPTED. Under the old `between:1990,2026`
        // string-length rule this failed with "must be between 1990 and 2026
        // characters".
        $this->actingAs($student->user)->put(route('siswa.biodata.update'), [
            'full_name' => 'Uji Coba',
            'nisn' => $student->nisn,
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2009-01-01',
            'religion' => 'Islam',
            'phone' => '081200000000',
            'address' => 'Jl. Test',
            'city' => 'Bandung',
            'previous_school' => 'SMP Test',
            'graduation_year' => '2026',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026', $student->fresh()->graduation_year);
    }

    public function test_a_year_outside_the_allowed_range_is_rejected(): void
    {
        $student = $this->studentAt(Registration::STATUS_DRAFT);

        // A referer is required: back() has no other way to know where the
        // user came from. The exception is thrown by the validator and handled
        // by Laravel, which flashes the errors and redirects.
        $response = $this->actingAs($student->user)
            ->from(route('siswa.biodata'))
            ->put(route('siswa.biodata.update'), [
                'full_name' => 'Uji Gagal',
                'nisn' => $student->nisn,
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2009-01-01',
                'religion' => 'Islam',
                'phone' => '081200000000',
                'address' => 'Jl. Test',
                'city' => 'Bandung',
                'previous_school' => 'SMP Test',
                'graduation_year' => '1985', // 4 digits, but below the 1990 floor
            ]);

        // Regression guard: a custom error renderer must never turn Laravel's
        // validation redirect into a 500.
        $response->assertRedirect(route('siswa.biodata'));
        $response->assertSessionHasErrors('graduation_year');
    }

    /**
     * The rendered error itself is covered end-to-end by
     * hermes-verify-validation.sh, which drives a real browser session.
     */
    public function test_the_numeric_range_rule_rejects_an_old_year(): void
    {
        $validator = validator(
            ['graduation_year' => '1985'],
            ['graduation_year' => ['required', 'digits:4', \Illuminate\Validation\Rule::numeric()->between(1990, (int) date('Y'))]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('between', $validator->errors()->first('graduation_year'));

        // And the message must not leak the old string-length wording.
        $this->assertStringNotContainsString('characters', $validator->errors()->first('graduation_year'));
    }
}
