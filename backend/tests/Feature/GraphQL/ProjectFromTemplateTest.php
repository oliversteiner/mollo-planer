<?php

namespace Tests\Feature\GraphQL;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Template;
use App\Planer\TemplateContent;
use App\Planer\TemplateSync;

/**
 * A project from a template (docs/auftrag-m3.md 3.3, 0a): the labels and the
 * outline are copied in the transaction that creates the project, in the
 * order of the template, as one change.
 */
class ProjectFromTemplateTest extends GraphQLTestCase
{
    private const CREATE = 'mutation ($in: CreateProjectInput!) { createProject(input: $in) { id name planerRevision template { name } labels { key names { locale name } position } milestones { title todos { title tasks { title status boardPosition labels { key } } } } } }';

    protected function setUp(): void
    {
        parent::setUp();

        TemplateSync::sync();
    }

    private function template(string $key): Template
    {
        return Template::query()->where('key', $key)->firstOrFail();
    }

    public function test_the_project_gets_the_labels_and_the_outline_of_the_template(): void
    {
        $project = $this->as('admin')->gql(self::CREATE, ['in' => ['name' => 'Neu', 'templateId' => $this->template('software-development')->id]])
            ->assertJsonMissingPath('errors')
            ->json('data.createProject');

        $this->assertSame('Software development', $project['template']['name'] ?? null, 'where it came from (the tests answer in English)');
        $this->assertSame(['research', 'design', 'feature', 'bug', 'planning', 'blocked'], array_column($project['labels'], 'key'));
        $this->assertSame([0, 1, 2, 3, 4, 5], array_column($project['labels'], 'position'));
        $this->assertSame([['locale' => 'DE', 'name' => 'Fehler'], ['locale' => 'EN', 'name' => 'Bug']], $project['labels'][3]['names']);

        $this->assertSame(['Konzept', 'Erste Version'], array_column($project['milestones'], 'title'));
        $this->assertSame(['Ziele und Nutzer klären', 'Anforderungen festhalten'], array_column($project['milestones'][0]['todos'][0]['tasks'], 'title'));
        $this->assertSame([['key' => 'research']], $project['milestones'][0]['todos'][0]['tasks'][0]['labels']);
    }

    public function test_the_tasks_are_planned_one_after_the_other_in_that_column(): void
    {
        $id = $this->as('admin')->gql(self::CREATE, ['in' => ['name' => 'Neu', 'templateId' => $this->template('software-development')->id]])->json('data.createProject.id');

        $tasks = Task::query()->where('project_id', $id)->orderBy('board_position')->get();

        $this->assertSame(4, $tasks->count());
        $this->assertSame(['planned'], $tasks->pluck('status')->map->value->unique()->values()->all());
        $this->assertSame([0, 1, 2, 3], $tasks->pluck('board_position')->all());
        $this->assertSame(['Ziele und Nutzer klären', 'Anforderungen festhalten', 'Kernfunktion umsetzen', 'Fehler aus den Tests beheben'], $tasks->pluck('title')->all());
        $this->assertSame([0, 1], Milestone::query()->where('project_id', $id)->orderBy('position')->pluck('position')->all());
    }

    public function test_creating_from_a_template_is_one_change_of_the_planer_file(): void
    {
        $this->as('admin')->gql(self::CREATE, ['in' => ['name' => 'Neu', 'templateId' => $this->template('event')->id]])
            ->assertJsonPath('data.createProject.planerRevision', 1);
    }

    public function test_without_a_template_nothing_is_copied(): void
    {
        $project = $this->as('admin')->gql(self::CREATE, ['in' => ['name' => 'Leer']])->json('data.createProject');

        $this->assertNull($project['template']);
        $this->assertSame([], $project['labels']);
        $this->assertSame([], $project['milestones']);
    }

    public function test_an_unknown_template_is_refused_and_nothing_is_created(): void
    {
        $before = Project::query()->count();

        $this->as('admin')->gql(self::CREATE, ['in' => ['name' => 'Neu', 'templateId' => '9999']])->assertGraphQLValidationKeys(['input.templateId']);

        $this->assertSame($before, Project::query()->count());
    }

