<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Enums\Right;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Template;
use App\Models\User;
use App\Planer\TemplateSync;

/**
 * Templates (docs/auftrag-m3.md 3.3): everybody signed in reads them, those
 * with templates.manage make and change their own, built-in ones stay as they are.
 */
class TemplateTest extends GraphQLTestCase
{
    private const TEMPLATES = 'query { templates { id key name description isBuiltin locale viewerCanUpdate viewerCanDelete labels { key name category categoryLabel color } } }';

    protected function setUp(): void
    {
        parent::setUp();

        TemplateSync::sync();
    }

    /**
     * @return array<string, mixed>
     */
    private function label(string $de, ?string $en = null, string $color = 'RED', ?string $key = null): array
    {
        $names = [['locale' => 'DE', 'name' => $de]];

        if ($en !== null) {
            $names[] = ['locale' => 'EN', 'name' => $en];
        }

        return array_filter(['key' => $key, 'names' => $names, 'category' => null, 'color' => $color], fn ($value) => $value !== null);
    }

    private function builtin(string $key): Template
    {
        return Template::query()->where('key', $key)->firstOrFail();
    }

    private function own(string $creator, string $name = 'Eigene Vorlage'): Template
    {
        return Template::factory()->create(['name' => $name, 'created_by' => $this->users[$creator]->id]);
    }

    // ─── Built-in templates ─────────────────────────────────────

    public function test_built_in_templates_come_with_the_app_translated_by_the_request_language(): void
    {
        $german = $this->as('member')->withHeader('Accept-Language', 'de')->gql(self::TEMPLATES)->json('data.templates');

        $this->assertSame(['Softwareentwicklung', 'Event', 'Podcast'], array_column($german, 'name'));
        $this->assertTrue($german[0]['isBuiltin']);
        $this->assertSame('software-development', $german[0]['key']);
        $this->assertSame(['key' => 'bug', 'name' => 'Fehler', 'category' => 'type', 'categoryLabel' => 'Typ', 'color' => 'RED'], $german[0]['labels'][3]);

        $english = $this->as('member')->withHeader('Accept-Language', 'en')->gql(self::TEMPLATES)->json('data.templates');

        $this->assertSame('Software development', $english[0]['name']);
        $this->assertSame(['key' => 'bug', 'name' => 'Bug', 'category' => 'type', 'categoryLabel' => 'Type', 'color' => 'RED'], $english[0]['labels'][3]);
    }

    public function test_the_outline_of_a_template_is_there_to_look_at(): void
    {
        $outline = $this->as('member')->gql('query ($id: ID!) { template(id: $id) { outline { title version todos { title tasks { title labels } } } } }', ['id' => $this->builtin('event')->id])
            ->json('data.template.outline');

        $this->assertSame('Planung', $outline[0]['title']);
        $this->assertSame(['title' => 'Locations', 'tasks' => [['title' => 'Veranstaltungsort anfragen', 'labels' => ['location']]]], $outline[0]['todos'][0]);
    }

    public function test_nobody_changes_or_deletes_a_built_in_template(): void
    {
        $id = ['id' => $this->builtin('event')->id];

        $this->assertUnauthorized($this->as('admin')->gql('mutation ($id: ID!) { updateTemplate(id: $id, input: {name: "Neu", labels: [{names: [{locale: DE, name: "A"}], color: RED}]}) { id } }', $id));
        $this->assertUnauthorized($this->as('admin')->gql('mutation ($id: ID!) { deleteTemplate(id: $id) }', $id));
        $this->as('admin')->gql(self::TEMPLATES)->assertJsonPath('data.templates.1', fn (array $event) => $event['viewerCanUpdate'] === false && $event['viewerCanDelete'] === false);
    }

    // ─── Reading ────────────────────────────────────────────────

    public function test_everybody_signed_in_sees_all_templates_but_a_login_is_needed(): void
    {
        $this->graphQL(self::TEMPLATES)->assertGraphQLErrorMessage('Unauthenticated.');
        $this->own('projektleitung', 'Fremde Vorlage');

        foreach (['member', 'lesend', 'stranger', 'koordination'] as $who) {
            $this->assertSame(['Softwareentwicklung', 'Event', 'Podcast', 'Fremde Vorlage'], array_column($this->as($who)->withHeader('Accept-Language', 'de')->gql(self::TEMPLATES)->json('data.templates'), 'name'), $who);
        }
    }

