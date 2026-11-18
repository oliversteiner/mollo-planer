<?php

namespace App\Webhooks;

use App\Jobs\SendPlanerWebhook;
use App\Models\ProjectWebhook;

/**
 * Called after the revision of projects went up (App\Planer\PlanerRevision): a project with
 * a webhook gets one job, delayed so that at least five minutes lie between two sends.
 */
final class PlanerWebhooks
{
    public const MINIMUM_DELAY = 60;

    public const SPACING = 300;

    /**
     * @param  list<int>  $projectIds
     */
    public function changed(array $projectIds): void
    {
        $webhooks = ProjectWebhook::query()->whereIn('project_id', $projectIds)->get(['project_id', 'last_sent_at']);

        foreach ($webhooks as $webhook) {
            $wait = $webhook->last_sent_at === null ? 0 : (int) now()->diffInSeconds($webhook->last_sent_at->copy()->addSeconds(self::SPACING), false);

            SendPlanerWebhook::dispatch($webhook->project_id)->delay(now()->addSeconds(max(self::MINIMUM_DELAY, $wait)));
        }
    }
}
