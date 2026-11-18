<?php

namespace App\Assistant;

use App\Enums\Right;
use App\Models\Template;
use App\Models\User;
use App\Planer\Canonical;
use App\Planer\Export;
use App\Planer\Import;
use App\Planer\ImportMode;
use App\Planer\RichText\InvalidMarkdown;
use App\Planer\RichText\MarkdownToTiptap;
use App\Planer\RichText\Mode;
use App\Planer\RichText\TiptapToMarkdown;
use App\Planer\Validator;
use App\Support\Rights;
use Illuminate\Support\Str;

/**
 * What the server does with the answer of Claude before the preview (docs/auftrag-m6.md §4.3):
 *
 * 1. the excerpt is made a planer-v2 document and checked against the schema; an invalid one gives a question,
 *    not an error, and no second call to Claude;
 * 2. ids: anchors are real ids of the project (an unknown one is left as it is, so the dry run names it), every new
 *    element gets its `-new-` id from here, whatever Claude gave;
 * 3. values: status of new tasks planned (or idea), only fields the assistant may set, labels of the project;
 * 4. assignments by the rights of the person; names become accounts only when unambiguous;
 * 5. descriptions through the lenient reader, its warnings are hints;
 * 6. a duplicate title gives a hint and the choice to append to the todo;
 * 7. the dry run of the importer with appendOnly is the preview.
 */
class DraftProcessor
{
    private const COLORS = ['gray', 'red', 'orange', 'yellow', 'green', 'cyan', 'blue', 'indigo', 'purple', 'pink'];

    /** @var array<string, int> */
    private array $counters = [];

    /** @var list<string> */
    private array $hints = [];

    /** @var list<array<string, mixed>> */
    private array $extraChoices = [];

    /** @var array<string, array<string, mixed>> Real id => element of the exported tree */
    private array $anchors = [];

    /** @var list<array{kind: string, anchor: array<string, mixed>}> Existing places something new goes into */
    private array $targets = [];

    public function __construct(private readonly Export $export, private readonly Import $import) {}

    /**
     * @param  array<string, mixed>  $answer  The decoded answer of Claude
     * @return array{reply: string, choices: list<array<string, mixed>>, plan: ?string, projectId: int|string|null, templateId: int|string|null, preview: ?array<string, mixed>, hints: list<string>}
     */
    public function process(array $answer, AssistantRequest $request): array
    {
        $this->counters = [];
        $this->hints = [];
        $this->extraChoices = [];
        $this->anchors = [];
        $this->targets = [];

        $reply = Str::limit(trim((string) ($answer['reply'] ?? '')), 4000);
        $choices = PlanChoices::clean((array) ($answer['choices'] ?? []), $request->viewer, $request->project);
        $template = $this->template($answer['template'] ?? null, $request);
        $result = ['reply' => $reply, 'choices' => $choices, 'plan' => null, 'projectId' => $request->project?->getKey(), 'templateId' => $template?->getKey(), 'preview' => null, 'hints' => []];

        if (! is_array($answer['plan'] ?? null)) {
            return $result;
        }

        $document = $this->document($answer['plan'], $request);

        if ($document === null || Validator::errors($document) !== []) {
            return [...$result, 'reply' => trim($reply.' '.__('assistant.invalid_draft')), 'hints' => $this->hints];
        }

        if (($ask = $this->unconfirmed($request, $reply)) !== null) {
            return [...$result, ...$ask, 'hints' => $this->hints];
        }

        $preview = $this->import->run($request->project, $document, ImportMode::Partial, true, false, $request->viewer, true, $template?->getKey());

        $choices = PlanChoices::clean([...$choices, ...$this->extraChoices], $request->viewer, $request->project);

        return [...$result, 'choices' => $choices, 'plan' => Canonical::text($document), 'preview' => $preview->forGraphQL(), 'hints' => $this->hints];
    }

