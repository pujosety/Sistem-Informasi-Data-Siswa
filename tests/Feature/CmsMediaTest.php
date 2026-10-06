<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Services\CmsMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Security tests for the CMS media library.
 *
 * WHAT IS ACTUALLY BEING PROVEN HERE
 *
 * An upload endpoint is a remote code execution surface wearing a form. The
 * whole feature rests on five claims, and each one below is a claim an
 * attacker would try first:
 *
 *   1. A real image round-trips — otherwise every other test is vacuous, and
 *      a suite where everything is rejected also "passes" all the refusals.
 *   2. A PHP webshell renamed to `.png` is refused. This is the one that
 *      matters: `mimes:png` and `$file->getClientMimeType()` both read the
 *      attacker's string, and only reading the bytes rejects the payload.
 *   3. An oversized file is refused, so the public disk cannot be filled.
 *   4. `../` in the incoming filename cannot escape the media directory.
 *   5. A user without `cms.media.manage` gets 403, not a redirect and not a
 *      validation error.
 *
 * WHY THE SERVICE IS TESTED DIRECTLY, NOT ONLY THROUGH HTTP
 *
 * The controller's validator carries an `image` rule, which consults the
 * file's *guessed* extension — and a PHP file guesses as `php`, so Laravel
 * rejects a webshell before CmsMediaService is ever reached. Testing only
 * through HTTP would therefore prove "the request failed", not "the security
 * control works", and it would keep passing if the service's finfo check were
 * deleted while `image` happened to still catch the test's particular
 * payload. The service is reachable from a console command, a seeder and any
 * future API, so it is the layer that must hold on its own — and that is what
 * the `throws_validation_exception` tests assert.
 *
 * Storage is faked on the `public` disk throughout; no test touches a real
 * disk, and the oversized test writes megabytes into a temp file only.
 */
class CmsMediaTest extends TestCase
{
    use RefreshDatabase;

    private CmsMediaService $media;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Media::query()->delete();

