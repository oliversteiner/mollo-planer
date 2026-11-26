<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Enums\ReleaseStatus;
use App\Mcp\Servers\MolloPlanerServer;
use App\Models\Milestone;
use App\Models\Role;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Services\MilestoneVersionOrder;
use App\Services\ReleaseRules;
use Tests\McpTools;

/**
 * "Nach Version ordnen" and reorder (task 47): the comparison of versions, every condition on its own, rights,
 * conflict of the revision, and the MCP tool for the three levels.
 */
class MilestoneVersionOrderTest extends GraphQLTestCase
{
    private const PLAN = 'query ($id: ID!) { milestoneVersionOrder(projectId: $id) { revision canApply condition moveCount conditionMilestones { id } order { id version from to moved } } }';

    private const APPLY = 'mutation ($id: ID!, $rev: Int, $dry: Boolean) { sortMilestones(projectId: $id, expectedRevision: $rev, dryRun: $dry) { revision moveCount order before } }';

    protected function setUp(): void
    {
        parent::setUp();

        $this->milestone->delete();
        $this->users['guest'] = User::factory()->create();
        $this->project->members()->attach($this->users['guest']->id, ['role_id' => Role::idOf(Role::SCOPE_PROJECT, ProjectRole::GUEST)]);
    }

    /**
     * @param  list<string|null>  $versions  in the stored order
     * @return list<Milestone>
     */
    private function milestones(array $versions): array
    {
        $result = [];

        foreach ($versions as $position => $version) {
            // The release rules only allow M.m; older data and imports may hold other values, which is what B 3 is for.
            $result[] = ReleaseRules::withoutChecks(fn () => Milestone::factory()->for($this->project)->create([
                'title' => $version === null ? 'Backlog '.$position : 'Version '.$version,
                'position' => $position,
                'release_version' => $version,
                'release_status' => $version === null ? null : ReleaseStatus::PLANNED,
            ]));
        }

        return $result;
    }

    /** @return list<string|null> */
    private function storedVersions(): array
    {
        return Milestone::query()->where('project_id', $this->project->id)->orderBy('position')->pluck('release_version')->all();
    }

    private function plan(string $as = 'owner'): array
    {
        return $this->as($as)->gql(self::PLAN, ['id' => (string) $this->project->id])->assertGraphQLErrorFree()->json('data.milestoneVersionOrder');
    }

    public function test_versions_are_compared_by_numbers_part_by_part(): void
    {
        $sorted = ['1.3', '1.20', '1.29', '2.0', '2.5', '2.10'];
        $this->assertSame($sorted, collect(array_reverse($sorted))->sort(fn ($a, $b) => MilestoneVersionOrder::compare($a, $b))->values()->all());
        $this->assertLessThan(0, MilestoneVersionOrder::compare('1.2', '1.2.1'));
        $this->assertGreaterThan(0, MilestoneVersionOrder::compare('1.10', '1.9'));
    }

    public function test_it_orders_by_version_and_puts_milestones_without_version_last_in_their_order(): void
    {
        $this->milestones(['2.10', null, '1.20', '2.0', null]);
        $titles = Milestone::query()->where('project_id', $this->project->id)->orderBy('position')->pluck('title')->all();

        $plan = $this->plan();
        $this->assertTrue($plan['canApply']);
        $this->assertNull($plan['condition']);
        $this->assertSame(['1.20', '2.0', '2.10', null, null], array_column($plan['order'], 'version'));

        $this->as('owner')->gql(self::APPLY, ['id' => (string) $this->project->id, 'rev' => $plan['revision']])
            ->assertGraphQLErrorFree();

        $this->assertSame(['1.20', '2.0', '2.10', null, null], $this->storedVersions());
        $now = Milestone::query()->where('project_id', $this->project->id)->orderBy('position')->pluck('title')->all();
        $this->assertSame([$titles[1], $titles[4]], array_slice($now, 3), 'unversioned keep their order');
    }

    public function test_apply_counts_the_revision_and_returns_the_old_order_for_undo(): void
    {
        $milestones = $this->milestones(['2.0', '1.0']);
        $revision = (int) $this->project->fresh()->planer_revision;

        $data = $this->as('owner')->gql(self::APPLY, ['id' => (string) $this->project->id, 'rev' => $revision])->json('data.sortMilestones');

        $this->assertSame(2, $data['moveCount']);
        $this->assertGreaterThan($revision, $data['revision']);
        $this->assertSame([(string) $milestones[0]->id, (string) $milestones[1]->id], $data['before']);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->milestones(['2.0', '1.0']);
        $revision = (int) $this->project->fresh()->planer_revision;

        $this->as('owner')->gql(self::APPLY, ['id' => (string) $this->project->id, 'dry' => true])->assertGraphQLErrorFree();

        $this->assertSame(['2.0', '1.0'], $this->storedVersions());
        $this->assertSame($revision, (int) $this->project->fresh()->planer_revision);
    }

    public function test_a_changed_plan_is_refused_with_the_current_revision(): void
    {
        $this->milestones(['2.0', '1.0']);
        $old = (int) $this->project->fresh()->planer_revision;
        Milestone::query()->where('project_id', $this->project->id)->first()->update(['title' => 'Geändert']);

        $response = $this->as('owner')->gql(self::APPLY, ['id' => (string) $this->project->id, 'rev' => $old]);

        $this->assertSame('ORDER_REVISION', $response->json('errors.0.extensions.code'));
        $this->assertSame(['2.0', '1.0'], $this->storedVersions());
    }

