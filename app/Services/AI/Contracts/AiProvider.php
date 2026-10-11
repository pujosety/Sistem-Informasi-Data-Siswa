<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\Providers\AiProviderResponse;

interface AiProvider
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function chat(array $messages, array $options = []): AiProviderResponse;

    public function name(): string;

    public function model(): string;
}
