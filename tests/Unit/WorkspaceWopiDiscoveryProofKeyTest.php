<?php

namespace Tests\Unit;

use App\Services\WorkspaceWopiDiscoveryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkspaceWopiDiscoveryProofKeyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.env' => 'local',
            'workspace.collabora.internal_url' => 'http://collabora:9980',
            'workspace.collabora.public_url' => 'http://localhost:9980',
        ]);
        Cache::forget('workspace.collabora.discovery.'.sha1('http://collabora:9980/hosting/discovery'));
    }

    public function test_extracts_current_and_old_proof_keys_from_discovery(): void
    {
        Http::fake([
            'http://collabora:9980/hosting/discovery' => Http::response(
                '<wopi-discovery><proof-key modulus="current-mod" exponent="AQAB" oldmodulus="old-mod" oldexponent="AQAB" /></wopi-discovery>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
        ]);

        self::assertSame([
            'modulus' => 'current-mod',
            'exponent' => 'AQAB',
            'oldmodulus' => 'old-mod',
            'oldexponent' => 'AQAB',
        ], app(WorkspaceWopiDiscoveryService::class)->proofKeys());
    }

    public function test_returns_no_proof_keys_when_discovery_does_not_advertise_any(): void
    {
        Http::fake([
            'http://collabora:9980/hosting/discovery' => Http::response(
                '<wopi-discovery><net-zone name="external-http" /></wopi-discovery>',
                200,
                ['Content-Type' => 'application/xml'],
            ),
        ]);

        self::assertSame([], app(WorkspaceWopiDiscoveryService::class)->proofKeys());
    }
}
