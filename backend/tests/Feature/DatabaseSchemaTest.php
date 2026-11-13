<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function expectedColumns(): array
    {
        return [
            'users' => ['users', ['role', 'job_function', 'notify_by_email', 'avatar_path', 'two_factor_secret', 'two_factor_confirmed_at']],
            'projects' => ['projects', ['name', 'description', 'icon', 'locale']],
            'project_user' => ['project_user', ['project_id', 'user_id', 'role']],
            'milestones' => ['milestones', ['project_id', 'title', 'description', 'icon', 'due_date', 'status']],
            'todos' => ['todos', ['milestone_id', 'title', 'description', 'icon', 'due_date', 'status']],
            'tasks' => ['tasks', ['todo_id', 'assigned_user_id', 'title', 'description', 'icon', 'status', 'position', 'due_date']],
            'comments' => ['comments', ['task_id', 'user_id', 'body', 'created_at', 'updated_at']],
        ];
    }

    /**
     * @param  list<string>  $columns
     */
    #[DataProvider('expectedColumns')]
    public function test_table_has_expected_columns(string $table, array $columns): void
    {
        $this->assertTrue(Schema::hasColumns($table, $columns), "Table {$table} is missing columns.");
    }

    public function test_defaults_for_new_columns(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test', 'email' => 'test@example.com', 'password' => 'x',
        ]);
        $projectId = DB::table('projects')->insertGetId(['name' => 'Project']);
        DB::table('project_user')->insert(['project_id' => $projectId, 'user_id' => $userId]);

        $this->assertSame('mitglied', DB::table('users')->value('role'));
        $this->assertSame(1, (int) DB::table('users')->value('notify_by_email'));
        $this->assertSame('de', DB::table('projects')->value('locale'));
        $this->assertSame('member', DB::table('project_user')->value('role'));
    }

    public function test_comments_are_deleted_with_task_and_kept_without_author(): void
    {
        Schema::enableForeignKeyConstraints();

        $userId = DB::table('users')->insertGetId(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);
        $projectId = DB::table('projects')->insertGetId(['name' => 'P']);
        $milestoneId = DB::table('milestones')->insertGetId(['project_id' => $projectId, 'title' => 'M']);
        $todoId = DB::table('todos')->insertGetId(['milestone_id' => $milestoneId, 'project_id' => $projectId, 'title' => 'T']);
        $taskId = DB::table('tasks')->insertGetId(['todo_id' => $todoId, 'project_id' => $projectId, 'title' => 'Task']);
        $body = json_encode(['type' => 'doc', 'content' => []]);
        DB::table('comments')->insert(['task_id' => $taskId, 'user_id' => $userId, 'body' => $body]);

        DB::table('users')->where('id', $userId)->delete();
        $this->assertNull(DB::table('comments')->value('user_id'));

        DB::table('tasks')->where('id', $taskId)->delete();
        $this->assertSame(0, DB::table('comments')->count());
    }

    public function test_a_user_is_member_of_a_project_only_once(): void
    {
        $userId = DB::table('users')->insertGetId(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x']);
        $projectId = DB::table('projects')->insertGetId(['name' => 'P']);
        DB::table('project_user')->insert(['project_id' => $projectId, 'user_id' => $userId]);

        $this->expectException(QueryException::class);
        DB::table('project_user')->insert(['project_id' => $projectId, 'user_id' => $userId, 'role' => 'owner']);
    }
}
