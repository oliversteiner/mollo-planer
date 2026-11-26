<?php

namespace Tests\Feature\Assistant;

use App\Assistant\AssistantPrompt;
use App\Assistant\AssistantRequest;
use App\Assistant\ClaudeClient;
use App\Assistant\PlanTargetKind;
use Tests\Feature\GraphQL\GraphQLTestCase;

/**
 * The fixed part of the assistant's prompt (Auftrag 43 D) is a block of its own with a cache breakpoint after it, so the Claude
 * API can read it from its prompt cache for every request of the same kind and language.
 */
class PromptCachingTest extends GraphQLTestCase
{
    public function test_the_instructions_are_the_same_for_every_request_of_one_kind_and_language(): void
    {
        $prompt = app(AssistantPrompt::class);
        $first = $prompt->build($this->request('owner', 'Plane etwas', $this->project));
        $second = $prompt->build($this->request('member', 'Etwas ganz anderes', null));

        $this->assertNotSame($first['system'], $second['system'], 'the rest changes (person, project, text)');
        $this->assertSame($first['cacheable'], $second['cacheable']);
        $this->assertStringStartsWith($first['cacheable'], $first['system']);
        $this->assertGreaterThan(2000, strlen($first['cacheable']), 'worth a cache entry');
    }

    public function test_another_target_kind_or_language_has_its_own_instructions(): void
    {
        $prompt = app(AssistantPrompt::class);

        $this->assertNotSame(
            $prompt->build($this->request('owner', 'x', $this->project, PlanTargetKind::TASK))['cacheable'],
            $prompt->build($this->request('owner', 'x', $this->project, PlanTargetKind::MILESTONE))['cacheable'],
        );
        $this->assertNotSame(
            $prompt->build($this->request('owner', 'x', $this->project, null, 'de'))['cacheable'],
            $prompt->build($this->request('owner', 'x', $this->project, null, 'en'))['cacheable'],
        );
    }

    public function test_the_system_prompt_goes_to_the_api_as_two_blocks_with_a_breakpoint_after_the_first(): void
    {
        $blocks = ClaudeClient::systemBlocks("FIXED\n\nchanging part", 'FIXED');

        $this->assertSame([
            ['type' => 'text', 'text' => 'FIXED', 'cacheControl' => ['type' => 'ephemeral']],
            ['type' => 'text', 'text' => 'changing part'],
        ], $blocks);
    }

    public function test_without_a_usable_prefix_the_prompt_stays_one_string(): void
    {
        $this->assertSame('whole', ClaudeClient::systemBlocks('whole', null));
        $this->assertSame('whole', ClaudeClient::systemBlocks('whole', ''));
        $this->assertSame('whole', ClaudeClient::systemBlocks('whole', 'other'));
        $this->assertSame([['type' => 'text', 'text' => 'only', 'cacheControl' => ['type' => 'ephemeral']]], ClaudeClient::systemBlocks('only', 'only'));
    }

    private function request(string $as, string $text, mixed $project, ?PlanTargetKind $kind = null, string $locale = 'de'): AssistantRequest
    {
        return new AssistantRequest($this->users[$as], [['role' => 'user', 'content' => $text]], $project, $kind, null, null, null, $locale);
    }
}
