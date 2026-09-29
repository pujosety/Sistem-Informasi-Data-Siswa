<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guards the two file-serving paths after the storage disk was made
 * host-aware.
 *
 * WHY THIS EXISTS
 *
 * The `public` disk used to be a local directory published through
 * `storage:link`, so it could be assumed public and read with a local path.
 * That is no longer true on every host: setting AWS_BUCKET switches the same
 * disk to S3, and an S3 bucket holding student documents must stay private.
 *
 * Three things had to change together, and each is a silent wrong answer
 * rather than a crash if it regresses:
 *
 *   1. Documents stream through readStream(). response()->file() only works
 *      on a local path, so on a bucket it would 404 every real document.
 *   2. Brand images moved off a direct /storage/... URL. A bucket has one ACL
 *      per bucket, not per directory, so serving the logo from a public path
 *      would also publish every student's KK and KTP.
 *   3. The disk's own file must be re-typed from an allowlist, because a
 *      brand upload may be an SVG and an SVG served as image/svg+xml executes
 *      script in the site's own origin.
 */
class FileServingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seedRoles();
        $this->seedAcademicYear();
        $this->seedDocumentTypes();
    }

    private function documentFor(User $owner, string $path = 'documents/1/1/ktp.png'): Document
    {
        $student = Student::factory()->create(['user_id' => $owner->id]);
        $registration = $student->registration;

        Storage::disk('public')->put($path, 'PNG-BYTES');

        /*
         * updateOrCreate, not create: creating a Student seeds placeholder
         * Document rows for every required document type, and the table has a
         * unique key on (registration_id, document_type_id). A plain create()
         * therefore collides with the placeholder the factory just made, which
         * is a property of the fixture rather than of the code under test.
         *
         * Uploading through DocumentService would be the most faithful path,
         * but it also rewrites completeness state and audit rows, which makes
         * an authorization test fail for reasons unrelated to authorization.
         */
        return Document::updateOrCreate(
            [
                'registration_id' => $registration->id,
                'document_type_id' => DocumentType::first()->id,
            ],
            [
                'path' => $path,
                'original_name' => 'ktp.png',
                'mime_type' => 'image/png',
                'size_kb' => 1,
                'status' => 'pending',
            ]
        );
    }

    public function test_owner_can_stream_their_own_document(): void
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        $document = $this->documentFor($user);

        $response = $this->actingAs($user)->get(route('documents.show', $document));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertSame('PNG-BYTES', $response->streamedContent());
    }

    public function test_a_student_cannot_read_another_students_document(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('siswa');

        $document = $this->documentFor($owner);

        $intruder = User::factory()->create();
        $intruder->assignRole('siswa');

        $this->actingAs($intruder)
            ->get(route('documents.show', $document))
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_rather_than_shown_the_bytes(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('siswa');

        $document = $this->documentFor($owner);

        // The guard is the auth middleware, so a guest must never receive the
        // file. This asserts the redirect rather than a 403 because that is
        // what the middleware actually produces.
        $this->get(route('documents.show', $document))->assertRedirect(route('login'));
    }

    public function test_staff_with_the_review_role_can_read_any_document(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('siswa');

        $document = $this->documentFor($owner);

        $staff = User::factory()->create();
        $staff->assignRole('kesiswaan');

        $this->actingAs($staff)
            ->get(route('documents.show', $document))
            ->assertOk();
    }

    public function test_a_missing_file_is_a_404_and_not_a_500(): void
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        $document = $this->documentFor($user);

        // The row survives, the object does not. That is the state a
        // half-failed upload or a manually pruned file leaves behind, and it
        // must not surface as an error page.
        Storage::disk('public')->delete($document->path);

        $this->actingAs($user)
            ->get(route('documents.show', $document))
            ->assertNotFound();
    }

    public function test_the_download_variant_sets_an_attachment_disposition(): void
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');

        $document = $this->documentFor($user);

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="ktp.png"');
    }

    public function test_the_brand_logo_is_reachable_without_authentication(): void
    {
        // setMany() writes through set(), which flushes the settings cache
        // itself, so no explicit flush is needed here.
        $this->app->make(SettingsService::class)->setMany([
            'branding.logo' => 'branding/2026/09/logo.png',
        ]);

        Storage::disk('public')->put('branding/2026/09/logo.png', 'LOGO-BYTES');

        $response = $this->get(route('brand.asset', ['key' => 'branding.logo']));

        // The login screen renders before anyone is authenticated, so this
        // route has to work for a guest. If it ever moves behind auth the
        // login page silently loses its logo.
        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertSame('LOGO-BYTES', $response->streamedContent());
    }

    public function test_a_brand_key_outside_the_allowlist_is_a_404(): void
    {
        // The key arrives from the URL, so anything that is not one of the two
        // known assets must not reach the settings lookup at all.
        $this->get('/branding/branding.logo.secret')->assertNotFound();
        $this->get('/branding/app.name')->assertNotFound();
    }

    public function test_a_brand_asset_that_was_never_uploaded_is_a_404(): void
    {
        // The setting is empty, so there is no path to resolve.
        $this->app->make(SettingsService::class)->setMany(['branding.icon' => '']);

        $this->get(route('brand.asset', ['key' => 'branding.icon']))->assertNotFound();
    }

    public function test_an_svg_brand_asset_is_served_as_text_so_it_cannot_execute(): void
    {
        $this->app->make(SettingsService::class)->setMany(['branding.icon' => 'branding/icon.svg']);

        Storage::disk('public')->put('branding/icon.svg', '<svg onload="alert(1)"></svg>');

        $response = $this->get(route('brand.asset', ['key' => 'branding.icon']));

        $response->assertOk();

        // image/svg+xml would let a crafted logo run script in this origin.
        // text/plain cannot be navigated to as a document, and the logo still
        // renders because an <img> tag renders SVG by content sniffing.
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
    }

    public function test_the_asset_url_never_points_at_a_raw_storage_path(): void
    {
        $this->app->make(SettingsService::class)->setMany(['branding.logo' => 'branding/logo.png']);

        $url = $this->app->make(SettingsService::class)->asset('branding.logo');

        // A /storage/... URL only works on a local disk with a working
        // symlink. On a bucket it exposes every document in the prefix, so the
        // settings service must hand out an application route instead.
        $this->assertStringNotContainsString('/storage/', $url);
        $this->assertSame(route('brand.asset', ['key' => 'branding.logo']), $url);
    }
}
