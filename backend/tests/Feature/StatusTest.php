<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatusTest extends TestCase
{
    public function test_status_reports_backend_and_database_without_any_details(): void
    {
        $this->getJson('/status')
            ->assertOk()
            ->assertJsonPath('backend', 'ok')
            ->assertJsonPath('database', 'ok')
            ->assertJsonPath('apiVersion', 'v1')
            ->assertJsonStructure(['backend', 'database', 'checkedAt', 'apiVersion', 'appVersion', 'siteName'])
            ->assertJsonCount(6);
    }

    public function test_the_frontend_may_read_the_status_across_origins(): void
    {
        $this->getJson('/status', ['Origin' => 'http://localhost:3000'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_status_needs_no_login(): void
    {
        $this->getJson('/status')->assertOk()->assertJsonMissingPath('version');
    }

    public function test_status_answers_503_when_the_database_does_not_answer(): void
    {
        DB::shouldReceive('connection')->andThrow(new \RuntimeException('database is down'));

        $this->getJson('/status')
            ->assertStatus(503)
            ->assertJson(['backend' => 'ok', 'database' => 'error'])
            ->assertJsonMissingPath('message');
    }
}