    public function test_the_description_of_the_template_is_used_when_the_project_has_none(): void
    {
        $description = 'query ($id: ID!) { project(id: $id) { description } }';
        $id = $this->as('admin')->gql(self::CREATE, ['in' => ['name' => 'Eins', 'templateId' => $this->template('podcast')->id]])->json('data.createProject.id');
        $text = $this->as('admin')->gql($description, ['id' => $id])->json('data.project.description.content.0.content.0.text');

        $this->assertSame('Vorlage für eine Podcast-Produktion: von der Themenidee bis zur Veröffentlichung.', $text);

        $own = $this->doc('Eigene Beschreibung');
        $id = $this->as('admin')->gql(self::CREATE, ['in' => ['name' => 'Zwei', 'description' => $own, 'templateId' => $this->template('podcast')->id]])->json('data.createProject.id');
        $this->as('admin')->gql($description, ['id' => $id])->assertJsonPath('data.project.description', $own);
    }

    public function test_the_project_takes_all_languages_and_a_missing_one_from_the_template(): void
    {
        $content = TemplateContent::blank('Nur Deutsch', null, 'de', [['key' => 'eins', 'names' => ['de' => 'Eins'], 'category' => null, 'color' => 'blue']]);
        $template = Template::factory()->create(['content' => $content]);

        $project = $this->as('admin')->gql(self::CREATE, ['in' => ['name' => 'Englisch', 'locale' => 'EN', 'templateId' => $template->id]])->json('data.createProject');

        $this->assertSame([['locale' => 'DE', 'name' => 'Eins'], ['locale' => 'EN', 'name' => 'Eins']], $project['labels'][0]['names']);
    }

    public function test_a_description_that_is_not_valid_makes_nothing(): void
    {
        $content = TemplateContent::blank('Kaputt', null, 'de', [['key' => 'eins', 'names' => ['de' => 'Eins'], 'category' => null, 'color' => 'blue']]);
        $content['milestones'] = [['id' => 'm-new-1', 'title' => 'Eins', 'description' => '<b>HTML</b>', 'todos' => []]];
        $template = Template::factory()->create(['content' => $content]);
        $before = Project::query()->count();

        $this->as('admin')->gql(self::CREATE, ['in' => ['name' => 'Neu', 'templateId' => $template->id]])->assertGraphQLValidationKeys(['input.templateId']);

        $this->assertSame($before, Project::query()->count());
        $this->assertSame(0, Milestone::query()->count() - 1, 'only the milestone of the test setup');
    }

    public function test_descriptions_of_the_template_become_rich_text(): void
    {
        $content = TemplateContent::blank('Mit Text', null, 'de', [['key' => 'eins', 'names' => ['de' => 'Eins'], 'category' => null, 'color' => 'blue']]);
        $content['milestones'] = [['id' => 'm-new-1', 'title' => 'Erster', 'description' => "Text mit **fett**.\n\n- eins\n- zwei", 'release' => ['version' => '1.0', 'status' => 'planned'], 'todos' => [
            ['id' => 't-new-1', 'title' => 'Todo', 'tasks' => [['id' => 'k-new-1', 'title' => 'Task', 'status' => 'planned', 'labels' => ['eins'], 'description' => 'Beschreibung']]],
        ]]];
        $template = Template::factory()->create(['content' => $content]);

        $id = $this->as('admin')->gql(self::CREATE, ['in' => ['name' => 'Neu', 'templateId' => $template->id]])->assertJsonMissingPath('errors')->json('data.createProject.id');

        $milestone = Milestone::query()->where('project_id', $id)->sole();
        $this->assertSame('bulletList', $milestone->description['content'][1]['type']);
        $this->assertSame(['bold'], array_column($milestone->description['content'][0]['content'][1]['marks'], 'type'));
        $this->assertSame('1.0', $milestone->release_version);
        $this->assertSame('planned', $milestone->release_status->value);
        $this->assertSame(['eins'], Task::query()->where('project_id', $id)->sole()->labels()->pluck('key')->all());
    }

    public function test_a_template_that_is_not_a_valid_file_is_refused_and_nothing_is_created(): void
    {
        $content = TemplateContent::blank('Kaputt', null, 'de', [['key' => 'eins', 'names' => ['de' => 'Eins'], 'category' => null, 'color' => 'blue']]);
        $content['milestones'] = [['id' => 'm-new-1', 'title' => 'Erster', 'todos' => [['id' => 't-new-1', 'title' => 'Todo', 'tasks' => [['id' => 'k-new-1', 'title' => 'Task', 'status' => 'planned', 'labels' => ['gibt-es-nicht']]]]]]];
        $template = Template::factory()->create(['content' => $content]);
        $before = Project::query()->count();

        $this->as('admin')->gql(self::CREATE, ['in' => ['name' => 'Neu', 'templateId' => $template->id]])->assertGraphQLValidationKeys(['input.templateId']);

        $this->assertSame($before, Project::query()->count());
    }
}
