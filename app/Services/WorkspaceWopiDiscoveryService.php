<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

class WorkspaceWopiDiscoveryService
{
    public function editActionUrl(string $extension): string
    {
        $extension = strtolower(ltrim(trim($extension), '.'));
        abort_unless(
            preg_match('/\A[a-z0-9]{1,12}\z/D', $extension) === 1
                && in_array($extension, config('workspace.wopi.supported_extensions', []), true),
            415,
        );

        $internalUrl = rtrim($this->validateConfiguredUrl((string) config('workspace.collabora.internal_url')), '/');
        $publicUrl = rtrim($this->validateConfiguredUrl((string) config('workspace.collabora.public_url')), '/');
        $discoveryUrl = $internalUrl.'/hosting/discovery';
        $cacheKey = 'workspace.collabora.discovery.'.sha1($discoveryUrl);
        $xmlBody = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($discoveryUrl): string {
            try {
                $response = Http::accept('application/xml')->timeout(5)->get($discoveryUrl);
            } catch (Throwable) {
                abort(503, 'Collabora discovery is unavailable.');
            }

            abort_unless($response->successful(), 503, 'Collabora discovery is unavailable.');

            return $response->body();
        });

        $document = simplexml_load_string($xmlBody, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
        abort_unless($document instanceof SimpleXMLElement, 503, 'Collabora returned invalid discovery XML.');

        $actionUrl = null;
        foreach ($document->xpath('//action') ?: [] as $action) {
            if (strtolower((string) $action['name']) === 'edit'
                && strtolower((string) $action['ext']) === $extension) {
                $actionUrl = (string) $action['urlsrc'];
                break;
            }
        }

        abort_unless(is_string($actionUrl) && $actionUrl !== '', 415, 'Collabora does not advertise an edit action for this file type.');

        return $this->toBrowserOrigin($actionUrl, $internalUrl, $publicUrl);
    }

    /** @return array{modulus: string, exponent: string, oldmodulus: string, oldexponent: string}|array{} */
    public function proofKeys(): array
    {
        $internalUrl = rtrim($this->validateConfiguredUrl((string) config('workspace.collabora.internal_url')), '/');
        $discoveryUrl = $internalUrl.'/hosting/discovery';
        $cacheKey = 'workspace.collabora.discovery.'.sha1($discoveryUrl);
        $xmlBody = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($discoveryUrl): string {
            try {
                $response = Http::accept('application/xml')->timeout(5)->get($discoveryUrl);
            } catch (Throwable) {
                abort(503, 'Collabora discovery is unavailable.');
            }

            abort_unless($response->successful(), 503, 'Collabora discovery is unavailable.');

            return $response->body();
        });

        $document = simplexml_load_string($xmlBody, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
        abort_unless($document instanceof SimpleXMLElement, 503, 'Collabora returned invalid discovery XML.');
        $proofKeyNodes = $document->xpath('//proof-key') ?: [];
        if ($proofKeyNodes === []) {
            return [];
        }

        $attributes = $proofKeyNodes[0]->attributes();

        return [
            'modulus' => (string) ($attributes['modulus'] ?? ''),
            'exponent' => (string) ($attributes['exponent'] ?? ''),
            'oldmodulus' => (string) ($attributes['oldmodulus'] ?? ''),
            'oldexponent' => (string) ($attributes['oldexponent'] ?? ''),
        ];
    }

    public function validatedWopiHostUrl(): string
    {
        return rtrim($this->validateConfiguredUrl((string) config('workspace.wopi.host_url')), '/');
    }

    private function toBrowserOrigin(string $actionUrl, string $internalUrl, string $publicUrl): string
    {
        $action = $this->validateActionUrl($actionUrl);
        $internal = parse_url($internalUrl);
        $public = parse_url($publicUrl);
        abort_unless(is_array($action) && is_array($internal) && is_array($public), 503);
        abort_unless(isset($public['scheme'], $public['host']), 503);

        $publicOrigin = $this->origin($public);
        $internalOrigin = $this->origin($internal);

        if (! isset($action['host'])) {
            $path = (string) ($action['path'] ?? '');

            return rtrim($publicOrigin, '/').'/'.ltrim($path, '/').$this->suffix($action, $actionUrl);
        }

        $actionOrigin = $this->origin($action);
        abort_unless($actionOrigin === $internalOrigin || $actionOrigin === $publicOrigin, 503);
        $origin = $actionOrigin === $internalOrigin ? $publicOrigin : $actionOrigin;

        return rtrim($origin, '/').(string) ($action['path'] ?? '/')
            .$this->suffix($action, $actionUrl);
    }

    /** @return array<string, mixed> */
    private function validateActionUrl(string $url): array
    {
        $parts = parse_url(trim($url));
        abort_unless(is_array($parts) && $url !== '', 503);
        abort_unless(! array_key_exists('user', $parts) && ! array_key_exists('pass', $parts), 503);
        abort_unless(! array_key_exists('fragment', $parts), 503);

        if (isset($parts['scheme']) || isset($parts['host'])) {
            abort_unless(isset($parts['scheme'], $parts['host']), 503);
            $this->assertAllowedScheme((string) $parts['scheme']);
            $this->assertValidPort($parts);
        } else {
            $path = (string) ($parts['path'] ?? '');
            abort_unless(str_starts_with($path, '/') && ! str_starts_with($path, '//'), 503);
        }

        return $parts;
    }

    private function validateConfiguredUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        abort_unless(is_array($parts) && isset($parts['scheme'], $parts['host']), 503);
        abort_unless(! array_key_exists('user', $parts) && ! array_key_exists('pass', $parts), 503);
        abort_unless(! array_key_exists('query', $parts) && ! array_key_exists('fragment', $parts), 503);
        abort_unless(preg_match('/[\\x00-\\x20\\x7f]/', (string) $parts['host']) !== 1, 503);
        $this->assertAllowedScheme((string) $parts['scheme']);
        $this->assertValidPort($parts);

        return trim($url);
    }

    private function assertAllowedScheme(string $scheme): void
    {
        $scheme = strtolower($scheme);
        $environment = (string) config('app.env');
        $localHttpAllowed = $environment === 'local'
            || ($environment === 'testing' && app()->runningUnitTests());
        abort_unless($scheme === 'https' || ($localHttpAllowed && $scheme === 'http'), 503);
    }

    /** @param array<string, mixed> $parts */
    private function assertValidPort(array $parts): void
    {
        if (array_key_exists('port', $parts)) {
            $port = $parts['port'];
            abort_unless(is_int($port) && $port >= 1 && $port <= 65535, 503);
        }
    }

    /** @param array<string, mixed> $parts */
    private function origin(array $parts): string
    {
        abort_unless(isset($parts['scheme'], $parts['host']), 503);
        $origin = strtolower((string) $parts['scheme']).'://'.strtolower((string) $parts['host']);

        if (isset($parts['port'])) {
            $origin .= ':'.(int) $parts['port'];
        }

        return $origin;
    }

    /** @param array<string, mixed> $parts */
    private function suffix(array $parts, string $original): string
    {
        $suffix = array_key_exists('query', $parts)
            ? '?'.(string) $parts['query']
            : (str_contains($original, '?') ? '?' : '');

        if (isset($parts['fragment'])) {
            $suffix .= '#'.$parts['fragment'];
        }

        return $suffix;
    }
}
