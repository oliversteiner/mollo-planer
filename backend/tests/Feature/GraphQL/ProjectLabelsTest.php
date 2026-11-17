<?php

namespace Tests\Feature\GraphQL;

use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\Template;
use App\Planer\TemplateContent;
use App\Planer\TemplateSync;
use App\Services\Deletion;
use Illuminate\Support\Facades\DB;

/**
 * The labels of a project (docs/auftrag-m3.md 3.1, 3.3): each project has its
 * own, changed in one go; templates give labels, never share them.
 */
class ProjectLabelsTest extends GraphQLTestCase
{
    private const LABELS = 'query ($id: ID!) { project(id: $id) { labels { key name names { locale name } category color position taskCount } } }';

    private const SET = 'mutation ($p: ID!, $l: [LabelInput!]!) { updateProjectLabels(projectId: $p, labels: $l) { labels { key name color position } } }';

    private const APPLY = 'mutation ($p: ID!, $t: ID!, $m: TemplateLabelMode!) { applyTemplateLabels(projectId: $p, templateId: $t, mode: $m) { labels { key name position } } }';

    /**
     * @return array<string, mixed>
     */
    private function input(string $de, ?string $en = null, string $color = 'RED', ?string $key = null, ?string $category = null): array
    {
        $names = [['locale' => 'DE', 'name' => $de]];

        if ($en !== null) {
            $names[] = ['locale' => 'EN', 'name' => $en];
        }

        return array_filter(['key' => $key, 'names' => $names, 'category' => $category, 'color' => $color], fn ($value) => $value !== null);
    }

    private function label(string $key, string $de, int $position = 0, ?Project $project = null): Label
    {
        return Label::factory()->for($project ?? $this->project)->create(['key' => $key, 'names' => ['de' => $de], 'position' => $position]);
    }

    /**
     * @return list<string>
     */
    private function keys(?Project $project = null): array
    {
        return ($project ?? $this->project)->labels()->pluck('key')->all();
    }

    private function revision(): int
    {
        return (int) $this->project->fresh()->planer_revision;
    }

    // ─── Setting the labels ─────────────────────────────────────

    public function test_who_may_change_the_project_sets_its_labels(): void
    {
        $variables = ['p' => $this->project->id, 'l' => [$this->input('Eins')]];

        $this->assertUnauthorized($this->as('member')->gql(self::SET, $variables));
        $this->assertUnauthorized($this->as('stranger')->gql(self::SET, $variables));
        $this->as('manager')->gql(self::SET, $variables)->assertJsonPath('data.updateProjectLabels.labels', [['key' => 'eins', 'name' => 'Eins', 'color' => 'RED', 'position' => 0]]);
    }

    public function test_update_keeps_renames_adds_and_removes_labels_in_the_given_order_and_keeps_the_keys(): void
    {
        [$first, $second, $third] = [$this->label('eins', 'Eins', 0), $this->label('zwei', 'Zwei', 1), $this->label('drei', 'Drei', 2)];
        $this->task->labels()->attach([$first->id, $third->id]);

        $this->as('manager')->withHeader('Accept-Language', 'de')->gql(self::SET, ['p' => $this->project->id, 'l' => [
            $this->input('Zwei neu', 'Two new', 'GREEN', 'zwei'),
            $this->input('Eins', null, 'BLUE', 'eins'),
            $this->input('Vier', null, 'PINK'),
        ]])->assertJsonPath('data.updateProjectLabels.labels', [
            ['key' => 'zwei', 'name' => 'Zwei neu', 'color' => 'GREEN', 'position' => 0],
            ['key' => 'eins', 'name' => 'Eins', 'color' => 'BLUE', 'position' => 1],
            ['key' => 'vier', 'name' => 'Vier', 'color' => 'PINK', 'position' => 2],
        ]);

        // "Zwei" is the same label after the rename, "Drei" is gone and so is its use on the task.
        $this->assertSame($second->id, Label::query()->where('key', 'zwei')->value('id'));
        $this->assertModelMissing($third);
        $this->assertSame([$first->id], $this->task->labels()->pluck('labels.id')->all());
        $this->assertSame(['de' => 'Zwei neu', 'en' => 'Two new'], $second->fresh()->names);
    }

