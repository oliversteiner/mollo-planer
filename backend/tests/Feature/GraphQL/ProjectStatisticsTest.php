<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Enums\ReleaseStatus;
use App\Mcp\Servers\MolloPlanerServer;
use App\Mcp\Tools\GetProjectStatisticsTool;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Services\Deletion;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The statistics of a project (task 33): every number counted in the database, the trash left out, the same rights
 * as reading the project, a fixed number of queries whatever the size, and the same numbers through the connector.
 */
class ProjectStatisticsTest extends GraphQLTestCase
{
    private const QUERY = <<<'GQL'
        query ($id: ID!) { projectStatistics(projectId: $id) {
            milestones { total released planned unversioned }
            todos tasks
            progress { total done percent }
            tasksByStatus { status count percent }
            tasksByLabel { label { key } count }
            tasksByPerson { user { name isAgent } open done }
            due { overdue soon undated }
            perMilestone { milestone { id title release { version status date } } todos tasks progress { total done percent } }
            doneByWeek { weekStart count }
            withCommit comments
        } }
        GQL;

    /** A project that has every kind of number once or twice. */
    private function filledProject(): Project
    {
        $project = Project::factory()->create(['name' => 'Statistik']);
        $anna = $this->users['member'];
        $agent = User::factory()->agent()->create(['name' => 'Kevin']);
        $first = Milestone::factory()->for($project)->create(['position' => 0, 'title' => 'Erster', 'release_version' => '1.0', 'release_status' => ReleaseStatus::RELEASED, 'release_date' => '2026-11-01']);
        $second = Milestone::factory()->for($project)->create(['position' => 1, 'title' => 'Zweiter', 'release_version' => '1.1', 'release_status' => ReleaseStatus::PLANNED]);
        Milestone::factory()->for($project)->create(['position' => 2, 'title' => 'Ohne Version']);
        $todoA = Todo::factory()->for($first)->create(['position' => 0]);
        $todoB = Todo::factory()->for($second)->create(['position' => 0]);
        Todo::factory()->for($second)->create(['position' => 1]);

        $today = Carbon::today();
        $make = fn (Todo $todo, array $attributes): Task => Task::factory()->for($todo)->create($attributes);
        $make($todoA, ['status' => 'done', 'assigned_user_id' => $anna->id, 'commit' => 'abc1234']);
        $make($todoA, ['status' => 'done', 'assigned_user_id' => $agent->id, 'commit' => 'none']);
        $make($todoB, ['status' => 'open', 'assigned_user_id' => $anna->id, 'due_date' => $today->copy()->subDay()]);
        $make($todoB, ['status' => 'in_progress', 'assigned_user_id' => $anna->id, 'due_date' => $today->copy()->addDays(3)]);
        $make($todoB, ['status' => 'review', 'due_date' => $today->copy()->addDays(30)]);
        $make($todoB, ['status' => 'planned']);
        $make($todoB, ['status' => 'idea', 'assigned_user_id' => $agent->id]);

        $bug = Label::factory()->for($project)->create(['key' => 'bug', 'position' => 0]);
        Label::factory()->for($project)->create(['key' => 'unused', 'position' => 1]);
        $inProject = fn (string $status): Task => Task::query()->inProject($project->id)->where('status', $status)->orderBy('id')->firstOrFail();
        $inProject('open')->labels()->attach($bug->id);
        $inProject('done')->labels()->attach($bug->id);

        Comment::factory()->count(2)->for($inProject('open'))->create();

        return $project;
    }

    /** @return array<string, mixed> */
    private function statistics(Project $project, string $as = 'admin'): array
    {
        return $this->as($as)->gql(self::QUERY, ['id' => $project->id])->assertJsonMissingPath('errors')->json('data.projectStatistics');
    }

    public function test_the_numbers_of_a_project(): void
    {
        $stats = $this->statistics($this->filledProject());

        $this->assertSame(['total' => 3, 'released' => 1, 'planned' => 1, 'unversioned' => 1], $stats['milestones']);
        $this->assertSame(3, $stats['todos']);
        $this->assertSame(7, $stats['tasks']);
        // 6 tasks that are not ideas, 2 of them done: the progress rounds as everywhere (33 %).
        $this->assertSame(['total' => 6, 'done' => 2, 'percent' => 33], $stats['progress']);

        $byStatus = collect($stats['tasksByStatus'])->mapWithKeys(fn (array $row): array => [$row['status'] => $row['count']])->all();
        $this->assertSame(['IDEA' => 1, 'PLANNED' => 1, 'OPEN' => 1, 'IN_PROGRESS' => 1, 'REVIEW' => 1, 'DONE' => 2], $byStatus);
        $this->assertSame(29, collect($stats['tasksByStatus'])->firstWhere('status', 'DONE')['percent'], 'a share of all 7 tasks, rounded as the progress');

        // "none" is a done task without a commit: it does not count as one with a commit.
        $this->assertSame(['withCommit' => 1, 'comments' => 2], ['withCommit' => $stats['withCommit'], 'comments' => $stats['comments']]);
    }

