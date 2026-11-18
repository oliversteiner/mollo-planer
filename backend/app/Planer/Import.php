<?php

namespace App\Planer;

use App\Enums\IdeaState;
use App\Enums\ReleaseStatus;
use App\Enums\TaskStatus;
use App\Models\Idea;
use App\Models\Label;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Planer\RichText\InvalidMarkdown;
use App\Planer\RichText\MarkdownToTiptap;
use App\Planer\RichText\Mode;
use App\Planer\RichText\TiptapToMarkdown;
use App\Services\Deletion;
use App\Services\KanbanOrdering;
use App\Services\Ordering;
use App\Services\ProjectLabels;
use App\Services\ProjectMembership;
use App\Services\ReleaseRules;
use App\Services\TaskLabels;
use App\Services\TaskNotifier;
use App\Support\AppSettings;
use App\Support\Rights;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Writes a planer-v2 document into a project, or into a new one
 * (docs/auftrag-m4.md §4, docs/planer-abgleich.md §2).
 *
 * Everything in one transaction, all or nothing. A dry run does the whole
 * work and rolls it back, so the list of changes and the errors are what a
 * real run would meet: the same rights, limits and rules. Every single
 * change goes through the policies and services of the app (no GraphQL
 * strings): rights are asked for each change, the order comes from the
 * order of the arrays, the status of a task moves it in its Kanban column,
 * a deletion in full mode is one deletion in the trash. The limits and the
 * rules of releases are checked for the result, not step by step.
 *
 * The revision goes up by one for a run with changes, never for a dry run
 * and never for a run without changes.
 */
final class Import
{
    private ImportMode $mode;

    private bool $notify;

    private bool $dryRun = false;

    private bool $appendOnly = false;

    /** @var array<string, string> Titles by id (m-12, t-4, k-9) before the run, for the messages of appendOnly */
    private array $titles = [];

    private ?int $templateId = null;

    /** @var list<int> Todos this run created, for the idea they came from */
    private array $createdTodoIds = [];

    private User $actor;

    private Project $project;

    private bool $newProject;

    /** @var array<string, mixed> */
    private array $document;

    /** @var list<array{kind: string, path: string, id: ?string, fields: list<string>}> */
    private array $changes = [];

    /** @var list<array{path: string, kind: string, message: string}> */
    private array $errors = [];

    /** @var array<string, string> */
    private array $idMap = [];

    /** @var Collection<int, Milestone> */
    private Collection $milestones;

    /** @var Collection<int, Todo> */
    private Collection $todos;

    /** @var Collection<int, Task> */
    private Collection $tasks;

    /** @var array{milestone: array<int, true>, todo: array<int, true>, task: array<int, true>} Real ids the file lists */
    private array $seen = ['milestone' => [], 'todo' => [], 'task' => []];

    /** @var array<string, string> Real id ("m-12") => path in the file */
    private array $paths = [];

    /** @var array{milestones: list<int>, todos: array<int, list<int>>, tasks: array<int, list<int>>} Order of the file */
    private array $wanted = ['milestones' => [], 'todos' => [], 'tasks' => []];

    /** @var array{milestones: list<int>, todos: array<int, list<int>>, tasks: array<int, list<int>>} Order before the import */
    private array $before = ['milestones' => [], 'todos' => [], 'tasks' => []];

    /** @var list<array{string, int}> Groups an element left by a move: [table, parent id] */
    private array $leftGroups = [];

    /** @var array<int, true> Tasks written, for the check of the releases */
    private array $touchedTasks = [];

    /** @var array<int, true> Elements that moved to another parent (not reported again as a change of order) */
    private array $moved = [];

