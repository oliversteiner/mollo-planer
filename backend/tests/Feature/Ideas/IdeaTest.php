<?php

namespace Tests\Feature\Ideas;

use App\Models\Idea;
use App\Models\Project;
use App\Planer\Canonical;
use Tests\Feature\GraphQL\GraphQLTestCase;

/**
 * Ideas (docs/auftrag-m6.md §2): private to their author, optionally tied to a project, with a draft, in the trash.
 */
class IdeaTest extends GraphQLTestCase
{
    private const CREATE = 'mutation ($in: CreateIdeaInput!) { createIdea(input: $in) { id title status draft project { id } author { id } viewerCanUpdate } }';

    private const LIST = 'query ($status: IdeaStatus, $project: ID, $author: ID) { ideas(status: $status, projectId: $project, authorId: $author, first: 50) { data { id title } paginatorInfo { total } } }';

    private function idea(string $as, array $attributes = []): Idea
    {
        return Idea::factory()->create(['user_id' => $this->users[$as]->id, ...$attributes]);
    }

    /** @return list<string> */
    private function titles(string $as, array $variables = []): array
    {
        return collect($this->as($as)->gql(self::LIST, $variables)->assertJsonMissingPath('errors')->json('data.ideas.data'))->pluck('title')->all();
    }

    private function draft(): string
    {
        return json_encode([
            'format' => 'planer-v2', 'revision' => 0,
            'project' => ['name' => 'Entwurf', 'locale' => 'de', 'members' => []],
            'milestones' => [['id' => 'm-new-1', 'title' => 'Start', 'todos' => [['id' => 't-new-1', 'title' => 'Arbeit', 'tasks' => []]]]],
        ], JSON_THROW_ON_ERROR);
    }

    // ─── Rights ──────────────────────────────────────────────────────

    public function test_whoever_has_ideas_create_makes_an_idea_and_is_its_author(): void
    {
        $this->as('member')->gql(self::CREATE, ['in' => ['title' => 'Ausschilderung']])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.createIdea.status', 'OPEN')
            ->assertJsonPath('data.createIdea.author.id', (string) $this->users['member']->id)
            ->assertJsonPath('data.createIdea.viewerCanUpdate', true);

        $this->assertSame($this->users['member']->id, Idea::query()->sole()->user_id);
    }

    public function test_without_ideas_create_nothing_is_made(): void
    {
        $this->assertUnauthorized($this->as('lesend')->gql(self::CREATE, ['in' => ['title' => 'Nicht erlaubt']]));
        $this->assertSame(0, Idea::query()->count());
    }

    public function test_others_see_no_idea_and_only_the_author_changes_it(): void
    {
        $mine = $this->idea('member', ['title' => 'Meine']);
        $this->idea('manager', ['title' => 'Fremde']);

        $this->assertSame(['Meine'], $this->titles('member'));
        $this->as('manager')->gql('query ($id: ID!) { idea(id: $id) { title } }', ['id' => $mine->id])->assertJsonPath('data.idea', null);

        $update = 'mutation ($id: ID!) { updateIdea(id: $id, input: {title: "Geändert"}) { id } }';
        $this->assertUnauthorized($this->as('manager')->gql($update, ['id' => $mine->id]));
        $this->assertUnauthorized($this->as('manager')->gql('mutation ($id: ID!) { setIdeaStatus(id: $id, status: DISCARDED) { id } }', ['id' => $mine->id]));
        $this->assertUnauthorized($this->as('manager')->gql('mutation ($id: ID!) { deleteIdea(id: $id) { id } }', ['id' => $mine->id]));
        $this->assertSame('Meine', $mine->fresh()->title);
    }

