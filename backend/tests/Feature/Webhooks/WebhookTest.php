<?php

namespace Tests\Feature\Webhooks;

use App\GraphQL\Mutations\ProjectWebhookMutations;
use App\Jobs\SendPlanerWebhook;
use App\Mcp\Servers\MolloPlanerServer;
use App\Mcp\Tools\ExportPlanTool;
use App\Mcp\Tools\ImportPlanTool;
use App\Models\Project;
use App\Models\ProjectWebhook;
use App\Planer\Export;
use App\Webhooks\WebhookFailed;
use App\Webhooks\WebhookSender;
use App\Webhooks\WebhookTarget;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Feature\GraphQL\GraphQLTestCase;

/**
 * The webhook of a project (docs/auftrag-m5.md §4): bundling, the two kinds of request, retries
 * and the status, rights, the secret that never leaves, the target check, and that importing
 * triggers it and exporting does not.
 */
class WebhookTest extends GraphQLTestCase
{
    private const SECRET = 'SUPER-SECRET-TOKEN-4711';

    private const SET = 'mutation ($p: ID!, $in: ProjectWebhookInput!) { setProjectWebhook(projectId: $p, input: $in) { id webhook { kind target hasSecret lastSentAt lastSentRevision lastFailedAt lastError } } }';

    protected function setUp(): void
    {
        parent::setUp();

        // The queue of the tests is synchronous: without this every change would send at once.
        Queue::fake();
    }

    private function github(?Project $project = null, array $attributes = []): ProjectWebhook
    {
        $webhook = new ProjectWebhook(['kind' => 'github', 'target' => 'oliver/podcast', 'secret' => self::SECRET]);
        $webhook->forceFill($attributes);
        ($project ?? $this->project)->webhook()->save($webhook);

        return $webhook->fresh();
    }

    private function generic(array $attributes = []): ProjectWebhook
    {
        $webhook = new ProjectWebhook(['kind' => 'generic', 'target' => 'https://hooks.example.org/planer', 'secret' => self::SECRET]);
        $webhook->forceFill($attributes);
        $this->project->webhook()->save($webhook);

        return $webhook->fresh();
    }

    private function revision(): int
    {
        return (int) $this->project->fresh()->planer_revision;
    }

    private function runJob(): void
    {
        (new SendPlanerWebhook($this->project->id))->handle(app(WebhookSender::class));
    }

    // ─── Bundling ────────────────────────────────────────────────────

    public function test_several_changes_make_one_job_that_waits_at_least_a_minute(): void
    {
        Queue::fake();
        $this->github();

        foreach (['Eins', 'Zwei', 'Drei'] as $title) {
            $this->as('manager')->gql('mutation ($id: ID!, $t: String!) { updateMilestone(id: $id, input: {title: $t}) { id } }', ['id' => $this->milestone->id, 't' => $title])->assertJsonMissingPath('errors');
        }

        Queue::assertPushed(SendPlanerWebhook::class, 1);
        Queue::assertPushed(SendPlanerWebhook::class, fn (SendPlanerWebhook $job) => $job->projectId === $this->project->id && $job->delay !== null && now()->diffInSeconds($job->delay, false) >= 59);
    }

    public function test_after_a_send_the_next_waits_until_five_minutes_have_passed(): void
    {
        Queue::fake();
        $this->github(null, ['last_sent_at' => now()->subMinute(), 'last_sent_revision' => 1]);

        $this->as('manager')->gql('mutation ($id: ID!) { updateMilestone(id: $id, input: {title: "Neu"}) { id } }', ['id' => $this->milestone->id]);

        Queue::assertPushed(SendPlanerWebhook::class, function (SendPlanerWebhook $job) {
            $wait = now()->diffInSeconds($job->delay, false);

            return $wait >= 235 && $wait <= 241;
        });
    }

    public function test_a_project_without_a_webhook_gets_no_job(): void
    {
        Queue::fake();

        $this->as('manager')->gql('mutation ($id: ID!) { updateMilestone(id: $id, input: {title: "Neu"}) { id } }', ['id' => $this->milestone->id]);

        Queue::assertNothingPushed();
    }

