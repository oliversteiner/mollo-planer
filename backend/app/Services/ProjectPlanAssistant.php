<?php

namespace App\Services;

use Anthropic\Beta\Messages\BetaStopReason;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use App\Models\User;
use Carbon\CarbonImmutable;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Drafts a project plan from a chat with Claude. Nothing is written: the
 * draft goes to the preview, "Jetzt eintragen" imports it with
 * ProjectPlanImporter.
 *
 * Claude answers with structured output (JSON schema): a short reply for
 * the chat and the whole plan, or null while it still needs information.
 * The answer is cleaned up (dates, roles, lengths), so the preview only
 * shows what the import accepts.
 *
 * @phpstan-type DraftTask array{title: string, description: string|null, dueDate: string|null, assigneeEmail: string|null}
 * @phpstan-type DraftTodo array{title: string, description: string|null, dueDate: string|null, tasks: list<DraftTask>}
 * @phpstan-type DraftMilestone array{title: string, description: string|null, dueDate: string|null, todos: list<DraftTodo>}
 * @phpstan-type DraftPlan array{name: string, description: string|null, locale: string, members: list<array{email: string, role: string}>, milestones: list<DraftMilestone>}
 * @phpstan-type ChatMessage array{role: string, content: string}
 */
class ProjectPlanAssistant
{
    private const ROLES = ['OWNER', 'MANAGER', 'MEMBER'];

    /**
     * @param  list<ChatMessage>  $messages  Chat so far, the last one from the user
     * @param  DraftPlan|null  $currentPlan  The draft shown in the preview
     * @return array{reply: string, plan: DraftPlan|null}
     *
     * @throws Error with a message for the user
     */
    public function draft(User $viewer, array $messages, ?array $currentPlan, string $locale): array
    {
        $key = config('services.anthropic.key');

        if (! is_string($key) || $key === '') {
            throw new Error(__('projects.plan.not_configured'));
        }

        $answer = $this->ask(new Client(apiKey: $key), $this->system($viewer, $locale), $this->conversation($messages, $currentPlan));

        return [
            'reply' => Str::limit(trim((string) ($answer['reply'] ?? '')), 4000),
            'plan' => is_array($answer['plan'] ?? null) ? $this->clean($answer['plan']) : null,
        ];
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array<string, mixed> The decoded JSON answer
     *
     * @throws Error
     */
    protected function ask(Client $client, string $system, array $messages): array
    {
        // A whole plan takes Claude a while; PHP must not stop it halfway.
        set_time_limit(300);

        try {
            $message = $client->beta->messages->create(
                maxTokens: 32000,
                messages: $messages,
                model: config('services.anthropic.model'),
                cacheControl: ['type' => 'ephemeral'],
                fallbacks: 'default',
                outputConfig: [
                    'effort' => 'medium',
                    'format' => ['type' => 'json_schema', 'schema' => self::schema()],
                ],
                system: $system,
                thinking: ['type' => 'adaptive'],
                betas: ['server-side-fallback-2026-07-01'],
                requestOptions: ['timeout' => 280.0, 'maxRetries' => 1],
            );
        } catch (AuthenticationException|PermissionDeniedException $exception) {
            Log::warning('Plan assistant: Claude API refused the key', ['error' => $exception->getMessage()]);

            throw new Error(__('projects.plan.invalid_key'));
        } catch (APIStatusException|APIConnectionException $exception) {
            Log::warning('Plan assistant: Claude API failed', ['error' => $exception->getMessage()]);

            throw new Error(__('projects.plan.unavailable'));
        }

        $stopReason = $message->stopReason instanceof BetaStopReason ? $message->stopReason->value : (string) $message->stopReason;

        if ($stopReason === 'refusal') {
            throw new Error(__('projects.plan.refused'));
        }

        if ($stopReason === 'max_tokens') {
            throw new Error(__('projects.plan.too_large'));
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $answer = json_decode($block->text, true);

                if (is_array($answer)) {
                    return $answer;
                }
            }
        }

        throw new Error(__('projects.plan.unavailable'));
    }

