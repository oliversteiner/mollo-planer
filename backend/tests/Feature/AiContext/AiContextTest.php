<?php

namespace Tests\Feature\AiContext;

use App\Mcp\Servers\MolloPlanerServer;
use App\Mcp\Tools\GetProjectAiContextTool;
use App\Models\ProjectAiContext;
use App\Planer\PlanerLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\Feature\GraphQL\GraphQLTestCase;

/**
 * The AI context of a project (docs/auftrag-m6.md §3): limit over text and file together, upload rules,
 * rights, the archive, and the release for external tools.
 */
class AiContextTest extends GraphQLTestCase
{
    private const SET = 'mutation ($p: ID!, $in: ProjectAiContextInput!) { setProjectAiContext(projectId: $p, input: $in) { aiContext { text fileName fileText characters sharedWithTools updatedBy { id } } } }';

    private const UPLOAD = 'mutation ($p: ID!, $file: Upload!) { uploadProjectAiContextFile(projectId: $p, file: $file) { aiContext { fileName fileText characters } } }';

    private function upload(string $actor, UploadedFile $file): TestResponse
    {
        $this->as($actor);

        return $this->multipartGraphQL(
            ['query' => self::UPLOAD, 'variables' => ['p' => $this->project->id, 'file' => null]],
            ['0' => ['variables.file']],
            ['0' => $file],
            ['Referer' => 'http://localhost'],
        );
    }

    private function file(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_the_manager_sets_the_text_and_everybody_in_the_project_reads_it(): void
    {
        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['text' => "Sie-Form.\r\nBudget max. 2000 CHF.", 'sharedWithTools' => false]])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.setProjectAiContext.aiContext.text', "Sie-Form.\nBudget max. 2000 CHF.")
            ->assertJsonPath('data.setProjectAiContext.aiContext.updatedBy.id', (string) $this->users['manager']->id);