    public function test_with_ideas_view_any_an_admin_reads_all_but_changes_none(): void
    {
        $mine = $this->idea('member', ['title' => 'Von David']);
        $this->idea('manager', ['title' => 'Von Chiara']);

        $this->assertEqualsCanonicalizing(['Von David', 'Von Chiara'], $this->titles('admin'));
        $this->assertSame(['Von David'], $this->titles('admin', ['author' => $this->users['member']->id]));
        $this->as('admin')->gql('query ($id: ID!) { idea(id: $id) { title viewerCanUpdate } }', ['id' => $mine->id])
            ->assertJsonPath('data.idea.title', 'Von David')
            ->assertJsonPath('data.idea.viewerCanUpdate', false);
        $this->assertUnauthorized($this->as('admin')->gql('mutation ($id: ID!) { updateIdea(id: $id, input: {title: "Admin"}) { id } }', ['id' => $mine->id]));
        $this->assertUnauthorized($this->as('admin')->gql('mutation ($id: ID!) { deleteIdea(id: $id) { id } }', ['id' => $mine->id]));
    }

    public function test_the_author_filter_for_other_people_needs_ideas_view_any(): void
    {
        $this->idea('manager');

        $this->assertUnauthorized($this->as('member')->gql(self::LIST, ['author' => $this->users['manager']->id]));
        $this->as('member')->gql(self::LIST, ['author' => $this->users['member']->id])->assertJsonMissingPath('errors');
    }

    // ─── The list ────────────────────────────────────────────────────

    public function test_the_list_is_newest_first_and_filters_by_status_and_project(): void
    {
        $this->idea('member', ['title' => 'Alt', 'created_at' => now()->subDays(2)]);
        $this->idea('member', ['title' => 'Verworfen', 'status' => 'discarded', 'created_at' => now()->subDay()]);
        $this->idea('member', ['title' => 'Mit Projekt', 'project_id' => $this->project->id]);

        $this->assertSame(['Mit Projekt', 'Verworfen', 'Alt'], $this->titles('member'));
        $this->assertSame(['Verworfen'], $this->titles('member', ['status' => 'DISCARDED']));
        $this->assertSame(['Mit Projekt'], $this->titles('member', ['project' => $this->project->id]));
        $this->as('member')->gql(self::LIST)->assertJsonPath('data.ideas.paginatorInfo.total', 3);
    }

    // ─── Project and draft ───────────────────────────────────────────

    public function test_a_project_must_be_one_the_person_sees(): void
    {
        $foreign = Project::factory()->create();

        $this->as('member')->gql(self::CREATE, ['in' => ['title' => 'Fremd', 'projectId' => $foreign->id]])->assertGraphQLValidationKeys(['input.projectId']);
        $this->as('member')->gql(self::CREATE, ['in' => ['title' => 'Eigen', 'projectId' => $this->project->id]])->assertJsonPath('data.createIdea.project.id', (string) $this->project->id);
    }

    public function test_a_draft_is_checked_against_the_schema_and_stored_canonical(): void
    {
        $this->as('member')->gql(self::CREATE, ['in' => ['title' => 'Kaputt', 'draft' => '{"format":"planer-v2"}']])->assertGraphQLValidationKeys(['input.draft']);
        $this->as('member')->gql(self::CREATE, ['in' => ['title' => 'Kaputt', 'draft' => 'kein json']])->assertGraphQLValidationKeys(['input.draft']);
        $this->assertSame(0, Idea::query()->count());

        $draft = $this->as('member')->gql(self::CREATE, ['in' => ['title' => 'Gut', 'draft' => $this->draft()]])->assertJsonMissingPath('errors')->json('data.createIdea.draft');

        $this->assertSame(Canonical::text(json_decode($this->draft(), true)), $draft);
        $this->assertSame($draft, Idea::query()->sole()->draft);
    }

    public function test_updating_changes_only_what_is_given_and_null_empties_a_field(): void
    {
        $idea = $this->idea('member', ['title' => 'Vorher', 'project_id' => $this->project->id, 'draft' => Canonical::text(json_decode($this->draft(), true))]);

        $this->as('member')->gql('mutation ($id: ID!) { updateIdea(id: $id, input: {title: "Nachher"}) { id } }', ['id' => $idea->id])->assertJsonMissingPath('errors');
        $this->assertSame(['Nachher', $this->project->id], [$idea->fresh()->title, $idea->fresh()->project_id]);
        $this->assertNotNull($idea->fresh()->draft);

        $this->as('member')->gql('mutation ($id: ID!) { updateIdea(id: $id, input: {projectId: null, draft: null}) { id } }', ['id' => $idea->id])->assertJsonMissingPath('errors');
        $this->assertNull($idea->fresh()->project_id);
        $this->assertNull($idea->fresh()->draft);
    }