    public function __construct(
        private readonly ProjectLabels $labels,
        private readonly TaskLabels $taskLabels,
        private readonly ProjectMembership $membership,
        private readonly KanbanOrdering $kanban,
        private readonly Ordering $ordering,
        private readonly Deletion $deletion,
        private readonly TaskNotifier $notifier,
        private readonly PlanerRevision $revision,
        private readonly Export $export,
        private readonly LimitCheck $limits,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     * @param  bool  $appendOnly  Only add: every change of something that exists is an error (the assistant, docs/auftrag-m6.md §1 Nr. 4)
     * @param  int|null  $templateId  The template a new project comes from (only without $project)
     * @param  Idea|null  $idea  The idea this file is made from: afterwards adopted, its new todos know it
     */
    public function run(?Project $project, array $document, ImportMode $mode, bool $dryRun, bool $notifyAssignees, User $actor, bool $appendOnly = false, ?int $templateId = null, ?Idea $idea = null): ImportResult
    {
        $this->reset($document, $mode, $notifyAssignees, $actor);
        $this->dryRun = $dryRun;
        $this->appendOnly = $appendOnly && $project !== null; // a new project has nothing existing to protect
        $this->templateId = $project === null ? $templateId : null;
        $this->titles = $this->appendOnly ? $this->titlesOf($project) : [];

        if ($idea !== null && ! $actor->can('update', $idea)) {
            $this->error('', 'denied', __('planer.import.denied', ['what' => __('planer.import.what.idea')]));
        }

        foreach (Validator::errors($document) as $issue) {
            $this->error($issue['path'], 'invalid', __('planer.import.invalid', ['message' => $issue['message']]));
        }

        if ($project !== null) {
            $this->precheck($project, $document);
        }

        if ($this->errors !== []) {
            return new ImportResult(errors: $this->errors, dryRun: $dryRun, projectId: $project?->getKey());
        }

        $previous = Auth::user();
        Auth::setUser($actor);
        DB::beginTransaction();

        try {
            ReleaseRules::withoutChecks(fn () => PlanerLimits::withoutChecks(fn () => $this->apply($project)));
            $this->checkResult();
            $this->checkAppendOnly();

            if ($idea !== null && $this->errors === [] && ! $dryRun) {
                $this->adopt($idea);
            }

            if ($this->errors === [] && $this->changes !== []) {
                $this->revision->touch($this->project->getKey());
            }

            $commit = $this->errors === [] && ! $dryRun;
            $projectId = $this->newProject && ! $commit ? null : $this->project->getKey();
            $commit ? DB::commit() : DB::rollBack();
        } catch (Throwable $error) {
            DB::rollBack();

            throw $error;
        } finally {
            $previous === null ? Auth::forgetUser() : Auth::setUser($previous);
            Rights::clearCache();
            Role::forgetRegistry();
        }

        if ($this->errors !== []) {
            return new ImportResult(errors: $this->errors, dryRun: $dryRun);
        }

        return new ImportResult(
            changes: $this->changes,
            idMap: $dryRun ? [] : $this->idMap,
            document: $dryRun ? null : $this->export->document($this->project->fresh()),
            dryRun: $dryRun,
            projectId: $projectId,
        );
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function reset(array $document, ImportMode $mode, bool $notify, User $actor): void
    {
        $this->document = $document;
        $this->mode = $mode;
        $this->notify = $notify;
        $this->actor = $actor;
        $this->changes = [];
        $this->errors = [];
        $this->idMap = [];
        $this->seen = ['milestone' => [], 'todo' => [], 'task' => []];
        $this->paths = [];
        $this->wanted = ['milestones' => [], 'todos' => [], 'tasks' => []];
        $this->before = ['milestones' => [], 'todos' => [], 'tasks' => []];
        $this->leftGroups = [];
        $this->touchedTasks = [];
        $this->moved = [];
        $this->createdTodoIds = [];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function precheck(Project $project, array $document): void
    {
        if ($project->archived_at !== null) {
            $this->error('', 'archived', __('projects.archived'));

            return;
        }

        $current = (int) Project::query()->whereKey($project->getKey())->value('planer_revision');

        if ($this->mode === ImportMode::Full && isset($document['revision']) && (int) $document['revision'] !== $current) {
            $this->error('revision', 'revision', __('planer.import.revision', ['file' => $document['revision'], 'project' => $current]));
        }
    }

    private function apply(?Project $project): void
    {
        $this->newProject = $project === null;

        if ($project === null) {
            if (! $this->allowed('create', Project::class, '', 'create_project')) {
                $this->project = new Project;

                return;
            }

            $project = $this->createProject();
        } else {
            $this->updateProject($project);
        }

        $this->project = $project;
        $this->load();

        $this->applyLabels();
        $this->applyMembers();
        $this->applyStructure();
        $this->applyOrder();
        $this->applyDeletions();
    }

    private function createProject(): Project
    {
        $data = $this->document['project'];
        $project = new Project([
            'name' => $data['name'],
            'description' => $this->description($data['description'] ?? null, 'project.description')[1],
            'locale' => $data['locale'],
        ]);
        $project->template_id = $this->templateId;
        $project->save();
        $project->members()->attach($this->actor->getKey(), ['role_id' => AppSettings::projectCreatorRole()->id]);
        Rights::clearCache();

        $this->change('create', 'project', null, ['name', 'description', 'locale']);

        return $project;
    }

    private function updateProject(Project $project): void
    {
        $data = $this->document['project'];
        $fields = [];
        $values = [];

        if ($data['name'] !== $project->name) {
            $fields[] = 'name';
            $values['name'] = $data['name'];
        }

        if ($data['locale'] !== $project->locale->value) {
            $fields[] = 'locale';
            $values['locale'] = $data['locale'];
        }

        if ($this->present($data, 'description')) {
            [$ok, $doc] = $this->description($data['description'] ?? null, 'project.description');

            if ($ok && ! $this->sameDocument($project->description, $doc)) {
                $fields[] = 'description';
                $values['description'] = $doc;
            }
        }

        if ($fields === [] || ! $this->allowed('update', $project, 'project', 'update_project')) {
            return;
        }

        $this->save($project->fill($values), 'project');
        $this->change('update', 'project', null, $fields);
    }

    private function load(): void
    {
        $projectId = $this->project->getKey();
        $this->milestones = Milestone::query()->with('project')->where('project_id', $projectId)->orderBy('position')->orderBy('id')->get()->keyBy('id');
        $this->todos = Todo::query()->with('project')->where('project_id', $projectId)->orderBy('position')->orderBy('id')->get()->keyBy('id');
        $this->tasks = Task::query()->where('project_id', $projectId)->with(['project', 'labels', 'assignee'])->orderBy('position')->orderBy('id')->get()->keyBy('id');

        $this->before['milestones'] = $this->milestones->keys()->all();

        foreach ($this->todos->groupBy('milestone_id', preserveKeys: true) as $milestoneId => $group) {
            $this->before['todos'][(int) $milestoneId] = $group->keys()->all();
        }

        foreach ($this->tasks->groupBy('todo_id', preserveKeys: true) as $todoId => $group) {
            $this->before['tasks'][(int) $todoId] = $group->keys()->all();
        }
    }

    // ─── Labels ──────────────────────────────────────────────────────

    private function applyLabels(): void
    {
        $data = $this->document['project'];

        if (! array_key_exists('labels', $data) && $this->mode === ImportMode::Partial) {
            return;
        }

        $wanted = array_map(fn (array $label): array => [
            'key' => $label['key'],
            'names' => $label['names'],
            'category' => $label['category'] ?? null,
            'color' => $label['color'],
        ], $data['labels'] ?? []);

        $current = $this->project->labels()->get();
        $differs = $this->labelsDiffer($current, $wanted, $this->mode === ImportMode::Partial);

        if (! $differs) {
            return;
        }

        if (! $this->allowed('update', $this->project, 'project.labels', 'update_project')) {
            return;
        }

        try {
            $this->labels->replace($this->project, $wanted, $this->mode === ImportMode::Partial);
        } catch (ValidationException $error) {
            $this->validation('project.labels', $error);

            return;
        }

        $this->change('update', 'project.labels', null, ['labels']);
    }

    /**
     * @param  Collection<int, Label>  $current
     * @param  list<array{key: string, names: array<string, string>, category: ?string, color: string}>  $wanted
     */
    private function labelsDiffer(Collection $current, array $wanted, bool $keepOthers): bool
    {
        $existing = $current->keyBy('key');
        $listed = array_column($wanted, 'key');

        if (! $keepOthers && $existing->keys()->all() !== $listed) {
            return true;
        }

        if ($keepOthers && ! empty(array_diff($listed, $existing->keys()->all()))) {
            return true;
        }

        foreach ($wanted as $label) {
            $label['names'] = $this->sorted($label['names']);
            $had = $existing->get($label['key']);

            if ($had === null || $this->sorted((array) $had->names) !== $label['names'] || $had->category !== $label['category'] || $had->color->value !== $label['color']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $names
     * @return array<string, string>
     */
    private function sorted(array $names): array
    {
        ksort($names);

        return $names;
    }

    // ─── Members ─────────────────────────────────────────────────────

    private function applyMembers(): void
    {
        $listed = [];

        foreach ($this->document['project']['members'] as $index => $entry) {
            $path = "project.members[{$index}]";
            $user = $this->person($entry['email']);

            if ($user === null) {
                $this->error("{$path}.email", 'person', __('planer.import.person_unknown', ['email' => $entry['email']]));

                continue;
            }

            $listed[$user->getKey()] = true;
            $role = Role::byKey(Role::SCOPE_PROJECT, $entry['role']);

            if ($role === null) {
                $this->error("{$path}.role", 'role', __('planer.import.role_unknown', ['role' => $entry['role']]));

                continue;
            }

            // The person who creates a project keeps the creator role; an entry for them does not count.
            if ($this->newProject && $user->is($this->actor)) {
                continue;
            }

            if ($this->project->roleIdOf($user) === $role->id) {
                continue;
            }

            try {
                $this->membership->set($this->actor, $this->project, $user, $role);
                $this->change('update', $path, null, ['member']);
            } catch (AuthorizationException) {
                $this->error($path, 'denied', __('planer.import.denied', ['what' => __('planer.import.what.members')]));
            } catch (ValidationException $error) {
                $this->validation($path, $error);
            }
        }

        if ($this->mode !== ImportMode::Full || $this->newProject) {
            return;
        }

        foreach ($this->project->members()->get() as $member) {
            if (isset($listed[$member->getKey()])) {
                continue;
            }

            if ($member->is($this->actor)) {
                $this->error('project.members', 'self', __('planer.import.self_removal'));

                continue;
            }

            try {
                $this->membership->remove($this->actor, $this->project, $member);
                $this->change('delete', 'project.members', null, ['member']);
            } catch (AuthorizationException) {
                $this->error('project.members', 'denied', __('planer.import.denied', ['what' => __('planer.import.what.members')]));
            } catch (ValidationException $error) {
                $this->validation('project.members', $error);
            }
        }
    }

    private function person(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->whereNotNull('approved_at')->first();
    }

    // ─── Milestones, todos, tasks ────────────────────────────────────

    private function applyStructure(): void
    {
        foreach ($this->document['milestones'] as $mi => $entry) {
            $path = "milestones[{$mi}]";
            $milestone = $this->milestone($entry, $path);

            if ($milestone === null) {
                continue;
            }

            $this->wanted['milestones'][] = $milestone->getKey();

            foreach ($entry['todos'] as $ti => $todoEntry) {
                $todoPath = "{$path}.todos[{$ti}]";
                $todo = $this->todo($todoEntry, $todoPath, $milestone);

                if ($todo === null) {
                    continue;
                }

                $this->wanted['todos'][$milestone->getKey()][] = $todo->getKey();

                foreach ($todoEntry['tasks'] as $ki => $taskEntry) {
                    $task = $this->task($taskEntry, "{$todoPath}.tasks[{$ki}]", $todo);

                    if ($task !== null) {
                        $this->wanted['tasks'][$todo->getKey()][] = $task->getKey();
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function milestone(array $entry, string $path): ?Milestone
    {
        [$model, $isNew] = $this->resolve($entry['id'], 'm', $this->milestones, "{$path}.id");

        if ($model === null && ! $isNew) {
            return null;
        }

        $values = [];
        $fields = [];

        if ($isNew || $entry['title'] !== $model->title) {
            $values['title'] = $entry['title'];

            if (! $isNew) {
                $fields[] = 'title';
            }
        }

        if ($this->present($entry, 'description')) {
            [$ok, $doc] = $this->description($entry['description'] ?? null, "{$path}.description");

            if ($ok && ($isNew ? $doc !== null : ! $this->sameDocument($model->description, $doc))) {
                $values['description'] = $doc;
                $fields[] = 'description';
            }
        }

        if ($this->present($entry, 'dueDate')) {
            $due = $entry['dueDate'] ?? null;

            if ($isNew ? $due !== null : $due !== $model->due_date?->format('Y-m-d')) {
                $values['due_date'] = $due;
                $fields[] = 'dueDate';
            }
        }

        if ($this->present($entry, 'release')) {
            $release = $entry['release'] ?? null;
            $version = $release['version'] ?? null;
            $status = $release['status'] ?? null;

            if ($isNew ? $release !== null : ($version !== $model->release_version || $status !== $model->release_status?->value)) {
                $values['release_version'] = $version;
                $values['release_status'] = $status === null ? null : ReleaseStatus::from($status);
                $fields[] = 'release';
            }
        }

        if ($isNew) {
            if (! $this->allowed('createMilestone', $this->project, $path, 'create_milestone')) {
                return null;
            }

            $model = new Milestone(['project_id' => $this->project->getKey()] + $values);
            $this->save($model, $path);
            $this->created($model, 'm', $entry['id'], $path, ['title', ...$fields]);
        } elseif ($fields !== [] && $this->allowed('update', $model, $path, 'update')) {
            $this->save($model->fill($values), $path);
            $this->change('update', $path, "m-{$model->getKey()}", $fields);
        }

        $this->seen['milestone'][$model->getKey()] = true;
        $this->paths["m-{$model->getKey()}"] = $path;

        return $model;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function todo(array $entry, string $path, Milestone $milestone): ?Todo
    {
        [$model, $isNew] = $this->resolve($entry['id'], 't', $this->todos, "{$path}.id");

        if ($model === null && ! $isNew) {
            return null;
        }

        $values = [];
        $fields = [];

        if ($isNew || $entry['title'] !== $model->title) {
            $values['title'] = $entry['title'];

            if (! $isNew) {
                $fields[] = 'title';
            }
        }

        if ($this->present($entry, 'description')) {
            [$ok, $doc] = $this->description($entry['description'] ?? null, "{$path}.description");

            if ($ok && ($isNew ? $doc !== null : ! $this->sameDocument($model->description, $doc))) {
                $values['description'] = $doc;
                $fields[] = 'description';
            }
        }

        if ($this->present($entry, 'dueDate')) {
            $due = $entry['dueDate'] ?? null;

            if ($isNew ? $due !== null : $due !== $model->due_date?->format('Y-m-d')) {
                $values['due_date'] = $due;
                $fields[] = 'dueDate';
            }
        }

        if ($isNew) {
            if (! $this->allowed('createTodo', $milestone, $path, 'create_todo')) {
                return null;
            }

            $model = new Todo(['milestone_id' => $milestone->getKey()] + $values);
            $this->save($model, $path);
            $this->createdTodoIds[] = (int) $model->getKey();
            $this->created($model, 't', $entry['id'], $path, ['title', ...$fields]);
        } else {
            $parentChanged = (int) $model->milestone_id !== (int) $milestone->getKey();

            if (($fields !== [] || $parentChanged) && $this->allowed('update', $model, $path, 'update')) {
                if ($parentChanged) {
                    $this->leftGroups[] = ['todos', (int) $model->milestone_id];
                    $model->milestone_id = $milestone->getKey();
                    $this->moved[(int) $model->getKey()] = true;
                }

                $this->save($model->fill($values), $path);

                if ($fields !== []) {
                    $this->change('update', $path, "t-{$model->getKey()}", $fields);
                }

                if ($parentChanged) {
                    $this->change('move', $path, "t-{$model->getKey()}", ['milestone']);
                }
            }
        }

        $this->seen['todo'][$model->getKey()] = true;
        $this->paths["t-{$model->getKey()}"] = $path;

        return $model;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function task(array $entry, string $path, Todo $todo): ?Task
    {
        [$model, $isNew] = $this->resolve($entry['id'], 'k', $this->tasks, "{$path}.id");

        if ($model === null && ! $isNew) {
            return null;
        }

        $values = [];
        $fields = [];

        if ($isNew || $entry['title'] !== $model->title) {
            $values['title'] = $entry['title'];

            if (! $isNew) {
                $fields[] = 'title';
            }
        }

        if ($this->present($entry, 'description')) {
            [$ok, $doc] = $this->description($entry['description'] ?? null, "{$path}.description");

            if ($ok && ($isNew ? $doc !== null : ! $this->sameDocument($model->description, $doc))) {
                $values['description'] = $doc;
                $fields[] = 'description';
            }
        }

        if ($this->present($entry, 'dueDate')) {
            $due = $entry['dueDate'] ?? null;

            if ($isNew ? $due !== null : $due !== $model->due_date?->format('Y-m-d')) {
                $values['due_date'] = $due;
                $fields[] = 'dueDate';
            }
        }

        if ($this->present($entry, 'commit')) {
            $commit = $entry['commit'] ?? null;

            if ($isNew ? $commit !== null : $commit !== $model->commit) {
                $values['commit'] = $commit;
                $fields[] = 'commit';
            }
        }

        if ($this->present($entry, 'userStory')) {
            $story = $entry['userStory'] ?? null;

            if ($isNew ? $story !== null : $story !== $model->user_story) {
                $values['user_story'] = $story;
                $fields[] = 'userStory';
            }
        }

        $labelKeys = $this->present($entry, 'labels') ? array_values($entry['labels'] ?? []) : null;
        $labelsDiffer = $labelKeys !== null && ($isNew ? $labelKeys !== [] : $this->labelKeysOf($model) !== $this->orderedByProject($labelKeys));

        $status = TaskStatus::from($entry['status']);
        $assigneeGiven = $this->present($entry, 'assigneeEmail');
        $assignee = $assigneeGiven ? $this->assigneeOf($entry, $path) : null;

        // An assignee that could not be found is an error already; do not read it as "nobody".
        if ($assignee === false) {
            $assignee = null;
            $assigneeGiven = false;
        }

        if ($isNew) {
            if (! $this->allowed('createTask', $todo, $path, 'create_task')) {
                return null;
            }

            $model = new Task(['todo_id' => $todo->getKey()] + $values);
            $model->forceFill([
                'status' => $status,
                'board_position' => $this->kanban->nextPosition($this->project->getKey(), $status),
            ]);
            $this->save($model, $path);
            $fields = [...$fields, 'status'];

            if ($assigneeGiven && $assignee !== null) {
                $this->assign($model, $assignee, $path, null);
                $fields[] = 'assigneeEmail';
            }

            if ($labelsDiffer) {
                $this->setLabels($model, $labelKeys, $path);
                $fields[] = 'labels';
            }

            $this->created($model, 'k', $entry['id'], $path, ['title', ...$fields]);
            $this->notifyAssigned($model, $assigneeGiven && $assignee !== null, false);
        } else {
            $this->updateTask($model, $todo, $values, $fields, $status, $assignee, $assigneeGiven, $labelsDiffer, $labelKeys, $path);
        }

        $this->touchedTasks[$model->getKey()] = true;
        $this->seen['task'][$model->getKey()] = true;
        $this->paths["k-{$model->getKey()}"] = $path;

        return $model;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $fields
     * @param  list<string>|null  $labelKeys
     */
    private function updateTask(Task $task, Todo $todo, array $values, array $fields, TaskStatus $status, ?User $assignee, bool $assigneeGiven, bool $labelsDiffer, ?array $labelKeys, string $path): void
    {
        $id = "k-{$task->getKey()}";
        $parentChanged = (int) $task->todo_id !== (int) $todo->getKey();
        $statusChanged = $status !== $task->status;
        $assigneeChanged = $assigneeGiven && (int) ($assignee?->getKey() ?? 0) !== (int) ($task->assigned_user_id ?? 0);
        $wasIdea = $task->status === TaskStatus::IDEA;

        $needsUpdate = $fields !== [] || $parentChanged || $labelsDiffer;

        if ($needsUpdate && ! $this->allowed('update', $task, $path, 'update')) {
            return;
        }

        if ($statusChanged && ! $this->allowed('move', $task, $path, 'move')) {
            return;
        }

        if ($assigneeChanged && ! Rights::mayAssignTask($this->actor, $this->project, $assignee?->getKey(), $task->assigned_user_id)) {
            $this->error($path, 'denied', __('planer.import.denied', ['what' => __('planer.import.what.assign')]));

            return;
        }

        if ($parentChanged) {
            $this->leftGroups[] = ['tasks', (int) $task->todo_id];
            $task->todo_id = $todo->getKey();
            $this->moved[(int) $task->getKey()] = true;
        }

        $task->fill($values);

        if ($assigneeChanged) {
            $task->assigned_user_id = $assignee?->getKey();
        }

        $this->save($task, $path);

        if ($statusChanged) {
            $this->kanban->move($task, $status, PHP_INT_MAX);
        }

        if ($labelsDiffer) {
            $this->setLabels($task, $labelKeys, $path);
        }

        if ($labelsDiffer) {
            $fields[] = 'labels';
        }

        if ($statusChanged) {
            $fields[] = 'status';
        }

        if ($assigneeChanged) {
            $fields[] = 'assigneeEmail';
        }

        if ($fields !== []) {
            $this->change('update', $path, $id, $fields);
        }

        if ($parentChanged) {
            $this->change('move', $path, $id, ['todo']);
        }

        $this->notifyAssigned($task, $assigneeChanged && $assignee !== null, $wasIdea && $statusChanged);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function assigneeOf(array $entry, string $path): User|false|null
    {
        $email = $entry['assigneeEmail'] ?? null;

        if ($email === null || $email === '') {
            return null;
        }

        $user = $this->person($email);

        if ($user === null || $this->project->roleIdOf($user) === null) {
            $this->error("{$path}.assigneeEmail", 'assignee', __('planer.import.assignee_not_member', ['email' => $email]));

            return false;
        }

        return $user;
    }

    private function assign(Task $task, User $assignee, string $path, ?int $current): void
    {
        if (! Rights::mayAssignTask($this->actor, $this->project, $assignee->getKey(), $current)) {
            $this->error($path, 'denied', __('planer.import.denied', ['what' => __('planer.import.what.assign')]));

            return;
        }

        $task->assigned_user_id = $assignee->getKey();
        $this->save($task, $path);
    }

    /**
     * @param  list<string>  $keys
     */
    private function setLabels(Task $task, array $keys, string $path): void
    {
        $ids = $this->project->labels()->whereIn('key', $keys)->pluck('id')->all();

        try {
            $this->taskLabels->set($task, $ids);
        } catch (ValidationException $error) {
            $this->validation($path, $error);
        }
    }

    /**
     * Mails go only on request, once per task: for a new assignment, or when the task leaves the idea status with an assignee.
     */
    private function notifyAssigned(Task $task, bool $newAssignment, bool $leftIdea): void
    {
        if (! $this->notify || $task->assigned_user_id === null || ! ($newAssignment || $leftIdea)) {
            return;
        }

        $this->notifier->assigned($task->refresh(), $this->actor);
    }

    /**
     * @return list<string>
     */
    private function labelKeysOf(Task $task): array
    {
        return $this->orderedByProject($task->labels->pluck('key')->all());
    }

    /**
     * The keys in the order of the project's labels, as the file writes them.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function orderedByProject(array $keys): array
    {
        $order = $this->project->labels()->orderBy('position')->orderBy('id')->pluck('key')->flip();

        $keys = array_values(array_unique($keys));
        usort($keys, fn (string $a, string $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return $keys;
    }

    // ─── Order ───────────────────────────────────────────────────────

    private function applyOrder(): void
    {
        $this->orderGroup('milestones', 'project_id', $this->project->getKey(), $this->wanted['milestones'], $this->before['milestones'], 'm');

        foreach ($this->wanted['todos'] as $milestoneId => $ids) {
            $this->orderGroup('todos', 'milestone_id', $milestoneId, $ids, $this->before['todos'][$milestoneId] ?? [], 't');
        }

        foreach ($this->wanted['tasks'] as $todoId => $ids) {
            $this->orderGroup('tasks', 'todo_id', $todoId, $ids, $this->before['tasks'][$todoId] ?? [], 'k');
        }

        // Groups an element left, whose parent the file does not list (partial): close the gap.
        foreach ($this->leftGroups as [$table, $parentId]) {
            if ($table === 'todos' && ! isset($this->wanted['todos'][$parentId])) {
                $this->ordering->close($this->project->getKey(), Todo::query()->where('milestone_id', $parentId));
            }

            if ($table === 'tasks' && ! isset($this->wanted['tasks'][$parentId])) {
                $this->ordering->close($this->project->getKey(), Task::query()->where('todo_id', $parentId));
            }
        }
    }

    /**
     * Numbers a group in the order of the file; what the file does not list
     * (partial mode) follows in its old order.
     *
     * @param  list<int>  $wanted  Ids in the order of the file
     * @param  list<int>  $before  Ids of the group before the import, in their order
     */
    private function orderGroup(string $table, string $column, int|string $parentId, array $wanted, array $before, string $prefix): void
    {
        $now = DB::table($table)->where($column, $parentId)->whereNull('deleted_at')->orderBy('position')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $order = $this->mode === ImportMode::Partial ? $this->refillSlots($now, $wanted) : [...$wanted, ...array_values(array_diff($now, $wanted))];

        // Items that were in the group before and are still: did their relative order change?
        $kept = array_values(array_filter($before, fn (int $id): bool => in_array($id, $order, true)));
        $keptNow = array_values(array_filter($order, fn (int $id): bool => in_array($id, $kept, true)));

        foreach ($order as $index => $id) {
            DB::table($table)->where('id', $id)->where('position', '!=', $index)->update(['position' => $index]);
        }

        if ($kept === $keptNow) {
            return;
        }

        foreach ($keptNow as $index => $id) {
            if ($kept[$index] !== $id && ! isset($this->moved[$id])) {
                $this->change('move', $this->paths["{$prefix}-{$id}"] ?? "{$prefix}-{$id}", "{$prefix}-{$id}", ['position']);
            }
        }
    }

    /**
     * Partial: what the file does not list keeps its place. The places of the
     * listed items are filled with them again, in the order of the file.
     *
     * @param  list<int>  $now  The group as it is
     * @param  list<int>  $wanted  The listed items in the order of the file
     * @return list<int>
     */
    private function refillSlots(array $now, array $wanted): array
    {
        $listed = array_values(array_filter($wanted, fn (int $id): bool => in_array($id, $now, true)));
        $slots = $listed;
        $result = [];

        foreach ($now as $id) {
            $result[] = in_array($id, $slots, true) ? array_shift($listed) : $id;
        }

        return $result;
    }

    // ─── Deletions (full) ────────────────────────────────────────────

    private function applyDeletions(): void
    {
        if ($this->mode !== ImportMode::Full || $this->newProject) {
            return;
        }

        $notSeen = fn (string $type, Collection $items): Collection => $items->reject(fn (Model $model): bool => isset($this->seen[$type][$model->getKey()]));

        // Only the tops: what is inside a milestone or todo that goes goes with it.
        $milestones = $notSeen('milestone', $this->milestones);
        $goneMilestones = $milestones->keys()->flip();
        $todosNotSeen = $notSeen('todo', $this->todos);
        $goneTodos = $todosNotSeen->keys()->flip();

        $todos = $todosNotSeen->reject(fn (Todo $todo): bool => $goneMilestones->has((int) $todo->milestone_id));
        $tasks = $notSeen('task', $this->tasks)->reject(fn (Task $task): bool => $goneTodos->has((int) $task->todo_id)
            || $goneMilestones->has((int) ($this->todos->get((int) $task->todo_id)?->milestone_id ?? 0)));

        $tops = [];

        foreach ([['m', $milestones], ['t', $todos], ['k', $tasks]] as [$prefix, $group]) {
            foreach ($group as $model) {
                if (! $this->allowed('delete', $model, "{$prefix}-{$model->getKey()}", 'delete')) {
                    continue;
                }

                $tops[] = $model;
                $this->change('delete', "{$prefix}-{$model->getKey()}", "{$prefix}-{$model->getKey()}", [], (string) $model->title);
            }
        }

        if ($this->errors === []) {
            $this->deletion->deleteMany(array_map(fn (Model $model): Model => $model->fresh() ?? $model, $tops));
        }
    }

    // ─── The result as a whole ───────────────────────────────────────

    private function checkResult(): void
    {
        if ($this->errors !== [] || ! isset($this->project) || ! $this->project->exists) {
            return;
        }

        foreach ($this->limits->forProject($this->project->fresh()) as $finding) {
            $this->error($finding['path'], 'limit', $finding['message']);
        }

        foreach (Milestone::query()->where('project_id', $this->project->getKey())->where('release_status', ReleaseStatus::RELEASED->value)->get() as $milestone) {
            try {
                ReleaseRules::checkMilestone($milestone, true);
            } catch (ValidationException $error) {
                $this->validation($this->paths["m-{$milestone->getKey()}"] ?? "m-{$milestone->getKey()}", $error);
            }
        }

        foreach (array_keys($this->touchedTasks) as $taskId) {
            try {
                ReleaseRules::checkTask(Task::query()->findOrFail($taskId), true);
            } catch (ValidationException $error) {
                $this->validation($this->paths["k-{$taskId}"] ?? "k-{$taskId}", $error);
            }
        }
    }

    /**
     * The assistant only adds (docs/auftrag-m6.md §1 Nr. 4): a change of something that exists is an error with its
     * path, also in a dry run. A new project is all new.
     */
    private function checkAppendOnly(): void
    {
        if (! $this->appendOnly || $this->errors !== []) {
            return;
        }

        foreach ($this->changes as $change) {
            if ($change['kind'] !== 'create') {
                $this->error($change['path'], 'append_only', __('planer.import.append_only', ['what' => $this->titleOf($change['id'] ?? null) ?? $change['id'] ?? $change['path']]));
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function titlesOf(Project $project): array
    {
        $titles = [];

        foreach ([['m', Milestone::query()], ['t', Todo::query()], ['k', Task::query()]] as [$prefix, $query]) {
            foreach ($query->where('project_id', $project->getKey())->pluck('title', 'id') as $id => $title) {
                $titles["{$prefix}-{$id}"] = (string) $title;
            }
        }

        return $titles;
    }

    private function titleOf(?string $id): ?string
    {
        return $id === null ? null : ($this->titles[$id] ?? null);
    }

    /** The idea is put into the project: adopted, its new todos know it, the project is set if it had none. */
    private function adopt(Idea $idea): void
    {
        Todo::query()->whereIn('id', $this->createdTodoIds)->update(['idea_id' => $idea->getKey()]);

        $idea->status = IdeaState::ADOPTED;
        $idea->adopted_at = now();
        $idea->project_id ??= $this->project->getKey();
        $idea->save();
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * The existing element a file id points at, or "new". An id of a project
     * that is created is always new: the file may come from another project.
     *
     * @param  Collection<int, covariant Model>  $existing
     * @return array{0: ?Model, 1: bool} [model, isNew]
     */
    private function resolve(string $id, string $prefix, Collection $existing, string $path): array
    {
        preg_match('/^[mtk]-(new-)?(\d+)$/', $id, $match);

        if ($this->newProject || ($match[1] ?? '') === 'new-') {
            return [null, true];
        }

        $model = $existing->get((int) ($match[2] ?? 0));

        if ($model === null) {
            $this->error($path, 'unknown_id', __('planer.import.unknown_id', ['id' => $id]));

            return [null, false];
        }

        return [$model, false];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function present(array $entry, string $key): bool
    {
        return $this->mode === ImportMode::Full || array_key_exists($key, $entry);
    }

    /**
     * @return array{0: bool, 1: array<string, mixed>|null} [ok, document]
     */
    private function description(?string $markdown, string $path): array
    {
        if ($markdown === null || trim($markdown) === '') {
            return [true, null];
        }

        try {
            $doc = MarkdownToTiptap::convert($markdown, Mode::Strict)->doc;
        } catch (InvalidMarkdown $error) {
            $this->error($path, 'description', __('planer.import.description', ['problem' => $error->getMessage()]));

            return [false, null];
        }

        return [true, $doc['content'] === [] ? null : $doc];
    }

    /**
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>|null  $wanted
     */
    private function sameDocument(?array $current, ?array $wanted): bool
    {
        $a = $current === null ? '' : TiptapToMarkdown::convert($current);
        $b = $wanted === null ? '' : TiptapToMarkdown::convert($wanted);

        return $a === $b;
    }

    private function allowed(string $ability, mixed $subject, string $path, string $what): bool
    {
        if ($this->actor->can($ability, $subject)) {
            return true;
        }

        $this->error($path, 'denied', __('planer.import.denied', ['what' => __("planer.import.what.{$what}")]));

        return false;
    }

    private function save(Model $model, string $path): void
    {
        try {
            $model->save();
        } catch (ValidationException $error) {
            $this->validation($path, $error);
        }
    }

    /**
     * @param  list<string>  $fields
     */
    private function created(Model $model, string $prefix, string $fileId, string $path, array $fields): void
    {
        $id = "{$prefix}-{$model->getKey()}";

        if ($fileId !== $id) {
            $this->idMap[$fileId] = $id;
        }

        // A dry run names the id of the file: the number the app took for it is rolled back and shows nowhere.
        $this->change('create', $path, $this->dryRun ? $fileId : $id, $fields);
    }

    /**
     * @param  list<string>  $fields
     */
    private function change(string $kind, string $path, ?string $id, array $fields, ?string $title = null): void
    {
        $this->changes[] = ['kind' => $kind, 'path' => $path, 'id' => $id, 'fields' => $fields, ...($title === null ? [] : ['title' => $title])];
    }

    private function error(string $path, string $kind, string $message): void
    {
        $this->errors[] = ['path' => $path, 'kind' => $kind, 'message' => $message];
    }

    private function validation(string $path, ValidationException $error): void
    {
        foreach ($error->errors() as $messages) {
            foreach ((array) $messages as $message) {
                $this->error($path, 'rule', (string) $message);
            }
        }
    }
}