        $query = 'query ($id: ID!) { project(id: $id) { aiContext { text sharedWithTools } } }';
        $this->as('member')->gql($query, ['id' => $this->project->id])->assertJsonPath('data.project.aiContext.text', "Sie-Form.\nBudget max. 2000 CHF.");
        $this->as('stranger')->gql($query, ['id' => $this->project->id])->assertJsonPath('data.project', null);
    }

    public function test_without_a_context_the_field_is_null(): void
    {
        $this->as('member')->gql('query ($id: ID!) { project(id: $id) { aiContext { text } } }', ['id' => $this->project->id])->assertJsonPath('data.project.aiContext', null);
    }

    public function test_only_those_with_the_right_change_it(): void
    {
        $this->assertUnauthorized($this->upload('member', $this->file('a.md', 'x')));
        $this->assertUnauthorized($this->as('member')->gql(self::SET, ['p' => $this->project->id, 'in' => ['text' => 'Nein']]));
        $this->assertUnauthorized($this->as('member')->gql('mutation ($p: ID!) { clearProjectAiContext(projectId: $p) { id } }', ['p' => $this->project->id]));
        $this->assertUnauthorized($this->as('stranger')->gql(self::SET, ['p' => $this->project->id, 'in' => ['text' => 'Nein']]));
        $this->assertSame(0, ProjectAiContext::query()->count());
    }

    public function test_text_and_file_together_are_limited_to_twenty_thousand_characters(): void
    {
        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['text' => str_repeat('ä', 12000)]])->assertJsonMissingPath('errors');

        $this->upload('manager', $this->file('wissen.md', str_repeat('b', 8000)))->assertJsonMissingPath('errors')->assertJsonPath('data.uploadProjectAiContextFile.aiContext.characters', 20000);

        $this->upload('manager', $this->file('mehr.md', str_repeat('b', 8001)))->assertGraphQLValidationKeys(['file']);
        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['text' => str_repeat('ä', 12001)]])->assertGraphQLValidationKeys(['input.text']);
        $this->assertSame(20000, ProjectAiContext::query()->sole()->characters, 'nothing changed');
        $this->assertSame(20000, PlanerLimits::AI_CONTEXT_CHARACTERS);
    }

    public function test_a_file_is_txt_or_md_utf8_and_small(): void
    {
        $this->upload('manager', $this->file('wissen.pdf', 'Text'))->assertGraphQLValidationKeys(['file']);
        $this->upload('manager', $this->file('wissen.txt', "caf\xE9"))->assertGraphQLValidationKeys(['file']);
        $this->upload('manager', $this->file('gross.txt', str_repeat('x', 102_401)))->assertGraphQLValidationKeys(['file']);
        $this->assertSame(0, ProjectAiContext::query()->count());

        $this->upload('manager', $this->file('Notizen.TXT', "\xEF\xBB\xBFZeile eins\r\nZeile zwei\rDrei"))
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.uploadProjectAiContextFile.aiContext.fileName', 'Notizen.TXT')
            ->assertJsonPath('data.uploadProjectAiContextFile.aiContext.fileText', "Zeile eins\nZeile zwei\nDrei");
    }

    public function test_a_new_file_replaces_the_old_one_and_removing_it_keeps_the_text(): void
    {
        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['text' => 'Bleibt']]);
        $this->upload('manager', $this->file('eins.md', 'Erste'));
        $this->upload('manager', $this->file('zwei.md', 'Zweite'))->assertJsonPath('data.uploadProjectAiContextFile.aiContext.fileText', 'Zweite');

        $this->as('manager')->gql('mutation ($p: ID!) { removeProjectAiContextFile(projectId: $p) { aiContext { text fileName fileText } } }', ['p' => $this->project->id])
            ->assertJsonPath('data.removeProjectAiContextFile.aiContext', ['text' => 'Bleibt', 'fileName' => null, 'fileText' => null]);

        $this->as('manager')->gql('mutation ($p: ID!) { clearProjectAiContext(projectId: $p) { aiContext { text } } }', ['p' => $this->project->id])->assertJsonPath('data.clearProjectAiContext.aiContext', null);
        $this->assertSame(0, ProjectAiContext::query()->count());
    }

    public function test_an_archived_project_refuses_every_change(): void
    {
        $this->project->forceFill(['archived_at' => now()])->save();

        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['text' => 'Nein']])->assertGraphQLErrorMessage(__('projects.archived'));
        $this->as('manager')->gql('mutation ($p: ID!) { clearProjectAiContext(projectId: $p) { id } }', ['p' => $this->project->id])->assertGraphQLErrorMessage(__('projects.archived'));
        $this->upload('manager', $this->file('a.md', 'x'))->assertGraphQLErrorMessage(__('projects.archived'));
        $this->assertSame(0, ProjectAiContext::query()->count());
    }

    public function test_the_context_is_no_change_of_the_plan(): void
    {
        $before = (int) $this->project->fresh()->planer_revision;

        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['text' => 'Still']]);
        $this->upload('manager', $this->file('a.md', 'Datei'));

        $this->assertSame($before, (int) $this->project->fresh()->planer_revision);
    }

    // ─── MCP ─────────────────────────────────────────────────────────

    public function test_the_tool_gives_the_context_only_when_it_is_shared_and_marks_it_as_background(): void
    {
        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['text' => 'Sie-Form verwenden.', 'sharedWithTools' => false]]);
        $call = fn (string $who) => MolloPlanerServer::actingAs($this->users[$who])->tool(GetProjectAiContextTool::class, ['project_id' => (string) $this->project->id]);

        $call('member')->assertOk()->assertSee(__('ai_context.not_shared'))->assertDontSee('Sie-Form');

        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['text' => 'Sie-Form verwenden.', 'sharedWithTools' => true]]);
        $this->upload('manager', $this->file('wissen.md', 'Budget 2000 CHF'));

        $call('member')->assertOk()->assertSee(__('ai_context.label'))->assertSee('Sie-Form verwenden.')->assertSee('wissen.md')->assertSee('Budget 2000 CHF');
    }

    public function test_the_tool_has_the_read_rights_of_the_project(): void
    {
        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'in' => ['text' => 'Geheim', 'sharedWithTools' => true]]);

        MolloPlanerServer::actingAs($this->users['stranger'])->tool(GetProjectAiContextTool::class, ['project_id' => (string) $this->project->id])
            ->assertHasErrors(['This project does not exist, or you have no access to it.']);
    }
}
