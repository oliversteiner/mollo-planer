<?php

namespace Tests\Feature\GraphQL;

use App\Models\Comment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Todo;
use App\Services\Deletion;
use App\Support\Rights;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The trash in GraphQL (docs/auftrag-m2.md §3) and who may do what in it
 * (§2.6): one test per right with the standard roles and with a role of
 * one's own; guests and read-only people can do none of it.
 */
class TrashGraphQLTest extends GraphQLTestCase
{
    private const TRASH = 'query ($p: ID) { trash(projectId: $p) { deletionId type id title childCount childCounts { type count } deletedBy { name } project { name } viewerCanRestore viewerCanPurge } }';

    private const RESTORE = 'mutation ($id: ID!) { restoreFromTrash(deletionId: $id) { type id title } }';

    private const PURGE = 'mutation ($id: ID!) { purgeFromTrash(deletionId: $id) }';

    private const EMPTY = 'mutation ($p: ID!) { emptyProjectTrash(projectId: $p) }';

    public function test_delete_mutations_return_the_deletion_id_for_undo(): void
    {
        $deletionId = $this->as('owner')->gql('mutation ($id: ID!) { deleteTask(id: $id) { deletionId } }', ['id' => $this->task->id])
            ->assertJsonMissingPath('errors')
            ->json('data.deleteTask.deletionId');

        $this->assertSame(Task::onlyTrashed()->find($this->task->id)->deletion_id, $deletionId);

        $this->as('owner')->gql(self::RESTORE, ['id' => $deletionId])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.restoreFromTrash', ['type' => 'TASK', 'id' => (string) $this->task->id, 'title' => $this->task->title]);
        $this->assertNotNull(Task::find($this->task->id));
    }

    public function test_the_trash_lists_one_entry_per_deletion_oldest_first(): void
    {
        $earlier = Task::factory()->for($this->todo)->create(['title' => 'Vorher']);
        $this->as('owner')->gql('mutation ($id: ID!) { deleteTask(id: $id) { id } }', ['id' => $earlier->id]);
        $this->travel(1)->minute();
        Todo::factory()->for($this->milestone)->has(Task::factory()->count(2))->create();
        $this->as('owner')->gql('mutation ($id: ID!) { deleteMilestone(id: $id) { id } }', ['id' => $this->milestone->id]);

        $items = $this->as('owner')->gql(self::TRASH, ['p' => $this->project->id])->assertJsonMissingPath('errors')->json('data.trash');

        $this->assertSame(['TASK', 'MILESTONE'], array_column($items, 'type'));
        $this->assertSame('Vorher', $items[0]['title']);
        $this->assertSame(0, $items[0]['childCount']);
        $this->assertSame(5, $items[1]['childCount']);
        $this->assertEqualsCanonicalizing([['type' => 'TODO', 'count' => 2], ['type' => 'TASK', 'count' => 3]], $items[1]['childCounts']);
        $this->assertSame('Ben Owner', $items[1]['deletedBy']['name']);
        $this->assertSame('Mollo Planer', $items[1]['project']['name']);
        $this->assertTrue($items[1]['viewerCanRestore'] && $items[1]['viewerCanPurge']);
    }

