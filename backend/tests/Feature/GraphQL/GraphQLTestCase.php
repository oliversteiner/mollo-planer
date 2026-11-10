<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Support\StrongAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

/**
 * Requests go through the real /graphql route with all middleware:
 * Sanctum (stateful via Referer), authentication and the strong-auth lock.
 */
abstract class GraphQLTestCase extends TestCase
{
    use MakesGraphQLRequests;
    use RefreshDatabase;

    protected Project $project;

    protected Milestone $milestone;

    protected Todo $todo;

    protected Task $task;

    /** @var array<string, User> */
    protected array $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = [
            'owner' => User::factory()->create(['name' => 'Ben Owner']),
            'manager' => User::factory()->create(['name' => 'Chiara Manager']),
            'member' => User::factory()->create(['name' => 'David Member']),
            'stranger' => User::factory()->create(['name' => 'Eva Stranger']),
            'admin' => User::factory()->admin()->create(['name' => 'Anna Admin']),
            'projektleitung' => User::factory()->role(UserRole::PROJEKTLEITUNG)->create(['name' => 'Paula Projektleitung']),
            'koordination' => User::factory()->role(UserRole::KOORDINATION)->create(['name' => 'Kurt Koordination']),
            'lesend' => User::factory()->role(UserRole::LESEND)->create(['name' => 'Lea Lesend']),
        ];

        $this->project = Project::factory()
            ->withMember($this->users['owner'], ProjectRole::OWNER)
            ->withMember($this->users['manager'], ProjectRole::MANAGER)
            ->withMember($this->users['member'], ProjectRole::MEMBER)
            ->create(['name' => 'FCA DB']);
        $this->milestone = Milestone::factory()->for($this->project)->create();
        $this->todo = Todo::factory()->for($this->milestone)->create();
        $this->task = Task::factory()->for($this->todo)->create(['position' => 0]);
    }

    /**
     * Log in with a strongly authenticated session.
     */
    protected function as(string $name): static
    {
        return $this->actingAs($this->users[$name])
            ->withSession([StrongAuthentication::SESSION_KEY => (string) $this->users[$name]->id])
            ->withHeader('Referer', 'http://localhost');
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    protected function gql(string $query, array $variables = []): TestResponse
    {
        return $this->graphQL($query, $variables);
    }

    /**
     * @return array<string, mixed>
     */
    protected function doc(string $text): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
    }

    protected function assertUnauthorized(TestResponse $response): void
    {
        $response->assertGraphQLErrorMessage('This action is unauthorized.');
    }
}
