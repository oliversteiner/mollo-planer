<?php

namespace App\Console\Commands;

use App\Assistant\AssistantError;
use App\Assistant\AssistantSchema;
use App\Assistant\ClaudeClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One small real call with the real answer schema (docs/fix-m6.md F10.3). The tests use a fake client and cannot see
 * that the API refuses a schema. Run it after every change of the assistant or its schema, and after a deploy (it writes
 * nothing, needs ANTHROPIC_API_KEY and costs a few tokens).
 */
#[Signature('assistant:ping')]
#[Description('Sends a small real request with the real schema to the Claude API')]
class AssistantPing extends Command
{
    public function handle(ClaudeClient $claude): int
    {
        try {
            $answer = $claude->ask('Answer very briefly.', [['role' => 'user', 'content' => 'Say hello. plan null, choices empty.']], AssistantSchema::answer());
        } catch (AssistantError $error) {
            $this->components->error($error->getMessage());
            $this->line('API: '.($claude->lastError ?? '-'));

            return self::FAILURE;
        }

        $this->components->info('OK: '.json_encode($answer, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
