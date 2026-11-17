<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Enums\Right;
use App\Models\Comment;
use App\Models\Project;
use App\Services\TaskNotifier;
use Illuminate\Support\Facades\Notification;

/**
 * Archive and bring back (docs/auftrag-m2.md §1): read only within, left out
 * of the lists across projects, not counted as a change of the planer file.
 * Which mutations an archived project refuses: ArchivedProjectMutationsTest.
 */
class ProjectArchiveTest extends GraphQLTestCase
{
    private const ARCHIVE = 'mutation ($id: ID!) { archiveProject(id: $id) { id archivedAt viewerRights } }';

    private const UNARCHIVE = 'mutation ($id: ID!) { unarchiveProject(id: $id) { id archivedAt viewerRights } }';

    public function test_the_manager_archives_and_the_owner_brings_the_project_back(): void
    {
        $archived = $this->as('manager')->gql(self::ARCHIVE, ['id' => $this->project->id])->assertJsonMissingPath('errors');

        $this->assertNotNull($archived->json('data.archiveProject.archivedAt'));
        $this->assertTrue($this->project->fresh()->isArchived());

        $this->as('owner')->gql(self::UNARCHIVE, ['id' => $this->project->id])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.unarchiveProject.archivedAt', null);
        $this->assertFalse($this->project->fresh()->isArchived());
    }

    public function test_archiving_twice_keeps_the_first_date(): void
    {
        $this->archive();
        $first = $this->project->fresh()->archived_at;
        $this->travel(1)->hour();

        $this->as('owner')->gql(self::ARCHIVE, ['id' => $this->project->id])->assertJsonMissingPath('errors');

        $this->assertEquals($first, $this->project->fresh()->archived_at);
    }

    public function test_members_guests_and_read_only_people_cannot_archive(): void
    {
        foreach (['member', 'stranger', 'lesend'] as $actor) {
            $this->assertUnauthorized($this->as($actor)->gql(self::ARCHIVE, ['id' => $this->project->id]));
        }

        $this->assertFalse($this->project->fresh()->isArchived());
    }

    public function test_archiving_and_bringing_back_leave_the_planer_revision(): void
    {
        $revision = $this->project->fresh()->planer_revision;

        $this->as('owner')->gql(self::ARCHIVE, ['id' => $this->project->id]);
        $this->as('owner')->gql(self::UNARCHIVE, ['id' => $this->project->id]);

        $this->assertSame($revision, $this->project->fresh()->planer_revision);
    }

    public function test_in_an_archived_project_only_reading_bringing_back_and_restoring_remain(): void
    {
        $this->archive();
        $kept = [Right::PROJECT_ARCHIVE->name, Right::TRASH_RESTORE->name, Right::PLANER_EXPORT->name];

        foreach (['owner', 'admin'] as $actor) {
            $rights = $this->as($actor)->gql('query ($id: ID!) { project(id: $id) { viewerRights } }', ['id' => $this->project->id])
                ->json('data.project.viewerRights');

            $this->assertEqualsCanonicalizing($kept, $rights, $actor);
        }

        $memberRights = $this->as('member')->gql('query ($id: ID!) { project(id: $id) { viewerRights } }', ['id' => $this->project->id])
            ->json('data.project.viewerRights');
        $this->assertSame([], array_values(array_diff($memberRights, $kept)));
    }

    public function test_the_fields_of_a_task_say_no_in_an_archived_project(): void
    {
        $this->task->forceFill(['created_by' => $this->users['member']->id])->save();
        $this->archive();

        foreach (['owner', 'member', 'admin'] as $actor) {
            $this->as($actor)->gql('query ($id: ID!) { task(id: $id) { viewerCanUpdate viewerCanAssign viewerCanAssignSelf viewerCanDelete } }', ['id' => $this->task->id])
                ->assertJsonPath('data.task', ['viewerCanUpdate' => false, 'viewerCanAssign' => false, 'viewerCanAssignSelf' => false, 'viewerCanDelete' => false]);
        }
    }

    public function test_the_author_can_no_longer_change_or_delete_a_comment(): void
    {
        $comment = Comment::factory()->for($this->task)->for($this->users['member'], 'author')->create();
        $this->archive();

        $this->as('member')->gql('query ($id: ID!) { task(id: $id) { comments { viewerCanEdit viewerCanDelete } } }', ['id' => $this->task->id])
            ->assertJsonPath('data.task.comments.0', ['viewerCanEdit' => false, 'viewerCanDelete' => false]);
        $this->assertNotNull($comment->fresh());
    }

    public function test_projects_lists_the_active_ones_and_on_request_the_archived(): void
    {
        $other = Project::factory()->withMember($this->users['owner'], ProjectRole::OWNER)->create(['name' => 'Aktiv']);
        $this->archive();

        $this->as('owner')->gql('{ projects { name archivedAt } }')
            ->assertJsonPath('data.projects', [['name' => 'Aktiv', 'archivedAt' => null]]);
        $this->as('owner')->gql('{ projects(archived: true) { name } }')
            ->assertJsonPath('data.projects', [['name' => 'Mollo Planer']]);
        $this->as('owner')->gql('query ($id: ID!) { project(id: $id) { name } }', ['id' => $this->project->id])
            ->assertJsonPath('data.project.name', 'Mollo Planer');
        $this->assertNotNull($other);
    }

    public function test_my_tasks_and_the_dashboard_leave_archived_projects_out(): void
    {
        $this->task->forceFill(['assigned_user_id' => $this->users['member']->id])->save();
        $query = '{ myTasks { id } dashboard { upcomingTodos(limit: 50) { id } myOpenTasks { id } projects { id } } }';

        $this->as('member')->gql($query)
            ->assertJsonCount(1, 'data.myTasks')
            ->assertJsonCount(1, 'data.dashboard.projects')
            ->assertJsonCount(1, 'data.dashboard.upcomingTodos')
            ->assertJsonCount(1, 'data.dashboard.myOpenTasks');

        $this->archive();

        $this->as('member')->gql($query)
            ->assertJsonPath('data.myTasks', [])
            ->assertJsonPath('data.dashboard.upcomingTodos', [])
            ->assertJsonPath('data.dashboard.myOpenTasks', [])
            ->assertJsonPath('data.dashboard.projects', []);
    }

    public function test_no_mails_about_tasks_of_an_archived_project(): void
    {
        Notification::fake();
        $this->task->forceFill(['assigned_user_id' => $this->users['member']->id])->save();
        $this->archive();

        app(TaskNotifier::class)->assigned($this->task->fresh(), $this->users['owner']);

        Notification::assertNothingSent();
    }

    private function archive(): void
    {
        $this->project->forceFill(['archived_at' => now()])->save();
        $this->project->refresh();
    }
}
