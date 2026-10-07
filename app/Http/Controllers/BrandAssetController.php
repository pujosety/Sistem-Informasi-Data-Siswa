<?php

namespace App\Http\Controllers;

use App\Services\SettingsService;
use App\Models\Setting;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the two brand images (logo and favicon icon).
 *
 * WHY A CONTROLLER INSTEAD OF A DIRECT /storage/ URL
 *
 * Brand images and student documents live on the SAME disk, because both are
 * written through `Storage::disk('public')`. That made a public disk acceptable
 * while the only files on it were a logo and an icon.
 *
 * It stops being acceptable the moment the disk is an S3 bucket. A bucket has
 * one ACL, not one per directory, so a bucket readable enough for the logo also
 * serves `documents/<student>/<registration>/...` — every student's birth
 * certificate and KTP, to anyone who can guess or harvest a key. Making the
 * bucket private to fix that in turn breaks the direct /storage/... URL the
 * layout used to render the logo from.
 *
 * Streaming both kinds of file through PHP resolves that. The bucket stays
 * private, and each file is exposed only by the route that is entitled to it:
 * this one for the two public brand images, DocumentFileController for the
 * private student documents behind an ownership check.
 */
class BrandAssetController extends Controller
{
    /**
     * Only these two keys may be resolved to a file.
     *
     * The key arrives from a URL, so it is matched against a fixed list rather
     * than sanitised. Anything else is a 404, not a lookup, so no crafted key
     * can walk out of the branding/ prefix.
     */
    private const ALLOWED = ['branding.logo', 'branding.icon'];

    public function __construct(private readonly SettingsService $settings) {}

    public function show(Request $request, string $asset): StreamedResponse
    {
        $key = match ($asset) {
            'logo' => 'branding.logo',
            'icon' => 'branding.icon',
            default => abort(404),
        };

        abort_unless(in_array($key, self::ALLOWED, true), 404);

        $path = $this->settings->get($key);

        // Do not let a stale settings cache turn a valid uploaded object into
        // a 404 on another PHPix worker. Asset rows are tiny and are read fresh.
        try {
            $path = Setting::where('key', $key)->first()?->typedValue() ?? $path;
        } catch (\Throwable $e) {
            report($e);
        }

        abort_if(blank($path), 404);

        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        try {
            $exists = $disk->exists($path);
        } catch (\Throwable $e) {
            report($e);
            $exists = false;
        }

        abort_unless($exists, 404);

        /*
         * A brand upload is validated to be a real image by BrandService, but
         * the stored bytes are re-typed from a short allowlist rather than
         * from the file itself. The only script-capable brand format is SVG,
         * and it is served as text/plain so it can never execute as a document
         * in the site's own origin. The logo is displayed in an <img> tag,
         * where the browser will not run script either way.
         */
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mime = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'text/plain; charset=utf-8',
            default => 'application/octet-stream',
        };

        try {
            $stream = $this->readStream($path, $disk);
        } catch (\Throwable $e) {
            report($e);
            $stream = false;
        }

        if ($stream === false) {
            abort(404, 'Berkas tidak ditemukan di penyimpanan.');
        }

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            // The logo is on every page including the login screen, so it is
            // cacheable. It is not a student document, so a shared cache is
            // not a disclosure risk here.
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }

    /**
     * Flysystem's S3 readStream path is not reliable on the Wasmer volume
     * endpoint even though HEAD/PUT work. Use the AWS client directly for the
     * two public brand files while keeping local development on Flysystem.
     */
    private function readStream(string $path, mixed $disk): mixed
    {
        $config = config('filesystems.disks.public', []);

        if (($config['driver'] ?? null) !== 's3') {
            return $disk->readStream($path);
        }

        $client = new S3Client([
            'version' => 'latest',
            'region' => $config['region'] ?? 'us-east-1',
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? false),
            'credentials' => [
                'key' => $config['key'] ?? null,
                'secret' => $config['secret'] ?? null,
            ],
            'http' => [
                'verify' => $config['http']['verify'] ?? true,
            ],
        ]);

        $object = $client->getObject([
            'Bucket' => $config['bucket'],
            'Key' => $path,
        ]);

        return $object['Body']->detach();
    }
}
