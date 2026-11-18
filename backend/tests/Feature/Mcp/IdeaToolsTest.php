<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\MolloPlanerServer;
use App\Mcp\Tools\CreateIdeaTool;
use App\Mcp\Tools\ListIdeasTool;
use App\Models\Idea;

/**
 * create_idea and list_ideas (docs/auftrag-m6.md §2.4): the same rights as the app, Markdown through the lenient reader.
 */
class IdeaToolsTest extends McpTestCase
{
    public function test_create_idea_saves_a_private_idea_with_a_markdown_description(): void
    {
        MolloPlanerServer::actingAs($this->member)->tool(CreateIdeaTool::class, ['title' => 'Sponsoring prüfen', 'description' => "Wir sollten **früh** fragen.\n\n- Banken\n- Vereine", 'project_id' => (string) $this->project->id])
            ->assertOk()
            ->assertSee('Sponsoring prüfen');

        $idea = Idea::query()->sole();
        $this->assertSame($this->member->id, $idea->user_id);
        $this->assertSame($this->project->id, $idea->project_id);
        $this->assertSame('bulletList', $idea->description['content'][1]['type']);
    }

    public function test_what_the_editor_cannot_hold_becomes_text_with_a_warning(): void
    {
        MolloPlanerServer::actingAs($this->member)->tool(CreateIdeaTool::class, ['title' => 'Mit Tabelle', 'description' => "| a | b |\n|---|---|\n| 1 | 2 |"])
            ->assertOk()
            ->assertSee('warnings');

        $this->assertSame(1, Idea::query()->count());
    }

    public function test_a_project_the_person_does_not_see_is_refused_and_nothing_is_saved(): void
    {
        MolloPlanerServer::actingAs($this->stranger)->tool(CreateIdeaTool::class, ['title' => 'Fremdes Projekt', 'project_id' => (string) $this->project->id])->assertHasErrors();

        $this->assertSame(0, Idea::query()->count());
    }

    public function test_list_ideas_shows_only_the_own_open_ones_by_default(): void
    {
        Idea::factory()->create(['user_id' => $this->member->id, 'title' => 'Meine offene']);
        Idea::factory()->create(['user_id' => $this->member->id, 'title' => 'Meine verworfene', 'status' => 'discarded']);
        Idea::factory()->create(['user_id' => $this->owner->id, 'title' => 'Fremde']);

        MolloPlanerServer::actingAs($this->member)->tool(ListIdeasTool::class)->assertOk()->assertSee('Meine offene')->assertDontSee('Meine verworfene')->assertDontSee('Fremde');
        MolloPlanerServer::actingAs($this->member)->tool(ListIdeasTool::class, ['status' => 'discarded'])->assertOk()->assertSee('Meine verworfene');
    }
}
