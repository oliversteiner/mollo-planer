<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\ProjectWebhook;
use App\Webhooks\WebhookFailed;
use App\Webhooks\WebhookSender;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Tells the webhook of a project that its planer file has a new revision (docs/auftrag-m5.md
 * §4.3). Bundled: one waiting job per project (unique until it starts), delayed so that there
 * are at least five minutes between two sends. It reads the revision when it runs, so changes
 * that came in the meantime are one announcement; the same revision as the last send sends
 * nothing. Three more tries after 1, 5 and 15 minutes; then the failure shows in the settings.
 */
class SendPlanerWebhook implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public function __construct(public readonly int $projectId) {}

    public function uniqueId(): string
    {
        return (string) $this->projectId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(WebhookSender $sender): void
    {
        $webhook = ProjectWebhook::query()->where('project_id', $this->projectId)->first();
        $revision = Project::query()->whereKey($this->projectId)->value('planer_revision');

        if ($webhook === null || $revision === null || (int) $revision === $webhook->last_sent_revision) {
            return;
        }

        $error = $sender->send($webhook, (int) $revision);

        if ($error !== null) {
            throw new WebhookFailed($error);
        }

        $webhook->forceFill(['last_sent_revision' => (int) $revision, 'last_sent_at' => now(), 'last_failed_at' => null, 'last_error' => null])->save();
    }

    /** All tries are used up: show it in the settings. No mail. */
    public function failed(Throwable $error): void
    {
        ProjectWebhook::query()->where('project_id', $this->projectId)->update([
            'last_failed_at' => now(),
            'last_error' => $error instanceof WebhookFailed ? $error->getMessage() : 'error',
        ]);
    }
}
