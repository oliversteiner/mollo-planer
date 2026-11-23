<?php

namespace Tests\Feature;

use App\Enums\DocumentAccess;
use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\User;
use App\Services\Deletion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/** D1 of docs/dokumentenverwaltung.md: tables, models, versions, trash. */
class DocumentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_document_is_readable_for_all_members_of_the_kind_other_in_revision_one(): void
    {
        $document = new Document(['title' => 'Notizen', 'content' => "Text\n"]);
        $document->project_id = Project::factory()->create()->id;
        $document->save();

        $document->refresh();
        $this->assertSame(DocumentAccess::READ, $document->project_access);
        $this->assertSame(DocumentKind::OTHER, $document->kind);
        $this->assertSame(1, $document->revision);
        $this->assertNull($document->owner_id);
    }

    public function test_the_factory_writes_the_first_version_with_the_content_of_the_document(): void
    {
        $document = Document::factory()->create(['kind' => DocumentKind::MINUTES]);

        $version = $document->versions()->sole();
        $this->assertSame([1, $document->title, DocumentKind::MINUTES, $document->content], [$version->revision, $version->title, $version->kind, $version->content]);
        $this->assertSame($document->owner_id, $version->created_by);
    }

    public function test_versions_are_never_changed_or_deleted_one_by_one(): void
    {
        $version = Document::factory()->create()->versions()->sole();

        $this->expectException(LogicException::class);
        $version->update(['content' => 'changed']);
    }

    public function test_a_version_cannot_be_deleted_on_its_own(): void
    {
        $version = Document::factory()->create()->versions()->sole();

        $this->expectException(LogicException::class);
        $version->delete();
    }

    public function test_a_revision_exists_once_per_document(): void
    {
        $document = Document::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);
        $document->versions()->create(['revision' => 1, 'title' => 'x', 'kind' => DocumentKind::OTHER, 'content' => 'x']);
    }

    public function test_a_person_is_shared_once_per_document(): void
    {
        $document = Document::factory()->create();
        $user = User::factory()->create();
        $document->shares()->create(['user_id' => $user->id, 'access' => 'edit']);

        $this->expectException(UniqueConstraintViolationException::class);
        $document->shares()->create(['user_id' => $user->id, 'access' => 'read']);
    }

    public function test_a_deleted_owner_leaves_the_document_without_owner_and_the_versions_stay(): void
    {
        $document = Document::factory()->create();
        $document->owner->delete();

        $this->assertNull($document->fresh()->owner_id);
        $this->assertSame(1, $document->versions()->count());
    }

    public function test_deleting_a_project_takes_its_documents_along_with_the_same_deletion_id_and_restoring_brings_them_back(): void
    {
        $project = Project::factory()->create();
        $own = Document::factory()->for($project)->create();
        $deletedBefore = Document::factory()->for($project)->create();
        app(Deletion::class)->delete($deletedBefore);

        $deletionId = app(Deletion::class)->delete($project)->deletion_id;

        $this->assertSame($deletionId, Document::withTrashed()->findOrFail($own->id)->deletion_id);
        $this->assertNotSame($deletionId, Document::withTrashed()->findOrFail($deletedBefore->id)->deletion_id);

        app(Deletion::class)->restore($deletionId, User::factory()->create());

        $this->assertNull(Document::query()->findOrFail($own->id)->deleted_at);
        $this->assertTrue(Document::withTrashed()->findOrFail($deletedBefore->id)->trashed());
    }

    public function test_a_document_is_restored_alone_and_only_while_its_project_is_there(): void
    {
        $document = Document::factory()->create();
        $deletion = app(Deletion::class);
        $deletionId = $deletion->delete($document)->deletion_id;

        $this->assertInstanceOf(Document::class, $deletion->topOf($deletionId));
        $deletion->restore($deletionId, User::factory()->create());

        $this->assertFalse($document->fresh()->trashed());
    }

    public function test_deleting_for_good_removes_the_document_with_its_versions_and_shares(): void
    {
        $document = Document::factory()->create();
        $document->shares()->create(['user_id' => User::factory()->create()->id, 'access' => 'read']);
        $deletionId = app(Deletion::class)->delete($document)->deletion_id;

        app(Deletion::class)->purge($deletionId);

        $this->assertSame(0, Document::withTrashed()->count() + DocumentVersion::count() + $document->shares()->count());
    }

    public function test_the_tables_have_the_columns_of_the_plan(): void
    {
        $this->assertTrue(Schema::hasColumns('documents', ['project_id', 'owner_id', 'title', 'kind', 'content', 'project_access', 'revision', 'updated_by', 'deleted_at', 'deleted_by', 'deletion_id']));
        $this->assertTrue(Schema::hasColumns('document_shares', ['document_id', 'user_id', 'access']));
        $this->assertTrue(Schema::hasColumns('document_versions', ['document_id', 'revision', 'title', 'kind', 'content', 'created_by', 'contributors', 'restored_from', 'created_at']));
    }
}