    /**
     * The chat as Claude sees it. The current draft goes with the last user
     * message, so Claude changes it instead of starting over.
     *
     * @param  list<ChatMessage>  $messages
     * @param  DraftPlan|null  $currentPlan
     * @return list<array{role: string, content: string}>
     */
    public function conversation(array $messages, ?array $currentPlan): array
    {
        $conversation = array_values(array_map(fn (array $message) => [
            'role' => $message['role'] === 'ASSISTANT' ? 'assistant' : 'user',
            'content' => $message['content'],
        ], $messages));

        $last = count($conversation) - 1;

        if ($currentPlan !== null && $last >= 0 && $conversation[$last]['role'] === 'user') {
            $conversation[$last]['content'] = "<current_draft>\n".json_encode($currentPlan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                ."\n</current_draft>\n\n".$conversation[$last]['content'];
        }

        return $conversation;
    }

    public function system(User $viewer, string $locale): string
    {
        $people = User::query()->orderBy('name')->get(['name', 'email'])
            ->map(fn (User $user) => "- {$user->name} <{$user->email}>")
            ->implode("\n");
        $today = CarbonImmutable::today()->toDateString();
        $language = $locale === 'en' ? 'English' : 'German (Swiss spelling: "ss" instead of "ß")';

        return <<<PROMPT
        You help {$viewer->name} <{$viewer->email}> plan a new project in a project management app. The user describes the project in a chat (often by voice, so expect typos and missing punctuation). You answer with a short chat reply and the complete plan, which the app shows as a preview. Nothing is saved until the user clicks "Jetzt eintragen" under the preview.

        How the app works: a project has milestones (the timeline), a milestone has todos, a todo has tasks. A task is one person's piece of work and appears on a Kanban board. Statuses are derived automatically, so plans have none.

        What a good plan looks like:
        - A clear project name and a description of two to four sentences: goal, audience, scope.
        - Milestones in chronological order, usually four to eight, each a meaningful result ("Konzept steht", not "Phase 1"), with a due date.
        - Under each milestone two to five todos, under each todo two to five concrete tasks. Fewer is fine for small projects; never pad.
        - Every item has a description of one to three sentences that says what is done and when it is finished. Use "- " lines for short lists.
        - Due dates (YYYY-MM-DD) form a realistic timeline starting after today ({$today}): tasks before their todo, todos before their milestone. If the user names a deadline or a start date, plan towards it.
        - Every task is assigned to exactly one person by e-mail, matching what the user said about who does what. Leave a task unassigned only if nobody fits.

        Members: add every assigned person to members. {$viewer->name} becomes OWNER if they take part in the project or the user does not say otherwise; others become MEMBER unless the user says they lead or manage it (then MANAGER). Only people from this list have accounts:
        {$people}
        Match names loosely (first name, nickname, spelling). If the user mentions someone without an account, leave their tasks unassigned and say in the reply that an admin must invite them first (Verwaltung > Benutzer).

        Locale is the language of the project's notification e-mails: DE unless the user asks for English.

        Changing a plan: when a <current_draft> is given, it is what the preview shows. Apply the user's change to it and return the whole updated plan, keeping everything else as it is.

        Asking: if the request is too vague to plan anything sensible (no idea what the project is), set plan to null and ask one or two short questions. Otherwise make reasonable assumptions, create the plan, and mention the main assumptions in the reply.

        The reply is two to four short sentences for the chat: what you created or changed, assumptions, open points. Do not repeat the plan in the reply, the preview shows it. Write the reply and all titles and descriptions in {$language}, unless the user writes in another language. Plain text only, no Markdown.
        PROMPT;
    }

    /**
     * JSON schema of Claude's answer (structured output).
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $text = ['type' => ['string', 'null']];
        $date = ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD'];
        $object = fn (array $properties): array => [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];

        $task = $object(['title' => ['type' => 'string'], 'description' => $text, 'dueDate' => $date, 'assigneeEmail' => $text]);
        $todo = $object(['title' => ['type' => 'string'], 'description' => $text, 'dueDate' => $date, 'tasks' => ['type' => 'array', 'items' => $task]]);
        $milestone = $object(['title' => ['type' => 'string'], 'description' => $text, 'dueDate' => $date, 'todos' => ['type' => 'array', 'items' => $todo]]);
        $plan = $object([
            'name' => ['type' => 'string'],
            'description' => $text,
            'locale' => ['type' => 'string', 'enum' => ['DE', 'EN']],
            'members' => ['type' => 'array', 'items' => $object([
                'email' => ['type' => 'string'],
                'role' => ['type' => 'string', 'enum' => self::ROLES],
            ])],
            'milestones' => ['type' => 'array', 'items' => $milestone],
        ]);

        return $object([
            'reply' => ['type' => 'string'],
            'plan' => ['anyOf' => [$plan, ['type' => 'null']]],
        ]);
    }

    /**
     * Keeps only what the import accepts: known people, valid dates and
     * roles, titles and texts within the limits of the schema.
     *
     * @param  array<string, mixed>  $plan
     * @return DraftPlan
     */
    public function clean(array $plan): array
    {
        $accounts = User::query()->pluck('email')->mapWithKeys(fn (string $email) => [mb_strtolower($email) => $email])->all();
        $members = [];

        foreach ((array) ($plan['members'] ?? []) as $member) {
            $email = $accounts[mb_strtolower(trim((string) ($member['email'] ?? '')))] ?? null;

            if ($email !== null) {
                $members[mb_strtolower($email)] = [
                    'email' => $email,
                    'role' => in_array($member['role'] ?? null, self::ROLES, true) ? $member['role'] : 'MEMBER',
                ];
            }
        }

        $milestones = [];

        foreach (array_slice((array) ($plan['milestones'] ?? []), 0, 30) as $milestone) {
            $todos = [];

            foreach (array_slice((array) ($milestone['todos'] ?? []), 0, 50) as $todo) {
                $tasks = [];

                foreach (array_slice((array) ($todo['tasks'] ?? []), 0, 50) as $task) {
                    $assignee = mb_strtolower(trim((string) ($task['assigneeEmail'] ?? '')));

                    // An assignee who has an account becomes a member.
                    if (isset($accounts[$assignee]) && ! isset($members[$assignee])) {
                        $members[$assignee] = ['email' => $accounts[$assignee], 'role' => 'MEMBER'];
                    }

                    $tasks[] = [
                        ...$this->item($task),
                        'assigneeEmail' => isset($members[$assignee]) ? $members[$assignee]['email'] : null,
                    ];
                }

                $todos[] = [...$this->item($todo), 'tasks' => $tasks];
            }

            $milestones[] = [...$this->item($milestone), 'todos' => $todos];
        }

        return [
            'name' => $this->title($plan['name'] ?? null, 120),
            'description' => $this->text($plan['description'] ?? null, 2000),
            'locale' => ($plan['locale'] ?? null) === 'EN' ? 'EN' : 'DE',
            'members' => array_values($members),
            'milestones' => $milestones,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{title: string, description: string|null, dueDate: string|null}
     */
    private function item(array $item): array
    {
        return [
            'title' => $this->title($item['title'] ?? null, 160),
            'description' => $this->text($item['description'] ?? null, 5000),
            'dueDate' => $this->date($item['dueDate'] ?? null),
        ];
    }

    private function title(mixed $title, int $max): string
    {
        $title = trim((string) $title);

        return mb_strlen($title) < 2 ? Str::padRight($title, 2, '…') : mb_substr($title, 0, $max);
    }

    private function text(mixed $text, int $max): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    private function date(mixed $date): ?string
    {
        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year) ? $date : null;
    }
}
