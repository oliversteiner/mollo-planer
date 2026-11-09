<?php

namespace Tests\Feature\GraphQL;

use App\Enums\LabelColor;
use App\Enums\ProjectRole;
use App\Models\Label;
use App\Models\LabelSet;
use App\Models\Project;
use App\Models\User;

class LabelSetTest extends GraphQLTestCase
{
    private const SETS = 'query { labelSets { name description isBuiltin labels { name category color } } }';

    private function builtin(string $key): LabelSet
    {
        return LabelSet::query()->where('key', $key)->firstOrFail();
    }

    private function ownSet(string $creator, int $labels = 2): LabelSet
    {
        $set = LabelSet::factory()->create(['name' => 'Eigenes Set', 'created_by' => $this->users[$creator]->id]);

        for ($position = 0; $position < $labels; $position++) {
            Label::factory()->for($set, 'labelSet')->create(['name' => "Label {$position}", 'position' => $position]);
        }

        return $set->fresh('labels');
    }

    // ─── Built-in sets ──────────────────────────────────────────

    public function test_built_in_sets_come_with_the_app_translated_by_the_request_language(): void
    {
        $german = $this->as('member')->withHeader('Accept-Language', 'de')->gql(self::SETS)->json('data.labelSets');

        $this->assertSame(['Softwareentwicklung', 'Event', 'Podcast'], array_column($german, 'name'));
        $this->assertTrue($german[0]['isBuiltin']);
        $this->assertSame(['name' => 'Fehler', 'category' => 'Typ', 'color' => 'RED'], $german[0]['labels'][3]);

        $english = $this->as('member')->withHeader('Accept-Language', 'en')->gql(self::SETS)->json('data.labelSets');

        $this->assertSame('Software development', $english[0]['name']);
        $this->assertSame(['name' => 'bug', 'category' => 'type', 'color' => 'RED'], $english[0]['labels'][3]);
    }

    public function test_nobody_changes_or_deletes_a_built_in_set(): void
    {
        $id = ['id' => $this->builtin('event')->id];

        $this->assertUnauthorized($this->as('admin')->gql('mutation ($id: ID!) { updateLabelSet(id: $id, input: {name: "Neu", labels: []}) { id } }', $id));
        $this->assertUnauthorized($this->as('admin')->gql('mutation ($id: ID!) { deleteLabelSet(id: $id) { id } }', $id));
    }

    // ─── Managing own sets ──────────────────────────────────────

    public function test_owners_managers_and_admins_create_sets_members_do_not(): void
    {
        $mutation = 'mutation { createLabelSet(input: {name: "Bau", labels: [{name: "Rohbau", category: "Phase", color: ORANGE}, {name: "Ausbau", color: BLUE}]}) { name isBuiltin viewerCanUpdate labels { name category color } } }';

        $this->assertUnauthorized($this->as('member')->gql($mutation));
        $this->assertUnauthorized($this->as('stranger')->gql($mutation));

        $this->as('manager')->gql($mutation)->assertJsonPath('data.createLabelSet', [
            'name' => 'Bau',
            'isBuiltin' => false,
            'viewerCanUpdate' => true,
            'labels' => [
                ['name' => 'Rohbau', 'category' => 'Phase', 'color' => 'ORANGE'],
                ['name' => 'Ausbau', 'category' => null, 'color' => 'BLUE'],
            ],
        ]);
        $this->assertSame($this->users['manager']->id, LabelSet::query()->where('name', 'Bau')->value('created_by'));
    }

    public function test_duplicating_copies_a_set_as_own_set_in_the_language_of_the_request(): void
    {
        $mutation = 'mutation ($id: ID!) { duplicateLabelSet(id: $id) { name isBuiltin labels { name category color } } }';
        $id = ['id' => $this->builtin('software_dev')->id];

        $this->assertUnauthorized($this->as('member')->gql($mutation, $id));

        $copy = $this->as('owner')->withHeader('Accept-Language', 'de')->gql($mutation, $id)->json('data.duplicateLabelSet');

        $this->assertSame('Kopie von Softwareentwicklung', $copy['name']);
        $this->assertFalse($copy['isBuiltin']);
        $this->assertSame(['name' => 'Recherche', 'category' => 'Phase', 'color' => 'BLUE'], $copy['labels'][0]);

        // Stays in German, whatever language reads it later; a second copy gets a number.
        $this->as('owner')->withHeader('Accept-Language', 'en')->gql($mutation, $id)
            ->assertJsonPath('data.duplicateLabelSet.name', 'Copy of Software development');
        $this->as('owner')->withHeader('Accept-Language', 'de')->gql($mutation, $id)
            ->assertJsonPath('data.duplicateLabelSet.name', 'Kopie von Softwareentwicklung (2)');
    }

