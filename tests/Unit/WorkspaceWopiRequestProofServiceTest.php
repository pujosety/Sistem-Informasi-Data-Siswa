<?php

namespace Tests\Unit;

use App\Services\WorkspaceWopiDiscoveryService;
use App\Services\WorkspaceWopiProofKeyVerifier;
use App\Services\WorkspaceWopiRequestProofService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkspaceWopiRequestProofServiceTest extends TestCase
{
    public function test_allows_missing_proof_keys_only_in_local_environment(): void
    {
        config(['app.env' => 'local']);
        $discovery = $this->mock(WorkspaceWopiDiscoveryService::class);
        $discovery->shouldReceive('proofKeys')->once()->andReturn([]);

        app(WorkspaceWopiRequestProofService::class, [
            'discovery' => $discovery,
            'verifier' => new WorkspaceWopiProofKeyVerifier(),
        ])->authorize(Request::create('/wopi/files/file?access_token=local-token'), 'local-token');

        $this->assertTrue(true);
    }

    public function test_fails_closed_when_non_local_discovery_has_no_proof_keys(): void
    {
        config(['app.env' => 'production']);
        $discovery = $this->mock(WorkspaceWopiDiscoveryService::class);
        $discovery->shouldReceive('proofKeys')->once()->andReturn([]);

        try {
            app(WorkspaceWopiRequestProofService::class, [
                'discovery' => $discovery,
                'verifier' => new WorkspaceWopiProofKeyVerifier(),
            ])->authorize(Request::create('/wopi/files/file?access_token=token'), 'token');
            self::fail('Expected non-local requests without discovery proof keys to be denied.');
        } catch (HttpException $exception) {
            self::assertSame(401, $exception->getStatusCode());
        }
    }

    public function test_passes_the_configured_absolute_url_and_proof_headers_to_verifier(): void
    {
        config(['app.env' => 'production']);
        $keys = ['modulus' => 'm', 'exponent' => 'e'];
        $discovery = $this->mock(WorkspaceWopiDiscoveryService::class);
        $discovery->shouldReceive('proofKeys')->once()->andReturn($keys);
        $discovery->shouldReceive('validatedWopiHostUrl')->once()->andReturn('https://wopi.example.test');
        $verifier = $this->mock(WorkspaceWopiProofKeyVerifier::class);
        $verifier->shouldReceive('verify')
            ->once()
            ->with(
                'access-token',
                'https://wopi.example.test/wopi/files/file?access_token=access-token',
                '635655897610773532',
                'proof-signature',
                'old-proof-signature',
                $keys,
            )
            ->andReturn(true);

        $request = Request::create('/wopi/files/file?access_token=access-token');
        $request->headers->set('X-WOPI-TimeStamp', '635655897610773532');
        $request->headers->set('X-WOPI-Proof', 'proof-signature');
        $request->headers->set('X-WOPI-ProofOld', 'old-proof-signature');

        app(WorkspaceWopiRequestProofService::class, [
            'discovery' => $discovery,
            'verifier' => $verifier,
        ])->authorize($request, 'access-token');

        $this->assertTrue(true);
    }

    public function test_rejects_requests_with_bad_proof_when_discovery_advertises_keys(): void
    {
        config(['app.env' => 'local']);
        $discovery = $this->mock(WorkspaceWopiDiscoveryService::class);
        $discovery->shouldReceive('proofKeys')->once()->andReturn(['modulus' => 'invalid', 'exponent' => 'invalid']);
        $discovery->shouldReceive('validatedWopiHostUrl')->once()->andReturn('http://localhost:8000');

        try {
            app(WorkspaceWopiRequestProofService::class, [
                'discovery' => $discovery,
                'verifier' => new WorkspaceWopiProofKeyVerifier(),
            ])->authorize(Request::create('/wopi/files/file?access_token=token'), 'token');
            self::fail('Expected an invalid proof to be denied when proof keys are advertised.');
        } catch (HttpException $exception) {
            self::assertSame(401, $exception->getStatusCode());
        }
    }
}