    public function test_done_tasks_per_week_of_the_last_twelve_weeks(): void
    {
        $project = Project::factory()->create();
        $todo = Todo::factory()->for(Milestone::factory()->for($project))->create();
        $monday = Carbon::today()->startOfWeek();
        $done = fn (Carbon $at) => Task::factory()->for($todo)->create(['status' => 'done', 'commit' => 'none', 'done_at' => $at]);
        $done($monday->copy()->addDays(2));
        $done($monday->copy()->addDays(3));
        $done($monday->copy()->subWeeks(2)->addDay());
        $done($monday->copy()->subWeeks(11)->addHours(5));
        $done($monday->copy()->subWeeks(12)->addDay()); // older than the course
        // Done before the time was recorded: the model would set it now, so it is emptied behind the model's back.
        $old = Task::factory()->for($todo)->create(['status' => 'done', 'commit' => 'none']);
        Task::query()->whereKey($old->id)->toBase()->update(['done_at' => null]);

        $weeks = $this->statistics($project)['doneByWeek'];

        $this->assertCount(12, $weeks);
        $this->assertSame($monday->copy()->subWeeks(11)->toDateString(), $weeks[0]['weekStart']);
        $this->assertSame($monday->toDateString(), $weeks[11]['weekStart']);
        $this->assertSame([1, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 2], array_column($weeks, 'count'));
    }

    public function test_a_task_gets_its_time_on_the_way_into_done_and_loses_it_on_the_way_out(): void
    {
        $task = Task::factory()->for(Todo::factory()->for(Milestone::factory()->for(Project::factory())))->create(['status' => 'open']);
        $this->assertNull($task->done_at);

        $this->travelTo(now()->subDay());
        $task->forceFill(['status' => 'done', 'commit' => 'none'])->save();
        $first = $task->fresh()->done_at;
        $this->assertNotNull($first);

        $this->travelBack();
        $task->update(['title' => 'Nur ein Titel']);
        $this->assertEquals($first, $task->fresh()->done_at, 'an edit that leaves the status keeps the time');

        $task->forceFill(['status' => 'review', 'commit' => null])->save();
        $this->assertNull($task->fresh()->done_at);

        $task->forceFill(['status' => 'done', 'commit' => 'none'])->save();
        $this->assertTrue($task->fresh()->done_at->greaterThan($first));
    }

    public function test_labels_with_zero_and_the_tasks_without_a_label(): void
    {
        $rows = $this->statistics($this->filledProject())['tasksByLabel'];

        $this->assertSame([['label' => ['key' => 'bug'], 'count' => 2], ['label' => ['key' => 'unused'], 'count' => 0], ['label' => null, 'count' => 5]], $rows);
    }

    public function test_persons_with_open_and_done_the_agent_marked_and_the_unassigned_last(): void
    {
        $rows = $this->statistics($this->filledProject())['tasksByPerson'];

        $this->assertSame([
            ['user' => ['name' => 'David Member', 'isAgent' => false], 'open' => 2, 'done' => 1],
            ['user' => ['name' => 'Kevin', 'isAgent' => true], 'open' => 0, 'done' => 1],
            ['user' => null, 'open' => 2, 'done' => 0],
        ], $rows);
    }

    public function test_due_dates_count_open_tasks_only(): void
    {
        // open (overdue), in progress (in 3 days) and review (in 30 days), planned (no date); done and ideas never count.
        $this->assertSame(['overdue' => 1, 'soon' => 1, 'undated' => 1], $this->statistics($this->filledProject())['due']);
    }

    public function test_one_row_per_milestone_in_plan_order_with_the_release_and_the_progress(): void
    {
        $rows = $this->statistics($this->filledProject())['perMilestone'];

        $this->assertSame(['Erster', 'Zweiter', 'Ohne Version'], array_column(array_column($rows, 'milestone'), 'title'));
        $this->assertSame(['version' => '1.0', 'status' => 'RELEASED', 'date' => '2026-11-01'], $rows[0]['milestone']['release']);
        $this->assertSame(['version' => '1.1', 'status' => 'PLANNED', 'date' => null], $rows[1]['milestone']['release']);
        $this->assertNull($rows[2]['milestone']['release']);
        $this->assertSame([1, 2, 0], array_column($rows, 'todos'));
        $this->assertSame([2, 5, 0], array_column($rows, 'tasks'));
        $this->assertSame(['total' => 2, 'done' => 2, 'percent' => 100], $rows[0]['progress']);
        $this->assertSame(['total' => 4, 'done' => 0, 'percent' => 0], $rows[1]['progress']);
        $this->assertSame(['total' => 0, 'done' => 0, 'percent' => 0], $rows[2]['progress']);
    }