    public function test_a_new_key_comes_from_the_name_in_the_language_of_the_project(): void
    {
        $this->label('ui', 'UI');

        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'l' => [
            $this->input('Über uns', 'About'), $this->input('UI', null, 'RED', 'ui'), $this->input('Ui  Ux', 'Ui'), $this->input('Ui-Ux', 'Ux'),
        ]])->assertJsonMissingPath('errors');

        $this->assertSame(['uber_uns', 'ui', 'ui_ux', 'ui_ux_2'], $this->keys());
    }

    public function test_labels_are_checked(): void
    {
        $this->label('eins', 'Eins');
        $foreign = $this->label('fremd', 'Fremd', 0, Project::factory()->create());
        $set = fn (array $labels) => $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'l' => $labels]);

        $set([$this->input('Bug'), $this->input('bug ')])->assertGraphQLValidationKeys(['labels.1.names']);
        // The same name in English twice is as wrong as in German.
        $set([$this->input('Eins', 'Same'), $this->input('Zwei', 'same')])->assertGraphQLValidationKeys(['labels.1.names']);
        // The same name in two languages is fine.
        $set([$this->input('Gleich', 'Gleich')])->assertJsonMissingPath('errors');
        $set([$this->input('Fremd', null, 'RED', $foreign->key)])->assertGraphQLValidationKeys(['labels.0.key']);
        $set([['names' => [['locale' => 'EN', 'name' => 'Only English']], 'color' => 'RED']])->assertGraphQLValidationKeys(['labels.0.names']);
        $set(array_map(fn (int $i) => $this->input("L{$i}"), range(1, 31)))->assertGraphQLValidationKeys(['labels']);

        $this->assertSame(['gleich'], $this->keys());
        $this->assertModelExists($foreign);
    }

    public function test_the_language_of_the_project_decides_which_name_is_needed(): void
    {
        $this->project->forceFill(['locale' => 'en'])->save();

        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'l' => [$this->input('Nur Deutsch')]])->assertGraphQLValidationKeys(['labels.0.names']);
        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'l' => [$this->input('Deutsch', 'English')]])->assertJsonMissingPath('errors');
        $this->assertSame(['english'], $this->keys());
    }

    public function test_setting_the_labels_changes_no_other_project_and_no_template(): void
    {
        TemplateSync::sync();
        $other = Project::factory()->create();
        $this->label('eins', 'Eins', 0, $other);
        $template = Template::query()->where('key', 'event')->firstOrFail();
        $content = $template->content;

        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'l' => [$this->input('Neu')]])->assertJsonMissingPath('errors');

        $this->assertSame(['eins'], $this->keys($other));
        $this->assertSame($content, $template->fresh()->content);
    }

    public function test_one_change_of_the_labels_counts_once_for_the_planer_file(): void
    {
        $before = $this->revision();
        $this->label('eins', 'Eins');

        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'l' => [$this->input('Zwei'), $this->input('Drei'), $this->input('Vier')]])->assertJsonMissingPath('errors');

        $this->assertSame($before + 1, $this->revision());
    }

    // ─── Tasks and the trash ────────────────────────────────────

    public function test_task_labels_come_from_the_labels_of_the_project_and_old_ones_may_stay(): void
    {
        $bug = $this->label('bug', 'Fehler');
        $feature = $this->label('feature', 'Feature', 1);
        $foreign = $this->label('fremd', 'Fremd', 0, Project::factory()->create());
        $mutation = 'mutation ($t: ID!, $l: [ID!]!) { setTaskLabels(taskId: $t, labelIds: $l) { labels { id } } }';

        $this->assertUnauthorized($this->as('member')->gql($mutation, ['t' => $this->task->id, 'l' => [$bug->id]]));
        $this->as('manager')->gql($mutation, ['t' => $this->task->id, 'l' => [$foreign->id]])->assertGraphQLValidationKeys(['labelIds']);

        $labels = $this->as('manager')->gql($mutation, ['t' => $this->task->id, 'l' => [$bug->id, $feature->id, $bug->id]])->json('data.setTaskLabels.labels');
        $this->assertEqualsCanonicalizing([['id' => (string) $feature->id], ['id' => (string) $bug->id]], $labels);

        $labels = $this->as('manager')->gql($mutation, ['t' => $this->task->id, 'l' => [$bug->id]])->json('data.setTaskLabels.labels');
        $this->assertSame([['id' => (string) $bug->id]], $labels);
    }

    public function test_a_new_task_gets_labels_of_the_project(): void
    {
        $idea = $this->label('topic_idea', 'Themenidee');
        $mutation = 'mutation ($in: CreateTaskInput!) { createTask(input: $in) { labels { name } } }';

        $this->as('manager')->withHeader('Accept-Language', 'de')->gql($mutation, ['in' => ['todoId' => $this->todo->id, 'title' => 'Folge 1', 'labelIds' => [$idea->id]]])
            ->assertJsonPath('data.createTask.labels', [['name' => 'Themenidee']]);

        $this->as('manager')->gql($mutation, ['in' => ['todoId' => $this->todo->id, 'title' => 'Folge 2', 'labelIds' => [Label::factory()->create()->id]]])
            ->assertGraphQLValidationKeys(['input.labelIds']);
        // The labels are checked after the task is saved, in the same transaction: nothing stays.
        $this->assertFalse(Task::where('title', 'Folge 2')->exists());
    }

    public function test_the_task_count_leaves_out_tasks_in_the_trash_and_deleting_the_label_takes_it_from_them_too(): void
    {
        $label = $this->label('bug', 'Fehler');
        $deleted = Task::factory()->for($this->todo)->create();
        $this->task->labels()->attach($label);
        $deleted->labels()->attach($label);
        app(Deletion::class)->delete($deleted);

        $this->as('manager')->gql(self::LABELS, ['id' => $this->project->id])->assertJsonPath('data.project.labels.0.taskCount', 1);

        $revision = $this->revision();
        $this->as('manager')->gql(self::SET, ['p' => $this->project->id, 'l' => []])->assertJsonMissingPath('errors');

        $this->assertSame(0, DB::table('label_task')->count());
        $this->assertSame($revision + 1, $this->revision(), 'one change, whatever the trashed tasks held');

        // Brought back, the task has no label any more.
        app(Deletion::class)->restore(Task::onlyTrashed()->findOrFail($deleted->id)->deletion_id, $this->users['owner']);
        $this->assertSame([], $deleted->fresh()->labels()->pluck('labels.id')->all());
    }

    public function test_a_project_in_the_trash_keeps_its_labels(): void
    {
        $this->label('bug', 'Fehler');
        app(Deletion::class)->delete($this->project);
        $id = Project::onlyTrashed()->findOrFail($this->project->id)->deletion_id;

        $this->assertSame(['bug'], Label::query()->where('project_id', $this->project->id)->pluck('key')->all());

        app(Deletion::class)->restore($id, $this->users['owner']);
        $this->assertSame(['bug'], $this->keys());
    }

    // ─── Labels from a template ─────────────────────────────────

    public function test_append_adds_only_the_labels_whose_key_is_missing(): void
    {
        TemplateSync::sync();
        $this->label('bug', 'Meine Fehler', 0);
        $this->label('extra', 'Extra', 1);
        $template = Template::query()->where('key', 'software-development')->firstOrFail();

        $before = $this->revision();
        $this->assertUnauthorized($this->as('member')->gql(self::APPLY, ['p' => $this->project->id, 't' => $template->id, 'm' => 'APPEND']));

        $this->as('manager')->withHeader('Accept-Language', 'de')->gql(self::APPLY, ['p' => $this->project->id, 't' => $template->id, 'm' => 'APPEND'])
            ->assertJsonPath('data.applyTemplateLabels.labels', [
                ['key' => 'bug', 'name' => 'Meine Fehler', 'position' => 0],
                ['key' => 'extra', 'name' => 'Extra', 'position' => 1],
                ['key' => 'research', 'name' => 'Recherche', 'position' => 2],
                ['key' => 'design', 'name' => 'Design', 'position' => 3],
                ['key' => 'feature', 'name' => 'Feature', 'position' => 4],
                ['key' => 'planning', 'name' => 'Planung', 'position' => 5],
                ['key' => 'blocked', 'name' => 'Blockiert', 'position' => 6],
            ]);
        $this->assertSame($before + 1, $this->revision());
    }

    public function test_replace_sets_the_labels_of_the_template_and_keeps_what_tasks_use(): void
    {
        TemplateSync::sync();
        $bug = $this->label('bug', 'Meine Fehler', 3);
        $used = $this->label('eigen', 'Eigenes, an einem Task', 1);
        $unused = $this->label('lose', 'Lose', 2);
        $this->task->labels()->attach([$bug->id, $used->id]);
        $template = Template::query()->where('key', 'software-development')->firstOrFail();

        $this->as('manager')->withHeader('Accept-Language', 'de')->gql(self::APPLY, ['p' => $this->project->id, 't' => $template->id, 'm' => 'REPLACE'])
            ->assertJsonPath('data.applyTemplateLabels.labels', [
                ['key' => 'research', 'name' => 'Recherche', 'position' => 0],
                ['key' => 'design', 'name' => 'Design', 'position' => 1],
                ['key' => 'feature', 'name' => 'Feature', 'position' => 2],
                ['key' => 'bug', 'name' => 'Fehler', 'position' => 3],
                ['key' => 'planning', 'name' => 'Planung', 'position' => 4],
                ['key' => 'blocked', 'name' => 'Blockiert', 'position' => 5],
                ['key' => 'eigen', 'name' => 'Eigenes, an einem Task', 'position' => 6],
            ]);

        // The label with the same key is the same label: the task keeps it. Unused labels are gone.
        $this->assertSame($bug->id, Label::query()->where('key', 'bug')->value('id'));
        $this->assertEqualsCanonicalizing([$bug->id, $used->id], $this->task->labels()->pluck('labels.id')->all());
        $this->assertModelMissing($unused);
        $this->assertSame(['de' => 'Fehler', 'en' => 'Bug'], $bug->fresh()->names);
    }

    public function test_a_template_without_the_language_of_the_project_gives_its_own_name(): void
    {
        $this->project->forceFill(['locale' => 'en'])->save();
        $template = Template::factory()->create(['content' => TemplateContent::blank('Nur Deutsch', null, 'de', [
            ['key' => 'eins', 'names' => ['de' => 'Eins'], 'category' => null, 'color' => 'blue'],
        ])]);

        $this->as('manager')->gql(self::APPLY, ['p' => $this->project->id, 't' => $template->id, 'm' => 'APPEND'])->assertJsonMissingPath('errors');

        $this->assertSame(['de' => 'Eins', 'en' => 'Eins'], Label::query()->where('key', 'eins')->firstOrFail()->names);
    }

    public function test_the_labels_of_a_project_stay_at_most_30_when_a_template_is_taken_over(): void
    {
        $content = TemplateContent::blank('Voll', null, 'de', array_map(
            fn (int $i): array => ['key' => "t{$i}", 'names' => ['de' => "T{$i}"], 'category' => null, 'color' => 'gray'],
            range(1, 20),
        ));
        $template = Template::factory()->create(['content' => $content]);
        foreach (range(1, 11) as $i) {
            $this->label("p{$i}", "P{$i}", $i);
        }

        $this->as('manager')->gql(self::APPLY, ['p' => $this->project->id, 't' => $template->id, 'm' => 'APPEND'])->assertGraphQLValidationKeys(['labels']);

        $this->assertCount(11, $this->keys());
    }
}
