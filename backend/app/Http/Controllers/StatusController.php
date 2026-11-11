<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Health check for the status page: does the application and the database
 * answer. Says nothing else (no versions, no database name, no error details).
 */
class StatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $this->databaseAnswers() ? 'ok' : 'error';

        return response()->json([
            'backend' => 'ok',
            'database' => $database,
            'checkedAt' => now()->toIso8601String(),
        ], $database === 'ok' ? 200 : 503);
    }

    private function databaseAnswers(): bool
    {
        try {
            DB::connection()->select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
