<?php

namespace Tests\Feature\GraphQL;

use App\Models\Milestone;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * The order of the milestones of a project: no gaps after a delete, and every
 * change runs under the row lock of the project, so two requests cannot
 * write the same position.
 */
class MilestoneOrderTest extends GraphQLTestCase
{
    private const DELETE = 'mutation ($id: ID!) { deleteMilestone(id: $id) { id } }';

    private const CREATE = 'mutation ($in: CreateMilestoneInput!) { createMilestone(input: $in) { id position } }';

    private const MOVE = 'mutation ($id: ID!, $position: Int!) { moveMilestone(id: $id, position: $position) { id position } }';

    public function test_deleting_a_milestone_closes_the_gap(): void
    {
        $second = Milestone::factory()->for($this->project)->create();
        $third = Milestone::factory()->for($this->project)->create();

        $this->assertSame([0, 1, 2], $this->positions());

        $this->as('owner')->gql(self::DELETE, ['id' => $second->id])->assertJsonMissingPath('errors');

        $this->assertSame([0, 1], $this->positions());
        $this->assertSame(1, (int) $third->fresh()->position);
    }

    public function test_a_milestone_created_after_a_delete_gets_the_next_free_place(): void
    {
        $second = Milestone::factory()->for($this->project)->create();
        $this->as('owner')->gql(self::DELETE, ['id' => $second->id])->assertJsonMissingPath('errors');

        $this->as('owner')->gql(self::CREATE, ['in' => ['projectId' => $this->project->id, 'title' => 'Version 2']])
            ->assertJsonPath('data.createMilestone.position', 1);

        $this->assertSame([0, 1], $this->positions());
    }

    public function test_changing_the_order_locks_the_project_row(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite has no row locks. Run composer test:mariadb for this test.');
        }

        $second = Milestone::factory()->for($this->project)->create();

        $statements = [];
        DB::listen(function (QueryExecuted $query) use (&$statements): void {
            $statements[] = strtolower($query->sql);
        });

        $this->as('owner')->gql(self::MOVE, ['id' => $second->id, 'position' => 0])->assertJsonMissingPath('errors');
        $this->as('owner')->gql(self::CREATE, ['in' => ['projectId' => $this->project->id, 'title' => 'Version 3']])->assertJsonMissingPath('errors');

        $locks = array_filter($statements, fn (string $sql) => str_contains($sql, 'from `projects`') && str_contains($sql, 'for update'));

        $this->assertCount(2, $locks, 'Move and create must each lock the project row before they touch positions.');
    }

    /**
     * @return list<int>
     */
    private function positions(): array
    {
        return $this->project->milestones()->orderBy('position')->orderBy('id')->pluck('position')
            ->map(fn ($position) => (int) $position)
            ->values()
            ->all();
    }
}
