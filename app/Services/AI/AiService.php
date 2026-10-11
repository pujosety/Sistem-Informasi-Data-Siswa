<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AiProvider;
use App\Services\AI\Providers\AiProviderResponse;

class AiService
{
    public function __construct(private readonly AiProvider $provider) {}

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function chat(array $messages, array $options = []): AiProviderResponse
    {
        return $this->provider->chat($messages, $options);
    }

    public function providerName(): string
    {
        return $this->provider->name();
    }

    public function model(): string
    {
        return $this->provider->model();
    }
}