    // ─── Managing own templates ─────────────────────────────────

    public function test_only_template_managers_create_templates(): void
    {
        $mutation = 'mutation ($in: TemplateInput!) { createTemplate(input: $in) { name locale isBuiltin viewerCanUpdate labels { key name names { locale name } category color } } }';
        $variables = ['in' => ['name' => 'Bau', 'description' => 'Für Baustellen', 'locale' => 'DE', 'labels' => [
            ['names' => [['locale' => 'DE', 'name' => 'Rohbau'], ['locale' => 'EN', 'name' => 'Shell']], 'category' => 'Phase', 'color' => 'ORANGE'],
            $this->label('Ausbau', null, 'BLUE'),
        ]]];

        foreach (['owner', 'manager', 'member', 'lesend', 'stranger', 'koordination'] as $who) {
            $this->assertUnauthorized($this->as($who)->gql($mutation, $variables));
        }

        $this->as('projektleitung')->withHeader('Accept-Language', 'de')->gql($mutation, $variables)->assertJsonPath('data.createTemplate', [
            'name' => 'Bau',
            'locale' => 'DE',
            'isBuiltin' => false,
            'viewerCanUpdate' => true,
            'labels' => [
                ['key' => 'rohbau', 'name' => 'Rohbau', 'names' => [['locale' => 'DE', 'name' => 'Rohbau'], ['locale' => 'EN', 'name' => 'Shell']], 'category' => 'Phase', 'color' => 'ORANGE'],
                ['key' => 'ausbau', 'name' => 'Ausbau', 'names' => [['locale' => 'DE', 'name' => 'Ausbau']], 'category' => null, 'color' => 'BLUE'],
            ],
        ]);

        $template = Template::query()->where('name', 'Bau')->sole();
        $this->assertSame($this->users['projektleitung']->id, $template->created_by);
        $this->assertSame([], $template->content['milestones']);
        $this->assertSame(0, $template->content['revision']);
        $this->assertSame('Für Baustellen', $template->content['project']['description']);
    }

    public function test_the_viewer_learns_their_system_rights(): void
    {
        $query = 'query { viewerRights }';

        $this->as('projektleitung')->gql($query)->assertJsonPath('data.viewerRights', [
            'PROJECTS_CREATE', 'TEMPLATES_MANAGE', 'ASSISTANT_USE', 'IDEAS_CREATE', 'SYSTEM_VIEW',
        ]);
        $this->as('lesend')->gql($query)->assertJsonPath('data.viewerRights', ['SYSTEM_VIEW']);
        $this->as('admin')->gql($query)->assertJsonCount(count(Right::ofScope(Right::SCOPE_SYSTEM)), 'data.viewerRights');
    }

    public function test_a_name_is_used_once(): void
    {
        $this->own('projektleitung', 'Doppelt');
        $mutation = 'mutation ($in: TemplateInput!) { createTemplate(input: $in) { id } }';

        $this->as('projektleitung')->gql($mutation, ['in' => ['name' => 'Doppelt', 'locale' => 'DE', 'labels' => [$this->label('Eins')]]])
            ->assertGraphQLValidationKeys(['input.name']);
    }

    public function test_duplicating_copies_a_template_with_its_outline_in_the_language_of_the_request(): void
    {
        $mutation = 'mutation ($id: ID!) { duplicateTemplate(id: $id) { name isBuiltin locale labels { key name } outline { title } } }';
        $id = ['id' => $this->builtin('software-development')->id];

        $this->assertUnauthorized($this->as('member')->gql($mutation, $id));

        $copy = $this->as('projektleitung')->withHeader('Accept-Language', 'de')->gql($mutation, $id)->json('data.duplicateTemplate');

        $this->assertSame('Kopie von Softwareentwicklung', $copy['name']);
        $this->assertFalse($copy['isBuiltin']);
        $this->assertSame(['key' => 'research', 'name' => 'Recherche'], $copy['labels'][0]);
        $this->assertNotEmpty($copy['outline']);

        $stored = Template::query()->where('name', 'Kopie von Softwareentwicklung')->sole();
        $this->assertNull($stored->key);
        $this->assertSame('Kopie von Softwareentwicklung', $stored->content['project']['name']);
        $this->assertSame($this->builtin('software-development')->content['milestones'], $stored->content['milestones']);

        // Stays as it was written, whatever language reads it later; a second copy gets a number.
        $this->as('projektleitung')->withHeader('Accept-Language', 'en')->gql($mutation, $id)
            ->assertJsonPath('data.duplicateTemplate.name', 'Copy of Software development');
        $this->as('projektleitung')->withHeader('Accept-Language', 'de')->gql($mutation, $id)
            ->assertJsonPath('data.duplicateTemplate.name', 'Kopie von Softwareentwicklung (2)');
    }

