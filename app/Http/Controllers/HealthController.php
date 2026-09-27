<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Deployment health endpoint.
 *
 * Exists because the production failure mode was silent: a wrong DB_CONNECTION
 * looked like a healthy app until a page that needed the database was opened.
 * A platform health probe hitting /up only proves PHP booted.
 *
 * This reports three distinct states so a deploy can be judged automatically:
 *
 *   ok       — booted AND the database answered AND core tables exist
 *   degraded — booted, but the database is unusable (still HTTP 200, because
 *              the app itself is serving; the body carries the truth)
 *   booting  — reached before installation completed
 *
 * It deliberately exposes NO credentials, no host, no driver name, no stack
 * trace. Only whether the app can talk to its database.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $this->databaseStatus();

        $status = $database['state'] === 'ok' ? 'ok' : ($database['state'] === 'degraded' ? 'degraded' : 'ok');

        return response()->json([
            'status' => $status,
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'installed' => $database['installed'],
            'database' => [
                'state' => $database['state'],
                'tables' => $database['tables'],
            ],
            'time' => now()->toIso8601String(),
        ], 200, [
            // A probe must not be served from any cache.
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @return array{state: string, installed: bool, tables: bool}
     */
    private function databaseStatus(): array
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            // Deliberately no message: the driver/host would leak the
            // configuration, and a public endpoint should not be an oracle.
            report($e);

            return ['state' => 'unavailable', 'installed' => false, 'tables' => false];
        }

        try {
            $tables = Schema::hasTable('settings')
                && Schema::hasTable('users')
                && Schema::hasTable('cache')
                && Schema::hasTable('sessions');
        } catch (Throwable $e) {
            report($e);

            return ['state' => 'degraded', 'installed' => false, 'tables' => false];
        }

        if (! $tables) {
            return ['state' => 'booting', 'installed' => false, 'tables' => false];
        }

        return ['state' => 'ok', 'installed' => true, 'tables' => true];
    }
}