    /**
     * The template of a new project: the one chosen by a button, else the one Claude says it used (it must exist).
     * A plan for an existing project has none.
     */
    private function template(mixed $claudeValue, AssistantRequest $request): ?Template
    {
        if ($request->project !== null) {
            return null;
        }

        if ($request->template !== null) {
            return $request->template;
        }

        return is_string($claudeValue) && preg_match('/^\d+$/', $claudeValue) === 1 ? Template::query()->find((int) $claudeValue) : null;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>|null
     */
    private function document(array $plan, AssistantRequest $request): ?array
    {
        $viewer = $request->viewer;
        $project = $request->project;
        $locale = $project !== null ? $project->locale->value : (($plan['project']['locale'] ?? 'de') === 'en' ? 'en' : 'de');

        $existing = $project !== null ? $this->export->document($project) : null;

        foreach ($existing['milestones'] ?? [] as $milestone) {
            $this->anchors[$milestone['id']] = $milestone;

            foreach ($milestone['todos'] as $todo) {
                $this->anchors[$todo['id']] = $todo;

                foreach ($todo['tasks'] as $task) {
                    $this->anchors[$task['id']] = $task;
                }
            }
        }

        $labelKeys = $existing !== null ? array_column($existing['project']['labels'] ?? [], 'key') : [];
        $newLabels = [];

        if ($existing === null) {
            $newLabels = $this->labels((array) ($plan['project']['labels'] ?? []), $locale);
            $labelKeys = array_column($newLabels, 'key');
        }

        $assign = $this->assignment($request, $existing);
        $assignees = [];

        $milestones = [];

        foreach (array_slice((array) ($plan['milestones'] ?? []), 0, 30) as $milestone) {
            $milestones[] = $this->milestone((array) $milestone, $project !== null, $labelKeys, $assign, $assignees);
        }

        if ($existing !== null) {
            $projectBlock = $existing['project'];
        } else {
            $members = $this->members($assignees, $viewer, (array) ($plan['project']['members'] ?? []), $assign);
            $projectBlock = array_filter([
                'name' => Str::limit(trim((string) ($plan['project']['name'] ?? '')), 120, ''),
                'description' => $this->markdown($plan['project']['description'] ?? null, 'project'),
                'locale' => $locale,
                'members' => $members,
                'labels' => $newLabels === [] ? null : $newLabels,
            ], fn (mixed $value): bool => $value !== null);

            if (mb_strlen($projectBlock['name']) < 2) {
                return null;
            }
        }

        return [
            'format' => 'planer-v2',
            'revision' => $existing !== null ? (int) $existing['revision'] : 0,
            'project' => $projectBlock,
            'milestones' => $milestones,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $labelKeys
     * @param  array<string, mixed>  $assign
     * @param  array<string, true>  $assignees
     * @return array<string, mixed>
     */
    private function milestone(array $entry, bool $existingProject, array $labelKeys, array $assign, array &$assignees): array
    {
        $anchor = $existingProject ? $this->anchor($entry['id'] ?? null, 'm') : null;

        if ($anchor !== null) {
            $out = $this->withoutChildren($anchor);
        } elseif ($existingProject && $this->isAnchorId($entry['id'] ?? null, 'm')) {
            // An id that is no milestone of this project: left as it is, the dry run names it.
            $out = ['id' => (string) $entry['id'], 'title' => $this->title($entry['title'] ?? '')];
        } else {
            $version = $entry['release']['version'] ?? null;
            $out = [
                'id' => $this->nextId('m'),
                'title' => $this->title($entry['title'] ?? ''),
                ...$this->optional('description', $this->markdown($entry['description'] ?? null, 'milestone')),
                ...$this->optional('dueDate', $this->date($entry['dueDate'] ?? null)),
                ...(is_string($version) && preg_match('/^\d+\.\d+$/', $version) === 1 ? ['release' => ['version' => $version, 'status' => 'planned']] : []),
            ];
        }

        $todos = [];

        foreach (array_slice((array) ($entry['todos'] ?? []), 0, 50) as $todo) {
            $todos[] = $this->todo((array) $todo, $anchor, $existingProject, $labelKeys, $assign, $assignees);
        }

        return [...$out, 'todos' => $todos];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>|null  $milestoneAnchor
     * @param  list<string>  $labelKeys
     * @param  array<string, mixed>  $assign
     * @param  array<string, true>  $assignees
     * @return array<string, mixed>
     */
    private function todo(array $entry, ?array $milestoneAnchor, bool $existingProject, array $labelKeys, array $assign, array &$assignees): array
    {
        $anchor = $existingProject ? $this->anchor($entry['id'] ?? null, 't') : null;

        if ($anchor !== null) {
            $out = $this->withoutChildren($anchor);
        } elseif ($existingProject && $this->isAnchorId($entry['id'] ?? null, 't')) {
            // An id that is no todo of this project: left as it is, the dry run names it.
            $out = ['id' => (string) $entry['id'], 'title' => $this->title($entry['title'] ?? '')];
        } else {
            $out = [
                'id' => $this->nextId('t'),
                'title' => $this->title($entry['title'] ?? ''),
                ...$this->optional('description', $this->markdown($entry['description'] ?? null, 'todo')),
                ...$this->optional('dueDate', $this->date($entry['dueDate'] ?? null)),
            ];

            $this->duplicateTodo($out['title'], $milestoneAnchor);

            if ($milestoneAnchor !== null) {
                $this->targets[] = ['kind' => 'milestone', 'anchor' => $milestoneAnchor];
            }
        }

        $tasks = [];

        foreach (array_slice((array) ($entry['tasks'] ?? []), 0, 50) as $task) {
            $tasks[] = $this->task((array) $task, $anchor, $existingProject, $labelKeys, $assign, $assignees);
        }

        return [...$out, 'tasks' => $tasks];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>|null  $todoAnchor
     * @param  list<string>  $labelKeys
     * @param  array<string, mixed>  $assign
     * @param  array<string, true>  $assignees
     * @return array<string, mixed>
     */
    private function task(array $entry, ?array $todoAnchor, bool $existingProject, array $labelKeys, array $assign, array &$assignees): array
    {
        $anchor = $existingProject ? $this->anchor($entry['id'] ?? null, 'k') : null;

        if ($anchor !== null) {
            return $anchor;
        }

        if ($existingProject && $this->isAnchorId($entry['id'] ?? null, 'k')) {
            return ['id' => (string) $entry['id'], 'title' => $this->title($entry['title'] ?? ''), 'status' => 'planned'];
        }

        $title = $this->title($entry['title'] ?? '');

        if ($todoAnchor !== null) {
            $this->targets[] = ['kind' => 'todo', 'anchor' => $todoAnchor];

            foreach ($todoAnchor['tasks'] ?? [] as $known) {
                if (mb_strtolower($known['title']) === mb_strtolower($title)) {
                    $this->hints[] = __('assistant.duplicate_task', ['title' => $title, 'todo' => $todoAnchor['title']]);
                }
            }
        }

        $email = $this->assignee($entry['assigneeEmail'] ?? null, $assign);

        if ($email !== null) {
            $assignees[mb_strtolower($email)] = true;
        }

        $labels = array_values(array_unique(array_filter((array) ($entry['labels'] ?? []), fn (mixed $key): bool => is_string($key) && in_array($key, $labelKeys, true))));
        $story = $entry['userStory'] ?? null;
        $hasStory = is_array($story) && trim((string) ($story['role'] ?? '')) !== '' && trim((string) ($story['goal'] ?? '')) !== '' && trim((string) ($story['benefit'] ?? '')) !== '';

        return [
            'id' => $this->nextId('k'),
            'title' => $title,
            ...$this->optional('description', $this->markdown($entry['description'] ?? null, 'task')),
            ...$this->optional('dueDate', $this->date($entry['dueDate'] ?? null)),
            'status' => ($entry['status'] ?? null) === 'idea' ? 'idea' : 'planned',
            ...$this->optional('assigneeEmail', $email),
            ...($labels === [] ? [] : ['labels' => $labels]),
            ...($hasStory ? ['userStory' => ['role' => Str::limit(trim((string) $story['role']), 80, ''), 'goal' => Str::limit(trim((string) $story['goal']), 160, ''), 'benefit' => Str::limit(trim((string) $story['benefit']), 160, '')]] : []),
        ];
    }

    // ─── Values ──────────────────────────────────────────────────────

    private function nextId(string $prefix): string
    {
        $this->counters[$prefix] = ($this->counters[$prefix] ?? 0) + 1;

        return "{$prefix}-new-{$this->counters[$prefix]}";
    }

    /** @return array<string, mixed>|null The element of the project an id points at */
    private function anchor(mixed $id, string $prefix): ?array
    {
        return $this->isAnchorId($id, $prefix) ? ($this->anchors[(string) $id] ?? null) : null;
    }

    private function isAnchorId(mixed $id, string $prefix): bool
    {
        return is_string($id) && preg_match('/^'.$prefix.'-\d+$/', $id) === 1;
    }

    private function title(mixed $title): string
    {
        $title = trim((string) $title);

        return mb_strlen($title) < 2 ? str_pad($title, 2, '.') : Str::limit($title, 160, '');
    }

    private function date(mixed $date): ?string
    {
        if (! is_string($date) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $match) !== 1 || ! checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            return null;
        }

        return $date;
    }

    /**
     * @return array<string, mixed>
     */
    private function optional(string $key, mixed $value): array
    {
        return $value === null || $value === '' ? [] : [$key => $value];
    }

    /**
     * Markdown through the lenient reader: what the editor cannot hold becomes text, the warnings are hints.
     */
    private function markdown(mixed $text, string $where): ?string
    {
        if (! is_string($text) || trim($text) === '') {
            return null;
        }

        try {
            $result = MarkdownToTiptap::convert($text, Mode::Lenient);
        } catch (InvalidMarkdown) {
            return Str::limit(trim($text), 5000, '');
        }

        foreach ($result->warnings as $warning) {
            $this->hints[] = __('assistant.description_warning', ['where' => $where, 'kind' => $warning->kind, 'line' => $warning->line]);
        }

        $markdown = TiptapToMarkdown::convert($result->doc);

        return $markdown === '' ? null : Str::limit($markdown, 5000, '');
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array<string, mixed>
     */
    private function withoutChildren(array $element): array
    {
        return array_diff_key($element, ['todos' => 1, 'tasks' => 1]);
    }

    // ─── Never choose silently ───────────────────────────────────────

    /**
     * Something new in an existing milestone (or todo) that the person has not named: no plan, a choice instead
     * (docs/assistent-ideen-konzept.md §5). Named is by a button (its message carries the title and the id) or
     * by the title or the id in a message of the person.
     *
     * @return array{reply: string, choices: list<array<string, mixed>>, plan: null, preview: null}|null
     */
    private function unconfirmed(AssistantRequest $request, string $claudeReply): ?array
    {
        $said = mb_strtolower(implode("\n", array_column(array_filter($request->messages, fn (array $message): bool => $message['role'] === 'user'), 'content')));

        foreach ($this->targets as $target) {
            $anchor = $target['anchor'];
            $named = preg_match('/(?<![\w-])'.preg_quote(mb_strtolower((string) $anchor['id']), '/').'(?![\w-])/', $said) === 1
                || ($anchor['title'] !== '' && str_contains($said, mb_strtolower((string) $anchor['title'])));

            if ($named) {
                continue;
            }

            $milestone = $target['kind'] === 'milestone';
            $options = array_values(array_filter($this->anchors, fn (array $element, string $id): bool => str_starts_with($id, $milestone ? 'm-' : 't-'), ARRAY_FILTER_USE_BOTH));
            $choices = array_map(fn (array $element): array => [
                'kind' => $milestone ? 'MILESTONE' : 'APPEND_TO_TODO',
                'label' => (string) $element['title'],
                'detail' => isset($element['dueDate']) ? (string) $element['dueDate'] : null,
                'value' => (string) $element['id'],
                'recommended' => $element['id'] === $anchor['id'],
            ], $options);

            if ($milestone) {
                array_push($choices, ...[
                    ['kind' => 'NEW_MILESTONE', 'label' => __('assistant.new_milestone'), 'detail' => null, 'value' => null, 'recommended' => false],
                    ['kind' => 'CUSTOM_MILESTONE', 'label' => __('assistant.custom_milestone'), 'detail' => null, 'value' => null, 'recommended' => false],
                    ['kind' => 'IDEA', 'label' => __('assistant.keep_as_idea'), 'detail' => null, 'value' => null, 'recommended' => false],
                ]);
            }

            // The recommended one first, so "at most five" keeps it.
            usort($choices, fn (array $a, array $b): int => (int) $b['recommended'] <=> (int) $a['recommended']);

            return [
                'reply' => __($milestone ? 'assistant.which_milestone' : 'assistant.which_todo'),
                'choices' => PlanChoices::clean($choices, $request->viewer, $request->project),
                'plan' => null,
                'preview' => null,
            ];
        }

        return null;
    }

    // ─── Duplicates ──────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>|null  $milestoneAnchor
     */
    private function duplicateTodo(string $title, ?array $milestoneAnchor): void
    {
        foreach ($milestoneAnchor['todos'] ?? [] as $known) {
            if (mb_strtolower($known['title']) === mb_strtolower($title)) {
                $this->hints[] = __('assistant.duplicate_todo', ['title' => $title, 'milestone' => $milestoneAnchor['title']]);
                $this->extraChoices[] = ['kind' => 'APPEND_TO_TODO', 'label' => $known['title'], 'detail' => $milestoneAnchor['title'], 'value' => $known['id'], 'recommended' => false];
            }
        }
    }

    // ─── Assignments and people ──────────────────────────────────────

    /**
     * How a name or an e-mail in assigneeEmail becomes an account, by the rights of the person.
     *
     * @param  array<string, mixed>|null  $existing
     * @return array{mode: string, emails: array<string, string>, members: array<string, string>, self: string, byName: array<string, list<string>>}
     */
    private function assignment(AssistantRequest $request, ?array $existing): array
    {
        $viewer = $request->viewer;
        $project = $request->project;
        $members = $existing !== null ? array_column($existing['project']['members'], 'email', 'email') : [];

        if ($project !== null) {
            $mode = Rights::allows($viewer, Right::TASK_ASSIGN, $project) ? 'any' : (Rights::allows($viewer, Right::TASK_ASSIGN_SELF, $project) ? 'self' : 'none');
        } else {
            $mode = 'any';
        }

        $accounts = User::query()->whereNotNull('approved_at')->get(['name', 'email']);
        $byName = [];

        foreach ($accounts as $account) {
            $byName[mb_strtolower($account->name)][] = $account->email;
            foreach (preg_split('/\s+/', mb_strtolower($account->name)) ?: [] as $part) {
                $byName[$part][] = $account->email;
            }
        }

        return [
            'mode' => $mode,
            'emails' => $accounts->mapWithKeys(fn (User $account): array => [mb_strtolower($account->email) => $account->email])->all(),
            'members' => array_change_key_case($members, CASE_LOWER),
            'self' => $viewer->email,
            'byName' => array_map(fn (array $emails): array => array_values(array_unique($emails)), $byName),
            'projectKnown' => $project !== null ? '1' : '',
            'manage' => Rights::allows($viewer, Right::USERS_MANAGE) ? '1' : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $assign
     */
    private function assignee(mixed $value, array $assign): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            return null;
        }

        $email = null;

        if (isset($assign['emails'][mb_strtolower($value)])) {
            $email = $assign['emails'][mb_strtolower($value)];
        } elseif ($assign['projectKnown'] === '' && $assign['manage'] === '') {
            $matches = $assign['byName'][mb_strtolower($value)] ?? [];

            if (count($matches) !== 1) {
                $this->hints[] = __('assistant.unclear_person', ['name' => $value]);

                return null;
            }

            $email = $matches[0];
        }

        if ($email === null) {
            $this->hints[] = __('assistant.unknown_person', ['name' => $value]);

            return null;
        }

        if ($assign['projectKnown'] !== '' && ! isset($assign['members'][mb_strtolower($email)])) {
            $this->hints[] = __('assistant.unknown_person', ['name' => $value]);

            return null;
        }

        if ($assign['mode'] === 'none' || ($assign['mode'] === 'self' && mb_strtolower($email) !== mb_strtolower($assign['self']))) {
            $this->hints[] = __('assistant.only_self', ['name' => $value]);

            return null;
        }

        return $email;
    }

    /**
     * The members of a new project: the people the plan assigns, the creator among them (she is added by the importer anyway).
     *
     * @param  array<string, true>  $assignees
     * @param  list<mixed>  $claudeMembers
     * @param  array<string, mixed>  $assign
     * @return list<array{email: string, role: string}>
     */
    private function members(array $assignees, User $viewer, array $claudeMembers, array $assign): array
    {
        $members = [];

        foreach (array_keys($assignees) as $email) {
            $members[$email] = ['email' => $assign['emails'][$email] ?? $email, 'role' => mb_strtolower($email) === mb_strtolower($viewer->email) ? 'owner' : 'member'];
        }

        return array_values($members);
    }

    /**
     * @param  list<mixed>  $labels
     * @return list<array<string, mixed>>
     */
    private function labels(array $labels, string $locale): array
    {
        $out = [];
        $keys = [];

        foreach (array_slice($labels, 0, 30) as $label) {
            $label = (array) $label;
            $names = array_filter(['de' => $label['names']['de'] ?? null, 'en' => $label['names']['en'] ?? null], fn (mixed $name): bool => is_string($name) && trim($name) !== '');
            $key = Str::slug((string) ($label['key'] ?? ''), '_');

            if ($names === [] || $key === '' || isset($keys[$key])) {
                continue;
            }

            $names = array_map(fn (string $name): string => Str::limit(trim($name), 40, ''), $names);
            $names[$locale] ??= reset($names);
            $keys[$key] = true;
            $out[] = array_filter([
                'key' => Str::limit($key, 40, ''),
                'names' => $names,
                'category' => isset($label['category']) && trim((string) $label['category']) !== '' ? Str::limit(trim((string) $label['category']), 40, '') : null,
                'color' => in_array($label['color'] ?? null, self::COLORS, true) ? $label['color'] : 'gray',
            ], fn (mixed $value): bool => $value !== null);
        }

        return $out;
    }
}
