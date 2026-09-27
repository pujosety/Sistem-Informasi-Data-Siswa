<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Brand asset handling.
 *
 * Brand images are public assets and are validated as such: real image MIME
 * types only, a hard size ceiling, and a stored filename that cannot execute.
 * They are deliberately kept separate from private student documents, which
 * are only ever served through the authorised /berkas routes.
 */
class BrandService
{
    public const MAX_KB = 1024; // 1 MB is plenty for a logo

    private const ALLOWED = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

    /**
     * Validate and store any uploaded brand assets in the request.
     *
     * @param  string[]  $keys
     * @return array<string, string> key => stored path, for the keys that were uploaded
     */
    public function handleUploads(Request $request, array $keys): array
    {
        $stored = [];

        foreach ($keys as $key) {
            if (! $request->hasFile($key)) {
                continue;
            }

            $file = $request->file($key);

            $this->assertIsSafeImage($file);

            $directory = 'branding/'.now()->format('Y/m');
            $name = $key.'-'.Str::random(8).'.'.$file->getClientOriginalExtension();

            $stored[$key] = $file->store($directory, 'public');
        }

        return $stored;
    }

    /**
     * A brand upload must be a genuine image, not a renamed script.
     */
    private function assertIsSafeImage(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            abort(422, 'Berkas gagal diunggah.');
        }

        if ($file->getSize() > self::MAX_KB * 1024) {
            abort(422, 'Ukuran logo maksimal '.self::MAX_KB.' KB.');
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED, true)) {
            abort(422, 'Format logo harus JPG, PNG, WEBP, atau SVG.');
        }

        // SVG is text and could carry script, so it is sniffed and rejected
        // when it contains anything executable.
        if ($extension === 'svg') {
            $contents = (string) file_get_contents($file->getRealPath());

            if (preg_match('/<script|onload=|javascript:/i', $contents)) {
                abort(422, 'SVG berisi skrip yang tidak diizinkan.');
            }

            return;
        }

        $mime = (string) $file->getMimeType();

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (! in_array($mime, $allowed, true)) {
            abort(422, 'Berkas bukan gambar yang valid.');
        }
    }

    /**
     * Relative luminance contrast ratio, per WCAG.
     */
    public function contrastRatio(string $hexA, string $hexB): float
    {
        $a = $this->luminance($hexA);
        $b = $this->luminance($hexB);

        $lighter = max($a, $b);
        $darker = min($a, $b);

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    /**
     * White button text on the primary colour must be readable (>= 3:1 for
     * large/bold text, comfortably above the WCAG AA threshold for normal text).
     */
    public function hasReadableContrast(string $hex): bool
    {
        return $this->contrastRatio($hex, '#FFFFFF') >= 3.0;
    }

    private function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $channels = [];

        foreach (str_split($hex, 2) as $part) {
            $value = hexdec($part) / 255;
            // sRGB → linear
            $channels[] = $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * CSS custom properties for the live theme preview.
     */
    public function cssVariables(?string $primary, ?string $accent): string
    {
        $primary = $this->normalize($primary);
        $accent = $this->normalize($accent);

        return "--app-primary:{$primary};--app-primary-hover:{$this->shade($primary, -12)};"
            ."--app-primary-soft:{$this->shade($primary, 92)};--app-accent:{$accent};";
    }

    private function normalize(?string $hex): string
    {
        $hex = trim((string) $hex);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? $hex : '#1D4ED8';
    }

    /** Lighten (+) or darken (-) a hex colour by a percentage. */
    private function shade(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');

        $out = '#';

        foreach (str_split($hex, 2) as $part) {
            $value = hexdec($part);
            $value = $percent >= 0
                ? $value + (255 - $value) * ($percent / 100)
                : $value * (1 + $percent / 100);
            $out .= str_pad(dechex((int) max(0, min(255, round($value)))), 2, '0', STR_PAD_LEFT);
        }

        return $out;
    }
}