    public function test_a_comment_in_the_trash_shows_a_short_plain_excerpt(): void
    {
        $long = str_repeat('Sehr lange Bemerkung ', 10);
        $comment = Comment::factory()->for($this->task)->for($this->users['member'], 'author')->create([
            'body' => ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Fett', 'marks' => [['type' => 'bold']]], ['type' => 'text', 'text' => 'gedruckt']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $long]]],
            ]],
        ]);
        $this->as('member')->gql('mutation ($id: ID!) { deleteComment(id: $id) { id } }', ['id' => $comment->id]);

        $title = $this->as('owner')->gql(self::TRASH, ['p' => $this->project->id])->json('data.trash.0.title');

        $this->assertStringStartsWith('Fettgedruckt Sehr lange Bemerkung', $title);
        $this->assertSame(80, mb_strlen($title));
        $this->assertStringEndsWith('…', $title);
    }

    public function test_restoring_into_a_deleted_parent_names_it(): void
    {
        $taskDeletion = $this->deleteAs('owner', $this->task);
        $this->as('owner')->gql('mutation ($id: ID!) { deleteTodo(id: $id) { id } }', ['id' => $this->todo->id]);

        $this->as('owner')->withHeader('Accept-Language', 'de')->gql(self::RESTORE, ['id' => $taskDeletion])
            ->assertGraphQLValidationKeys(['deletionId']);
    }

    public function test_standard_roles_restore_and_purge_by_their_rights(): void
    {
        // Owner: trash.restore and trash.purge; manager: trash.restore; member and guest: neither.
        [$a, $b, $c, $d, $e] = $this->trashedTasks(5);

        $this->assertUnauthorized($this->as('member')->gql(self::RESTORE, ['id' => $a]));
        $this->assertUnauthorized($this->as('stranger')->gql(self::RESTORE, ['id' => $a]));

        $this->as('manager')->gql(self::RESTORE, ['id' => $b])->assertJsonMissingPath('errors');
        $this->assertUnauthorized($this->as('manager')->gql(self::PURGE, ['id' => $c]));
        $this->assertUnauthorized($this->as('manager')->gql(self::EMPTY, ['p' => $this->project->id]));

        $this->as('owner')->gql(self::PURGE, ['id' => $d])->assertJsonPath('data.purgeFromTrash', true);
        $this->as('owner')->gql(self::EMPTY, ['p' => $this->project->id])->assertJsonPath('data.emptyProjectTrash', 3);
        $this->assertSame(0, Task::onlyTrashed()->count());
        $this->assertNull(Task::withTrashed()->where('deletion_id', $e)->first());
    }

    public function test_the_trash_list_is_empty_without_a_trash_right(): void
    {
        $this->deleteAs('owner', $this->task);

        $this->as('member')->gql(self::TRASH, ['p' => $this->project->id])->assertJsonPath('data.trash', []);
        $this->as('stranger')->gql(self::TRASH, ['p' => $this->project->id])->assertJsonPath('data.trash', []);
        $this->as('manager')->gql(self::TRASH, ['p' => $this->project->id])
            ->assertJsonPath('data.trash.0.viewerCanRestore', true)
            ->assertJsonPath('data.trash.0.viewerCanPurge', false);
    }

    public function test_a_role_of_ones_own_restores_or_purges_with_the_right_it_has(): void
    {
        $restorer = $this->customProjectRole('restorer', ['manage-tasks']);
        $purger = $this->customProjectRole('purger', ['own-project']);
        DB::table('project_user')->where(['project_id' => $this->project->id, 'user_id' => $this->users['member']->id])->update(['role_id' => $restorer->id]);
        $this->project->members()->attach($this->users['stranger'], ['role_id' => $purger->id]);
        [$a, $b, $c, $d] = $this->trashedTasks(4);

        $this->as('member')->gql(self::RESTORE, ['id' => $a])->assertJsonMissingPath('errors');
        $this->assertUnauthorized($this->as('member')->gql(self::PURGE, ['id' => $b]));

        $this->as('stranger')->gql(self::PURGE, ['id' => $c])->assertJsonPath('data.purgeFromTrash', true);
        $this->assertUnauthorized($this->as('stranger')->gql(self::RESTORE, ['id' => $d]));
    }

    public function test_read_only_people_can_do_nothing_in_the_trash(): void
    {
        $this->project->members()->attach($this->users['lesend'], ['role_id' => Role::idOf(Role::SCOPE_PROJECT, 'owner')]);
        $deletionId = $this->deleteAs('owner', $this->task);

        $this->assertUnauthorized($this->as('lesend')->gql(self::RESTORE, ['id' => $deletionId]));
        $this->assertUnauthorized($this->as('lesend')->gql(self::PURGE, ['id' => $deletionId]));
        $this->assertUnauthorized($this->as('lesend')->gql(self::EMPTY, ['p' => $this->project->id]));
        $this->as('lesend')->gql(self::TRASH, ['p' => $this->project->id])->assertJsonPath('data.trash', []);
    }

    public function test_a_comment_comes_back_with_its_author_or_a_moderator(): void
    {
        $comments = collect([1, 2, 3])->map(fn () => Comment::factory()->for($this->task)->for($this->users['member'], 'author')->create());
        $ids = $comments->map(fn (Comment $comment) => $this->deleteAs('member', $comment));
        $this->project->members()->attach($this->users['stranger'], ['role_id' => Role::idOf(Role::SCOPE_PROJECT, 'member')]);

        $this->as('member')->gql(self::RESTORE, ['id' => $ids[0]])->assertJsonPath('data.restoreFromTrash.type', 'COMMENT');
        $this->as('manager')->gql(self::RESTORE, ['id' => $ids[1]])->assertJsonMissingPath('errors');
        $this->assertUnauthorized($this->as('stranger')->gql(self::RESTORE, ['id' => $ids[2]]));
    }

    public function test_deleted_projects_come_back_with_project_delete_or_manage_any(): void
    {
        $deletionId = $this->deleteAs('owner', $this->project);
        $this->travel(1)->minute();
        $other = Project::factory()->create(['name' => 'Fremd']);
        $this->deleteAs('admin', $other);

        $this->as('owner')->gql(self::TRASH, ['p' => null])->assertJsonPath('data.trash.*.title', ['Mollo Planer']);
        $this->as('manager')->gql(self::TRASH, ['p' => null])->assertJsonPath('data.trash', []);
        $this->as('admin')->gql(self::TRASH, ['p' => null])->assertJsonPath('data.trash.*.title', ['Mollo Planer', 'Fremd']);

        $this->assertUnauthorized($this->as('manager')->gql(self::RESTORE, ['id' => $deletionId]));
        $this->as('owner')->gql(self::RESTORE, ['id' => $deletionId])->assertJsonPath('data.restoreFromTrash.type', 'PROJECT');
        $this->assertNotNull(Project::find($this->project->id));

        $this->as('admin')->gql(self::PURGE, ['id' => Project::onlyTrashed()->find($other->id)->deletion_id])->assertJsonPath('data.purgeFromTrash', true);
        $this->assertNull(Project::withTrashed()->find($other->id));
    }

    public function test_the_trash_list_does_not_grow_queries_with_its_entries(): void
    {
        $this->deleteAs('owner', Task::factory()->for($this->todo)->create());
        $this->deleteAs('owner', Comment::factory()->for($this->task)->for($this->users['owner'], 'author')->create());
        $this->as('owner')->gql(self::TRASH, ['p' => $this->project->id]);
        $few = $this->countQueries(fn () => $this->as('owner')->gql(self::TRASH, ['p' => $this->project->id])->assertJsonCount(2, 'data.trash'));

        foreach (range(1, 6) as $i) {
            $this->deleteAs('owner', Task::factory()->for($this->todo)->create());
            $this->deleteAs('owner', Comment::factory()->for($this->task)->for($this->users['owner'], 'author')->create());
        }
        $this->deleteAs('owner', Milestone::factory()->for($this->project)->create());

        $many = $this->countQueries(fn () => $this->as('owner')->gql(self::TRASH, ['p' => $this->project->id])->assertJsonCount(15, 'data.trash'));

        $this->assertSame($few, $many);
    }

    /**
     * Tasks deleted one by one by the owner.
     *
     * @return list<string>
     */
    private function trashedTasks(int $count): array
    {
        return array_map(fn () => $this->deleteAs('owner', Task::factory()->for($this->todo)->create()), range(1, $count));
    }

    private function deleteAs(string $actor, Model $model): string
    {
        $this->actingAs($this->users[$actor]);

        return (string) app(Deletion::class)->delete($model)->deletion_id;
    }

    private function countQueries(\Closure $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $work();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @param  list<string>  $functions
     */
    private function customProjectRole(string $key, array $functions): Role
    {
        Role::create(['scope' => Role::SCOPE_PROJECT, 'key' => $key, 'names' => ['de' => $key, 'en' => $key]]);
        $roleId = DB::table('roles')->where(['scope' => Role::SCOPE_PROJECT, 'key' => $key])->value('id');

        foreach ($functions as $function) {
            DB::table('role_functions')->insert([
                'role_id' => $roleId,
                'function_id' => DB::table('functions')->where(['scope' => Role::SCOPE_PROJECT, 'key' => $function])->value('id'),
            ]);
        }

        Rights::clearCache();

        return Role::resolveOrFail(Role::SCOPE_PROJECT, $key);
    }
}
