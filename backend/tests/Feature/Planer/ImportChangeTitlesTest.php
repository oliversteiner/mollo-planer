<?php

namespace Tests\Feature\Planer;

use App\Models\Task;
use App\Models\Todo;
use App\Planer\Export;
use Tests\Feature\GraphQL\GraphQLTestCase;

/**
 * Every change in the result of importPlan / import_plan carries a title (Auftrag 49 C): the one of the document for
 * CREATE, the current one for UPDATE and MOVE (the new one when the title changes). Never null, so an agent can read a
 * dry run without looking the IDs up. The same for a dry run and for the real run.
 */
class ImportChangeTitlesTest extends GraphQLTestCase
{
    private const MUTATION = 'mutation ($p: ID, $d: String!, $mode: ImportMode, $dry: Boolean) {
        importPlan(projectId: $p, document: $d, mode: $mode, dryRun: $dry) {
            ok changes { kind path id fields title } errors { path kind message }
        }
    }';

    /** @return array<string, mixed> */
    private function exported(): array
    {
        return app(Export::class)->document($this->project->fresh());
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<array{kind: string, path: string, id: ?string, fields: list<string>, title: ?string}>
     */
    private function changes(array $document, bool $dry, string $mode = 'FULL'): array
    {
        $response = $this->as('owner')->gql(self::MUTATION, ['p' => $this->project->id, 'd' => json_encode($document), 'mode' => $mode, 'dry' => $dry])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.importPlan.ok', true);

        return $response->json('data.importPlan.changes');
    }

    private function assertNoTitleIsNull(array $changes): void
    {
        $this->assertNotEmpty($changes);

        foreach ($changes as $change) {
            $this->assertIsString($change['title'], "{$change['kind']} {$change['path']} has no title");
            $this->assertNotSame('', $change['title']);
        }
    }

    public function test_a_create_carries_the_title_of_the_document_in_a_dry_run_and_in_the_real_run(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Neue Aufgabe', 'status' => 'open'];
        $document['milestones'][0]['todos'][] = ['id' => 't-new-1', 'title' => 'Neues ToDo', 'tasks' => []];
        $document['milestones'][] = ['id' => 'm-new-1', 'title' => 'Neuer Meilenstein', 'todos' => []];

        foreach ([true, false] as $dry) {
            $changes = collect($this->changes($document, $dry))->where('kind', 'CREATE')->pluck('title')->all();

            $this->assertEqualsCanonicalizing(['Neue Aufgabe', 'Neues ToDo', 'Neuer Meilenstein'], $changes);

            if ($dry) {
                $this->assertNull(Task::query()->where('title', 'Neue Aufgabe')->first());
            }

            $document = $this->exported();

            if (! $dry) {
                break;
            }

            $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Neue Aufgabe', 'status' => 'open'];
            $document['milestones'][0]['todos'][] = ['id' => 't-new-1', 'title' => 'Neues ToDo', 'tasks' => []];
            $document['milestones'][] = ['id' => 'm-new-1', 'title' => 'Neuer Meilenstein', 'todos' => []];
        }
    }

    public function test_an_update_carries_the_current_title_and_a_changed_title_carries_the_new_one(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][0]['status'] = 'done';
        $document['milestones'][0]['todos'][0]['tasks'][0]['commit'] = 'none';

        $changes = $this->changes($document, true);

        $this->assertNoTitleIsNull($changes);
        $this->assertSame($this->task->title, collect($changes)->firstWhere('kind', 'UPDATE')['title']);

        $document['milestones'][0]['todos'][0]['tasks'][0]['title'] = 'Anderer Titel';
        $titles = collect($this->changes($document, true))->where('kind', 'UPDATE')->pluck('title')->all();

        $this->assertSame(['Anderer Titel'], $titles);
        $this->assertSame($this->task->title, $this->task->fresh()->title, 'a dry run writes nothing');
    }

    public function test_moving_a_task_to_another_todo_carries_its_title(): void
    {
        $other = Todo::factory()->for($this->milestone)->create(['title' => 'Zweites ToDo']);
        $document = $this->exported();
        $moved = array_shift($document['milestones'][0]['todos'][0]['tasks']);
        $document['milestones'][0]['todos'][array_search("t-{$other->id}", array_column($document['milestones'][0]['todos'], 'id'), true)]['tasks'][] = $moved;

        $changes = $this->changes($document, true);
        $move = collect($changes)->firstWhere('kind', 'MOVE');

        $this->assertNotNull($move);
        $this->assertSame($moved['title'], $move['title']);
        $this->assertNoTitleIsNull($changes);
    }

    public function test_a_change_of_order_in_a_group_carries_the_titles(): void
    {
        $second = Task::factory()->for($this->todo)->create(['title' => 'Zweite Aufgabe', 'board_position' => 1]);
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'] = array_reverse($document['milestones'][0]['todos'][0]['tasks']);

        $changes = $this->changes($document, true);
        $moves = collect($changes)->where('kind', 'MOVE');

        $this->assertNotEmpty($moves);
        $this->assertContains('Zweite Aufgabe', $moves->pluck('title')->all());
        $this->assertNoTitleIsNull($changes);
        $this->assertNotNull($second->id);
    }

    public function test_a_change_of_the_project_itself_carries_its_name(): void
    {
        $document = $this->exported();
        $document['project']['name'] = 'Umbenanntes Projekt';

        $changes = $this->changes($document, true);

        $this->assertSame('Umbenanntes Projekt', collect($changes)->firstWhere('path', 'project')['title']);
    }
}
