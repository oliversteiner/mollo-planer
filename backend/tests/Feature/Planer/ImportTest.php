<?php

namespace Tests\Feature\Planer;

use App\Enums\UserRole;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Planer\Export;
use App\Planer\Import;
use App\Planer\ImportMode;
use App\Planer\ImportResult;
use App\Services\Deletion;
use App\Services\RoleAdministration;
use App\Support\Rights;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\GraphQL\GraphQLTestCase;

/**
 * The import of a planer file (docs/auftrag-m4.md §4): the roundtrip, the
 * modes, the dry run and the revision.
 */
class ImportTest extends GraphQLTestCase
{
    private function import(?Project $project, array $document, ImportMode $mode = ImportMode::Full, bool $dryRun = false, string $as = 'owner', bool $notify = false): ImportResult
    {
        return app(Import::class)->run($project, $document, $mode, $dryRun, $notify, $this->users[$as]);
    }

    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        return json_decode((string) file_get_contents(base_path("../spec/fixtures/{$name}.planer.json")), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * The document without what the app decides: ids (replaced by their place), revision, schema hint, order of members.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function normalized(array $document): array
    {
        unset($document['revision'], $document['$schema']);
        usort($document['project']['members'], fn (array $a, array $b): int => $a['email'] <=> $b['email']);

        foreach ($document['milestones'] as $mi => $milestone) {
            $document['milestones'][$mi]['id'] = "m-{$mi}";

            foreach ($milestone['todos'] as $ti => $todo) {
                $document['milestones'][$mi]['todos'][$ti]['id'] = "t-{$mi}-{$ti}";

                foreach ($todo['tasks'] as $ki => $task) {
                    $document['milestones'][$mi]['todos'][$ti]['tasks'][$ki]['id'] = "k-{$mi}-{$ti}-{$ki}";

                    if (isset($task['labels'])) {
                        sort($task['labels']);
                        $document['milestones'][$mi]['todos'][$ti]['tasks'][$ki]['labels'] = $task['labels'];
                    }
                }
            }
        }

        return $document;
    }

    private function revision(Project $project): int
    {
        return (int) $project->fresh()->planer_revision;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validFixtures(): array
    {
        return [
            'planer-valid' => ['planer-valid'],
            'beispiel-planer' => ['beispiel-planer'],
            'planer-leer' => ['planer-leer'],
            'planer-projekt' => ['planer-projekt'],
        ];
    }

    #[DataProvider('validFixtures')]
    public function test_export_of_the_import_is_the_file(string $name): void
    {
        $document = $this->fixture($name);
        Role::query()->firstOrCreate(['scope' => 'project', 'key' => 'bar-team'], ['names' => ['de' => 'Bar-Team', 'en' => 'Bar team']]);
        Role::forgetRegistry();

        foreach ($document['project']['members'] as $member) {
            User::factory()->create(['email' => $member['email']]);
        }
        $actor = User::query()->where('email', $document['project']['members'][0]['email'])->firstOrFail();
        $actor->forceFill(['role' => UserRole::ADMIN])->save();
        $this->users['owner'] = $actor;

        $result = $this->import(null, $document);

        $this->assertSame([], $result->errors, json_encode($result->errors, JSON_PRETTY_PRINT));
        $this->assertSame($this->normalized($document), $this->normalized($result->document));
        $this->assertSame(1, $this->revision(Project::query()->findOrFail($result->projectId)));
    }

    public function test_importing_what_the_project_exports_changes_nothing(): void
    {
        Task::factory()->for($this->todo)->create(['title' => 'Zweiter']);
        $before = $this->revision($this->project);
        $document = app(Export::class)->document($this->project->fresh());

        $result = $this->import($this->project, $document);

        $this->assertSame([], $result->errors, json_encode($result->errors));
        $this->assertSame([], $result->changes);
        $this->assertSame($before, $this->revision($this->project));
        $this->assertSame($document, $result->document);
    }

    /** @return array<string, mixed> The export of the test project, to change in the tests */
    private function exported(): array
    {
        return app(Export::class)->document($this->project->fresh());
    }

    public function test_the_array_order_is_the_order_and_new_ids_are_mapped(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'] = [
            ['id' => 'k-new-1', 'title' => 'Neu vorne', 'status' => 'open'],
            ...$document['milestones'][0]['todos'][0]['tasks'],
            ['id' => 'k-new-2', 'title' => 'Neu hinten', 'status' => 'planned'],
        ];

        $result = $this->import($this->project, $document);

        $this->assertSame([], $result->errors, json_encode($result->errors));
        $this->assertSame(['Neu vorne', $this->task->title, 'Neu hinten'], $this->todo->tasks()->pluck('title')->all());
        $this->assertSame([0, 1, 2], $this->todo->tasks()->pluck('position')->map(fn ($p) => (int) $p)->all());
        $this->assertSame(['k-new-1', 'k-new-2'], array_keys($result->idMap));
        $this->assertSame(['create', 'create'], array_column($result->changes, 'kind'));
        $this->assertSame($this->revision($this->project), $document['revision'] + 1);
        $this->assertSame(Task::query()->where('title', 'Neu vorne')->value('created_by'), $this->users['owner']->id);
    }

    public function test_a_dry_run_writes_nothing_and_does_not_count(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['title'] = 'Anders';
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Neu', 'status' => 'open'];
        $before = [Task::query()->count(), $this->revision($this->project)];

        $result = $this->import($this->project, $document, dryRun: true);

        $this->assertSame([], $result->errors);
        $this->assertSame(['update', 'create'], array_column($result->changes, 'kind'));
        $this->assertSame('milestones[0]', $result->changes[0]['path']);
        $this->assertSame('k-new-1', $result->changes[1]['id']);
        $this->assertSame(['title'], $result->changes[0]['fields']);
        $this->assertNull($result->document);
        $this->assertSame([], $result->idMap);
        $this->assertSame($before, [Task::query()->count(), $this->revision($this->project)]);
        $this->assertNotSame('Anders', $this->milestone->fresh()->title);
    }

    public function test_full_needs_the_revision_of_the_project(): void
    {
        $document = $this->exported();
        $document['revision'] = $document['revision'] + 5;

        $result = $this->import($this->project, $document);

        $this->assertSame('revision', $result->errors[0]['path']);
        $this->assertStringContainsString((string) $document['revision'], $result->errors[0]['message']);
        $this->assertStringContainsString((string) $this->revision($this->project), $result->errors[0]['message']);

        $this->assertSame([], $this->import($this->project, $document, ImportMode::Partial)->errors);
    }

    public function test_full_puts_what_the_file_lacks_into_the_trash_as_one_deletion_and_undo_brings_it_back(): void
    {
        $second = Milestone::factory()->for($this->project)->create(['title' => 'Zweiter']);
        $secondTodo = Todo::factory()->for($second)->create();
        $secondTask = Task::factory()->for($secondTodo)->create();
        $extra = Task::factory()->for($this->todo)->create(['title' => 'Überzählig']);
        $document = $this->exported();
        $document['milestones'] = [$document['milestones'][0]];
        $document['milestones'][0]['todos'][0]['tasks'] = [$document['milestones'][0]['todos'][0]['tasks'][0]];

        $result = $this->import($this->project, $document);

        $this->assertSame([], $result->errors);
        $this->assertSame(['delete', 'delete'], array_column($result->changes, 'kind'));
        $this->assertSame(['Zweiter', 'Überzählig'], array_column($result->changes, 'title'));
        $this->assertNull(Milestone::query()->find($second->id));
        $this->assertNull(Task::query()->find($extra->id));
        $ids = [Milestone::onlyTrashed()->find($second->id)->deletion_id, Task::onlyTrashed()->find($extra->id)->deletion_id, Task::onlyTrashed()->find($secondTask->id)->deletion_id];
        $this->assertCount(1, array_unique($ids));

        $this->actingAs($this->users['owner']);
        app(Deletion::class)->restore($ids[0], $this->users['owner']);

        $this->assertNotNull(Milestone::query()->find($second->id));
        $this->assertNotNull(Todo::query()->find($secondTodo->id));
        $this->assertNotNull(Task::query()->find($secondTask->id));
        $this->assertNotNull(Task::query()->find($extra->id));
    }

    public function test_partial_adds_and_changes_but_never_deletes_and_keeps_fields_it_leaves_out(): void
    {
        $this->task->update(['due_date' => '2026-12-24']);
        $keep = Task::factory()->for($this->todo)->create(['title' => 'Bleibt']);
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'] = [['id' => "k-{$this->task->id}", 'title' => 'Neuer Titel', 'status' => 'open']];

        $result = $this->import($this->project, $document, ImportMode::Partial);

        $this->assertSame([], $result->errors, json_encode($result->errors));
        $this->assertSame('Neuer Titel', $this->task->fresh()->title);
        $this->assertSame('2026-12-24', $this->task->fresh()->due_date->format('Y-m-d'));
        $this->assertNotNull(Task::query()->find($keep->id));
        $this->assertSame([$this->task->id, $keep->id], $this->todo->tasks()->pluck('id')->all());
    }

    public function test_a_task_moves_to_another_todo_and_a_todo_to_another_milestone(): void
    {
        $other = Todo::factory()->for($this->milestone)->create(['title' => 'Anderes ToDo']);
        $document = $this->exported();
        $todos = $document['milestones'][0]['todos'];
        $document['milestones'][0]['todos'] = [$todos[0]];
        $moved = $todos[0]['tasks'][0];
        $document['milestones'][0]['todos'][0]['tasks'] = [];
        $document['milestones'][0]['todos'][1] = $todos[1];
        $document['milestones'][0]['todos'][1]['tasks'] = [$moved];

        $result = $this->import($this->project, $document);

        $this->assertSame([], $result->errors, json_encode($result->errors));
        $this->assertSame($other->id, $this->task->fresh()->todo_id);
        $this->assertContains('move', array_column($result->changes, 'kind'));
    }

    public function test_the_status_moves_the_task_in_its_column(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][0]['status'] = 'done';

        $this->import($this->project, $document);

        $this->assertSame('done', $this->task->fresh()->status->value);
        $this->assertSame(0, (int) $this->task->fresh()->board_position);
    }