    public function test_the_status_is_set_by_the_author(): void
    {
        $idea = $this->idea('member');

        $this->as('member')->gql('mutation ($id: ID!) { setIdeaStatus(id: $id, status: DISCARDED) { status } }', ['id' => $idea->id])->assertJsonPath('data.setIdeaStatus.status', 'DISCARDED');
        $this->as('member')->gql('mutation ($id: ID!) { setIdeaStatus(id: $id, status: OPEN) { status } }', ['id' => $idea->id])->assertJsonPath('data.setIdeaStatus.status', 'OPEN');
    }

    public function test_ideas_do_not_count_as_a_revision_of_a_project(): void
    {
        $before = (int) $this->project->fresh()->planer_revision;

        $idea = $this->as('member')->gql(self::CREATE, ['in' => ['title' => 'Still', 'projectId' => $this->project->id]])->json('data.createIdea.id');
        $this->as('member')->gql('mutation ($id: ID!) { setIdeaStatus(id: $id, status: DISCARDED) { id } }', ['id' => $idea]);

        $this->assertSame($before, (int) $this->project->fresh()->planer_revision);
    }

    // ─── Trash ───────────────────────────────────────────────────────

    public function test_deleting_goes_to_the_trash_where_only_the_author_sees_and_restores_it(): void
    {
        $idea = $this->idea('member', ['title' => 'Weg']);

        $deletionId = $this->as('member')->gql('mutation ($id: ID!) { deleteIdea(id: $id) { deletionId } }', ['id' => $idea->id])->json('data.deleteIdea.deletionId');
        $this->assertNotNull($deletionId);
        $this->assertSame([], $this->titles('member'));

        $trash = 'query { trash { type title deletionId viewerCanRestore viewerCanPurge project { id } } }';
        $this->as('member')->gql($trash)->assertJsonPath('data.trash.0.type', 'IDEA')->assertJsonPath('data.trash.0.title', 'Weg')->assertJsonPath('data.trash.0.viewerCanRestore', true)->assertJsonPath('data.trash.0.project', null);
        $this->as('manager')->gql($trash)->assertJsonPath('data.trash', []);
        $this->as('admin')->gql($trash)->assertJsonPath('data.trash', []);

        $restore = 'mutation ($d: ID!) { restoreFromTrash(deletionId: $d) { type title } }';
        $this->assertUnauthorized($this->as('manager')->gql($restore, ['d' => $deletionId]));
        $this->as('member')->gql($restore, ['d' => $deletionId])->assertJsonPath('data.restoreFromTrash.title', 'Weg');
        $this->assertSame(['Weg'], $this->titles('member'));
    }

    public function test_the_author_can_remove_an_idea_for_good(): void
    {
        $idea = $this->idea('member');
        $deletionId = $this->as('member')->gql('mutation ($id: ID!) { deleteIdea(id: $id) { deletionId } }', ['id' => $idea->id])->json('data.deleteIdea.deletionId');

        $this->assertUnauthorized($this->as('manager')->gql('mutation ($d: ID!) { purgeFromTrash(deletionId: $d) }', ['d' => $deletionId]));
        $this->as('member')->gql('mutation ($d: ID!) { purgeFromTrash(deletionId: $d) }', ['d' => $deletionId])->assertJsonPath('data.purgeFromTrash', true);
        $this->assertNull(Idea::withTrashed()->find($idea->id));
    }

    public function test_deleting_the_person_or_the_project_keeps_or_removes_ideas_as_the_concept_says(): void
    {
        $inProject = $this->idea('member', ['project_id' => $this->project->id]);
        $this->project->forceDelete();
        $this->assertNull($inProject->fresh()->project_id, 'the project goes, the idea stays');

        $this->users['member']->delete();
        $this->assertNull(Idea::query()->find($inProject->id), 'the person goes, the ideas with them');
    }
}
