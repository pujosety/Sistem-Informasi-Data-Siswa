<?php

namespace Tests\Unit;

use App\Services\AI\Providers\NaraRouterProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NaraRouterProviderTest extends TestCase
{
    public function test_it_parses_an_openai_compatible_response(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame('https://router.test/v1/chat/completions', $request->url());
            $this->assertSame('Bearer test-key', $request->header('Authorization')[0]);
            $this->assertSame('agnes-2.5-flash', $request->data()['model']);

            return Http::response([
                'id' => 'test-response',
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'OK'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['total_tokens' => 3],
            ], 200);
        });

        $result = (new NaraRouterProvider([
            'base_url' => 'https://router.test/v1',
            'api_key' => 'test-key',
            'model' => 'agnes-2.5-flash',
            'temperature' => 0.2,
            'max_tokens' => 16,
            'connect_timeout' => 1,
            'timeout' => 2,
        ]))->chat([
            ['role' => 'user', 'content' => 'test'],
        ]);

        $this->assertSame('OK', $result->content);
        $this->assertSame(200, $result->status);
        $this->assertSame('test-response', $result->rawMeta['id']);
    }

    public function test_missing_api_key_fails_before_network_access(): void
    {
        Http::fake();

        $this->expectExceptionMessage('API key is not configured');

        (new NaraRouterProvider([
            'base_url' => 'https://router.test/v1',
            'api_key' => '',
            'model' => 'agnes-2.5-flash',
        ]))->chat([
            ['role' => 'user', 'content' => 'test'],
        ]);

        Http::assertNothingSent();
    }
}
