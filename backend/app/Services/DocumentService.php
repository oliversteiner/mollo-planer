<?php

namespace App\Services;

use App\Enums\DocumentAccess;
use App\Enums\DocumentKind;
use App\GraphQL\Errors\DocumentConflict;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Support\DocumentContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating, changing, sharing and handing over documents (docs/dokumentenverwaltung.md §3, §4, §6). Who may do it is
 * checked before (DocumentPolicy). Every save that changes something writes a version in the same transaction;
 * owner and updated_by come from the person who acts, never from the input.
 */
final class DocumentService
{
    /**
     * @param  array{title: string, kind?: string|null, contentJson: array<string, mixed>, projectAccess?: string|null}  $input
     */
    public function create(User $actor, Project $project, array $input): Document
    {
        return DB::transaction(function () use ($actor, $project, $input): Document {
            $document = new Document([
                'title' => trim($input['title']),
                'kind' => DocumentKind::from($input['kind'] ?? DocumentKind::OTHER->value),
                'content' => DocumentContent::markdownFromJson($input['contentJson']),
                'project_access' => $this->projectAccess($input['projectAccess'] ?? DocumentAccess::READ->value),
            ]);
            $document->project_id = $project->getKey();
            $document->owner_id = $actor->getKey();
            $document->updated_by = $actor->getKey();
            $document->revision = 1;
            $document->save();

            $this->writeVersion($document, $actor);

            return $document;
        });
    }

    /**
     * Title, kind and content. An editor that loaded an older revision gets DocumentConflict and nothing is saved.
     * Without a change (same title, kind and, after the conversion, same Markdown) nothing is written either.
     *
     * @param  array{title?: string|null, kind?: string|null, contentJson?: array<string, mixed>|null}  $input
     *
     * @throws DocumentConflict
     */
    public function update(User $actor, Document $document, int $revision, array $input): Document
    {
        return DB::transaction(function () use ($actor, $document, $revision, $input): Document {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->getKey());

            if ($locked->revision !== $revision) {
                throw new DocumentConflict($locked->revision);
            }

            $title = isset($input['title']) ? trim($input['title']) : $locked->title;
            $kind = isset($input['kind']) ? DocumentKind::from($input['kind']) : $locked->kind;
            $content = isset($input['contentJson']) ? DocumentContent::markdownFromJson($input['contentJson']) : $locked->content;

            if ($title === $locked->title && $kind === $locked->kind && $content === $locked->content) {
                return $locked;
            }

            $locked->forceFill(['title' => $title, 'kind' => $kind, 'content' => $content, 'updated_by' => $actor->getKey(), 'revision' => $locked->revision + 1])->save();
            $this->writeVersion($locked, $actor);

            return $locked;
        });
    }

    /**
     * Brings an old version back: a NEW version with the old title, kind and content is written, nothing is
     * overwritten (§3). It remembers the revision it was made from. Restoring what is the current state changes
     * nothing.
     *
     * @throws ValidationException No such version
     */
    public function restoreVersion(User $actor, Document $document, int $revision): Document
    {
        return DB::transaction(function () use ($actor, $document, $revision): Document {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->getKey());
            $version = $locked->versions()->where('revision', $revision)->first();

            if ($version === null) {
                throw ValidationException::withMessages(['revision' => __('documents.version_unknown')]);
            }

            if ($version->title === $locked->title && $version->kind === $locked->kind && $version->content === $locked->content) {
                return $locked;
            }

            $locked->forceFill(['title' => $version->title, 'kind' => $version->kind, 'content' => $version->content, 'updated_by' => $actor->getKey(), 'revision' => $locked->revision + 1])->save();
            $this->writeVersion($locked, $actor, $revision);

            return $locked;
        });
    }

    /**
     * The level for all members and the shares per person; the list replaces the old one. Only members of the
     * project can be in it (§4.3 rule 1). The revision does not change.
     *
     * @param  array{projectAccess?: string|null, shares?: list<array{userId: int|string, access: string}>|null}  $input
     *
     * @throws ValidationException
     */
    public function setSharing(Document $document, array $input): Document
    {
        $shares = $input['shares'] ?? null;

        if ($shares !== null) {
            $this->assertMembers($document->project, array_column($shares, 'userId'), 'input.shares', 'documents.share_not_member');
        }

        return DB::transaction(function () use ($document, $input, $shares): Document {
            Document::withoutTimestamps(function () use ($document, $input): void {
                if (isset($input['projectAccess'])) {
                    $document->project_access = $this->projectAccess($input['projectAccess']);
                    $document->save();
                }
            });

            if ($shares !== null) {
                $document->shares()->delete();

                foreach ($shares as $share) {
                    $document->shares()->updateOrCreate(['user_id' => $share['userId']], ['access' => $share['access']]);
                }
            }

            return $document->unsetRelation('shares');
        });
    }

    /**
     * @throws ValidationException
     */
    public function transfer(Document $document, int|string $ownerId): Document
    {
        $this->assertMembers($document->project, [$ownerId], 'ownerId', 'documents.owner_not_member');

        DB::transaction(fn () => Document::withoutTimestamps(function () use ($document, $ownerId): void {
            $document->owner_id = $ownerId;
            $document->save();
        }));

        return $document;
    }

    private function projectAccess(string $value): DocumentAccess
    {
        $access = DocumentAccess::from($value);

        if ($access === DocumentAccess::MANAGE) {
            throw ValidationException::withMessages(['input.projectAccess' => __('documents.access_not_allowed')]);
        }

        return $access;
    }

    /**
     * @param  list<int|string>  $userIds
     *
     * @throws ValidationException
     */
    private function assertMembers(Project $project, array $userIds, string $field, string $message): void
    {
        $members = $project->members()->whereIn('users.id', $userIds)->pluck('users.id')->map(fn ($id): int => (int) $id)->all();

        foreach ($userIds as $userId) {
            if (! in_array((int) $userId, $members, true)) {
                throw ValidationException::withMessages([$field => __($message)]);
            }
        }
    }

    private function writeVersion(Document $document, User $actor, ?int $restoredFrom = null): void
    {
        $document->versions()->create([
            'revision' => $document->revision,
            'title' => $document->title,
            'kind' => $document->kind,
            'content' => $document->content,
            'created_by' => $actor->getKey(),
            'restored_from' => $restoredFrom,
        ]);
    }
}
