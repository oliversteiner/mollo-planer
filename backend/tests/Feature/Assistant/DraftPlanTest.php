<?php

namespace Tests\Feature\Assistant;

use App\Assistant\AssistantError;
use App\Assistant\AssistantSchema;
use App\Assistant\ClaudeClient;
use App\Enums\ProjectRole;
use App\Models\Idea;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Template;
use App\Models\Todo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Mockery\MockInterface;
use Tests\Feature\GraphQL\GraphQLTestCase;

/**
 * draftPlan (docs/auftrag-m6.md §4): the answer of Claude is faked, the server's work around it is real.
 */
class DraftPlanTest extends GraphQLTestCase
{
    private const DRAFT = 'mutation ($p: ID, $t: ID, $plan: String, $idea: ID, $text: String!) {
        draftPlan(messages: [{role: USER, content: $text}], projectId: $p, templateId: $t, currentPlan: $plan, ideaId: $idea) {
            reply plan projectId hints
            choices { kind label detail value recommended }
            preview { ok changes { kind path } errors { kind path } }
        }
    }';

    private function draft(string $as, array $variables = [])
    {
        return $this->as($as)->gql(self::DRAFT, ['text' => "Ergänze etwas in m-{$this->milestone->id} und t-{$this->todo->id}", ...$variables]);
    }

    /** @var array{system: string, messages: array}|null */
    private ?array $sent = null;

    /** @param array<string, mixed> $answer */
    private function claude(array $answer): void
    {
        $this->partialMock(ClaudeClient::class, function (MockInterface $mock) use ($answer): void {
            $mock->shouldReceive('ask')->andReturnUsing(function (string $system, array $messages) use ($answer): array {
                $this->sent = ['system' => $system, 'messages' => $messages];

                return $answer;
            });
        });
    }

    /** @return array<string, mixed> */
    private function answer(?array $plan = null, array $choices = []): array
    {
        return ['reply' => 'Erledigt.', 'choices' => $choices, 'plan' => $plan];
    }

    /** @return array<string, mixed> */
    private function todoPlan(string $milestoneId, array $task = []): array
    {
        return [
            'project' => ['name' => null, 'description' => null, 'locale' => null, 'members' => [], 'labels' => []],
            'milestones' => [[
                'id' => $milestoneId, 'title' => 'egal', 'description' => null, 'dueDate' => null, 'release' => null,
                'todos' => [[
                    'id' => 'x', 'title' => 'Neues ToDo', 'description' => "Erste Zeile\n\n# Titel", 'dueDate' => '2027-02-30',
                    'tasks' => [[...['id' => 'y', 'title' => 'Neuer Task', 'description' => null, 'dueDate' => '2027-03-01', 'status' => null, 'assigneeEmail' => null, 'labels' => [], 'userStory' => null], ...$task]],
                ]],
            ]],
        ];
    }

    public function test_a_todo_in_an_existing_milestone_gives_a_preview_with_server_ids_and_saves_nothing(): void
    {
        $this->claude($this->answer($this->todoPlan("m-{$this->milestone->id}")));
        $before = Todo::query()->count();

        $result = $this->draft('manager', ['p' => $this->project->id])->assertJsonMissingPath('errors')->json('data.draftPlan');

        $this->assertSame('Erledigt.', $result['reply']);
        $this->assertTrue($result['preview']['ok']);
        $this->assertSame(['CREATE', 'CREATE'], array_column($result['preview']['changes'], 'kind'));
        $plan = json_decode($result['plan'], true);
        $this->assertSame("m-{$this->milestone->id}", $plan['milestones'][0]['id']);
        $this->assertSame('t-new-1', $plan['milestones'][0]['todos'][1]['id'] ?? $plan['milestones'][0]['todos'][count($plan['milestones'][0]['todos']) - 1]['id']);
        $this->assertSame($before, Todo::query()->count());
        $this->assertStringContainsString("m-{$this->milestone->id}", $this->sent['system']);
    }

    public function test_a_milestone_chosen_silently_gives_a_choice_instead_of_a_plan(): void
    {
        $other = Milestone::factory()->for($this->project)->create(['title' => 'Go-live']);
        $this->claude($this->answer($this->todoPlan("m-{$this->milestone->id}")));

        $result = $this->draft('manager', ['p' => $this->project->id, 'text' => 'Kontaktformular absichern bis Ende Dezember'])->json('data.draftPlan');

        $this->assertNull($result['plan']);
        $this->assertNull($result['preview']);
        $this->assertSame("m-{$this->milestone->id}", $result['choices'][0]['value']);
        $this->assertTrue($result['choices'][0]['recommended']);
        $this->assertContains("m-{$other->id}", array_column($result['choices'], 'value'));
        $this->assertContains('IDEA', array_column($result['choices'], 'kind'));
    }

    public function test_a_milestone_named_by_title_or_by_button_gives_the_plan(): void
    {
        $this->claude($this->answer($this->todoPlan("m-{$this->milestone->id}")));

        $byTitle = $this->draft('manager', ['p' => $this->project->id, 'text' => 'Plakate im Milestone '.strtoupper($this->milestone->title)])->json('data.draftPlan');
        $byButton = $this->draft('manager', ['p' => $this->project->id, 'text' => "Ich wähle: Irgendwas (m-{$this->milestone->id})"])->json('data.draftPlan');

        $this->assertNotNull($byTitle['plan']);
        $this->assertNotNull($byButton['plan']);
    }

    public function test_a_new_task_in_a_todo_nobody_named_gives_a_choice_of_todos(): void
    {
        $document = $this->todoPlan("m-{$this->milestone->id}");
        $document['milestones'][0]['todos'] = [['id' => "t-{$this->todo->id}", 'title' => 'egal', 'description' => null, 'dueDate' => null, 'tasks' => [['id' => 'k', 'title' => 'Neuer Task', 'description' => null, 'dueDate' => null, 'status' => null, 'assigneeEmail' => null, 'labels' => [], 'userStory' => null]]]];
        $this->claude($this->answer($document));

        $result = $this->draft('manager', ['p' => $this->project->id, 'text' => "Neuer Task im Milestone m-{$this->milestone->id}"])->json('data.draftPlan');

        $this->assertNull($result['plan']);
        $this->assertSame(['APPEND_TO_TODO'], array_unique(array_column($result['choices'], 'kind')));
        $this->assertSame("t-{$this->todo->id}", $result['choices'][0]['value']);
    }

    public function test_dates_are_checked_and_descriptions_read_leniently_with_hints(): void
    {
        $this->claude($this->answer($this->todoPlan("m-{$this->milestone->id}")));

        $result = $this->draft('manager', ['p' => $this->project->id])->json('data.draftPlan');
        $todo = collect(json_decode($result['plan'], true)['milestones'][0]['todos'])->firstWhere('title', 'Neues ToDo');

        $this->assertArrayNotHasKey('dueDate', $todo);
        $this->assertNotEmpty($result['hints']);
    }

    public function test_an_unknown_anchor_is_named_by_the_preview(): void
    {
        $this->claude($this->answer($this->todoPlan('m-999999')));

        $result = $this->draft('manager', ['p' => $this->project->id])->json('data.draftPlan');

        $this->assertFalse($result['preview']['ok']);
    }

    public function test_a_plan_of_null_gives_only_the_answer_and_cleaned_choices(): void
    {
        $this->claude($this->answer(null, [
            ['kind' => 'MILESTONE', 'label' => 'Start', 'detail' => null, 'value' => "m-{$this->milestone->id}", 'recommended' => true],
            ['kind' => 'MILESTONE', 'label' => 'Gibt es nicht', 'detail' => null, 'value' => 'm-999999', 'recommended' => false],
            ['kind' => 'PROJECT', 'label' => 'Fremd', 'detail' => null, 'value' => (string) Project::factory()->create()->id, 'recommended' => true],
            ['kind' => 'NEW_MILESTONE', 'label' => 'Neu', 'detail' => null, 'value' => null, 'recommended' => true],
            ['kind' => 'IDEA', 'label' => 'Idee', 'detail' => null, 'value' => null, 'recommended' => false],
        ]));

        $result = $this->draft('manager', ['p' => $this->project->id])->json('data.draftPlan');

        $this->assertNull($result['plan']);
        $this->assertNull($result['preview']);
        $this->assertSame(['MILESTONE', 'NEW_MILESTONE', 'IDEA'], array_column($result['choices'], 'kind'));
        $this->assertSame([true, false, false], array_column($result['choices'], 'recommended'));
    }

    public function test_the_prompt_lists_visible_active_projects_and_marks_todo_creation_rights_even_when_one_is_selected(): void
    {
        $guestProject = Project::factory()->withMember($this->users['manager'], ProjectRole::GUEST)->create(['name' => 'Nur lesbar']);
        $archivedProject = Project::factory()->withMember($this->users['manager'], ProjectRole::GUEST)->create(['name' => 'Archiviert']);
        $archivedProject->forceFill(['archived_at' => now()])->save();
        $foreignProject = Project::factory()->create(['name' => 'Fremd']);
        $this->claude($this->answer());

        foreach (['owner', 'member', 'manager'] as $viewer) {
            $this->draft($viewer, ['p' => $this->project->id])->assertJsonMissingPath('errors');

            $this->assertStringContainsString(
                '"id":"'.$this->project->id.'","name":"Mollo Planer","mayCreateTodos":true',
                $this->sent['system'],
            );
        }

        $prompt = $this->sent['system'];

        $this->assertStringContainsString(
            '"id":"'.$guestProject->id.'","name":"Nur lesbar","mayCreateTodos":false',
            $prompt,
        );
        $this->assertStringNotContainsString('Archiviert', $prompt);
        $this->assertStringNotContainsString('Fremd', $prompt);
    }

    public function test_a_view_all_user_gets_visible_projects_that_are_not_their_memberships(): void
    {
        $foreignProject = Project::factory()->create(['name' => 'Auch sichtbar']);
        $this->claude($this->answer());

        $this->draft('admin')->assertJsonMissingPath('errors');

        $this->assertStringContainsString(
            '"id":"'.$foreignProject->id.'","name":"Auch sichtbar","mayCreateTodos":true',
            $this->sent['system'],
        );
    }

    public function test_the_prompt_lists_more_than_fifty_visible_projects(): void
    {
        $lastProject = null;

        foreach (range(1, 55) as $number) {
            $lastProject = Project::factory()->withMember($this->users['manager'], ProjectRole::GUEST)
                ->create(['name' => sprintf('zz sichtbares Projekt %02d', $number)]);
        }

        $this->claude($this->answer());

        $this->draft('manager')->assertJsonMissingPath('errors');

        $this->assertStringContainsString(
            '"id":"'.$lastProject->id.'","name":"zz sichtbares Projekt 55","mayCreateTodos":false',
            $this->sent['system'],
        );
    }

    public function test_a_visible_project_without_todo_creation_right_is_not_kept_as_a_project_choice(): void
    {
        $guestProject = Project::factory()->withMember($this->users['manager'], ProjectRole::GUEST)->create(['name' => 'Nur lesbar']);
        $this->claude($this->answer(null, [
            ['kind' => 'PROJECT', 'label' => 'Nur lesbar', 'detail' => null, 'value' => (string) $guestProject->id, 'recommended' => false],
        ]));

        $result = $this->draft('manager')->json('data.draftPlan');

        $this->assertSame([], $result['choices']);
    }

    public function test_more_than_five_choices_end_with_show_all(): void
    {
        $choices = array_map(fn (int $i): array => ['kind' => 'CUSTOM_MILESTONE', 'label' => "Wahl {$i}", 'detail' => null, 'value' => null, 'recommended' => false], range(1, 7));
        $this->claude($this->answer(null, $choices));

        $kinds = array_column($this->draft('manager', ['p' => $this->project->id])->json('data.draftPlan.choices'), 'kind');

        $this->assertCount(6, $kinds);
        $this->assertSame('SHOW_ALL', end($kinds));
    }

    public function test_a_member_may_only_assign_to_themselves_and_stays_without_the_right_to_create_todos_otherwise(): void
    {
        $other = $this->users['manager']->email;
        $this->claude($this->answer($this->todoPlan("m-{$this->milestone->id}", ['assigneeEmail' => $other])));

        $result = $this->draft('owner', ['p' => $this->project->id])->json('data.draftPlan');

        $this->assertTrue($result['preview']['ok']);
    }

    public function test_a_person_without_the_right_is_refused(): void
    {
        $this->claude($this->answer());

        $this->draft('stranger', ['p' => $this->project->id])->assertGraphQLErrorMessage(__('assistant.no_project'));
    }

    public function test_an_archived_project_gives_a_message_not_a_draft(): void
    {
        $this->claude($this->answer());
        $this->project->forceFill(['archived_at' => now()])->save();

        $this->draft('manager', ['p' => $this->project->id])->assertGraphQLErrorMessage(__('assistant.archived'));
    }

    public function test_a_new_project_from_a_template_goes_from_the_draft_to_the_import(): void
    {
        $template = Template::factory()->create();
        $this->claude($this->answer([
            'project' => ['name' => 'Tag der offenen Tür', 'description' => null, 'locale' => 'de', 'labels' => [['key' => 'presse', 'names' => ['de' => 'Presse', 'en' => null], 'category' => null, 'color' => 'blue']]],
            'milestones' => [['id' => 'x', 'title' => 'Start', 'description' => null, 'dueDate' => null, 'todos' => [['id' => 'y', 'title' => 'Plakate', 'description' => null, 'dueDate' => null, 'tasks' => [['id' => 'z', 'title' => 'Plakat entwerfen', 'description' => null, 'dueDate' => null, 'status' => null, 'assigneeEmail' => null, 'labels' => ['presse'], 'userStory' => null]]]]]],
        ]));

        $draft = $this->draft('admin', ['t' => $template->id])->json('data.draftPlan');

        $this->assertTrue($draft['preview']['ok']);

        $this->as('admin')->gql('mutation ($d: String!, $t: ID) { importPlan(document: $d, mode: PARTIAL, appendOnly: true, templateId: $t) { ok errors { message } } }', ['d' => $draft['plan'], 't' => $template->id])
            ->assertJsonPath('data.importPlan.ok', true);

        $project = Project::query()->where('name', 'Tag der offenen Tür')->sole();
        $this->assertSame($template->id, $project->template_id);
    }

    public function test_a_template_named_in_the_text_is_found_by_the_server_and_links_the_new_project(): void
    {
        $template = Template::factory()->create(['name' => 'Veranstaltung Übersicht']);
        $plan = [
            'project' => ['name' => 'Sommerfest', 'description' => null, 'locale' => 'de', 'labels' => []],
            'milestones' => [['id' => 'x', 'title' => 'Start', 'description' => null, 'dueDate' => null, 'todos' => []]],
        ];
        $this->claude($this->answer($plan));

        $draft = $this->as('admin')->gql('mutation { draftPlan(messages: [{role: USER, content: "Nimm die vorlage veranstaltung UBERSICHT für das Fest"}]) { plan templateId preview { ok } } }')->json('data.draftPlan');

        $this->assertSame((string) $template->id, $draft['templateId']);
        $this->assertTrue($draft['preview']['ok']);

        $this->as('admin')->gql('mutation ($d: String!, $t: ID) { importPlan(document: $d, mode: PARTIAL, appendOnly: true, templateId: $t) { ok } }', ['d' => $draft['plan'], 't' => $draft['templateId']])->assertJsonPath('data.importPlan.ok', true);

        $this->assertSame($template->id, Project::query()->where('name', 'Sommerfest')->sole()->template_id);
    }

    public function test_no_template_when_none_or_several_are_named_and_never_for_an_existing_project(): void
    {
        Template::factory()->create(['name' => 'Event']);
        Template::factory()->create(['name' => 'Konzert']);
        $this->claude($this->answer(null));
        $draft = fn (string $text) => $this->as('admin')->gql('mutation ($t: String!) { draftPlan(messages: [{role: USER, content: $t}]) { templateId } }', ['t' => $text])->json('data.draftPlan.templateId');

        $this->assertNull($draft('Ein neues Projekt'));
        $this->assertNull($draft('Event oder Konzert, ich weiss nicht'));
        $this->assertNotNull($draft('Nimm die Vorlage Event'));
        $this->assertNull($draft('Eventuell etwas anderes'));

        $this->assertNull($this->draft('manager', ['p' => $this->project->id, 'text' => "Event in m-{$this->milestone->id}"])->json('data.draftPlan.templateId'));
    }

    public function test_the_detail_of_buttons_is_built_by_the_server_without_ids_or_iso_dates(): void
    {
        $this->milestone->forceFill(['due_date' => '2027-01-29'])->save();
        $this->claude($this->answer(null, [
            ['kind' => 'MILESTONE', 'label' => 'Start', 'detail' => "m-{$this->milestone->id}, fällig 2027-01-29", 'value' => "m-{$this->milestone->id}", 'recommended' => true],
            ['kind' => 'NEW_MILESTONE', 'label' => 'Neu', 'detail' => 'Nach m-3, bis 2027-02-26 passend', 'value' => null, 'recommended' => false],
        ]));

        $choices = $this->draft('manager', ['p' => $this->project->id])->json('data.draftPlan.choices');

        $this->assertStringNotContainsString($this->milestone->id.',', $choices[0]['detail'] ?? '');
        $this->assertStringContainsString('2027', $choices[0]['detail']);
        $this->assertStringNotContainsString('2027-01-29', $choices[0]['detail']);
        $this->assertSame('Nach bis '.CarbonImmutable::parse('2027-02-26')->locale(app()->getLocale())->isoFormat('LL').' passend', $choices[1]['detail'] ?? null);
    }

    public function test_the_hour_limit_refuses_with_the_time_and_counts_per_person(): void
    {
        $this->claude($this->answer());
        config(['services.anthropic.limits' => ['hour' => 2, 'day' => 200]]);
        RateLimiter::clear('assistant-hour:'.$this->users['manager']->id);

        $this->draft('manager', ['p' => $this->project->id])->assertJsonMissingPath('errors');
        $this->draft('manager', ['p' => $this->project->id])->assertJsonMissingPath('errors');
        $message = $this->draft('manager', ['p' => $this->project->id])->json('errors.0.message');

        $this->assertStringStartsWith(strstr(__('assistant.limit_hour', ['max' => 2, 'time' => '']), 'Possible', true) ?: '', $message);
        $this->assertMatchesRegularExpression('/\d{2}:\d{2}/', $message);
        $this->draft('owner', ['p' => $this->project->id])->assertJsonMissingPath('errors');
    }

    public function test_an_api_error_is_a_sentence_and_nothing_is_saved(): void
    {
        $this->partialMock(ClaudeClient::class, fn (MockInterface $mock) => $mock->shouldReceive('ask')->andReturnUsing(fn () => throw new AssistantError(__('assistant.unavailable'))));

        $this->draft('manager', ['p' => $this->project->id])->assertGraphQLErrorMessage(__('assistant.unavailable'));
    }

    public function test_the_idea_goes_into_the_prompt_and_only_its_author_may_use_it(): void
    {
        $this->claude($this->answer());
        $mine = Idea::factory()->create(['user_id' => $this->users['manager']->id, 'title' => 'Mein Einfall']);
        $foreign = Idea::factory()->create(['user_id' => $this->users['owner']->id]);

        $this->draft('manager', ['p' => $this->project->id, 'idea' => $mine->id])->assertJsonMissingPath('errors');
        $this->assertStringContainsString('Mein Einfall', $this->sent['system']);
        $this->draft('manager', ['p' => $this->project->id, 'idea' => $foreign->id])->assertGraphQLValidationKeys(['ideaId']);
    }

    public function test_the_ai_context_is_fenced_as_background_knowledge(): void
    {
        $this->claude($this->answer());
        $this->project->aiContext()->create(['text' => 'Wir bauen ein Haus.']);

        $this->draft('manager', ['p' => $this->project->id])->assertJsonMissingPath('errors');

        $this->assertStringContainsString('<project_background', $this->sent['system']);
        $this->assertStringContainsString('Wir bauen ein Haus.', $this->sent['system']);
    }

    public function test_the_schema_has_the_fields_of_the_spec_and_no_open_objects(): void
    {
        $spec = json_decode(file_get_contents(base_path('../spec/planer-v2.schema.json')), true);
        $plan = AssistantSchema::plan();
        $task = $plan['properties']['milestones']['items']['properties']['todos']['items']['properties']['tasks']['items'];

        $this->assertSame(array_keys($task['properties']), $task['required']);
        $this->assertEmpty(array_diff(array_keys($task['properties']), array_keys($spec['$defs']['task']['properties'])));
        $this->assertArrayNotHasKey('commit', $task['properties']);
        $this->assertArrayNotHasKey('revision', $plan['properties']);

        $walk = function (array $node) use (&$walk): void {
            if (($node['type'] ?? null) === 'object') {
                $this->assertFalse($node['additionalProperties']);
                $this->assertSame(array_keys($node['properties']), $node['required']);
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk(AssistantSchema::answer());
    }
}
