<?php

namespace Tests\Feature\Tools;

use App\Assistant\ToolBridge;
use App\Enums\ProjectRole;
use App\Mcp\ToolAdapter;
use App\Models\User;
use App\Support\McpTokens;
use App\Tools\Schema;
use App\Tools\Tool;
use App\Tools\ToolRegistry;
use App\Tools\ToolResult;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Mcp\McpTestCase;

/**
 * The tool layer (task 34): every tool is described once and offered by two adapters, the MCP server and the Claude
 * tool use of the assistant. Nothing changes for an MCP client: the list of tools is compared with a file made
 * before the change.
 */
class ToolLayerTest extends McpTestCase
{
    public function test_the_registry_has_every_tool_exactly_once(): void
    {
        $names = array_map(fn (Tool $tool): string => $tool->name(), ToolRegistry::all());

        $this->assertSame(array_values(array_unique($names)), $names, 'a name twice in the registry');
        $this->assertSame(array_values(array_unique(ToolRegistry::TOOLS)), ToolRegistry::TOOLS, 'a class twice in the registry');
        $this->assertCount(14, $names);

        // Everything under App\Tools that is a tool is in the registry: a tool written but not entered would be offered nowhere.
        $classes = collect(glob(app_path('Tools/*.php')))
            ->map(fn (string $file): string => 'App\\Tools\\'.basename($file, '.php'))
            ->filter(fn (string $class): bool => is_subclass_of($class, Tool::class) && ! (new \ReflectionClass($class))->isAbstract())
            ->sort()->values()->all();
        $registered = ToolRegistry::TOOLS;
        sort($registered);

        $this->assertSame($classes, $registered);
    }

    public function test_the_mcp_tool_list_is_exactly_the_one_of_the_fixture(): void
    {
        $token = McpTokens::create($this->owner, 'Claude')->plainTextToken;
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], [
            'Accept' => 'application/json, text/event-stream',
            'Authorization' => "Bearer {$token}",
        ])->assertOk();

        $listed = collect($response->json('result.tools'))->sortBy('name')->values()->all();
        $before = json_decode((string) file_get_contents(base_path('tests/Fixtures/mcp-tools-list.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals($before, $listed);
    }

    public function test_both_adapters_give_the_same_name_description_and_schema(): void
    {
        $definitions = collect(app(ToolBridge::class)->definitions())->keyBy('name');

        foreach (ToolRegistry::all() as $tool) {
            $mcp = (new ToolAdapter($tool))->toArray();
            $claude = $definitions[$tool->name()];

            $this->assertSame($mcp['name'], $claude['name']);
            $this->assertSame($mcp['description'], $claude['description']);
            $this->assertEquals(
                json_decode(json_encode($mcp['inputSchema'] + ['properties' => []]), true)['properties'] ?: [],
                json_decode(json_encode($claude['input_schema']), true)['properties'] ?: [],
                "{$tool->name()}: the properties differ",
            );
            $this->assertSame('object', $claude['input_schema']['type']);
            $this->assertSame(is_array($mcp['inputSchema']['required'] ?? null) ? $mcp['inputSchema']['required'] : null, $claude['input_schema']['required'] ?? null);
        }
    }

    public function test_a_tool_without_arguments_has_an_object_of_properties_for_claude(): void
    {
        $tool = new class extends Tool
        {
            public function name(): string
            {
                return 'no_arguments';
            }

            public function description(): string
            {
                return 'A tool without arguments.';
            }

            public function schema(): array
            {
                return Schema::input();
            }

            public function handle(User $actor, array $arguments): ToolResult
            {
                return ToolResult::text('ok');
            }
        };

        $schema = (new \ReflectionMethod(ToolBridge::class, 'inputSchema'))->invoke(app(ToolBridge::class), $tool);

        $this->assertSame('{"type":"object","properties":{}}', json_encode($schema));
    }

    public function test_the_bridge_runs_a_reading_tool_as_the_given_person_without_a_signed_in_request(): void
    {
        Auth::forgetGuards();

        $projects = app(ToolBridge::class)->call($this->member, 'list_projects', []);

        $this->assertFalse($projects->isError());
        $this->assertSame(['Podcast'], array_column($projects->data, 'name'));
        $this->assertNull(Auth::guard('sanctum')->user(), 'the person is not left signed in after the call');
    }

    public function test_the_bridge_respects_the_rights_of_the_person(): void
    {
        $foreign = app(ToolBridge::class)->call($this->stranger, 'export_plan', ['project_id' => (string) $this->project->id]);
        $own = app(ToolBridge::class)->call($this->owner, 'export_plan', ['project_id' => (string) $this->project->id]);

        $this->assertTrue($foreign->isError());
        $this->assertFalse($own->isError());
        $this->assertSame('Podcast', $own->data['document']['project']['name']);

        // The list of projects of a stranger does not name the project.
        $this->assertSame([], app(ToolBridge::class)->call($this->stranger, 'list_projects', [])->data);
    }

    public function test_the_bridge_answers_what_does_not_fit_as_an_error_result(): void
    {
        $bridge = app(ToolBridge::class);

        $this->assertSame('Unknown tool [delete_everything].', $bridge->call($this->owner, 'delete_everything', [])->data);

        $missing = $bridge->call($this->owner, 'export_plan', []);

        $this->assertTrue($missing->isError());
        $this->assertStringContainsString('Give project_id', $missing->data);
    }

    public function test_an_agent_account_works_through_mcp_as_before(): void
    {
        $agent = User::factory()->agent()->create();
        $this->project->members()->attach($agent->id, ['role_id' => $this->projectRole(ProjectRole::MEMBER)->id]);
        $token = McpTokens::create($agent, 'Kevin')->plainTextToken;

        $this->postJson('/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'list_projects', 'arguments' => (object) []],
        ], ['Accept' => 'application/json, text/event-stream', 'Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertSee('Podcast');
    }
}