    public function test_the_job_sends_the_latest_revision_once_and_nothing_for_the_same_revision(): void
    {
        Http::fake(['api.github.com/*' => Http::response('', 204)]);
        $this->github();
        $this->milestone->update(['title' => 'Eins']);
        $this->milestone->update(['title' => 'Zwei']);

        $this->runJob();
        $this->runJob();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['client_payload']['revision'] === $this->revision());
        $webhook = $this->project->webhook()->first();
        $this->assertSame($this->revision(), $webhook->last_sent_revision);
        $this->assertNotNull($webhook->last_sent_at);
        $this->assertNull($webhook->last_error);
    }

    // ─── The two requests ────────────────────────────────────────────

    public function test_the_github_request_is_a_repository_dispatch_with_the_token(): void
    {
        Http::fake(['api.github.com/*' => Http::response('', 204)]);
        $this->github();

        $this->runJob();

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.github.com/repos/oliver/podcast/dispatches'
                && $request->header('Authorization') === ['Bearer '.self::SECRET]
                && $request->header('Accept') === ['application/vnd.github+json']
                && $request->header('X-GitHub-Api-Version') === ['2022-11-28']
                && $request['event_type'] === 'planer-export'
                && $request['client_payload'] === ['project_id' => $this->project->id, 'revision' => $this->revision(), 'app_url' => config('app.url')];
        });
    }

    public function test_the_generic_request_is_signed_and_the_signature_can_be_recomputed(): void
    {
        Http::fake(['hooks.example.org/*' => Http::response('ok', 200)]);
        $this->generic();

        $this->runJob();

        Http::assertSent(function (Request $request) {
            $body = $request->body();
            $data = json_decode($body, true);

            return $request->url() === 'https://hooks.example.org/planer'
                && $request->header('X-Mollo-Event') === ['planer.revision']
                && $request->header('X-Mollo-Signature') === ['sha256='.hash_hmac('sha256', $body, self::SECRET)]
                && $data['event'] === 'planer.revision'
                && $data['project_id'] === $this->project->id
                && $data['revision'] === $this->revision()
                && isset($data['sent_at']);
        });
    }

    // ─── Failure and status ──────────────────────────────────────────

    public function test_a_failed_send_throws_for_the_retries_and_the_job_waits_one_five_and_fifteen_minutes(): void
    {
        Http::fake(['api.github.com/*' => Http::response('{"message":"Bad credentials"}', 401)]);
        $this->github();
        $job = new SendPlanerWebhook($this->project->id);

        $this->assertSame(4, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff());

        try {
            $job->handle(app(WebhookSender::class));
            $this->fail('A failed send must throw.');
        } catch (WebhookFailed $error) {
            $this->assertSame('HTTP 401', $error->getMessage());
        }

        $this->assertNull($this->project->webhook()->first()->last_failed_at);
    }

    public function test_after_the_last_try_the_failure_shows_and_a_success_clears_it(): void
    {
        $this->github();

        (new SendPlanerWebhook($this->project->id))->failed(new WebhookFailed('HTTP 401'));

        $webhook = $this->project->webhook()->first();
        $this->assertSame('HTTP 401', $webhook->last_error);
        $this->assertNotNull($webhook->last_failed_at);

        Http::fake(['api.github.com/*' => Http::response('', 204)]);
        $this->milestone->update(['title' => 'Neu']);
        $this->runJob();

        $webhook = $this->project->webhook()->first();
        $this->assertNull($webhook->last_error);
        $this->assertNull($webhook->last_failed_at);
    }

    public function test_the_error_is_a_short_code_and_never_the_body_the_headers_or_the_secret(): void
    {
        $this->github();
        Log::spy();

        Http::fake(['api.github.com/*' => Http::sequence()
            ->push('geheim '.self::SECRET, 500)
            ->push('', 302, ['Location' => 'https://evil.example'])
            ->pushFailedConnection('cURL error 28: Operation timed out')
            ->pushFailedConnection('Could not resolve host')]);

        $errors = array_map(fn () => app(WebhookSender::class)->send($this->project->webhook()->first(), 3), range(1, 4));

        $this->assertSame(['HTTP 500', 'HTTP 302', 'timeout', 'connection'], $errors);

        Log::shouldNotHaveReceived('error');
    }

    // ─── GraphQL: rights, secret, status ─────────────────────────────

    public function test_the_manager_sets_it_up_and_the_secret_never_comes_back(): void
    {
        $revision = $this->revision();

        $response = $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['kind' => 'GITHUB', 'target' => 'oliver/podcast', 'secret' => self::SECRET]])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.setProjectWebhook.webhook.kind', 'GITHUB')
            ->assertJsonPath('data.setProjectWebhook.webhook.target', 'oliver/podcast')
            ->assertJsonPath('data.setProjectWebhook.webhook.hasSecret', true);

        $this->assertStringNotContainsString(self::SECRET, $response->getContent());
        $this->assertSame(self::SECRET, $this->project->webhook()->first()->secret);
        $this->assertNotSame(self::SECRET, DB::table('project_webhooks')->value('secret'), 'stored encrypted');
        $this->assertSame($revision, $this->revision(), 'the webhook is no change of the plan');
        $this->assertArrayNotHasKey('secret', $this->project->webhook()->first()->toArray());
        $this->assertStringNotContainsString(self::SECRET, $this->project->webhook()->first()->toJson());

        $this->assertStringContainsString('Cannot query field', $this->as('manager')->gql('query ($id: ID!) { project(id: $id) { webhook { secret } } }', ['id' => $this->project->id])->getContent());
    }

    public function test_a_change_without_a_new_secret_keeps_the_old_one_and_a_new_kind_needs_one(): void
    {
        $this->github(null, ['last_sent_revision' => 2, 'last_sent_at' => now()]);

        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['kind' => 'GITHUB', 'target' => 'oliver/anders']])->assertJsonMissingPath('errors');
        $this->assertSame(self::SECRET, $this->project->webhook()->first()->secret);
        $this->assertNull($this->project->webhook()->first()->last_sent_revision, 'another target starts anew');

        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['kind' => 'GENERIC', 'target' => 'https://hooks.example.org/x']])->assertGraphQLValidationKeys(['input.secret']);
        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['kind' => 'GITHUB', 'target' => 'a/b', 'secret' => 'neu']])->assertJsonMissingPath('errors');
        $this->assertSame('neu', $this->project->webhook()->first()->secret);
    }

    public function test_a_new_webhook_needs_a_secret(): void
    {
        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['kind' => 'GITHUB', 'target' => 'oliver/podcast']])->assertGraphQLValidationKeys(['input.secret']);
    }

    public function test_only_those_who_may_change_the_project_see_and_change_the_webhook(): void
    {
        $this->github();
        $query = 'query ($id: ID!) { project(id: $id) { webhook { target } } }';

        $this->as('member')->gql($query, ['id' => $this->project->id])->assertJsonPath('data.project.webhook', null);
        $this->as('manager')->gql($query, ['id' => $this->project->id])->assertJsonPath('data.project.webhook.target', 'oliver/podcast');

        $this->assertUnauthorized($this->as('member')->gql(self::SET, ['p' => $this->project->id, 'in' => ['kind' => 'GITHUB', 'target' => 'a/b', 'secret' => 'x']]));
        $this->assertUnauthorized($this->as('member')->gql('mutation ($p: ID!) { removeProjectWebhook(projectId: $p) { id } }', ['p' => $this->project->id]));
        $this->assertUnauthorized($this->as('member')->gql('mutation ($p: ID!) { testProjectWebhook(projectId: $p) { ok } }', ['p' => $this->project->id]));
        $this->assertUnauthorized($this->as('stranger')->gql(self::SET, ['p' => $this->project->id, 'in' => ['kind' => 'GITHUB', 'target' => 'a/b', 'secret' => 'x']]));
        $this->assertSame('oliver/podcast', $this->project->webhook()->first()->target);
    }

    public function test_an_archived_project_refuses_every_change_of_the_webhook(): void
    {
        $this->github();
        $this->project->forceFill(['archived_at' => now()])->save();

        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['kind' => 'GITHUB', 'target' => 'a/b', 'secret' => 'x']])->assertGraphQLErrorMessage(__('projects.archived'));
        $this->as('manager')->gql('mutation ($p: ID!) { removeProjectWebhook(projectId: $p) { id } }', ['p' => $this->project->id])->assertGraphQLErrorMessage(__('projects.archived'));
        $this->assertNotNull($this->project->webhook()->first());
    }

    public function test_removing_and_deleting_the_project_remove_it(): void
    {
        $this->github();

        $this->as('manager')->gql('mutation ($p: ID!) { removeProjectWebhook(projectId: $p) { webhook { target } } }', ['p' => $this->project->id])->assertJsonPath('data.removeProjectWebhook.webhook', null);
        $this->assertSame(0, ProjectWebhook::query()->count());

        $this->github();
        $this->project->forceDelete();
        $this->assertSame(0, ProjectWebhook::query()->count());
    }

    public function test_testing_sends_now_and_reports_the_result_and_keeps_the_status(): void
    {
        Http::fake(['api.github.com/*' => Http::sequence()->push('', 204)->push('', 401)]);
        $this->github();
        $mutation = 'mutation ($p: ID!) { testProjectWebhook(projectId: $p) { ok message } }';
        $revision = $this->revision();

        $this->as('manager')->gql($mutation, ['p' => $this->project->id])->assertJsonPath('data.testProjectWebhook.ok', true);
        $webhook = $this->project->webhook()->first();
        $this->assertSame($revision, $webhook->last_sent_revision);
        $this->assertNotNull($webhook->last_sent_at);

        $this->as('manager')->gql($mutation, ['p' => $this->project->id])->assertJsonPath('data.testProjectWebhook.ok', false)->assertJsonPath('data.testProjectWebhook.message', 'The notification did not arrive (HTTP 401).');
        $webhook = $this->project->webhook()->first();
        $this->assertSame('HTTP 401', $webhook->last_error);
        $this->assertSame($revision, $this->revision());
    }

    // ─── The target ──────────────────────────────────────────────────

    public function test_the_kind_decides_what_a_target_may_look_like(): void
    {
        $this->assertNull(WebhookTarget::problem('github', 'oliver/podcast'));
        foreach (['oliver', 'oliver/', 'a/b/c', 'https://github.com/a/b', 'a b/c'] as $bad) {
            $this->assertSame('github_target', WebhookTarget::problem('github', $bad), $bad);
        }

        $this->assertNull(WebhookTarget::problem('generic', 'https://hooks.example.org/x'));
        $this->assertNull(WebhookTarget::problem('generic', 'http://localhost:9000/x'), 'http is fine in local and testing');
        $this->assertSame('url_invalid', WebhookTarget::problem('generic', 'nicht-eine-adresse'));
        $this->assertSame('url_invalid', WebhookTarget::problem('generic', 'https://user:pass@hooks.example.org/x'));
        $this->assertSame('url_https', WebhookTarget::problem('generic', 'ftp://hooks.example.org/x'));
    }

    public function test_in_production_only_https_and_public_addresses_are_accepted(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertSame('url_https', WebhookTarget::problem('generic', 'http://8.8.8.8/x'));
        $this->assertNull(WebhookTarget::problem('generic', 'https://8.8.8.8/x'));

        foreach (['https://127.0.0.1/x', 'https://10.1.2.3/x', 'https://192.168.0.5/x', 'https://172.16.0.1/x', 'https://169.254.169.254/x', 'https://[::1]/x', 'https://0.0.0.0/x'] as $private) {
            $this->assertSame('url_private', WebhookTarget::problem('generic', $private), $private);
        }

        // The rule of the mutation (the HTTP stack in production mode wants a CSRF token, so it is called itself).
        try {
            app(ProjectWebhookMutations::class)->set(null, ['projectId' => $this->project->id, 'input' => ['kind' => 'generic', 'target' => 'https://10.0.0.1/x', 'secret' => 'x']]);
            $this->fail('A private target must be refused.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('input.target', $error->errors());
        }
    }

    public function test_the_target_is_checked_again_when_it_is_used(): void
    {
        $this->generic();
        Http::fake();
        $this->app->detectEnvironment(fn () => 'production');
        $this->project->webhook()->update(['target' => 'https://127.0.0.1/planer']);

        $this->assertSame('target', app(WebhookSender::class)->send($this->project->webhook()->first(), 1));
        Http::assertNothingSent();
    }

    // ─── Import triggers it, export does not ─────────────────────────

    public function test_an_import_through_mcp_triggers_the_webhook_and_an_export_does_not(): void
    {
        Queue::fake();
        $this->github();
        $document = app(Export::class)->document($this->project->fresh());

        MolloPlanerServer::actingAs($this->users['owner'])->tool(ExportPlanTool::class, ['project_id' => (string) $this->project->id])->assertOk();
        Queue::assertNothingPushed();

        $document['milestones'][0]['title'] = 'Per MCP';
        MolloPlanerServer::actingAs($this->users['owner'])->tool(ImportPlanTool::class, ['project_id' => (string) $this->project->id, 'plan' => $document])->assertOk();
        Queue::assertPushed(SendPlanerWebhook::class, 1);
    }

    public function test_a_dry_run_and_an_import_without_changes_trigger_nothing(): void
    {
        Queue::fake();
        $this->github();
        $document = app(Export::class)->document($this->project->fresh());
        $document['milestones'][0]['title'] = 'Nur geprüft';

        MolloPlanerServer::actingAs($this->users['owner'])->tool(ImportPlanTool::class, ['project_id' => (string) $this->project->id, 'plan' => $document, 'dry_run' => true])->assertOk();
        MolloPlanerServer::actingAs($this->users['owner'])->tool(ImportPlanTool::class, ['project_id' => (string) $this->project->id, 'plan' => app(Export::class)->document($this->project->fresh())])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_the_message_after_testing_uses_the_words_of_the_status_line_in_both_languages(): void
    {
        $this->github();
        $mutation = 'mutation ($p: ID!) { testProjectWebhook(projectId: $p) { ok message } }';

        foreach (['de' => 'Die Benachrichtigung ist nicht angekommen (Zeitüberschreitung).', 'en' => 'The notification did not arrive (timeout).'] as $language => $message) {
            Http::fake(['api.github.com/*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);

            $this->as('manager')->withHeader('Accept-Language', $language)->gql($mutation, ['p' => $this->project->id])->assertJsonPath('data.testProjectWebhook.message', $message);
            $this->assertSame('timeout', $this->project->webhook()->first()->last_error, 'the status keeps the code, not the words');
        }
    }
}
