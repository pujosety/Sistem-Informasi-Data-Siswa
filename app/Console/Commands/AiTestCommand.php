<?php

namespace App\Console\Commands;

use App\Services\AI\AiService;
use Illuminate\Console\Command;
use Throwable;

class AiTestCommand extends Command
{
    protected $signature = 'ai:test {--message=Reply with exactly: OK : Test prompt sent to the configured AI provider}';

    protected $description = 'Test the configured LYFLA AI provider without exposing credentials';

    public function handle(AiService $ai): int
    {
        $message = (string) $this->option('message');
        $started = microtime(true);

        try {
            $result = $ai->chat([
                [
                    'role' => 'system',
                    'content' => 'You are a connectivity test. Reply with exactly: OK',
                ],
                ['role' => 'user', 'content' => $message],
            ], ['temperature' => 0, 'max_tokens' => 16]);
        } catch (Throwable $e) {
            $this->error('Status   : Failed');
            $this->line('Error    : '.$e->getMessage());
            $this->line('Latency  : '.(int) round((microtime(true) - $started) * 1000).' ms');

            return self::FAILURE;
        }

        $this->line('Provider : '.$ai->providerName());
        $this->line('Model    : '.$ai->model());
        $this->line('Status   : Connected');
        $this->line('Latency  : '.$result->latencyMs.' ms');
        $this->line('Response : '.trim($result->content));

        return self::SUCCESS;
    }
}
