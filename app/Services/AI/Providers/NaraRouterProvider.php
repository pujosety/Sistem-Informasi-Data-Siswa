<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class AiProviderResponse
{
    public function __construct(
        public readonly string $content,
        public readonly int $status,
        public readonly int $latencyMs,
        public readonly array $usage = [],
        public readonly array $rawMeta = [],
    ) {}
}

class NaraRouterProvider implements AiProvider
{
    public function __construct(private readonly array $config) {}

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function chat(array $messages, array $options = []): AiProviderResponse
    {
        $apiKey = trim((string) ($this->config['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new RuntimeException(sprintf('%s API key is not configured.', $this->providerName()));
        }

        $started = microtime(true);
        $payload = [
            'model' => $this->model(),
            'messages' => array_values($messages),
            'temperature' => (float) ($options['temperature'] ?? $this->config['temperature'] ?? 0.2),
            'max_tokens' => (int) ($options['max_tokens'] ?? $this->config['max_tokens'] ?? 700),
        ];

        try {
            $request = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout((int) ($this->config['connect_timeout'] ?? 8))
                ->timeout((int) ($this->config['timeout'] ?? 35));

            $headers = array_filter((array) ($this->config['headers'] ?? []), static fn ($value) => filled($value));
            if ($headers !== []) {
                $request = $request->withHeaders($headers);
            }

            $caBundle = (string) ($this->config['ca_bundle'] ?? '');
            if ($caBundle !== '' && is_file($caBundle)) {
                $request = $request->withOptions(['verify' => $caBundle]);
            }

            $response = $request->post(rtrim((string) $this->config['base_url'], '/').'/chat/completions', $payload);
        } catch (\Throwable $e) {
            report($e);
            throw new RuntimeException(sprintf('%s is unavailable.', $this->providerName()), 0, $e);
        }

        $latencyMs = (int) round((microtime(true) - $started) * 1000);
        $status = $response->status();

        if ($response->failed()) {
            report(new RuntimeException(sprintf(
                '%s request failed with HTTP %d: %s',
                $this->providerName(),
                $status,
                Str::limit((string) $response->body(), 500)
            )));

            throw new RuntimeException($this->friendlyFailure($status));
        }

        $json = $response->json();
        $content = trim((string) Arr::get($json, 'choices.0.message.content', ''));
        if ($content === '') {
            report(new RuntimeException(sprintf('%s returned no message content.', $this->providerName())));
            throw new RuntimeException(sprintf('%s returned an empty response.', $this->providerName()));
        }

        return new AiProviderResponse(
            content: $content,
            status: $status,
            latencyMs: $latencyMs,
            usage: (array) Arr::get($json, 'usage', []),
            rawMeta: [
                'id' => Arr::get($json, 'id'),
                'finish_reason' => Arr::get($json, 'choices.0.finish_reason'),
            ],
        );
    }

    public function name(): string
    {
        return $this->providerName();
    }

    public function model(): string
    {
        return (string) ($this->config['model'] ?? 'agnes-2.5-flash');
    }

    private function friendlyFailure(int $status): string
    {
        $provider = $this->providerName();

        return match ($status) {
            401, 403 => $provider.' authentication failed.',
            408, 504 => $provider.' timed out.',
            429 => $provider.' rate limit reached.',
            422 => $provider.' rejected the request.',
            404 => $provider.' endpoint was not found.',
            500, 502, 503 => $provider.' is temporarily unavailable.',
            default => $provider.' request failed.',
        };
    }

    private function providerName(): string
    {
        return (string) ($this->config['name'] ?? 'NaraRouter');
    }
}
