<?php

namespace App\Services;

use Illuminate\Http\Request;
use Throwable;

class WorkspaceWopiRequestProofService
{
    public function __construct(
        private readonly WorkspaceWopiDiscoveryService $discovery,
        private readonly WorkspaceWopiProofKeyVerifier $verifier,
    ) {}

    public function authorize(Request $request, string $accessToken): void
    {
        $environment = (string) config('app.env');
        $localDevelopment = $environment === 'local'
            || ($environment === 'testing' && app()->runningUnitTests());

        try {
            $keys = $this->discovery->proofKeys();
        } catch (Throwable $error) {
            if ($localDevelopment) {
                return;
            }

            throw $error;
        }

        if ($keys === []) {
            abort_unless($localDevelopment, 401);

            return;
        }

        $url = $this->discovery->validatedWopiHostUrl().$request->getRequestUri();
        $valid = $this->verifier->verify(
            $accessToken,
            $url,
            $request->header('X-WOPI-TimeStamp'),
            $request->header('X-WOPI-Proof'),
            $request->header('X-WOPI-ProofOld'),
            $keys,
        );

        abort_unless($valid, 401);
    }
}
