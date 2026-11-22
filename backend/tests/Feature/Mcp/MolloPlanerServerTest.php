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

    public function test_project_revisions_are_real_and_optional_details_are_not_loaded_by_default(): void
    {
        $compact = $this->decode(MolloPlanerServer::actingAs($this->owner)->tool(ListProjectsTool::class));

        $this->assertNotNull($compact[0]['planer_revision']);
        $this->assertArrayNotHasKey('members', $compact[0]);
        $this->assertArrayNotHasKey('abilities', $compact[0]);

        $detailed = $this->decode(MolloPlanerServer::actingAs($this->owner)->tool(ListProjectsTool::class, [
            'include_members' => true, 'include_rights' => true,
        ]));
        $this->assertNotEmpty($detailed[0]['members']);
        $this->assertNotEmpty($detailed[0]['abilities']);
    }

    public function test_list_templates_shows_the_templates_for_every_token_owner(): void
    {
        TemplateSync::sync();

        $compact = $this->decode(MolloPlanerServer::actingAs($this->member)->tool(ListTemplatesTool::class));
        $event = collect($compact)->firstWhere('key', 'event');
        $this->assertNotNull($event);
        $this->assertArrayHasKey('project_count', $event);
        $this->assertArrayNotHasKey('labels', $event);

        MolloPlanerServer::actingAs($this->member)->tool(ListTemplatesTool::class, ['include_details' => true])
            ->assertOk()
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

    public function test_list_users_returns_cursor_pages_beyond_the_first_limit(): void
    {
        for ($index = 1; $index <= 35; $index++) {
            User::factory()->create([
                'name' => sprintf('Page User %03d', $index),
                'approved_at' => now(),
            ]);
        }

        $firstPage = $this->decode(MolloPlanerServer::actingAs($this->member)->tool(ListUsersTool::class, ['query' => 'Page User', 'limit' => 30]));
        $this->assertCount(30, $firstPage['data']);
        $this->assertTrue($firstPage['has_more']);

        $lastPage = $this->decode(MolloPlanerServer::actingAs($this->member)->tool(ListUsersTool::class, [
            'query' => 'Page User', 'limit' => 30, 'cursor' => $firstPage['next_cursor'],
        ]));
        $this->assertCount(5, $lastPage['data']);
        $this->assertFalse($lastPage['has_more']);
    }

    public function test_the_server_offers_exactly_one_way_to_read_and_one_to_write_a_plan(): void
    {
        $list = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'];
        $headers = ['Accept' => 'application/json, text/event-stream'];
        $token = McpTokens::create($this->owner, 'Claude')->plainTextToken;

        $names = collect($this->postJson('/mcp', $list, [...$headers, 'Authorization' => "Bearer {$token}"])->json('result.tools'))->pluck('name')->sort()->values()->all();

        $this->assertSame(['add_comment', 'create_idea', 'export_plan', 'get_project_ai_context', 'import_plan', 'list_comments', 'list_ideas', 'list_projects', 'list_templates', 'list_users', 'update_tasks'], $names);
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

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $response): array
    {
        $response->assertOk();
        $content = (string) ((fn (): array => $this->content())->call($response)[0] ?? '');

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
