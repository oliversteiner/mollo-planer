<?php

namespace Tests\Feature\GraphQL;

use App\Models\Label;
use App\Models\Template;
use App\Planer\Canonical;
use App\Planer\Export;
use App\Planer\TemplateContent;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Templates from a planer file (docs/auftrag-m4.md §7): from a project, from an
 * uploaded file, and the file of a template.
 */
class TemplateFromPlanTest extends GraphQLTestCase
{
    private const FROM_PROJECT = 'mutation ($p: ID!, $n: String!) { createTemplateFromProject(projectId: $p, name: $n) { id name locale file outline { title todos { title tasks { title labels } } } } }';

    private const FROM_FILE = 'mutation ($d: String!) { createTemplateFromFile(document: $d) { id name file } }';

    private function withLabel(): void
    {
        $label = Label::factory()->for($this->project)->create(['key' => 'eins', 'names' => ['de' => 'Eins', 'en' => 'One'], 'color' => 'blue']);
        $this->task->labels()->attach($label);
        $this->task->forceFill(['status' => 'done', 'commit' => 'abc1234', 'assigned_user_id' => $this->users['member']->id, 'due_date' => '2026-12-01'])->save();
    }

    /** @return list<array{string}> */
    public static function fixtureNames(): array
    {
        return [['planer-valid'], ['beispiel-planer'], ['planer-projekt'], ['planer-leer']];
    }

    #[DataProvider('fixtureNames')]
    public function test_from_document_is_what_tools_planer_makes(string $name): void
    {
        $document = json_decode((string) file_get_contents(base_path("../spec/fixtures/{$name}.planer.json")), true, 512, JSON_THROW_ON_ERROR);
        unset($document['$schema']);

        $this->assertSame(
            (string) file_get_contents(base_path("../spec/fixtures/expected/{$name}.template.json")),
            Canonical::text(TemplateContent::fromDocument($document)),
        );
    }

    public function test_a_project_becomes_a_template_with_the_fixed_values(): void
    {
        $this->withLabel();

        $response = $this->as('admin')->gql(self::FROM_PROJECT, ['p' => $this->project->id, 'n' => 'Aus Projekt'])->assertJsonMissingPath('errors');

        $template = Template::query()->where('name', 'Aus Projekt')->sole();
        $content = $template->content;
        $this->assertSame('planer-v2', $content['format']);
        $this->assertSame(0, $content['revision']);
        $this->assertSame([], $content['project']['members']);
        $task = $content['milestones'][0]['todos'][0]['tasks'][0];
        $this->assertSame(['id' => 'k-new-1', 'title' => $this->task->title, 'status' => 'planned', 'labels' => ['eins']], $task);
        $this->assertSame([], TemplateContent::problems($content));
        $this->assertSame($template->creator->id, $this->users['admin']->id);
        $this->assertSame(Canonical::text($content), $response->json('data.createTemplateFromProject.file'));
        $this->assertSame(['eins'], $response->json('data.createTemplateFromProject.outline.0.todos.0.tasks.0.labels'));
    }

    public function test_a_project_without_labels_is_refused(): void
    {
        $this->as('admin')->gql(self::FROM_PROJECT, ['p' => $this->project->id, 'n' => 'Ohne Labels'])->assertGraphQLValidationKeys(['name']);

        $this->assertNull(Template::query()->where('name', 'Ohne Labels')->first());
    }

    public function test_the_name_must_be_free_and_the_rights_are_needed(): void
    {
        $this->withLabel();
        $this->as('admin')->gql(self::FROM_PROJECT, ['p' => $this->project->id, 'n' => 'Doppelt'])->assertJsonMissingPath('errors');
        $this->as('admin')->gql(self::FROM_PROJECT, ['p' => $this->project->id, 'n' => 'Doppelt'])->assertGraphQLValidationKeys(['name']);

        $this->assertUnauthorized($this->as('member')->gql(self::FROM_PROJECT, ['p' => $this->project->id, 'n' => 'Von Mitglied']));
        $this->assertUnauthorized($this->as('stranger')->gql(self::FROM_PROJECT, ['p' => $this->project->id, 'n' => 'Von Fremdem']));
    }

    public function test_an_uploaded_file_becomes_a_template_named_like_its_project(): void
    {
        $this->withLabel();
        $document = app(Export::class)->document($this->project->fresh());

        $this->as('admin')->gql(self::FROM_FILE, ['d' => json_encode($document)])->assertJsonMissingPath('errors')->assertJsonPath('data.createTemplateFromFile.name', 'Mollo Planer');

        $content = Template::query()->where('name', 'Mollo Planer')->sole()->content;
        $this->assertSame([], TemplateContent::problems($content));
        $this->assertSame([], $content['project']['members']);
    }

    public function test_an_invalid_file_is_refused_with_its_path(): void
    {
        $this->as('admin')->gql(self::FROM_FILE, ['d' => '{"format":"planer-v2"}'])->assertGraphQLValidationKeys(['document']);
        $this->as('admin')->gql(self::FROM_FILE, ['d' => 'kaputt'])->assertGraphQLValidationKeys(['document']);
        $this->assertUnauthorized($this->as('member')->gql(self::FROM_FILE, ['d' => '{}']));
    }
}