    public function test_items_in_the_trash_do_not_count(): void
    {
        $project = $this->filledProject();
        $before = $this->statistics($project);

        app(Deletion::class)->delete(Task::query()->inProject($project->id)->where('status', 'done')->orderBy('id')->firstOrFail());
        app(Deletion::class)->delete(Todo::query()->where('milestone_id', Milestone::query()->where('title', 'Zweiter')->value('id'))->orderBy('id')->firstOrFail());

        $after = $this->statistics($project);

        $this->assertSame($before['tasks'] - 1 - 5, $after['tasks'] + 0 - 0 + 0, 'a done task and the five tasks of the todo that went to the trash');
        $this->assertSame($before['todos'] - 1, $after['todos']);
        $this->assertSame($before['progress']['done'] - 1, $after['progress']['done']);
        $this->assertSame($before['comments'] - 2, $after['comments'], 'the comments of a trashed task do not count');
    }

    public function test_an_empty_project_shows_zeros(): void
    {
        $stats = $this->statistics(Project::factory()->create());

        $this->assertSame(0, $stats['tasks']);
        $this->assertSame(['total' => 0, 'done' => 0, 'percent' => 0], $stats['progress']);
        $this->assertSame([], $stats['perMilestone']);
        $this->assertSame([], $stats['tasksByPerson']);
        $this->assertSame(['overdue' => 0, 'soon' => 0, 'undated' => 0], $stats['due']);
        $this->assertCount(6, $stats['tasksByStatus']);
    }

    public function test_the_rights_are_those_of_reading_the_project(): void
    {
        $project = $this->filledProject();

        // A stranger to the project is refused like for any other query, whether or not the project exists.
        $this->assertUnauthorized($this->as('stranger')->gql(self::QUERY, ['id' => $project->id]));
        $this->assertUnauthorized($this->as('stranger')->gql(self::QUERY, ['id' => 999999]));

        $this->as('owner')->gql(self::QUERY, ['id' => $this->project->id])->assertJsonMissingPath('errors');
        $this->as('member')->gql(self::QUERY, ['id' => $this->project->id])->assertJsonMissingPath('errors');
    }

    public function test_an_archived_project_still_shows_its_statistics(): void
    {
        $project = $this->filledProject();
        $project->forceFill(['archived_at' => now()])->save();

        $this->assertSame(7, $this->statistics($project)['tasks']);
    }

    public function test_the_number_of_queries_does_not_grow_with_the_size(): void
    {
        $small = $this->projectOfSize(3);
        $large = $this->projectOfSize(60);

        $count = function (Project $project): int {
            $queries = 0;
            DB::listen(function (QueryExecuted $query) use (&$queries): void {
                $queries++;
            });
            $this->statistics($project);

            return $queries;
        };

        $count($small); // the first request of a test warms what every request reads once (rights, settings)
        $this->assertSame($count($small), $count($large));
    }

    private function projectOfSize(int $milestones): Project
    {
        $project = Project::factory()->create();
        $label = Label::factory()->for($project)->create();

        for ($m = 0; $m < $milestones; $m++) {
            $milestone = Milestone::factory()->for($project)->create(['position' => $m]);
            $todo = Todo::factory()->for($milestone)->create();
            Task::factory()->count(2)->for($todo)->create(['assigned_user_id' => $this->users['member']->id, 'due_date' => now()->addDay()])->each(fn (Task $task) => $task->labels()->attach($label->id));
        }

        return $project;
    }

    public function test_the_cards_of_the_project_list_count_in_a_fixed_number_of_queries(): void
    {
        $this->filledProject();
        $list = fn (): array => $this->as('admin')->gql('{ projects { id counts { milestones todos tasks } } }')->assertJsonMissingPath('errors')->json('data.projects');

        $queries = function () use ($list): int {
            $n = 0;
            DB::listen(function (QueryExecuted $query) use (&$n): void {
                $n++;
            });
            $list();

            return $n;
        };

        $queries(); // the first request of a test warms what every request reads once
        $few = $queries();
        foreach (range(1, 12) as $ignored) {
            $project = Project::factory()->create();
            Task::factory()->for(Todo::factory()->for(Milestone::factory()->for($project)))->create();
        }
        $this->assertSame($few, $queries(), 'more projects, the same number of queries');

        $counts = collect($list())->keyBy('id');
        $this->assertSame(['milestones' => 3, 'todos' => 3, 'tasks' => 7], $counts[(string) Project::query()->where('name', 'Statistik')->value('id')]['counts']);
        $this->assertSame(['milestones' => 1, 'todos' => 1, 'tasks' => 1], $counts[(string) Project::query()->latest('id')->value('id')]['counts']);
    }

    public function test_the_connector_gives_the_same_numbers(): void
    {
        $project = $this->filledProject();
        $project->members()->attach($this->users['owner']->id, ['role_id' => $this->projectRole(ProjectRole::OWNER)->id]);

        $expected = $this->statistics($project, 'owner');

        $response = MolloPlanerServer::actingAs($this->users['owner'])->tool(GetProjectStatisticsTool::class, ['project_id' => (string) $project->id]);
        $response->assertOk()->assertSee('"tasks":7')->assertSee('"percent":33')->assertSee('Kevin')->assertSee('m-'.Milestone::query()->where('title', 'Erster')->value('id'));
        $this->assertSame(7, $expected['tasks']);

        MolloPlanerServer::actingAs($this->users['stranger'])->tool(GetProjectStatisticsTool::class, ['project_id' => (string) $project->id])->assertHasErrors();
    }
}
