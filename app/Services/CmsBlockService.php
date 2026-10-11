<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Normalizes the finite, non-executable block schema stored in cms_posts.blocks.
 *
 * Text remains plain data and is escaped by the Blade renderer. The service
 * rejects unknown types/attributes so a saved block cannot smuggle arbitrary
 * renderer instructions into the public page.
 */
class CmsBlockService
{
    private const MAX_BLOCKS = 40;

    private const SCHEMA_VERSION = 1;

    public function normalize(mixed $blocks): array
    {
        if ($blocks === null || $blocks === '') {
            return [];
        }

        if (! is_array($blocks)) {
            $this->invalid('blocks', 'Daftar blok harus berupa array.');
        }

        if (count($blocks) > self::MAX_BLOCKS) {
            $this->invalid('blocks', 'Halaman hanya dapat memuat paling banyak 40 blok.');
        }

        $normalized = [];

        foreach (array_values($blocks) as $index => $block) {
            $field = "blocks.{$index}";

            if (! is_array($block)) {
                $this->invalid($field, 'Blok tidak valid.');
            }

            $type = $block['type'] ?? null;

            if (! is_string($type) || ! in_array($type, ['heading', 'paragraph', 'button'], true)) {
                $this->invalid("{$field}.type", 'Jenis blok tidak didukung.');
            }

            $version = filter_var($block['version'] ?? self::SCHEMA_VERSION, FILTER_VALIDATE_INT);

            if ($version !== self::SCHEMA_VERSION) {
                $this->invalid("{$field}.version", 'Versi skema blok tidak didukung.');
            }

            $allowed = match ($type) {
                'heading' => ['type', 'version', 'level', 'text'],
                'paragraph' => ['type', 'version', 'text'],
                'button' => ['type', 'version', 'text', 'url'],
            };

            if (array_diff(array_keys($block), $allowed) !== []) {
                $this->invalid($field, 'Blok memuat atribut yang tidak didukung.');
            }

            $text = $block['text'] ?? null;
            $maxLength = $type === 'button' ? 120 : 5000;

            if (! is_string($text) || trim($text) === '' || Str::length($text) > $maxLength) {
                $this->invalid("{$field}.text", 'Teks blok wajib diisi dan tidak melebihi batas.');
            }

            $text = trim($text);

            if ($type === 'heading') {
                $level = filter_var($block['level'] ?? null, FILTER_VALIDATE_INT);

                if (! in_array($level, [2, 3, 4], true)) {
                    $this->invalid("{$field}.level", 'Tingkat judul harus H2, H3, atau H4.');
                }

                $normalized[] = ['type' => 'heading', 'version' => self::SCHEMA_VERSION, 'level' => $level, 'text' => $text];

                continue;
            }

            if ($type === 'paragraph') {
                $normalized[] = ['type' => 'paragraph', 'version' => self::SCHEMA_VERSION, 'text' => $text];

                continue;
            }

            $url = $block['url'] ?? null;

            if (! is_string($url) || ! $this->isSafeUrl(trim($url))) {
                $this->invalid("{$field}.url", 'Tautan harus berupa path internal atau URL HTTPS yang aman.');
            }

            $normalized[] = ['type' => 'button', 'version' => self::SCHEMA_VERSION, 'text' => $text, 'url' => trim($url)];
        }

        return $normalized;
    }

    public function forPublicRender(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        $safe = [];

        foreach (array_slice(array_values($blocks), 0, self::MAX_BLOCKS) as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;
            $allowed = match ($type) {
                'heading' => ['type', 'version', 'level', 'text'],
                'paragraph' => ['type', 'version', 'text'],
                'button' => ['type', 'version', 'text', 'url'],
                default => null,
            };

            if ($allowed === null) {
                continue;
            }

            try {
                $normalized = $this->normalize([array_intersect_key($block, array_flip($allowed))]);
                $safe[] = $normalized[0];
            } catch (ValidationException) {
                // A malformed legacy row is omitted rather than breaking a public page.
            }
        }

        return $safe;
    }

    public function plainText(array $blocks): string
    {
        return trim(implode(' ', array_filter(array_map(
            static fn (array $block): string => (string) ($block['text'] ?? ''),
            $blocks,
        ))));
    }

    private function isSafeUrl(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x20\\\\]/', $url)) {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && filled($parts['host'] ?? null);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
