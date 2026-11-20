<?php

namespace Tests\Feature;

use App\Enums\DocumentAccess;
use App\Enums\DocumentShareAccess;
use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\Project;
use App\Models\User;
use App\Services\Deletion;
use App\Services\DocumentAccessService;
use App\Services\ProjectMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\TestCase;

/** D2 of docs/dokumentenverwaltung.md: effective access (§4.2), policy, ground rules (§4.3). */
class DocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    /** @var array<string, User> */
    private array $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = [
            'owner' => User::factory()->create(),
            'manager' => User::factory()->create(),
            'author' => User::factory()->create(),
            'member' => User::factory()->create(),
            'guest' => User::factory()->create(),
            'stranger' => User::factory()->create(),
            'admin' => User::factory()->admin()->create(),
            'readonly' => User::factory()->create(['role' => UserRole::LESEND]),
        ];

        $this->project = Project::factory()
            ->withMember($this->users['owner'], ProjectRole::OWNER)
            ->withMember($this->users['manager'], ProjectRole::MANAGER)
            ->withMember($this->users['author'], ProjectRole::MEMBER)
            ->withMember($this->users['member'], ProjectRole::MEMBER)
            ->withMember($this->users['guest'], ProjectRole::GUEST)
            ->withMember($this->users['readonly'], ProjectRole::MEMBER)
            ->create();
    }

    private function document(DocumentAccess $access = DocumentAccess::READ, string $owner = 'author'): Document
    {
        return Document::factory()->for($this->project)->create(['owner_id' => $this->users[$owner]->id, 'project_access' => $access]);
    }

    private function level(string $who, Document $document): DocumentAccess
    {
        return app(DocumentAccessService::class)->levelOf($this->users[$who], $document->fresh());
    }

    /** @return array<string, string> who => level for the project access of a document */
    private function levels(DocumentAccess $access): array
    {
        $document = $this->document($access);

        return collect(array_keys($this->users))->mapWithKeys(fn (string $who): array => [$who => $this->level($who, $document)->value])->all();
    }

    public function test_a_private_document_belongs_to_its_owner_and_those_who_manage_documents(): void
    {
        $this->assertSame([
            'owner' => 'manage', 'manager' => 'manage', 'author' => 'manage', 'member' => 'none',
            'guest' => 'none', 'stranger' => 'none', 'admin' => 'manage', 'readonly' => 'none',
        ], $this->levels(DocumentAccess::NONE));
    }

    public function test_an_owner_who_is_no_member_has_no_access_and_does_not_see_the_document(): void
    {
        $document = $this->document(DocumentAccess::EDIT, 'stranger');
        $service = app(DocumentAccessService::class);

        $this->assertSame(DocumentAccess::NONE, $service->levelOf($this->users['stranger'], $document));
        $this->assertFalse(Gate::forUser($this->users['stranger'])->allows('view', $document));
        $this->assertNotContains($document->id, Document::query()->visibleTo($this->users['stranger'])->pluck('id')->all());
        $this->assertContains($document->id, Document::query()->visibleTo($this->users['member'])->pluck('id')->all());
    }

    public function test_a_document_for_reading_is_read_by_all_members_and_by_nobody_else(): void
    {
        $this->assertSame([
            'owner' => 'manage', 'manager' => 'manage', 'author' => 'manage', 'member' => 'read',
            'guest' => 'read', 'stranger' => 'none', 'admin' => 'manage', 'readonly' => 'read',
        ], $this->levels(DocumentAccess::READ));
    }

    public function test_a_document_for_editing_is_edited_by_members_with_the_right_and_only_read_by_the_others(): void
    {
        $this->assertSame([
            'owner' => 'manage', 'manager' => 'manage', 'author' => 'manage', 'member' => 'edit',
            'guest' => 'read', 'stranger' => 'none', 'admin' => 'manage', 'readonly' => 'read',
        ], $this->levels(DocumentAccess::EDIT));
    }

    public function test_a_share_raises_the_level_of_one_person_but_never_above_the_rights_of_the_person(): void
    {
        $document = $this->document(DocumentAccess::NONE);
        foreach (['member' => 'edit', 'guest' => 'edit', 'readonly' => 'edit'] as $who => $access) {
            $document->shares()->create(['user_id' => $this->users[$who]->id, 'access' => $access]);
        }

        $this->assertSame('edit', $this->level('member', $document)->value);
        $this->assertSame('read', $this->level('guest', $document)->value, 'a guest has no document.update');
        $this->assertSame('read', $this->level('readonly', $document)->value, 'the system role "Lesend" caps the share');
    }

    public function test_the_higher_of_share_and_project_level_wins(): void
    {
        $document = $this->document(DocumentAccess::EDIT);
        $document->shares()->create(['user_id' => $this->users['member']->id, 'access' => DocumentShareAccess::READ]);

        $this->assertSame('edit', $this->level('member', $document)->value);
    }

    public function test_an_archived_project_leaves_documents_readable_only(): void
    {
        $document = $this->document(DocumentAccess::EDIT);
        $this->project->forceFill(['archived_at' => now()])->save();

        foreach (['owner', 'manager', 'author', 'member', 'admin'] as $who) {
            $this->assertSame('read', $this->level($who, $document)->value, $who);
        }
        $this->assertSame('none', $this->level('stranger', $document)->value);
    }

    public function test_a_document_in_the_trash_is_seen_only_by_those_who_manage_it(): void
    {
        $document = $this->document(DocumentAccess::READ);
        app(Deletion::class)->delete($document);

        $this->assertSame(['manage', 'manage', 'manage', 'none', 'none'], array_map(fn (string $who): string => $this->level($who, Document::withTrashed()->findOrFail($document->id))->value, ['owner', 'manager', 'author', 'member', 'guest']));
    }

    public function test_the_list_scope_and_the_level_agree_for_everybody_in_every_situation(): void
    {
        $documents = [
            $this->document(DocumentAccess::NONE),
            $this->document(DocumentAccess::READ),
            $this->document(DocumentAccess::EDIT),
            $this->document(DocumentAccess::NONE, 'manager'),
        ];
        $documents[0]->shares()->create(['user_id' => $this->users['guest']->id, 'access' => 'read']);
        $other = Project::factory()->withMember($this->users['stranger'], ProjectRole::OWNER)->create();
        $documents[] = Document::factory()->for($other)->create(['owner_id' => $this->users['stranger']->id, 'project_access' => DocumentAccess::READ]);
        $archived = Project::factory()->withMember($this->users['manager'], ProjectRole::MANAGER)->create(['archived_at' => now()]);
        $documents[] = Document::factory()->for($archived)->create(['owner_id' => null, 'project_access' => DocumentAccess::READ]);

        foreach ($this->users as $who => $user) {
            $expected = collect($documents)->filter(fn (Document $document): bool => app(DocumentAccessService::class)->levelOf($user, $document->fresh())->atLeast(DocumentAccess::READ))->pluck('id')->sort()->values()->all();
            $actual = Document::query()->visibleTo($user)->pluck('id')->sort()->values()->all();

            $this->assertSame($expected, $actual, "list and level disagree for {$who}");
        }
    }

    public function test_the_policy_turns_the_level_into_abilities(): void
    {
        $document = $this->document(DocumentAccess::EDIT);
        $can = fn (string $who, string $ability): bool => Gate::forUser($this->users[$who])->allows($ability, $document->fresh());

        $this->assertSame([true, true, false], [$can('member', 'view'), $can('member', 'update'), $can('member', 'manage')]);
        $this->assertSame([true, false, false], [$can('guest', 'view'), $can('guest', 'update'), $can('guest', 'delete')]);
        $this->assertSame([true, true, true], [$can('author', 'view'), $can('author', 'update'), $can('author', 'delete')]);
        $this->assertSame([true, true, true], [$can('manager', 'view'), $can('manager', 'update'), $can('manager', 'manage')]);
        $this->assertSame([false, false, false], [$can('stranger', 'view'), $can('stranger', 'update'), $can('stranger', 'manage')]);
        $this->assertTrue(Gate::forUser($this->users['member'])->allows('createDocument', $this->project));
        $this->assertFalse(Gate::forUser($this->users['guest'])->allows('createDocument', $this->project));
        $this->assertFalse(Gate::forUser($this->users['readonly'])->allows('createDocument', $this->project));
        $this->assertFalse(Gate::forUser($this->users['stranger'])->allows('createDocument', $this->project));
    }

    public function test_in_an_archived_project_writing_is_refused_with_the_archive_message_and_reading_stays(): void
    {
        $document = $this->document(DocumentAccess::EDIT);
        $this->project->forceFill(['archived_at' => now()])->save();

        $response = Gate::forUser($this->users['author'])->inspect('update', $document->fresh());
        $this->assertTrue($response->denied());
        $this->assertSame(__('projects.archived'), $response->message());
        $this->assertTrue(Gate::forUser($this->users['member'])->allows('view', $document->fresh()));
        $this->assertFalse(Gate::forUser($this->users['stranger'])->allows('update', $document->fresh()));
        $this->assertFalse(Gate::forUser($this->users['author'])->allows('createDocument', $this->project->fresh()));
    }

    public function test_restoring_is_for_the_owner_and_those_who_restore_in_the_project_and_deleting_for_good_for_the_owner_role(): void
    {
        $document = $this->document(DocumentAccess::READ);
        app(Deletion::class)->delete($document);
        $trashed = Document::withTrashed()->findOrFail($document->id);
        $can = fn (string $who, string $ability): bool => Gate::forUser($this->users[$who])->allows($ability, $trashed);

        $this->assertSame([true, true, false, false], [$can('author', 'restore'), $can('manager', 'restore'), $can('member', 'restore'), $can('stranger', 'restore')]);
        $this->assertSame([true, false, false], [$can('owner', 'forceDelete'), $can('manager', 'forceDelete'), $can('author', 'forceDelete')]);
    }

    public function test_the_project_of_a_document_never_changes(): void
    {
        $document = $this->document();

        $document->project_id = Project::factory()->create()->id;

        $this->expectException(LogicException::class);
        $document->save();
    }

    public function test_a_member_who_leaves_loses_the_shares_and_the_ownership_but_the_versions_stay(): void
    {
        $document = $this->document(DocumentAccess::NONE, 'member');
        $document->shares()->create(['user_id' => $this->users['guest']->id, 'access' => 'read']);
        $other = Document::factory()->for(Project::factory()->withMember($this->users['guest'], ProjectRole::MEMBER)->create())->create();
        $other->shares()->create(['user_id' => $this->users['guest']->id, 'access' => 'read']);

        DB::transaction(function (): void {
            app(ProjectMembership::class)->remove($this->users['owner'], $this->project, $this->users['guest']);
            app(ProjectMembership::class)->remove($this->users['owner'], $this->project, $this->users['member']);
        });

        $this->assertSame(0, DocumentShare::where('document_id', $document->id)->count());
        $this->assertSame(1, DocumentShare::where('document_id', $other->id)->count(), 'shares in other projects stay');
        $this->assertNull($document->fresh()->owner_id);
        $this->assertSame(1, $document->versions()->count());
        $this->assertSame('manage', $this->level('manager', $document)->value, 'without an owner those with document.manageAny manage it');
    }

    public function test_existing_installations_get_the_document_rights_without_a_reset_of_the_settings(): void
    {
        $contribute = DB::table('functions')->where(['scope' => 'project', 'key' => 'contribute'])->value('id');
        DB::table('function_rights')->where('function_id', $contribute)->whereIn('right', ['document.create', 'document.update'])->delete();
        DB::table('function_rights')->where('function_id', $contribute)->where('right', 'task.move')->delete(); // a change made in the settings

        (require database_path('migrations/2026_11_19_210000_add_document_rights_to_standard_functions.php'))->up();

        $rights = DB::table('function_rights')->where('function_id', $contribute)->pluck('right')->all();
        $this->assertContains('document.create', $rights);
        $this->assertContains('document.update', $rights);
        $this->assertNotContains('task.move', $rights, 'the migration does not reset other rights');
    }
}