    public function test_an_unknown_id_or_one_from_another_project_or_the_trash_is_refused_with_its_path(): void
    {
        $foreign = Task::factory()->for(Todo::factory()->for(Milestone::factory()->for(Project::factory())))->create();
        $trashed = Task::factory()->for($this->todo)->create();
        $this->actingAs($this->users['owner']);
        app(Deletion::class)->delete($trashed);
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => "k-{$foreign->id}", 'title' => 'Fremd', 'status' => 'open'];
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => "k-{$trashed->id}", 'title' => 'Papierkorb', 'status' => 'open'];
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-999999', 'title' => 'Unbekannt', 'status' => 'open'];
        $before = Task::query()->count();

        $result = $this->import($this->project, $document, ImportMode::Partial);

        $this->assertSame(
            ['milestones[0].todos[0].tasks[1].id', 'milestones[0].todos[0].tasks[2].id', 'milestones[0].todos[0].tasks[3].id'],
            array_column($result->errors, 'path'),
        );
        $this->assertSame($before, Task::query()->count());
    }

    public function test_a_file_with_errors_writes_nothing(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['title'] = 'X';

        $result = $this->import($this->project, $document);

        $this->assertSame('milestones[0].title', $result->errors[0]['path']);
        $this->assertNotSame('X', $this->milestone->fresh()->title);
    }

    public function test_the_result_is_checked_against_the_limits(): void
    {
        Todo::withoutEvents(fn () => Todo::factory()->count(49)->for($this->milestone)->create(['project_id' => $this->project->id]));
        $document = $this->exported();
        $document['milestones'][0]['todos'] = [
            ['id' => 't-new-1', 'title' => 'Eins zu viel', 'tasks' => []],
            ['id' => 't-new-2', 'title' => 'Noch eins', 'tasks' => []],
        ];

        $result = $this->import($this->project, $document, ImportMode::Partial);

        $this->assertNotSame([], $result->errors);
        $this->assertSame('milestones[0]', $result->errors[0]['path']);
    }

    // ─── Rights ──────────────────────────────────────────────────────

    public function test_a_member_may_plan_and_create_tasks_but_not_change_others_tasks_or_delete(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['title'] = 'Vom Mitglied umbenannt';
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Vom Mitglied', 'status' => 'open'];

        $this->assertSame([], $this->import($this->project, $document, as: 'member')->errors);
        $this->assertNotNull(Task::query()->where('title', 'Vom Mitglied')->first());
        $this->assertSame('Vom Mitglied umbenannt', $this->milestone->fresh()->title);

        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][0]['title'] = 'Umbenannt';
        $result = $this->import($this->project, $document, as: 'member');
        $this->assertSame([['path' => 'milestones[0].todos[0].tasks[0]', 'kind' => 'denied']], array_map(fn (array $e): array => ['path' => $e['path'], 'kind' => $e['kind']], $result->errors));
        $this->assertNotSame('Umbenannt', $this->task->fresh()->title);

        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'] = [];
        $result = $this->import($this->project, $document, as: 'member');
        $this->assertSame('denied', $result->errors[0]['kind']);
        $this->assertNotNull(Task::query()->find($this->task->id));
    }

    public function test_every_missing_right_is_named_before_anything_is_written(): void
    {
        $document = $this->exported();
        $document['project']['name'] = 'Neuer Name';
        $document['milestones'][0]['todos'][0]['tasks'][0]['title'] = 'Umbenannt';
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Neu', 'status' => 'open'];

        $result = $this->import($this->project, $document, as: 'member');

        $this->assertSame(['project', 'milestones[0].todos[0].tasks[0]'], array_column($result->errors, 'path'));
        $this->assertNull(Task::query()->where('title', 'Neu')->first());
        $this->assertNotSame('Neuer Name', $this->project->fresh()->name);
    }

    public function test_a_stranger_may_do_nothing(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Hack', 'status' => 'open'];

        $result = $this->import($this->project, $document, ImportMode::Partial, as: 'stranger');

        $this->assertSame('denied', $result->errors[0]['kind']);
        $this->assertNull(Task::query()->where('title', 'Hack')->first());
    }

    public function test_an_archived_project_is_refused(): void
    {
        $this->project->forceFill(['archived_at' => now()])->save();

        $result = $this->import($this->project->fresh(), $this->exported(), ImportMode::Partial);

        $this->assertSame('archived', $result->errors[0]['kind']);
    }

    public function test_a_manager_may_delete(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'] = [];

        $this->assertSame([], $this->import($this->project, $document, as: 'manager')->errors);
        $this->assertNull(Task::query()->find($this->task->id));
    }

    // ─── Members ─────────────────────────────────────────────────────

    public function test_a_person_without_an_account_is_skipped_with_a_warning(): void
    {
        $document = $this->exported();
        $document['project']['members'][] = ['email' => 'niemand@example.org', 'role' => 'member'];

        $result = $this->import($this->project, $document);

        $this->assertSame([], $result->errors, json_encode($result->errors));
        $this->assertCount(1, $result->warnings);
        $this->assertSame('project.members[3].email', $result->warnings[0]['path']);
        $this->assertSame('niemand@example.org', $result->warnings[0]['email']);
    }

    public function test_an_unknown_role_is_refused(): void
    {
        $document = $this->exported();
        $document['project']['members'][0]['role'] = 'gibt-es-nicht';

        $this->assertSame('role', $this->import($this->project, $document)->errors[0]['kind']);
    }

    public function test_a_member_is_added_and_changed_and_in_full_mode_removed(): void
    {
        $assigned = Task::factory()->for($this->todo)->create(['assigned_user_id' => $this->users['member']->id]);
        $document = $this->exported();
        $document['project']['members'] = array_values(array_filter($document['project']['members'], fn (array $m): bool => $m['email'] !== $this->users['member']->email));
        $document['project']['members'][] = ['email' => $this->users['stranger']->email, 'role' => 'guest'];
        $document['milestones'][0]['todos'][0]['tasks'] = array_values(array_filter($document['milestones'][0]['todos'][0]['tasks'], fn (array $t): bool => ($t['assigneeEmail'] ?? null) === null));
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => "k-{$assigned->id}", 'title' => $assigned->title, 'status' => 'open'];

        $result = $this->import($this->project, $document);

        $this->assertSame([], $result->errors, json_encode($result->errors));
        $this->assertNull($this->project->fresh()->roleOf($this->users['member']));
        $this->assertSame('guest', $this->project->fresh()->roleOf($this->users['stranger'])->key);
        $this->assertNull($assigned->fresh()->assigned_user_id);
    }

    public function test_partial_keeps_members_the_file_does_not_list(): void
    {
        $document = $this->exported();
        $document['project']['members'] = [['email' => $this->users['owner']->email, 'role' => 'owner']];

        $this->assertSame([], $this->import($this->project, $document, ImportMode::Partial)->errors);
        $this->assertNotNull($this->project->fresh()->roleOf($this->users['member']));
    }

    public function test_the_last_owner_cannot_be_removed(): void
    {
        $document = $this->exported();
        $document['project']['members'] = array_values(array_filter($document['project']['members'], fn (array $m): bool => $m['role'] !== 'owner'));

        $result = $this->import($this->project, $document, as: 'admin');

        $this->assertNotSame([], $result->errors);
        $this->assertNotNull($this->project->fresh()->roleOf($this->users['owner']));
    }

    public function test_a_file_may_not_remove_the_person_who_imports(): void
    {
        $document = $this->exported();
        $document['project']['members'] = array_values(array_filter($document['project']['members'], fn (array $m): bool => $m['email'] !== $this->users['manager']->email));

        $result = $this->import($this->project, $document, as: 'manager');

        $this->assertSame('self', $result->errors[0]['kind']);
        $this->assertNotNull($this->project->fresh()->roleOf($this->users['manager']));
    }

    public function test_a_manager_may_not_hand_out_the_owner_role(): void
    {
        $document = $this->exported();
        $document['project']['members'] = array_map(fn (array $m): array => $m['email'] === $this->users['member']->email ? ['email' => $m['email'], 'role' => 'owner'] : $m, $document['project']['members']);

        $result = $this->import($this->project, $document, as: 'manager');

        $this->assertSame('denied', $result->errors[0]['kind']);
        $this->assertSame('member', $this->project->fresh()->roleOf($this->users['member'])->key);
    }

    // ─── Assignees that are not members: a warning, not a refusal ────

    public function test_a_new_task_with_an_assignee_who_is_not_a_member_is_imported_without_one(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Neu', 'status' => 'open', 'assigneeEmail' => $this->users['stranger']->email];

        $result = $this->import($this->project, $document);

        $this->assertSame([], $result->errors, json_encode($result->errors));
        $this->assertCount(1, $result->warnings);
        $this->assertSame($this->users['stranger']->email, $result->warnings[0]['email']);
        $this->assertStringStartsWith('milestones[0].todos[0].tasks[', $result->warnings[0]['path']);
        $this->assertNull(Task::query()->where('title', 'Neu')->firstOrFail()->assigned_user_id);
    }

    public function test_the_dry_run_reports_the_same_warning_and_changes_nothing(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Neu', 'status' => 'open', 'assigneeEmail' => $this->users['stranger']->email];
        $before = $this->revision($this->project);

        $result = $this->import($this->project, $document, dryRun: true);

        $this->assertSame([], $result->errors, json_encode($result->errors));
        $this->assertCount(1, $result->warnings);
        $this->assertSame($before, $this->revision($this->project));
        $this->assertFalse(Task::query()->where('title', 'Neu')->exists());
    }

    public function test_an_existing_assignment_stays_when_the_file_names_someone_who_is_not_a_member(): void
    {
        $this->task->forceFill(['assigned_user_id' => $this->users['member']->id])->save();
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][0]['assigneeEmail'] = $this->users['stranger']->email;

        $result = $this->import($this->project, $document);

        $this->assertSame([], $result->errors, json_encode($result->errors));
        $this->assertCount(1, $result->warnings);
        $this->assertSame($this->users['member']->id, $this->task->fresh()->assigned_user_id);
    }

    public function test_a_file_without_unknown_assignees_has_no_warnings(): void
    {
        $result = $this->import($this->project, $this->exported());

        $this->assertSame([], $result->errors, json_encode($result->errors));
        $this->assertSame([], $result->warnings);
    }

    // ─── Mails ───────────────────────────────────────────────────────

    public function test_assignment_mails_go_only_on_request(): void
    {
        Notification::fake();
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][0]['assigneeEmail'] = $this->users['member']->email;

        $this->import($this->project, $document);
        Notification::assertNothingSent();

        $this->task->forceFill(['assigned_user_id' => null])->save();
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][0]['assigneeEmail'] = $this->users['member']->email;
        $this->import($this->project, $document, notify: true);

        Notification::assertSentTo($this->users['member'], TaskAssigned::class);
    }

    // ─── Roles, labels ───────────────────────────────────────────────

    public function test_a_guest_and_a_role_with_only_planning_rights_are_held_to_what_they_may(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Neu', 'status' => 'open'];

        $this->project->members()->updateExistingPivot($this->users['member']->id, ['role_id' => Role::byKey('project', 'guest')->id]);
        Role::forgetRegistry();
        Rights::clearCache();

        $this->assertSame('denied', $this->import($this->project, $document, ImportMode::Partial, as: 'member')->errors[0]['kind']);

        $planOnly = app(RoleAdministration::class)->createRole($this->users['admin'], 'project', ['de' => 'Planer', 'en' => 'Planner'], [DB::table('functions')->where(['scope' => 'project', 'key' => 'plan'])->value('id')]);
        $this->project->members()->updateExistingPivot($this->users['member']->id, ['role_id' => $planOnly->id]);
        Role::forgetRegistry();
        Rights::clearCache();

        $renamed = $this->exported();
        $renamed['milestones'][0]['title'] = 'Geplant';

        $this->assertSame([], $this->import($this->project, $renamed, ImportMode::Partial, as: 'member')->errors);
        $this->assertSame('denied', $this->import($this->project, $document, ImportMode::Partial, as: 'member')->errors[0]['kind']);
    }

    public function test_labels_are_written_with_their_keys_and_full_mode_deletes_the_others(): void
    {
        $document = $this->exported();
        $document['project']['labels'] = [
            ['key' => 'backend', 'names' => ['de' => 'Backend', 'en' => 'Backend'], 'color' => 'cyan'],
            ['key' => 'bug', 'names' => ['de' => 'Fehler', 'en' => 'Bug'], 'category' => 'type', 'color' => 'red'],
        ];
        $document['milestones'][0]['todos'][0]['tasks'][0]['labels'] = ['backend', 'bug'];

        $this->assertSame([], $this->import($this->project, $document)->errors);
        $this->assertSame(['backend', 'bug'], $this->project->labels()->pluck('key')->all());
        $this->assertSame(['backend', 'bug'], $this->task->fresh()->labels()->pluck('key')->all());

        $document = $this->exported();
        $document['project']['labels'] = [['key' => 'bug', 'names' => ['de' => 'Käfer', 'en' => 'Bug'], 'category' => 'type', 'color' => 'red']];
        $document['milestones'][0]['todos'][0]['tasks'][0]['labels'] = ['bug'];

        $this->assertSame([], $this->import($this->project, $document)->errors);
        $this->assertSame(['bug'], $this->project->labels()->pluck('key')->all());
        $this->assertSame('Käfer', $this->project->labels()->first()->names['de']);
        $this->assertSame(['bug'], $this->task->fresh()->labels()->pluck('key')->all());

        $document['project']['labels'] = [['key' => 'neu', 'names' => ['de' => 'Neu', 'en' => 'New'], 'color' => 'blue']];
        $document['milestones'][0]['todos'][0]['tasks'][0]['labels'] = ['neu'];
        $this->assertSame([], $this->import($this->project, $document, ImportMode::Partial)->errors);
        $this->assertSame(['neu', 'bug'], $this->project->labels()->orderBy('position')->pluck('key')->all());
    }

    public function test_a_file_without_changes_to_the_labels_does_not_count(): void
    {
        $this->import($this->project, [...$this->exported(), 'project' => [...$this->exported()['project'], 'labels' => [['key' => 'eins', 'names' => ['de' => 'Eins', 'en' => 'One'], 'color' => 'blue']]]]);
        $before = $this->revision($this->project);

        $result = $this->import($this->project, $this->exported());

        $this->assertSame([], $result->changes);
        $this->assertSame($before, $this->revision($this->project));
    }

    // ─── The order is compared by id (F7.1) ──────────────────────────

    public function test_a_fresh_export_changes_nothing_even_after_the_order_was_changed(): void
    {
        $second = Milestone::factory()->for($this->project)->create();
        $secondTodo = Todo::factory()->for($this->milestone)->create();
        Todo::factory()->for($second)->create();
        Task::factory()->count(2)->for($this->todo)->create();

        $document = $this->exported();
        $document['milestones'] = array_reverse($document['milestones']);
        $document['milestones'][1]['todos'] = array_reverse($document['milestones'][1]['todos']);
        $document['milestones'][1]['todos'][1]['tasks'] = array_reverse($document['milestones'][1]['todos'][1]['tasks']);
        $this->assertSame([], $this->import($this->project, $document)->errors);

        $fresh = $this->exported();
        $revision = $this->revision($this->project);

        $this->assertSame([], $this->import($this->project, $fresh, dryRun: true)->changes);
        $this->assertSame([], $this->import($this->project, $fresh)->changes);
        $this->assertSame($revision, $this->revision($this->project));
        $this->assertSame([], $this->import($this->project, $this->exported(), ImportMode::Partial)->changes);
        $this->assertNotNull($secondTodo);
    }

    public function test_partial_with_some_of_the_tasks_reports_no_move_for_the_others(): void
    {
        $b = Task::factory()->for($this->todo)->create();
        $c = Task::factory()->for($this->todo)->create();
        $document = $this->exported();
        $tasks = $document['milestones'][0]['todos'][0]['tasks'];
        $document['milestones'][0]['todos'][0]['tasks'] = [$tasks[0], $tasks[2]];

        $result = $this->import($this->project, $document, ImportMode::Partial, dryRun: true);

        $this->assertSame([], $result->errors);
        $this->assertSame([], $result->changes);
        $this->assertNotNull($b);
        $this->assertNotNull($c);
    }

    public function test_a_changed_task_is_reported_as_an_update_at_its_path(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][0]['title'] = 'Neuer Titel';

        $result = $this->import($this->project, $document, dryRun: true);

        $this->assertSame([['kind' => 'update', 'path' => 'milestones[0].todos[0].tasks[0]', 'id' => "k-{$this->task->id}", 'fields' => ['title'], 'title' => 'Neuer Titel']], $result->changes);
    }
}