    public function test_only_the_creator_or_an_admin_changes_and_deletes_an_own_template(): void
    {
        $template = $this->own('projektleitung');
        $update = 'mutation ($id: ID!) { updateTemplate(id: $id, input: {name: "Umbenannt", labels: [{names: [{locale: DE, name: "Eins"}], color: RED}]}) { name } }';
        $delete = 'mutation ($id: ID!) { deleteTemplate(id: $id) }';
        $id = ['id' => $template->id];

        $this->assertUnauthorized($this->as('owner')->gql($update, $id));
        $this->assertUnauthorized($this->as('member')->gql($update, $id));

        $this->as('projektleitung')->gql($update, $id)->assertJsonPath('data.updateTemplate.name', 'Umbenannt');
        $this->as('admin')->gql(self::TEMPLATES)
            ->assertJsonPath('data.templates.3.viewerCanUpdate', true)
            ->assertJsonPath('data.templates.3.viewerCanDelete', true);

        $this->assertUnauthorized($this->as('member')->gql($delete, $id));
        $this->as('admin')->gql($delete, $id)->assertJsonPath('data.deleteTemplate', true);
        $this->assertModelMissing($template);
    }

    public function test_a_creator_who_loses_the_role_cannot_change_the_template(): void
    {
        $template = $this->own('projektleitung');
        $this->users['projektleitung']->update(['role' => 'mitglied']);

        $this->assertUnauthorized($this->as('projektleitung')->gql('mutation ($id: ID!) { updateTemplate(id: $id, input: {name: "X1", labels: [{names: [{locale: DE, name: "Eins"}], color: RED}]}) { id } }', ['id' => $template->id]));
    }

    public function test_update_keeps_renames_adds_and_removes_labels_and_keeps_the_keys(): void
    {
        $template = $this->own('projektleitung');
        $mutation = 'mutation ($id: ID!, $in: UpdateTemplateInput!) { updateTemplate(id: $id, input: $in) { labels { key name color } } }';

        $this->as('projektleitung')->withHeader('Accept-Language', 'de')->gql($mutation, ['id' => $template->id, 'in' => ['name' => 'Eigene Vorlage', 'labels' => [
            $this->label('Zwei'), $this->label('Drei', null, 'BLUE'),
        ]]])->assertJsonPath('data.updateTemplate.labels', [
            ['key' => 'zwei', 'name' => 'Zwei', 'color' => 'RED'],
            ['key' => 'drei', 'name' => 'Drei', 'color' => 'BLUE'],
        ]);

        // The key of "Zwei" stays when it is renamed; "Eins" came from the factory and is gone.
        $this->as('projektleitung')->withHeader('Accept-Language', 'de')->gql($mutation, ['id' => $template->id, 'in' => ['name' => 'Eigene Vorlage', 'labels' => [
            $this->label('Drei und mehr', 'Three', 'GREEN', 'drei'), $this->label('Zwei', null, 'RED', 'zwei'),
        ]]])->assertJsonPath('data.updateTemplate.labels', [
            ['key' => 'drei', 'name' => 'Drei und mehr', 'color' => 'GREEN'],
            ['key' => 'zwei', 'name' => 'Zwei', 'color' => 'RED'],
        ]);
    }

