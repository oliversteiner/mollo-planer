<?php

namespace App\Assistant;

use Anthropic\Beta\Messages\BetaStopReason;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\NotFoundException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\UnprocessableEntityException;
use Illuminate\Support\Facades\Log;

/**
 * The one place that talks to the Claude API (docs/auftrag-m6.md §4.2): the key from the configuration, structured
 * output with the schema of the answer, and every failure turned into a sentence for the person. The tests replace
 * this class, so they need no network.
 */
class ClaudeClient
{
    /** The message of the last failed call as the API gave it (for `assistant:ping`; the person never sees it). */
    public ?string $lastError = null;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed> The decoded JSON answer
     *
     * @throws AssistantError
     */
    public function ask(string $system, array $messages, array $schema): array
    {
        $key = config('services.anthropic.key');

        if (! is_string($key) || $key === '') {
            throw new AssistantError(__('assistant.not_configured'));
        }

        // A whole plan takes Claude a while; PHP must not stop it halfway.
        set_time_limit(300);

        try {
            $message = (new Client(apiKey: $key))->beta->messages->create(
                maxTokens: 32000,
                messages: $messages,
                model: config('services.anthropic.model'),
                cacheControl: ['type' => 'ephemeral'],
                fallbacks: 'default',
                outputConfig: [
                    'effort' => 'medium',
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                system: $system,
                thinking: ['type' => 'adaptive'],
                betas: ['server-side-fallback-2026-07-01'],
                requestOptions: ['timeout' => 280.0, 'maxRetries' => 1],
            );
        } catch (AuthenticationException|PermissionDeniedException $exception) {
            $this->lastError = $exception->getMessage();
            Log::warning('Assistant: Claude API refused the key', ['error' => $exception->getMessage()]);

            throw new AssistantError(__('assistant.invalid_key'));
        } catch (BadRequestException|NotFoundException|UnprocessableEntityException $exception) {
            // Our request was refused (for example a schema that is too large): that is our problem, not the network's.
            $this->lastError = $exception->getMessage();
            Log::warning('Assistant: Claude API refused the request', ['error' => $exception->getMessage()]);

            throw new AssistantError(__('assistant.technical'));
        } catch (APIStatusException|APIConnectionException $exception) {
            $this->lastError = $exception->getMessage();
            Log::warning('Assistant: Claude API failed', ['error' => $exception->getMessage()]);

            throw new AssistantError(__('assistant.unavailable'));
        }

        $stopReason = $message->stopReason instanceof BetaStopReason ? $message->stopReason->value : (string) $message->stopReason;

        if ($stopReason === 'refusal') {
            throw new AssistantError(__('assistant.refused'));
        }

        if ($stopReason === 'max_tokens') {
            throw new AssistantError(__('assistant.too_large'));
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $answer = json_decode($block->text, true);

                if (is_array($answer)) {
                    return $answer;
                }
            }
        }

        throw new AssistantError(__('assistant.unavailable'));
    }
}
