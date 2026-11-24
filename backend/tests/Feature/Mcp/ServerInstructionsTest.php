<?php

namespace Tests\Feature\Mcp;

use App\Mcp\ServerInstructions;
use App\Models\Project;
use App\Support\McpTokens;
use App\Tools\ToolRegistry;

/**
 * What the server says with `initialize` (Auftrag 49 D): whose token it is, the fields of each level (read from
 * spec/planer-v2.schema.json), all limits including the title, and the links to the guide and the format schema.
 * The size is kept by McpBudgetTest. /mcp/llms.txt and /llms.txt on the API host go to the guide of the frontend.
 */
class ServerInstructionsTest extends McpTestCase
{
    private function instructionsFor(string $token): string
    {
        return (string) $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-03-26', 'capabilities' => new \stdClass, 'clientInfo' => ['name' => 'test', 'version' => '1']]], [
            'Accept' => 'application/json, text/event-stream',
            'Authorization' => "Bearer {$token}",
        ])->assertOk()->json('result.instructions');
    }

    public function test_the_first_line_names_the_account_of_the_token_and_its_projects(): void
    {
        $other = Project::factory()->withMember($this->member)->create(['name' => 'Zweites']);

        $member = $this->instructionsFor(McpTokens::create($this->member, 'Claude')->plainTextToken);
        // One test, one application: the guard of the first request would answer the second.
        $this->app['auth']->forgetGuards();
        $owner = $this->instructionsFor(McpTokens::create($this->owner, 'Claude')->plainTextToken);

        $this->assertStringStartsWith("You are {$this->member->name} (lindita@example.ch), member of projects {$this->project->id}, {$other->id}.", $member);
        $this->assertStringStartsWith("You are {$this->owner->name} (oliver@example.ch), member of projects {$this->project->id}.", $owner);
        $this->assertStringNotContainsString('lindita@example.ch', $owner);
    }

    public function test_an_account_without_a_project_says_so(): void
    {
        $text = ServerInstructions::for($this->stranger);

        $this->assertStringStartsWith("You are {$this->stranger->name} (eva@example.ch), member of no project.", $text);
    }

    public function test_the_project_list_does_not_grow_without_end(): void
    {
        Project::factory()->count(20)->withMember($this->member)->create();

        $first = strtok(ServerInstructions::for($this->member), "\n");

        $this->assertStringContainsString('(21 in all)', (string) $first);
        $this->assertLessThan(200, strlen((string) $first));
    }

    public function test_every_level_lists_the_fields_of_the_schema_without_its_children(): void
    {
        $text = ServerInstructions::for($this->member);

        $this->assertStringContainsString('milestone: id, title, description, dueDate, release; todo: id, title, description, dueDate; task: id, title, description, dueDate, status, assigneeEmail, labels, commit, userStory.', $text);
        $this->assertStringNotContainsString('todos,', $text);
    }

    public function test_the_limits_include_the_title_and_the_links_point_to_the_frontend(): void
    {
        config(['app.frontend_url' => 'https://planer.mollo.dev/']);
        $text = ServerInstructions::for($this->member);

        $this->assertStringContainsString('titles 2-160 characters', $text);
        $this->assertStringContainsString('Unknown fields are refused', $text);
        $this->assertStringContainsString('https://planer.mollo.dev/mcp/llms.txt', $text);
        $this->assertStringContainsString('https://planer.mollo.dev/mcp/format-v2.schema.json', $text);
    }

    public function test_import_plan_names_the_fields_and_the_schema_in_its_parameter(): void
    {
        $tool = collect(ToolRegistry::all())->first(fn ($tool) => $tool->name() === 'import_plan');

        $description = $tool->schema()['properties']['plan']['description'];

        $this->assertStringContainsString('instructions of the server', $description);
        $this->assertStringContainsString('JSON schema', $description);
        $this->assertStringContainsString('Unknown fields are refused', $description);
    }

    public function test_the_guide_on_the_api_host_moves_permanently_to_the_frontend(): void
    {
        config(['app.frontend_url' => 'https://planer.mollo.dev']);

        foreach (['/mcp/llms.txt', '/llms.txt'] as $path) {
            $this->get($path)->assertStatus(301)->assertRedirect('https://planer.mollo.dev/mcp/llms.txt');
        }
    }
}
