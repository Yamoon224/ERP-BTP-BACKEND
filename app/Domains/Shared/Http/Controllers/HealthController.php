<?php

namespace App\Domains\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sonde de sante consommee par les tests de deploiement et l orchestrateur.
 *
 * La connectivite base est verifiee explicitement : une API qui repond 200
 * alors que sa base est injoignable est le pire des signaux pour un
 * deploiement automatise.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $databaseUp = true;
        $error = null;

        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            $databaseUp = false;
            $error = $exception->getMessage();
        }

        return response()->json([
            'status' => $databaseUp ? 'ok' : 'degraded',
            'checks' => [
                'database' => $databaseUp ? 'ok' : 'unreachable',
            ],
            'matching_engine_version' => config('matching.engine_version'),
            'error' => $error,
            'timestamp' => now()->toIso8601String(),
        ], $databaseUp ? 200 : 503);
    }
}
