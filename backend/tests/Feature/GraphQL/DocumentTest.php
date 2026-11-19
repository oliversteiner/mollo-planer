<?php

namespace Tests\Feature\GraphQL;

use App\Enums\DocumentAccess;
use App\Enums\ProjectRole;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\Deletion;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Schema\SchemaBuilder;

/** D4 of docs/dokumentenverwaltung.md: types, queries and mutations of the documents. */
class DocumentTest extends GraphQLTestCase
{
    private const CREATE = 'mutation ($in: CreateDocumentInput!) { createDocument(input: $in) { id title kind revision projectAccess viewerAccess owner { id } updatedBy { id } content } }';

    private const UPDATE = 'mutation ($id: ID!, $rev: Int!, $in: UpdateDocumentInput!) { updateDocument(id: $id, revision: $rev, input: $in) { id title kind revision content updatedBy { id } } }';

    private function make(string $owner = 'owner', DocumentAccess $access = DocumentAccess::READ, array $attributes = []): Document
    {
        return Document::factory()->for($this->project)->create(['owner_id' => $this->users[$owner]->id, 'project_access' => $access, ...$attributes]);
    }

    /** @return array<string, mixed> */
    private function table(): array
    {
        $cell = fn (string $type, string $text): array => ['type' => $type, 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];

        return ['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Titel']]],
            ['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [$cell('tableHeader', 'A'), $cell('tableHeader', 'B')]], ['type' => 'tableRow', 'content' => [$cell('tableCell', '1'), $cell('tableCell', '2')]]]],
        ]];
    }

    // ─── Create ──────────────────────────────────────────────────────

    public function test_whoever_may_create_makes_a_document_that_has_them_as_owner_and_a_first_version(): void
    {
        $this->as('member')->gql(self::CREATE, ['in' => ['projectId' => $this->project->id, 'title' => 'Protokoll', 'kind' => 'MINUTES', 'contentJson' => $this->table()]])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.createDocument.kind', 'MINUTES')
            ->assertJsonPath('data.createDocument.revision', 1)
            ->assertJsonPath('data.createDocument.projectAccess', 'READ')
            ->assertJsonPath('data.createDocument.viewerAccess', 'MANAGE')
            ->assertJsonPath('data.createDocument.owner.id', (string) $this->users['member']->id)
            ->assertJsonPath('data.createDocument.updatedBy.id', (string) $this->users['member']->id)
            ->assertJsonPath('data.createDocument.content', "# Titel\n\n| A | B |\n| --- | --- |\n| 1 | 2 |");

        $document = Document::query()->sole();
        $this->assertSame($document->content, $document->versions()->sole()->content);
        $this->assertSame($this->users['member']->id, $document->versions()->sole()->created_by);
    }

    public function test_without_the_right_or_outside_the_project_nothing_is_made(): void
    {
        $input = ['in' => ['projectId' => $this->project->id, 'title' => 'x', 'contentJson' => $this->doc('x')]];

        $this->assertUnauthorized($this->as('stranger')->gql(self::CREATE, $input));
        $this->assertUnauthorized($this->as('lesend')->gql(self::CREATE, $input));
        $this->assertSame(0, Document::query()->count());
    }

    public function test_the_content_is_checked_against_the_profile_with_errors_on_the_field(): void
    {
        $bad = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]]]];
        $tooLong = $this->doc(str_repeat('a', 200001));

        foreach ([$bad, $tooLong] as $content) {
            $this->as('owner')->gql(self::CREATE, ['in' => ['projectId' => $this->project->id, 'title' => 'x', 'contentJson' => $content]])
                ->assertGraphQLValidationKeys(['input.contentJson']);
        }
        $this->assertSame(0, Document::query()->count());
    }

    public function test_the_level_for_all_members_cannot_be_manage(): void
    {
        $this->as('owner')->gql(self::CREATE, ['in' => ['projectId' => $this->project->id, 'title' => 'x', 'contentJson' => $this->doc('x'), 'projectAccess' => 'MANAGE']])
            ->assertGraphQLValidationKeys(['input.projectAccess']);
    }

    // ─── Read ────────────────────────────────────────────────────────

    public function test_the_viewer_reads_content_as_markdown_and_as_the_document_for_the_editor(): void
    {
        $document = $this->make(attributes: ['content' => "# Titel\n\n- [x] fertig\n"]);

        $this->as('member')->gql('query ($id: ID!) { document(id: $id) { content contentJson viewerAccess shares { access } } }', ['id' => $document->id])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.document.content', "# Titel\n\n- [x] fertig\n")
            ->assertJsonPath('data.document.contentJson.content.1.type', 'taskList')
            ->assertJsonPath('data.document.viewerAccess', 'READ')
            ->assertJsonPath('data.document.shares', []);
    }

    public function test_the_list_shows_what_the_viewer_may_read_with_its_access_and_newest_first(): void
    {
        $private = $this->make('manager', DocumentAccess::NONE, ['title' => 'Privat', 'updated_at' => now()->subDays(3)]);
        $shared = $this->make('manager', DocumentAccess::NONE, ['title' => 'Geteilt', 'updated_at' => now()->subDays(2)]);
        $shared->shares()->create(['user_id' => $this->users['member']->id, 'access' => 'edit']);
        $this->make('owner', DocumentAccess::EDIT, ['title' => 'Offen', 'updated_at' => now()->subDay()]);
        $query = 'query ($p: ID!) { documents(projectId: $p) { title viewerAccess } }';

        $this->assertSame([['Offen', 'EDIT'], ['Geteilt', 'EDIT']], collect($this->as('member')->gql($query, ['p' => $this->project->id])->json('data.documents'))->map(fn (array $row): array => [$row['title'], $row['viewerAccess']])->all());
        $this->assertSame([['Offen', 'MANAGE'], ['Geteilt', 'MANAGE'], ['Privat', 'MANAGE']], collect($this->as('manager')->gql($query, ['p' => $this->project->id])->json('data.documents'))->map(fn (array $row): array => [$row['title'], $row['viewerAccess']])->all());
        $this->assertSame([], $this->as('stranger')->gql($query, ['p' => $this->project->id])->json('data.documents'));
        $this->assertNotNull($private);
    }

    public function test_only_those_who_manage_the_document_see_its_shares(): void
    {
        $document = $this->make();
        $document->shares()->create(['user_id' => $this->users['member']->id, 'access' => 'edit']);
        $query = 'query ($id: ID!) { document(id: $id) { shares { user { id } access } } }';

        $this->as('owner')->gql($query, ['id' => $document->id])
            ->assertJsonPath('data.document.shares.0.access', 'EDIT')
            ->assertJsonPath('data.document.shares.0.user.id', (string) $this->users['member']->id);
        $this->as('member')->gql($query, ['id' => $document->id])->assertJsonPath('data.document.shares', []);
    }

    public function test_the_kind_filters_the_list(): void
    {
        $this->make(attributes: ['title' => 'A', 'kind' => 'minutes']);
        $this->make(attributes: ['title' => 'B', 'kind' => 'analysis']);

        $titles = $this->as('owner')->gql('query ($p: ID!) { documents(projectId: $p, kind: ANALYSIS) { title } }', ['p' => $this->project->id])->json('data.documents.*.title');

        $this->assertSame(['B'], $titles);
    }

    public function test_the_search_finds_title_and_content_without_regard_to_case(): void
    {
        $this->make(attributes: ['title' => 'Sitzung vom Montag', 'content' => "Es ging um den Termin.\n"]);
        $this->make(attributes: ['title' => 'Analyse', 'content' => "Die Kosten steigen.\n"]);
        $search = fn (string $term): array => $this->as('owner')->gql('query ($p: ID!, $s: String) { documents(projectId: $p, search: $s) { title } }', ['p' => $this->project->id, 's' => $term])->json('data.documents.*.title');

        $this->assertSame(['Sitzung vom Montag'], $search('montag'));
        $this->assertSame(['Analyse'], $search('Analyse'));
        $this->assertSame([], $search('gibtesnicht'));
    }

    public function test_the_number_of_queries_for_the_list_does_not_grow_with_the_rows(): void
    {
        $query = 'query ($p: ID!) { documents(projectId: $p) { id title kind owner { id name } projectAccess revision updatedBy { id } viewerAccess updatedAt } }';
        $count = function () use ($query): int {
            $queries = 0;
            DB::listen(function (QueryExecuted $executed) use (&$queries): void {
                $queries++;
            });
            $this->as('member')->gql($query, ['p' => $this->project->id])->assertJsonMissingPath('errors');

            return $queries;
        };

        foreach (range(1, 3) as $i) {
            $this->make('manager', DocumentAccess::READ);
        }
        $count(); // the rights are read once per request: the first call warms them up
        $few = $count();
        foreach (range(1, 12) as $i) {
            $document = $this->make('manager', DocumentAccess::NONE);
            $document->shares()->create(['user_id' => $this->users['member']->id, 'access' => 'read']);
            $this->make('owner', DocumentAccess::EDIT);
        }

        $this->assertSame($few, $count());
    }

    public function test_the_list_does_not_load_the_content(): void
    {
        $this->make(attributes: ['content' => "Geheimer Text\n"]);
        $selects = [];
        DB::listen(function (QueryExecuted $executed) use (&$selects): void {
            $selects[] = $executed->sql;
        });

        $this->as('owner')->gql('query ($p: ID!) { documents(projectId: $p) { title } }', ['p' => $this->project->id])->assertJsonMissingPath('errors');

        $documentSelects = array_filter($selects, fn (string $sql): bool => str_contains($sql, 'from "documents"') || str_contains($sql, 'from `documents`'));
        $this->assertNotEmpty($documentSelects);
        foreach ($documentSelects as $sql) {
            $this->assertStringNotContainsString('"content"', $sql);
            $this->assertStringNotContainsString('`content`', $sql);
            $this->assertStringNotContainsString('documents.*', $sql);
            $this->assertDoesNotMatchRegularExpression('/select \*/i', $sql);
        }
    }

    // ─── Update and the conflict ─────────────────────────────────────

    public function test_saving_changes_the_content_raises_the_revision_and_writes_a_version_of_the_person(): void
    {
        $document = $this->make('owner', DocumentAccess::EDIT);

        $this->as('member')->gql(self::UPDATE, ['id' => $document->id, 'rev' => 1, 'in' => ['title' => 'Neu', 'kind' => 'CONCEPT', 'contentJson' => $this->doc('Geändert')]])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.updateDocument.revision', 2)
            ->assertJsonPath('data.updateDocument.kind', 'CONCEPT')
            ->assertJsonPath('data.updateDocument.content', 'Geändert')
            ->assertJsonPath('data.updateDocument.updatedBy.id', (string) $this->users['member']->id);

        $this->assertSame(['Neu', 2, $this->users['member']->id], [$document->fresh()->title, $document->fresh()->revision, $document->versions()->first()->created_by]);
        $this->assertSame([2, 1], $document->versions()->pluck('revision')->all());
        $this->assertSame($document->content, $document->versions()->where('revision', 1)->sole()->content);
    }

    public function test_saving_the_same_content_writes_nothing(): void
    {
        $document = $this->make(attributes: ['title' => 'Gleich', 'content' => 'Text']);

        $this->as('owner')->gql(self::UPDATE, ['id' => $document->id, 'rev' => 1, 'in' => ['title' => 'Gleich', 'contentJson' => $this->doc('Text')]])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.updateDocument.revision', 1);

        $this->assertSame(1, $document->versions()->count());
    }

    public function test_an_old_revision_is_a_conflict_with_the_current_revision_and_nothing_is_saved(): void
    {
        $document = $this->make();
        $this->as('owner')->gql(self::UPDATE, ['id' => $document->id, 'rev' => 1, 'in' => ['contentJson' => $this->doc('Eins')]])->assertJsonMissingPath('errors');

        $response = $this->as('owner')->gql(self::UPDATE, ['id' => $document->id, 'rev' => 1, 'in' => ['contentJson' => $this->doc('Zwei')]]);

        $response->assertJsonPath('errors.0.extensions.code', 'DOCUMENT_CONFLICT')->assertJsonPath('errors.0.extensions.currentRevision', 2);
        $this->assertSame('Eins', $document->fresh()->content);
        $this->assertSame(2, $document->versions()->count());
    }

    public function test_the_level_and_the_right_decide_who_saves(): void
    {
        $document = $this->make('owner', DocumentAccess::READ);
        $input = ['id' => $document->id, 'rev' => 1, 'in' => ['title' => 'Hack']];

        $this->assertUnauthorized($this->as('member')->gql(self::UPDATE, $input));

        $document->shares()->create(['user_id' => $this->users['member']->id, 'access' => 'edit']);
        $this->as('member')->gql(self::UPDATE, $input)->assertJsonMissingPath('errors');

        // A share to edit does not help without the right: the system role "Lesend" reads only.
        $this->project->members()->attach($this->users['lesend'], ['role_id' => $this->projectRole(ProjectRole::MEMBER)->id]);
        $document->shares()->create(['user_id' => $this->users['lesend']->id, 'access' => 'edit']);
        $this->assertUnauthorized($this->as('lesend')->gql(self::UPDATE, ['id' => $document->id, 'rev' => 2, 'in' => ['title' => 'Hack 2']]));
    }

    public function test_in_an_archived_project_a_document_is_read_only(): void
    {
        $document = $this->make('owner', DocumentAccess::EDIT);
        $this->project->forceFill(['archived_at' => now()])->save();

        $this->as('owner')->gql(self::UPDATE, ['id' => $document->id, 'rev' => 1, 'in' => ['title' => 'Hack']])
            ->assertGraphQLErrorMessage(__('projects.archived'));
        $this->as('owner')->gql('query ($id: ID!) { document(id: $id) { title viewerAccess } }', ['id' => $document->id])->assertJsonPath('data.document.viewerAccess', 'READ');
    }

    // ─── Sharing and owner ───────────────────────────────────────────

    public function test_sharing_replaces_the_list_and_leaves_the_revision_alone(): void
    {
        $document = $this->make();
        $document->shares()->create(['user_id' => $this->users['manager']->id, 'access' => 'read']);
        $mutation = 'mutation ($id: ID!, $in: DocumentSharingInput!) { setDocumentSharing(id: $id, input: $in) { revision projectAccess shares { user { id } access } } }';

        $this->as('owner')->gql($mutation, ['id' => $document->id, 'in' => ['projectAccess' => 'NONE', 'shares' => [['userId' => $this->users['member']->id, 'access' => 'EDIT']]]])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.setDocumentSharing.revision', 1)
            ->assertJsonPath('data.setDocumentSharing.projectAccess', 'NONE')
            ->assertJsonPath('data.setDocumentSharing.shares.0.user.id', (string) $this->users['member']->id)
            ->assertJsonCount(1, 'data.setDocumentSharing.shares');

        $this->assertSame(1, $document->versions()->count());
    }

    public function test_only_members_of_the_project_get_a_share_or_the_ownership(): void
    {
        $document = $this->make();
        $share = 'mutation ($id: ID!, $u: ID!) { setDocumentSharing(id: $id, input: {shares: [{userId: $u, access: READ}]}) { id } }';
        $transfer = 'mutation ($id: ID!, $u: ID!) { transferDocument(id: $id, ownerId: $u) { id owner { id } } }';

        $this->as('owner')->gql($share, ['id' => $document->id, 'u' => $this->users['stranger']->id])->assertGraphQLValidationError('input.shares', __('documents.share_not_member'));
        $this->as('owner')->gql($transfer, ['id' => $document->id, 'u' => $this->users['stranger']->id])->assertGraphQLValidationError('ownerId', __('documents.owner_not_member'));
        $this->as('owner')->gql($transfer, ['id' => $document->id, 'u' => $this->users['member']->id])->assertJsonPath('data.transferDocument.owner.id', (string) $this->users['member']->id);

        $this->assertSame(0, $document->shares()->count());
        $this->assertSame($this->users['member']->id, $document->fresh()->owner_id);
    }

    // ─── Trash ───────────────────────────────────────────────────────

    public function test_a_deleted_document_comes_back_with_the_deletion_id(): void
    {
        $document = $this->make();

        $deletionId = $this->as('owner')->gql('mutation ($id: ID!) { deleteDocument(id: $id) { deletionId } }', ['id' => $document->id])
            ->assertJsonMissingPath('errors')->json('data.deleteDocument.deletionId');

        $this->assertNotNull($deletionId);
        $this->as('member')->gql('query ($id: ID!) { document(id: $id) { id } }', ['id' => $document->id])->assertJsonPath('data.document', null);

        $this->as('owner')->gql('mutation ($d: ID!) { restoreFromTrash(deletionId: $d) { type title } }', ['d' => $deletionId])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.restoreFromTrash.type', 'DOCUMENT');
        $this->assertFalse($document->fresh()->trashed());
    }

    public function test_the_trash_of_the_project_shows_documents_to_those_who_manage_them_only(): void
    {
        $document = $this->make('manager', DocumentAccess::READ, ['title' => 'Weg']);
        app(Deletion::class)->delete($document);
        $query = 'query ($p: ID!) { trash(projectId: $p) { type title viewerCanRestore } }';

        $this->assertSame([['DOCUMENT', 'Weg', true]], collect($this->as('manager')->gql($query, ['p' => $this->project->id])->json('data.trash'))->map(fn (array $row): array => [$row['type'], $row['title'], $row['viewerCanRestore']])->all());
        $this->assertSame([], $this->as('member')->gql($query, ['p' => $this->project->id])->json('data.trash'));
    }

    public function test_a_document_that_is_not_the_owners_is_not_deleted_by_a_member(): void
    {
        $document = $this->make();

        $this->assertUnauthorized($this->as('member')->gql('mutation ($id: ID!) { deleteDocument(id: $id) { id } }', ['id' => $document->id]));
        $this->assertFalse($document->fresh()->trashed());
        $this->assertSame(1, DocumentVersion::query()->count());
        $this->assertInstanceOf(User::class, $this->users['member']);
    }

    // ─── Versions (D9) ───────────────────────────────────────────────

    private const VERSIONS = 'query ($id: ID!) { document(id: $id) { versions { revision title kind createdBy { id } contributors { id } restoredFrom createdAt } } }';

    public function test_the_versions_come_newest_first_without_the_content_and_every_reader_sees_them(): void
    {
        $document = $this->make('owner', DocumentAccess::EDIT);
        $this->as('member')->gql(self::UPDATE, ['id' => $document->id, 'rev' => 1, 'in' => ['title' => 'Zwei']])->assertJsonMissingPath('errors');

        $response = $this->as('member')->gql(self::VERSIONS, ['id' => $document->id])->assertJsonMissingPath('errors');

        $this->assertSame([2, 1], $response->json('data.document.versions.*.revision'));
        $this->assertSame((string) $this->users['member']->id, $response->json('data.document.versions.0.createdBy.id'));
        $this->assertSame([(string) $this->users['member']->id], $response->json('data.document.versions.0.contributors.*.id'), 'in M7 the contributor is the person who saved');
        $this->assertNull($response->json('data.document.versions.0.restoredFrom'));

        $selects = [];
        DB::listen(function (QueryExecuted $executed) use (&$selects): void {
            $selects[] = $executed->sql;
        });
        $this->as('member')->gql(self::VERSIONS, ['id' => $document->id])->assertJsonMissingPath('errors');
        foreach (array_filter($selects, fn (string $sql): bool => str_contains($sql, 'document_versions')) as $sql) {
            $this->assertStringNotContainsString('content', $sql);
        }
        $this->assertSame([], $this->as('stranger')->gql(self::VERSIONS, ['id' => $document->id])->json('data.document.versions') ?? []);
    }

    public function test_one_version_comes_with_its_content_for_readers_only(): void
    {
        $document = $this->make(attributes: ['content' => 'Erste Fassung
']);
        $this->as('owner')->gql(self::UPDATE, ['id' => $document->id, 'rev' => 1, 'in' => ['contentJson' => $this->doc('Zweite Fassung')]])->assertJsonMissingPath('errors');
        $query = 'query ($id: ID!, $r: Int!) { documentVersion(documentId: $id, revision: $r) { revision title content } }';

        $this->as('member')->gql($query, ['id' => $document->id, 'r' => 1])
            ->assertJsonPath('data.documentVersion.content', 'Erste Fassung
')
            ->assertJsonPath('data.documentVersion.revision', 1);
        $this->as('member')->gql($query, ['id' => $document->id, 'r' => 9])->assertJsonPath('data.documentVersion', null);
        $this->as('stranger')->gql($query, ['id' => $document->id, 'r' => 1])->assertJsonPath('data.documentVersion', null);

        $private = $this->make('manager', DocumentAccess::NONE);
        $this->as('member')->gql($query, ['id' => $private->id, 'r' => 1])->assertJsonPath('data.documentVersion', null);
    }

    public function test_restoring_writes_a_new_version_with_the_old_content_and_overwrites_nothing(): void
    {
        $document = $this->make('owner', DocumentAccess::EDIT, ['title' => 'Alt', 'content' => 'Alter Text
']);
        $this->as('owner')->gql(self::UPDATE, ['id' => $document->id, 'rev' => 1, 'in' => ['title' => 'Neu', 'contentJson' => $this->doc('Neuer Text')]])->assertJsonMissingPath('errors');
        $restore = 'mutation ($id: ID!, $r: Int!) { restoreDocumentVersion(id: $id, revision: $r) { title revision content updatedBy { id } versions { revision restoredFrom } } }';

        $this->as('member')->gql($restore, ['id' => $document->id, 'r' => 1])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.restoreDocumentVersion.title', 'Alt')
            ->assertJsonPath('data.restoreDocumentVersion.revision', 3)
            ->assertJsonPath('data.restoreDocumentVersion.content', 'Alter Text
')
            ->assertJsonPath('data.restoreDocumentVersion.updatedBy.id', (string) $this->users['member']->id)
            ->assertJsonPath('data.restoreDocumentVersion.versions.0.restoredFrom', 1);

        $this->assertSame([3, 2, 1], $document->versions()->pluck('revision')->all());
        $this->assertSame('Neuer Text', $document->versions()->where('revision', 2)->sole()->content, 'the version in between stays as it was');
        $this->assertSame('Alter Text
', $document->versions()->where('revision', 1)->sole()->content);
    }

    public function test_restoring_the_current_state_changes_nothing_and_an_unknown_version_is_an_error(): void
    {
        $document = $this->make('owner', DocumentAccess::EDIT);
        $restore = 'mutation ($id: ID!, $r: Int!) { restoreDocumentVersion(id: $id, revision: $r) { revision } }';

        $this->as('owner')->gql($restore, ['id' => $document->id, 'r' => 1])->assertJsonPath('data.restoreDocumentVersion.revision', 1);
        $this->as('owner')->gql($restore, ['id' => $document->id, 'r' => 7])->assertGraphQLValidationKeys(['revision']);

        $this->assertSame(1, $document->versions()->count());
    }

    public function test_restoring_needs_the_edit_level_and_the_right_like_saving(): void
    {
        $document = $this->make('owner', DocumentAccess::READ);
        $restore = 'mutation ($id: ID!) { restoreDocumentVersion(id: $id, revision: 1) { id } }';

        $this->assertUnauthorized($this->as('member')->gql($restore, ['id' => $document->id]));
        $this->assertUnauthorized($this->as('stranger')->gql($restore, ['id' => $document->id]));
    }

    public function test_there_is_no_way_to_change_or_delete_a_version(): void
    {
        $mutations = $this->app->make(SchemaBuilder::class)->schema()->getMutationType()->getFieldNames();

        $this->assertSame(['restoreDocumentVersion'], array_values(array_filter($mutations, fn (string $name): bool => str_contains(strtolower($name), 'version'))));
        $this->assertArrayNotHasKey('DocumentVersionInput', $this->app->make(SchemaBuilder::class)->schema()->getTypeMap());
    }
}
