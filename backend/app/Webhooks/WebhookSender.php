<?php

namespace App\Webhooks;

use App\Models\ProjectWebhook;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sends one announcement (docs/auftrag-m5.md §4.3). GitHub gets a repository_dispatch with the
 * token; a generic target gets a body signed with HMAC-SHA256. Ten seconds, no redirects.
 * The result carries a short code, never a header, a body or the secret.
 */
final class WebhookSender
{
    public const TIMEOUT = 10;

    /**
     * @return string|null null when it worked, else "timeout", "connection", "target" or "HTTP <status>"
     */
    public function send(ProjectWebhook $webhook, int $revision): ?string
    {
        try {
            $response = $webhook->kind === ProjectWebhook::GITHUB
                ? $this->github($webhook, $revision)
                : $this->generic($webhook, $revision);
        } catch (ConnectionException $error) {
            return Str::contains($error->getMessage(), ['timed out', 'timeout', 'Timeout']) ? 'timeout' : 'connection';
        } catch (UnreachableTarget) {
            return 'target';
        }

        $ok = $webhook->kind === ProjectWebhook::GITHUB ? $response->status() === 204 : $response->successful();

        return $ok ? null : 'HTTP '.$response->status();
    }

    private function github(ProjectWebhook $webhook, int $revision): Response
    {
        [$owner, $repository] = explode('/', $webhook->target, 2);

        return Http::withToken((string) $webhook->secret)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'mollo-planer',
            ])
            ->timeout(self::TIMEOUT)
            ->withoutRedirecting()
            ->post("https://api.github.com/repos/{$owner}/{$repository}/dispatches", [
                'event_type' => 'planer-export',
                'client_payload' => [
                    'project_id' => $webhook->project_id,
                    'revision' => $revision,
                    'app_url' => config('app.url'),
                ],
            ]);
    }

    private function generic(ProjectWebhook $webhook, int $revision): Response
    {
        if (! WebhookTarget::reachable((string) parse_url($webhook->target, PHP_URL_HOST))) {
            throw new UnreachableTarget;
        }

        $body = json_encode([
            'event' => 'planer.revision',
            'project_id' => $webhook->project_id,
            'revision' => $revision,
            'sent_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return Http::withHeaders([
            'X-Mollo-Event' => 'planer.revision',
            'X-Mollo-Signature' => 'sha256='.hash_hmac('sha256', $body, (string) $webhook->secret),
            'User-Agent' => 'mollo-planer',
        ])
            ->timeout(self::TIMEOUT)
            ->withoutRedirecting()
            ->withBody($body, 'application/json')
            ->post($webhook->target);
    }
}
