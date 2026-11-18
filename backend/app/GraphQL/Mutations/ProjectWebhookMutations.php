<?php

namespace App\GraphQL\Mutations;

use App\Models\Project;
use App\Models\ProjectWebhook;
use App\Webhooks\WebhookSender;
use App\Webhooks\WebhookTarget;
use Illuminate\Validation\ValidationException;

/**
 * The webhook of a project (docs/auftrag-m5.md §4.2). The right (project.update) and the archive
 * are checked by @can on the field. None of this changes the plan, so the revision stays.
 * The secret comes in and is never given back.
 */
class ProjectWebhookMutations
{
    private const SECRET_MAX = 500;

    public function __construct(private readonly WebhookSender $sender) {}

    /**
     * @param  array{projectId: int|string, input: array{kind: string, target: string, secret?: string|null}}  $args
     *
     * @throws ValidationException
     */
    public function set(mixed $root, array $args): Project
    {
        $project = Project::query()->findOrFail($args['projectId']);
        $input = $args['input'];
        $kind = $input['kind'];
        $target = trim($input['target']);
        $secret = isset($input['secret']) ? trim($input['secret']) : '';
        $webhook = $project->webhook;

        $problem = WebhookTarget::problem($kind, $target);

        if ($problem !== null) {
            throw ValidationException::withMessages(['input.target' => __("planer.webhook.{$problem}")]);
        }

        // A new webhook needs a secret; another kind needs a new one, too (a GitHub token is no signing secret).
        if ($secret === '' && ($webhook === null || $webhook->kind !== $kind)) {
            throw ValidationException::withMessages(['input.secret' => __('planer.webhook.secret_required')]);
        }

        if (mb_strlen($secret) > self::SECRET_MAX) {
            throw ValidationException::withMessages(['input.secret' => __('planer.webhook.secret_long')]);
        }

        $webhook ??= new ProjectWebhook;
        $webhook->fill(['kind' => $kind, 'target' => $target]);

        if ($secret !== '') {
            $webhook->secret = $secret;
        }

        // What was sent before belongs to the old target.
        if ($webhook->isDirty(['kind', 'target'])) {
            $webhook->forceFill(['last_sent_revision' => null, 'last_sent_at' => null, 'last_failed_at' => null, 'last_error' => null]);
        }

        $project->webhook()->save($webhook);

        return $project->refresh();
    }

    /**
     * @param  array{projectId: int|string}  $args
     */
    public function remove(mixed $root, array $args): Project
    {
        $project = Project::query()->findOrFail($args['projectId']);
        $project->webhook()->delete();

        return $project->refresh();
    }

    /**
     * Sends now, in this request, and keeps the result in the status fields.
     *
     * @param  array{projectId: int|string}  $args
     * @return array{ok: bool, message: string}
     */
    public function test(mixed $root, array $args): array
    {
        $project = Project::query()->findOrFail($args['projectId']);
        $webhook = $project->webhook;

        if ($webhook === null) {
            throw ValidationException::withMessages(['projectId' => __('planer.webhook.none')]);
        }

        $revision = (int) $project->planer_revision;
        $error = $this->sender->send($webhook, $revision);

        $webhook->forceFill($error === null
            ? ['last_sent_revision' => $revision, 'last_sent_at' => now(), 'last_failed_at' => null, 'last_error' => null]
            : ['last_failed_at' => now(), 'last_error' => $error])->save();

        return ['ok' => $error === null, 'message' => $error === null ? __('planer.webhook.test_ok') : __('planer.webhook.test_failed', ['error' => $error])];
    }
}