    public function test_the_outline_loses_the_labels_that_are_gone(): void
    {
        $copy = $this->as('projektleitung')->gql('mutation ($id: ID!) { duplicateTemplate(id: $id) { id } }', ['id' => $this->builtin('event')->id])->json('data.duplicateTemplate.id');

        $this->as('projektleitung')->gql('mutation ($id: ID!, $in: UpdateTemplateInput!) { updateTemplate(id: $id, input: $in) { id } }', ['id' => $copy, 'in' => [
            'name' => 'Event ohne Location', 'labels' => [$this->label('Speaker', null, 'CYAN', 'speakers')],
        ]])->assertJsonMissingPath('errors');

        $tasks = collect(Template::query()->findOrFail($copy)->outline())->pluck('todos')->flatten(1)->pluck('tasks')->flatten(1);
        $this->assertSame([['title' => 'Veranstaltungsort anfragen', 'labels' => []], ['title' => 'Speaker einladen', 'labels' => ['speakers']], ['title' => 'Ankündigung versenden', 'labels' => []]], $tasks->all());
    }

    public function test_labels_are_checked(): void
    {
        $template = $this->own('projektleitung');
        $mutation = 'mutation ($id: ID!, $in: UpdateTemplateInput!) { updateTemplate(id: $id, input: $in) { id } }';
        $update = fn (array $labels) => $this->as('projektleitung')->gql($mutation, ['id' => $template->id, 'in' => ['name' => 'Eigene Vorlage', 'labels' => $labels]]);

        // The same name twice in one language, also in another case.
        $update([$this->label('Bug'), $this->label('bug ')])->assertGraphQLValidationKeys(['input.labels.1.names.de']);
        // A key of no label of the template.
        $update([$this->label('Fremd', null, 'RED', 'gibt-es-nicht')])->assertGraphQLValidationKeys(['input.labels.0.key']);
        // A key twice.
        $update([$this->label('Eins', null, 'RED', 'one'), $this->label('Zwei', null, 'RED', 'one')])->assertGraphQLValidationKeys(['input.labels.1.key']);
        // No name in the language of the template.
        $update([['names' => [['locale' => 'EN', 'name' => 'Only English']], 'color' => 'RED']])->assertGraphQLValidationKeys(['input.labels.0.names.de']);
        // A language twice at one label.
        $update([['names' => [['locale' => 'DE', 'name' => 'Eins'], ['locale' => 'DE', 'name' => 'Zwei']], 'color' => 'RED']])->assertGraphQLValidationKeys(['input.labels.0.names.de']);
        // More than 30, and none at all.
        $update(array_map(fn (int $i) => $this->label("L{$i}"), range(1, 31)))->assertGraphQLValidationError('input.labels', 'At most 30 labels.');
        $update([])->assertGraphQLValidationKeys(['input.labels']);
    }

    public function test_deleting_a_template_leaves_the_projects_made_from_it_as_they_are(): void
    {
        $template = $this->own('projektleitung');
        $project = Project::factory()->withMember($this->users['owner'], ProjectRole::OWNER)->create();
        $project->forceFill(['template_id' => $template->id])->save();

        $this->as('projektleitung')->gql('mutation ($id: ID!) { deleteTemplate(id: $id) }', ['id' => $template->id])->assertJsonMissingPath('errors');

        $this->assertNull($project->fresh()->template_id);
        $this->assertModelExists($project);
    }

    public function test_the_system_roles_that_may_create_templates(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->can('create', Template::class));
        $this->assertFalse(User::factory()->create()->can('create', Template::class));
        $this->assertTrue(User::factory()->role(UserRole::PROJEKTLEITUNG)->create()->can('create', Template::class));
        $this->assertFalse(User::factory()->role(UserRole::KOORDINATION)->create()->can('create', Template::class));
    }

    public function test_creating_a_template_names_the_language_and_the_limit_at_the_right_place(): void
    {
        $mutation = 'mutation ($in: TemplateInput!) { createTemplate(input: $in) { id } }';
        $create = fn (array $labels, string $locale = 'DE') => $this->as('projektleitung')->gql($mutation, ['in' => ['name' => 'Neu', 'locale' => $locale, 'labels' => $labels]]);

        // No name in the language of the template: the field of that language.
        $create([$this->label('Nur Deutsch')], 'EN')->assertGraphQLValidationKeys(['input.labels.0.names.en']);
        // The same name twice in English.
        $create([$this->label('Eins', 'Same'), $this->label('Zwei', 'same')])->assertGraphQLValidationKeys(['input.labels.1.names.en']);
        // More than 30: one clear sentence, no field name in it.
        $create(array_map(fn (int $i) => $this->label("L{$i}"), range(1, 31)))->assertGraphQLValidationError('input.labels', 'At most 30 labels.');
    }
}