    public function test_only_the_creator_or_an_admin_changes_and_deletes_an_own_set(): void
    {
        $set = $this->ownSet('manager');
        $update = 'mutation ($id: ID!) { updateLabelSet(id: $id, input: {name: "Umbenannt", labels: []}) { name } }';
        $delete = 'mutation ($id: ID!) { deleteLabelSet(id: $id) { id } }';
        $id = ['id' => $set->id];

        $this->assertUnauthorized($this->as('owner')->gql($update, $id));
        $this->assertUnauthorized($this->as('member')->gql($update, $id));

        $this->as('manager')->gql($update, $id)->assertJsonPath('data.updateLabelSet.name', 'Umbenannt');
        $this->as('admin')->gql('query { labelSets { name viewerCanUpdate viewerCanDelete } }')
            ->assertJsonPath('data.labelSets.3', ['name' => 'Umbenannt', 'viewerCanUpdate' => true, 'viewerCanDelete' => true]);

        $this->as('admin')->gql($delete, $id)->assertJsonPath('data.deleteLabelSet.id', (string) $set->id);
        $this->assertModelMissing($set);
    }

    public function test_a_creator_who_is_no_owner_or_manager_any_more_cannot_change_the_set(): void
    {
        $set = $this->ownSet('manager');
        $this->project->members()->updateExistingPivot($this->users['manager']->id, ['role' => ProjectRole::MEMBER->value]);

        $this->assertUnauthorized($this->as('manager')->gql('mutation ($id: ID!) { updateLabelSet(id: $id, input: {name: "X1", labels: []}) { id } }', ['id' => $set->id]));
    }

    public function test_update_keeps_renames_adds_and_removes_labels_in_the_given_order(): void
    {
        $set = $this->ownSet('owner', 3);
        [$first, $second, $third] = $set->labels->all();
        $this->task->labels()->attach([$first->id, $third->id]);

        $this->as('owner')->gql('mutation ($id: ID!, $in: LabelSetInput!) { updateLabelSet(id: $id, input: $in) { labels { id name color } } }', [
            'id' => $set->id,
            'in' => ['name' => 'Eigenes Set', 'labels' => [
                // Swapped names must not hit the unique name per set.
                ['id' => (string) $second->id, 'name' => 'Label 0', 'color' => 'GREEN'],
                ['id' => (string) $first->id, 'name' => 'Label 1', 'color' => 'RED'],
                ['name' => 'Neu', 'color' => 'PINK'],
            ]],
        ])->assertJsonPath('data.updateLabelSet.labels', [
            ['id' => (string) $second->id, 'name' => 'Label 0', 'color' => 'GREEN'],
            ['id' => (string) $first->id, 'name' => 'Label 1', 'color' => 'RED'],
            ['id' => (string) Label::query()->where('name', 'Neu')->value('id'), 'name' => 'Neu', 'color' => 'PINK'],
        ]);

        $this->assertModelMissing($third);
        $this->assertSame([$first->id], $this->task->labels()->pluck('labels.id')->all());
    }

    public function test_update_refuses_double_names_labels_of_another_set_and_more_than_30_labels(): void
    {
        $set = $this->ownSet('owner');
        $foreign = Label::factory()->create();
        $mutation = 'mutation ($id: ID!, $in: LabelSetInput!) { updateLabelSet(id: $id, input: $in) { id } }';

        $this->as('owner')->gql($mutation, ['id' => $set->id, 'in' => ['name' => 'Set', 'labels' => [
            ['name' => 'Bug', 'color' => 'RED'], ['name' => 'bug ', 'color' => 'RED'],
        ]]])->assertGraphQLValidationKeys(['input.labels']);

        $this->as('owner')->gql($mutation, ['id' => $set->id, 'in' => ['name' => 'Set', 'labels' => [
            ['id' => (string) $foreign->id, 'name' => 'Fremd', 'color' => 'RED'],
        ]]])->assertGraphQLValidationKeys(['input.labels.0.id']);

        $many = array_map(fn (int $i) => ['name' => "L{$i}", 'color' => 'GRAY'], range(1, 31));
        $this->as('owner')->gql($mutation, ['id' => $set->id, 'in' => ['name' => 'Set', 'labels' => $many]])
            ->assertGraphQLValidationKeys(['input.labels']);
    }

    public function test_a_set_in_use_cannot_be_deleted(): void
    {
        $set = $this->ownSet('owner');
        $this->project->labelSet()->associate($set)->save();

        $this->as('owner')->gql('mutation ($id: ID!) { deleteLabelSet(id: $id) { id } }', ['id' => $set->id])
            ->assertGraphQLValidationKeys(['id']);
        $this->assertModelExists($set);
        $this->as('owner')->gql('query { labelSets { usedByProjects } }')->assertJsonPath('data.labelSets.3.usedByProjects', 1);
    }