        $this->seedRoles();
        $this->media = app(CmsMediaService::class);
    }

    // ------------------------------------------------------------- fixtures

    /**
     * A genuinely valid PNG, 1x1, in test mode.
     *
     * Written as bytes rather than produced by GD so the suite does not
     * depend on the container having the image extension compiled in — a
     * missing extension must not read as "upload validation is untestable".
     */
    private function makeRealPng(string $name = 'foto-sekolah.png', int $padKb = 0): UploadedFile
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        if ($padKb > 0) {
            // Trailing padding after IEND. finfo still reports image/png
            // because the magic bytes are at the head, which is exactly the
            // property that lets a file be "a real image" and "too large" at
            // the same time — the case the size limit exists for.
            $bytes .= str_repeat("\0", $padKb * 1024);
        }

        $path = tempnam(sys_get_temp_dir(), 'sida-media').'.png';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    /**
     * A real GIF, used to prove the stored extension comes from the bytes and
     * not from the name the browser supplied.
     */
    private function makeRealGif(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'sida-media').'.gif';
        file_put_contents($path, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

        return new UploadedFile($path, $name, 'image/gif', null, true);
    }

    /**
     * A PHP webshell. The uploaded name and the declared mime are BOTH `.png`,
     * so nothing about the request is honest except the contents — which is
     * the entire point of the test.
     */
    private function makeDisguisedPhpPayload(string $name = 'foto.png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'sida-media').'.png';

        file_put_contents($path, "<?php\n".
            "// masquerades as an image; a webshell once written lands here\n".
            "if (isset(\$_REQUEST['cmd'])) { echo shell_exec(\$_REQUEST['cmd']); }\n".
            "?>\n");

        // $test = true is the 5th constructor argument: the file is reported
        // valid so the request reaches the checks under test.
        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    // ------------------------------------------------- 1. the happy path

    /**
     * @test
     */
    public function test_a_valid_image_uploads_and_is_retrievable_from_the_disk(): void
    {
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin)
            ->post(route('admin.media.store'), [
                'file' => $this->makeRealPng(),
                'alt_text' => 'Gedung sekolah',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $item = Media::query()->sole();

        $this->assertSame('public', $item->disk);
        $this->assertSame('image/png', $item->mime);
        $this->assertSame('foto-sekolah.png', $item->original_name);
        $this->assertSame($admin->id, $item->uploaded_by);

        // Actually on the fake disk, and readable back byte-for-byte. A row
        // whose bytes are missing is a 404 waiting for the first article that
        // references it.
        Storage::disk('public')->assertExists($item->path);

        $this->assertStringStartsWith(
            'cms-media/'.now()->format('Y/m').'/',
            $item->path,
            'Media must be sharded under the configured media directory by month.'
        );

        $this->assertStringNotContainsString('foto-sekolah', $item->path);
        $this->assertSame(
            file_get_contents($this->makeRealPng()->getRealPath()),
            Storage::disk('public')->get($item->path)
        );
    }

    /**
     * The stored extension is derived from the sniffed MIME, not from the
     * name. A GIF uploaded as `shell.php.png` must land as a `.gif`.
     *
     * @test
     */
    public function test_the_stored_extension_comes_from_the_bytes_not_the_supplied_name(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('admin.media.store'), [
            'file' => $this->makeRealGif('shell.php.png'),
        ])->assertSessionHasNoErrors();

        $item = Media::query()->sole();

        $this->assertSame('image/gif', $item->mime);
        $this->assertStringEndsWith('.gif', $item->path);
        $this->assertStringNotContainsString('.php', $item->path);
    }

    // --------------------------------- 2. the disguised executable (the one)

    /**
     * THE TEST THAT MATTERS. PHP source named `.png`, at the service layer —
     * where the finfo check is the only thing standing between the request and
     * a file on a public disk.
     *
     * @test
     */
    public function test_php_source_named_png_is_rejected_by_the_service(): void
    {
        $admin = $this->makeUser('admin');

        $this->expectException(ValidationException::class);

        try {
            $this->media->store($this->makeDisguisedPhpPayload(), $admin);
        } finally {
            $this->assertSame(0, Media::query()->count());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    /**
     * The same payload through the real HTTP route. Whatever layer refuses it
     * — the controller's `image` rule or the service's finfo check — the
     * outcome the platform owes the public site is: nothing is stored.
     *
     * @test
     */
    public function test_php_source_named_png_is_rejected_over_http_and_nothing_lands_on_disk(): void
    {
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin)->post(route('admin.media.store'), [
            'file' => $this->makeDisguisedPhpPayload(),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /**
     * The same, via a double extension — `foto.php.png`, the shape an
     * operator eyeballing a directory listing would not notice.
     *
     * @test
     */
    public function test_a_double_extension_php_payload_is_rejected(): void
    {
        $admin = $this->makeUser('admin');

        $this->expectException(ValidationException::class);

        try {
            $this->media->store($this->makeDisguisedPhpPayload('foto.php.png'), $admin);
        } finally {
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    /**
     * A shell script with a shebang also sniffs as text, not as an image.
     *
     * @test
     */
    public function test_a_shell_script_named_png_is_rejected(): void
    {
        $admin = $this->makeUser('admin');

        $path = tempnam(sys_get_temp_dir(), 'sida-media').'.png';
        file_put_contents($path, "#!/bin/sh\nrm -rf /\n");
        $payload = new UploadedFile($path, 'backdoor.png', 'image/png', null, true);

        $this->expectException(ValidationException::class);

        try {
            $this->media->store($payload, $admin);
        } finally {
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    // --------------------------------------------------- 3. the size limit

    /**
     * @test
     */
    public function test_a_file_over_the_configured_limit_is_rejected(): void
    {
        $admin = $this->makeUser('admin');

        $limitKb = (int) config('cms.media.max_kb');

        $this->expectException(ValidationException::class);

        try {
            // A real image by its bytes, one KB over the ceiling: only the size
            // check can be what refuses it.
            $this->media->store($this->makeRealPng('besar.png', $limitKb + 1), $admin);
        } finally {
            $this->assertSame(0, Media::query()->count());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    /**
     * The limit is read from config rather than hardcoded, and the shipped
     * default is the 4 MB the UI advertises. Guards against a config value
     * that silently becomes 4 (KB) or 0 (nothing uploads).
     *
     * @test
     */
    public function test_the_size_limit_comes_from_config_and_the_default_is_sane(): void
    {
        $this->assertSame(4096, (int) config('cms.media.max_kb'));
        $this->assertSame('public', config('cms.media.disk'));

        config(['cms.media.max_kb' => 4]);

        $admin = $this->makeUser('admin');

        $this->expectException(ValidationException::class);

        try {
            $this->media->store($this->makeRealPng('kecil.png', 8), $admin);
        } finally {
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    /**
     * @test
     */
    public function test_an_empty_file_is_rejected(): void
    {
        $admin = $this->makeUser('admin');

        $path = tempnam(sys_get_temp_dir(), 'sida-media').'.png';
        file_put_contents($path, '');

        $this->expectException(ValidationException::class);

        try {
            $this->media->store(new UploadedFile($path, 'kosong.png', 'image/png', null, true), $admin);
        } finally {
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    // ------------------------------------------------------- 4. traversal

    /**
     * A null byte, the classic truncation trick — and the one path component
     * Symfony's own basename() does NOT strip, so it is the only traversal
     * shape that actually reaches the service's own name check.
     *
     * @test
     */
    public function test_a_null_byte_in_the_filename_is_rejected(): void
    {
        $admin = $this->makeUser('admin');

        $this->expectException(ValidationException::class);

        try {
            $this->media->store($this->makeRealPng("evil\0.php.png"), $admin);
        } finally {
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    /**
     * A name whose client extension is not an image extension at all.
     *
     * @test
     */
    public function test_a_php_extension_in_the_filename_is_rejected(): void
    {
        $admin = $this->makeUser('admin');

        $path = tempnam(sys_get_temp_dir(), 'sida-media').'.png';
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        // A real image, sent under a .php name: the extension allowlist is
        // only a first filter, but it is the filter that stops the file being
        // *named* webshell in a directory listing.
        $this->expectException(ValidationException::class);

        try {
            $this->media->store(new UploadedFile($path, 'webshell.php', 'image/png', null, true), $admin);
        } finally {
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    /**
     * THE TRAVERSAL GUARANTEE, stated as an invariant rather than as a
     * rejection — because Symfony's UploadedFile strips path components from
     * the client name before the service ever sees them (`../../evil.png`
     * arrives as `evil.png`), so the service's own `..` filter is
     * unreachable through this stack.
     *
     * That is defence in depth working, not a hole: the guarantee does not
     * depend on that filter firing. The stored filename is
     * `Str::random(40)` plus an extension derived from the sniffed MIME, and
     * the directory comes from config — so no attacker-supplied string reaches
     * the path at all. These tests assert that directly, for every traversal
     * shape, which is the property that would still hold if someone deleted
     * `assertNameIsInert()` tomorrow.
     *
     * @test
     */
    public function test_no_traversal_filename_can_ever_place_a_file_outside_the_media_directory(): void
    {
        $admin = $this->makeUser('admin');

        $names = [
            '../../evil.png',
            '../evil.png',
            '..\\..\\evil.png',
            '/etc/cron.d/evil.png',
            '....//....//evil.png',
            str_repeat('a', 250).'.png',
        ];

        foreach ($names as $index => $name) {
            try {
                $this->media->store($this->makeRealPng($name), $admin);
            } catch (ValidationException) {
                // Refusing outright is an acceptable outcome. What is not
                // acceptable is a file outside cms-media/.
            }

            foreach (Storage::disk('public')->allFiles() as $path) {
                $this->assertStringStartsWith('cms-media/', $path, "Attempt #$index escaped: $path");
                $this->assertStringNotContainsString('..', $path);
                $this->assertStringNotContainsString("\0", $path);
                $this->assertStringNotContainsString('\\', $path);
            }
        }

        // Whatever the filenames were, the catalogue agrees.
        $this->assertSame(count($names), Media::query()->count());

        foreach (Media::query()->get() as $item) {
            $this->assertStringStartsWith('cms-media/'.now()->format('Y/m').'/', $item->path);
            $this->assertSame('image/png', $item->mime);
        }
    }

    /**
     * Two uploads of the same file produce two distinct stored paths, so a
     * name collision can never overwrite an image already in the library.
     *
     * @test
     */
    public function test_two_uploads_of_the_same_name_do_not_collide(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('admin.media.store'), ['file' => $this->makeRealPng('sama.png')]);
        $this->actingAs($admin)->post(route('admin.media.store'), ['file' => $this->makeRealPng('sama.png')]);

        $items = Media::query()->get();

        $this->assertCount(2, $items);
        $this->assertNotSame($items[0]->path, $items[1]->path);
        $this->assertCount(2, Storage::disk('public')->allFiles());
    }

    // ---------------------------------------------------- 5. authorisation

    /**
     * Kesiswaan writes CMS drafts and deliberately does NOT hold
     * `cms.media.manage` (see PermissionCatalog). Uploading is gated on that
     * permission at the route, so the request must be a 403 — not a redirect
     * with a flash message, which an operator would read as "saved".
     *
     * @test
     */
    public function test_a_user_without_cms_media_manage_gets_403_on_upload(): void
    {
        $kesiswaan = $this->makeUser('kesiswaan');

        // The premise, asserted: if this ever changes the test below is
        // measuring something else.
        $this->assertTrue($kesiswaan->can('cms.posts.edit'));
        $this->assertFalse($kesiswaan->can('cms.media.manage'));

        $this->actingAs($kesiswaan)
            ->post(route('admin.media.store'), ['file' => $this->makeRealPng()])
            ->assertForbidden();

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /**
     * A 403 is required for the destructive route too: a writer who can edit
     * an article must not be able to remove the school logo.
     *
     * @test
     */
    public function test_a_user_without_cms_media_manage_gets_403_on_delete(): void
    {
        $admin = $this->makeUser('admin');
        $kesiswaan = $this->makeUser('kesiswaan');

        $this->actingAs($admin)->post(route('admin.media.store'), [
            'file' => $this->makeRealPng(),
        ])->assertSessionHasNoErrors();

        $item = Media::query()->sole();

        $this->actingAs($kesiswaan)
            ->delete(route('admin.media.destroy', $item))
            ->assertForbidden();

        $this->assertNotNull($item->fresh());
    }

    /**
     * The policy is the second lock, and it is the one a future route or
     * controller would lean on. Asserted directly because MediaPolicy::create()
     * admits `cms.posts.edit` — a deliberate choice for attaching images to an
     * article, and exactly the kind of widening that silently authorises
     * uploads if the route guard is ever loosened.
     *
     * @test
     */
    public function test_the_policy_admits_a_writer_to_upload_but_never_to_delete(): void
    {
        $admin = $this->makeUser('admin');
        $kesiswaan = $this->makeUser('kesiswaan');

        $this->actingAs($admin)->post(route('admin.media.store'), [
            'file' => $this->makeRealPng(),
        ])->assertSessionHasNoErrors();

        $item = Media::query()->sole();

        $this->assertTrue($admin->can('create', Media::class));
        $this->assertTrue($admin->can('delete', $item));

        $this->assertTrue($kesiswaan->can('create', Media::class));
        $this->assertFalse($kesiswaan->can('delete', $item));
        $this->assertFalse($kesiswaan->can('restore', $item));
    }

    /**
     * A logged-out visitor is bounced to login, and cannot reach the library
     * by guessing the URL.
     *
     * @test
     */
    public function test_a_guest_cannot_reach_the_media_library(): void
    {
        $this->get(route('admin.media.index'))->assertRedirect(route('login'));

        $this->post(route('admin.media.store'), [
            'file' => $this->makeRealPng(),
        ])->assertRedirect(route('login'));

        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
