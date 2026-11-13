<?php

namespace Tests\Feature\GraphQL;

use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Tests\TestCase;

/**
 * The mutations an archived project refuses (schema-review A5: archived means
 * read only). The archive logic comes later; this list is fixed now, and every
 * mutation of the schema must be either on it or excluded with a reason. A new
 * mutation therefore gets a decision here, and nobody forgets the archive rule.
 */
class ArchivedProjectMutationsTest extends TestCase
{
    /** Writes into a project: refused while the project is archived. */
    private const REFUSED_WHEN_ARCHIVED = [
        'addComment',
        'assignTask',
        'createMilestone',
        'createTask',
        'createTodo',
        'deleteComment',
        'deleteMilestone',
        'deleteProject',
        'deleteTask',
        'deleteTodo',
        'moveMilestone',
        'moveTask',
        'moveTaskToTodo',
        'removeProjectMember',
        'setProjectLabelSet',
        'setProjectMember',
        'setTaskLabels',
        'updateComment',
        'updateMilestone',
        'updateProject',
        'updateTask',
        'updateTodo',
    ];

    /** Not a write into one project, so the archive rule does not apply. */
    private const EXCLUDED = [
        'approveUser' => 'user administration',
        'createApiToken' => 'personal token',
        'createLabelSet' => 'label sets belong to no project',
        'createProject' => 'a new project is not archived',
        'createUser' => 'user administration',
        'deleteApiToken' => 'personal token',
        'deleteLabelSet' => 'label sets belong to no project',
        'deleteUser' => 'user administration',
        'deleteUserAvatar' => 'user profile',
        'draftProjectPlan' => 'writes nothing',
        'duplicateLabelSet' => 'label sets belong to no project',
        'importProjectPlan' => 'creates a new project',
        'rejectUser' => 'user administration',
        'resetUserTwoFactor' => 'user administration',
        'updateAppSettings' => 'application settings',
        'updateLabelSet' => 'label sets belong to no project',
        'updateMyAvatar' => 'user profile',
        'updateMyNotifications' => 'user profile',
        'updateUser' => 'user administration',
    ];

    public function test_every_schema_mutation_is_either_refused_when_archived_or_excluded(): void
    {
        $schemaMutations = $this->app->make(SchemaBuilder::class)->schema()->getMutationType()->getFieldNames();

        $unclassified = array_values(array_diff($schemaMutations, self::REFUSED_WHEN_ARCHIVED, array_keys(self::EXCLUDED)));
        sort($unclassified);

        $this->assertSame([], $unclassified, 'Mutations without an archive decision: '.implode(', ', $unclassified));
    }

    public function test_the_lists_name_only_mutations_of_the_schema(): void
    {
        $schemaMutations = $this->app->make(SchemaBuilder::class)->schema()->getMutationType()->getFieldNames();

        $unknown = array_values(array_diff([...self::REFUSED_WHEN_ARCHIVED, ...array_keys(self::EXCLUDED)], $schemaMutations));

        $this->assertSame([], $unknown, 'Listed mutations the schema does not have: '.implode(', ', $unknown));
    }

    public function test_a_mutation_is_never_on_both_lists(): void
    {
        $this->assertSame([], array_values(array_intersect(self::REFUSED_WHEN_ARCHIVED, array_keys(self::EXCLUDED))));
    }
}