    public function test_fewer_than_two_versions_block(): void
    {
        $this->milestones(['1.0', null, null]);

        $this->assertSame('too_few_versions', $this->plan()['condition']);
    }

    public function test_invalid_versions_block_and_are_listed(): void
    {
        $milestones = $this->milestones(['2.0', '1.0', 'v2', '2.0-beta']);

        $plan = $this->plan();

        $this->assertFalse($plan['canApply']);
        $this->assertSame('invalid_version', $plan['condition']);
        $this->assertEqualsCanonicalizing([(string) $milestones[2]->id, (string) $milestones[3]->id], array_column($plan['conditionMilestones'], 'id'));
    }

    public function test_duplicate_versions_block_and_are_listed(): void
    {
        $milestones = $this->milestones(['1.0', '2.0', '1.0']);

        $plan = $this->plan();

        $this->assertSame('duplicate_version', $plan['condition']);
        $this->assertEqualsCanonicalizing([(string) $milestones[0]->id, (string) $milestones[2]->id], array_column($plan['conditionMilestones'], 'id'));
    }

    public function test_an_ordered_project_says_so(): void
    {
        $this->milestones(['1.0', '2.0', null]);

        $this->assertSame('already_ordered', $this->plan()['condition']);
    }

    public function test_without_the_right_the_button_is_off_and_the_mutation_refused(): void
    {
        $this->milestones(['2.0', '1.0']);

        $this->assertSame('no_right', $this->plan('guest')['condition']);
        $this->as('guest')->gql(self::APPLY, ['id' => (string) $this->project->id])->assertGraphQLErrorMessage(__('ordering.condition.no_right'));
        $this->assertSame(['2.0', '1.0'], $this->storedVersions());
    }

    public function test_an_archived_project_blocks(): void
    {
        $this->milestones(['2.0', '1.0']);
        $this->project->forceFill(['archived_at' => now()])->save();

        $this->assertSame('archived', $this->plan()['condition']);
    }

    public function test_all_conditions_met_means_free(): void
    {
        $this->milestones(['1.2.1', '1.2']);

        $plan = $this->plan();
        $this->assertTrue($plan['canApply']);
        $this->assertSame(2, $plan['moveCount']);
    }

    private function tool(string $as, array $arguments): mixed
    {
        return MolloPlanerServer::actingAs($this->users[$as])->tool(McpTools::of('reorder'), ['project_id' => (string) $this->project->id, ...$arguments]);
    }

    public function test_reorder_by_version_dry_run_names_the_same_moves_as_the_preview(): void
    {
        $this->milestones(['2.0', '1.0']);

        $this->tool('owner', ['by' => 'version', 'dry_run' => true])->assertOk()->assertSee('"moved":2')->assertSee('1.0: 1 → 0');
        $this->assertSame(['2.0', '1.0'], $this->storedVersions());

        $this->tool('owner', ['by' => 'version'])->assertOk()->assertSee('"moved":2');
        $this->assertSame(['1.0', '2.0'], $this->storedVersions());
    }

    public function test_reorder_by_version_gives_the_reason_of_the_interface(): void
    {
        $this->milestones(['1.0', 'v2']);

        $this->tool('owner', ['by' => 'version'])->assertHasErrors([__('ordering.condition.invalid_version', ['list' => ''])]);
    }

    public function test_reorder_milestones_todos_and_tasks(): void
    {
        [$a, $b] = $this->milestones(['1.0', '2.0']);
        $todoA = Todo::factory()->for($a)->create(['position' => 0]);
        $todoB = Todo::factory()->for($a)->create(['position' => 1]);
        $taskA = Task::factory()->for($todoA)->create(['position' => 0]);
        $taskB = Task::factory()->for($todoA)->create(['position' => 1]);

        $this->tool('owner', ['ids' => ["m-{$b->id}", "m-{$a->id}"]])->assertOk()->assertSee('"moved":2');
        $this->assertSame(0, $b->fresh()->position);

        $this->tool('owner', ['parent_id' => "m-{$a->id}", 'ids' => ["t-{$todoB->id}", "t-{$todoA->id}"]])->assertOk();
        $this->assertSame(0, $todoB->fresh()->position);

        $this->tool('owner', ['parent_id' => "t-{$todoA->id}", 'ids' => ["k-{$taskB->id}", "k-{$taskA->id}"]])->assertOk();
        $this->assertSame(0, $taskB->fresh()->position);
    }

    public function test_reorder_needs_all_siblings_and_no_foreign_one(): void
    {
        [$a, $b] = $this->milestones(['1.0', '2.0']);
        $other = Milestone::factory()->create();

        $this->tool('owner', ['ids' => ["m-{$a->id}"]])->assertHasErrors(["m-{$b->id}"]);
        $this->tool('owner', ['ids' => ["m-{$a->id}", "m-{$b->id}", "m-{$other->id}"]])->assertHasErrors(["m-{$other->id}"]);
        $this->assertSame(0, $a->fresh()->position);
    }

    public function test_reorder_dry_run_and_rights(): void
    {
        [$a, $b] = $this->milestones(['1.0', '2.0']);
        $ids = ["m-{$b->id}", "m-{$a->id}"];

        $this->tool('owner', ['ids' => $ids, 'dry_run' => true])->assertOk()->assertSee('dry_run');
        $this->assertSame(0, $a->fresh()->position);

        $this->tool('guest', ['ids' => $ids])->assertHasErrors();
        $this->assertSame(0, $a->fresh()->position);
    }
}
