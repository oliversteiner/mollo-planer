<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\MolloPlanerServer;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\McpTools;

/** Every tool call of an AI client is noted (tool, account, characters, time; no content), and app:mcp-usage adds it up. */
class McpUsageTest extends McpTestCase
{
    public function test_a_call_is_noted_without_its_content(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(McpTools::of('list_projects'))->assertOk();

        $row = DB::table('mcp_tool_calls')->sole();
        $this->assertSame('list_projects', $row->tool);
        $this->assertSame($this->owner->id, (int) $row->user_id);
        $this->assertGreaterThan(10, $row->chars);
        $this->assertTrue((bool) $row->ok);
        $this->assertGreaterThanOrEqual(0, $row->duration_ms);
        $this->assertSame(['id', 'tool', 'user_id', 'chars', 'duration_ms', 'ok', 'created_at'], array_keys((array) $row), 'no arguments, no answer');
    }

    public function test_a_failed_call_is_noted_as_failed(): void
    {
        MolloPlanerServer::actingAs($this->stranger)->tool(McpTools::of('list_tasks'), ['project_id' => (string) $this->project->id])->assertHasErrors();

        $this->assertFalse((bool) DB::table('mcp_tool_calls')->where('tool', 'list_tasks')->sole()->ok);
    }

    public function test_the_command_adds_up_per_tool_and_per_account(): void
    {
        $agent = User::factory()->agent()->create(['name' => 'Claude Club DB']);
        MolloPlanerServer::actingAs($this->owner)->tool(McpTools::of('list_projects'));
        MolloPlanerServer::actingAs($this->owner)->tool(McpTools::of('list_projects'));
        MolloPlanerServer::actingAs($agent)->tool(McpTools::of('list_templates'));
        DB::table('mcp_tool_calls')->insert(['tool' => 'old_tool', 'user_id' => $this->owner->id, 'chars' => 5, 'duration_ms' => 1, 'ok' => true, 'created_at' => now()->subDays(30)]);

        $this->assertSame(0, Artisan::call('app:mcp-usage'));
        $output = Artisan::output();

        $this->assertStringContainsString('list_projects', $output);
        $this->assertStringContainsString('list_templates', $output);
        $this->assertStringContainsString('Claude Club DB', $output);
        $this->assertStringNotContainsString('old_tool', $output, 'only the last seven days');
    }

    public function test_the_command_says_so_when_there_is_nothing(): void
    {
        $this->artisan('app:mcp-usage')->expectsOutputToContain(__('console.mcp_usage.none', ['days' => 7]))->assertSuccessful();
    }
}
