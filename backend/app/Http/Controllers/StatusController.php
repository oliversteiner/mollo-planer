<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Health check for the status page: does the application and the database
 * answer, plus the versions of the API and the app (public, no secrets).
 * No database name and no error details.
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
            'apiVersion' => (string) config('api.version'),
            'appVersion' => (string) config('api.app_version'),
            'siteName' => (string) config('api.site_name'),
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