    // ─── Project and task ───────────────────────────────────────

    public function test_who_may_change_the_project_chooses_its_set(): void
    {
        $mutation = 'mutation ($p: ID!, $s: ID) { setProjectLabelSet(projectId: $p, labelSetId: $s) { labelSet { name } } }';
        $vars = ['p' => $this->project->id, 's' => $this->builtin('event')->id];

        $this->assertUnauthorized($this->as('member')->gql($mutation, $vars));
        $this->as('manager')->gql($mutation, $vars)->assertJsonPath('data.setProjectLabelSet.labelSet.name', 'Event');
        $this->as('manager')->gql($mutation, ['p' => $this->project->id, 's' => null])->assertJsonPath('data.setProjectLabelSet.labelSet', null);
    }

    public function test_task_labels_come_from_the_set_of_the_project_and_old_ones_may_stay(): void
    {
        $software = $this->builtin('software_dev');
        $event = $this->builtin('event');
        $this->project->labelSet()->associate($software)->save();
        $bug = $software->labels()->where('key', 'bug')->firstOrFail();
        $feature = $software->labels()->where('key', 'feature')->firstOrFail();
        $location = $event->labels()->where('key', 'location')->firstOrFail();
        $mutation = 'mutation ($t: ID!, $l: [ID!]!) { setTaskLabels(taskId: $t, labelIds: $l) { labels { id } } }';

        $this->assertUnauthorized($this->as('member')->gql($mutation, ['t' => $this->task->id, 'l' => [$bug->id]]));
        $this->as('manager')->gql($mutation, ['t' => $this->task->id, 'l' => [$location->id]])->assertGraphQLValidationKeys(['labelIds']);

        $labels = $this->as('manager')->gql($mutation, ['t' => $this->task->id, 'l' => [$bug->id, $feature->id, $bug->id]])->json('data.setTaskLabels.labels');
        $this->assertEqualsCanonicalizing([['id' => (string) $feature->id], ['id' => (string) $bug->id]], $labels);

        // After a change of the set the task keeps "bug"; new ones come from the new set.
        $this->project->labelSet()->associate($event)->save();
        $labels = $this->as('manager')->gql($mutation, ['t' => $this->task->id, 'l' => [$bug->id, $location->id]])->json('data.setTaskLabels.labels');
        $this->assertEqualsCanonicalizing([['id' => (string) $bug->id], ['id' => (string) $location->id]], $labels);
        $this->as('manager')->gql($mutation, ['t' => $this->task->id, 'l' => [$feature->id]])->assertGraphQLValidationKeys(['labelIds']);

        $labels = $this->as('member')->withHeader('Accept-Language', 'de')->gql('query ($id: ID!) { task(id: $id) { labels { name position } } }', ['id' => $this->task->id])->json('data.task.labels');
        $this->assertEqualsCanonicalizing([['name' => 'Fehler', 'position' => 3], ['name' => 'Location', 'position' => 0]], $labels);
    }

    public function test_a_new_task_gets_labels_of_the_set(): void
    {
        $set = $this->builtin('podcast');
        $this->project->labelSet()->associate($set)->save();
        $idea = $set->labels()->where('key', 'topic_idea')->firstOrFail();
        $mutation = 'mutation ($in: CreateTaskInput!) { createTask(input: $in) { labels { name } } }';

        $this->as('manager')->withHeader('Accept-Language', 'de')->gql($mutation, ['in' => ['todoId' => $this->todo->id, 'title' => 'Folge 1', 'labelIds' => [$idea->id]]])
            ->assertJsonPath('data.createTask.labels', [['name' => 'Themenidee']]);

        $this->as('manager')->gql($mutation, ['in' => ['todoId' => $this->todo->id, 'title' => 'Folge 2', 'labelIds' => [Label::factory()->create()->id]]])
            ->assertGraphQLValidationKeys(['input.labelIds']);
    }

    public function test_label_sets_need_a_login_and_admins_of_no_project_may_manage_them(): void
    {
        $this->graphQL(self::SETS)->assertGraphQLErrorMessage('Unauthenticated.');

        $lonelyAdmin = User::factory()->admin()->create();
        $this->assertTrue($lonelyAdmin->can('create', LabelSet::class));
        $this->assertFalse(User::factory()->create()->can('create', LabelSet::class));
        $this->assertTrue(Project::factory()->withMember($owner = User::factory()->create(), ProjectRole::OWNER)->create() instanceof Project && $owner->can('create', LabelSet::class));
        $this->assertSame(LabelColor::RED, $this->builtin('software_dev')->labels()->where('key', 'bug')->firstOrFail()->color);
    }
}
