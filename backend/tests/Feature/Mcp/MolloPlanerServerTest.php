<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\MolloPlanerServer;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTemplatesTool;
use App\Mcp\Tools\ListUsersTool;
use App\Models\Project;
use App\Models\User;
use App\Planer\TemplateSync;
use App\Support\McpTokens;

/**
 * The MCP server acts through the GraphQL schema as the token's owner: same
 * permissions and rules as the app. Reading and writing a plan is export_plan
 * and import_plan (tests/Feature/Planer).
 */
class MolloPlanerServerTest extends McpTestCase
{
    public function test_list_projects_shows_only_what_the_token_owner_may_see(): void
    {
        Project::factory()->create(['name' => 'Fremd']);

        MolloPlanerServer::actingAs($this->member)->tool(ListProjectsTool::class)
            ->assertOk()
            ->assertSee('Podcast')
            ->assertDontSee('Fremd');
    }

    public function test_list_templates_shows_the_templates_for_every_token_owner(): void
    {
        TemplateSync::sync();

        MolloPlanerServer::actingAs($this->member)->tool(ListTemplatesTool::class)
            ->assertOk()
            ->assertSee('Event')
            ->assertSee('Veranstaltungsort anfragen')
            ->assertSee('location');
    }

    public function test_list_users_names_the_people_by_e_mail(): void
    {
        MolloPlanerServer::actingAs(User::factory()->admin()->create())->tool(ListUsersTool::class)
            ->assertOk()
            ->assertSee('lindita@example.ch');
    }

    public function test_list_users_without_a_project_gives_names_only_to_somebody_who_does_not_manage_users(): void
    {
        MolloPlanerServer::actingAs($this->member)->tool(ListUsersTool::class)
            ->assertOk()
            ->assertSee($this->owner->name)
            ->assertDontSee($this->owner->email);
    }

    public function test_list_users_with_a_project_gives_its_members_with_e_mail_and_hides_foreign_projects(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(ListUsersTool::class, ['project_id' => (string) $this->project->id])
            ->assertOk()
            ->assertSee($this->owner->email);

        MolloPlanerServer::actingAs($this->stranger)->tool(ListUsersTool::class, ['project_id' => (string) $this->project->id])
            ->assertHasErrors();
    }

    public function test_the_server_offers_exactly_one_way_to_read_and_one_to_write_a_plan(): void
    {
        $list = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'];
        $headers = ['Accept' => 'application/json, text/event-stream'];
        $token = McpTokens::create($this->owner, 'Claude')->plainTextToken;

        $names = collect($this->postJson('/mcp', $list, [...$headers, 'Authorization' => "Bearer {$token}"])->json('result.tools'))->pluck('name')->sort()->values()->all();

        $this->assertSame(['add_comment', 'create_idea', 'export_plan', 'get_project_ai_context', 'get_project_statistics', 'import_plan', 'list_comments', 'list_ideas', 'list_projects', 'list_templates', 'list_users', 'update_tasks'], $names);
    }

    public function test_http_endpoint_needs_a_token_made_for_claude(): void
    {
        $list = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'];
        $headers = ['Accept' => 'application/json, text/event-stream'];

        $this->postJson('/mcp', $list, $headers)->assertUnauthorized();

        $other = $this->owner->createToken('other')->plainTextToken;
        $this->postJson('/mcp', $list, [...$headers, 'Authorization' => "Bearer {$other}"])->assertForbidden();

        // Each real request starts fresh; the test app keeps the resolved user.
        $this->app['auth']->forgetGuards();
        $token = McpTokens::create($this->owner, 'Claude')->plainTextToken;
        $this->postJson('/mcp', $list, [...$headers, 'Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertSee('export_plan');
    }
}
