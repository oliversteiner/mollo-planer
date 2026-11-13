<?php

namespace Tests\Feature\GraphQL;

use App\Models\Task;

/**
 * The user story of a task (schema-review A6): three plain-text parts, all or none.
 */
class UserStoryTest extends GraphQLTestCase
{
    private const STORY = ['role' => 'Mitglied', 'goal' => 'meine Tasks nach Status filtern', 'benefit' => 'ich in Meetings schnell den Stand zeige'];

    private const CREATE = 'mutation ($in: CreateTaskInput!) { createTask(input: $in) { id userStory { role goal benefit } } }';

    private const UPDATE = 'mutation ($id: ID!, $in: UpdateTaskInput!) { updateTask(id: $id, input: $in) { id userStory { role goal benefit } } }';

    public function test_a_story_is_stored_and_returned_on_create(): void
    {
        $this->as('manager')->gql(self::CREATE, ['in' => ['todoId' => $this->todo->id, 'title' => 'Mit Story', 'userStory' => self::STORY]])
            ->assertJsonPath('data.createTask.userStory', self::STORY);
    }

    public function test_a_story_can_be_set_changed_and_removed(): void
    {
        $this->as('manager')->gql(self::UPDATE, ['id' => $this->task->id, 'in' => ['userStory' => self::STORY]])
            ->assertJsonPath('data.updateTask.userStory', self::STORY);

        $this->as('manager')->gql(self::UPDATE, ['id' => $this->task->id, 'in' => ['userStory' => null]])
            ->assertJsonPath('data.updateTask.userStory', null);

        $this->assertNull($this->task->fresh()->user_story);
    }

    public function test_every_part_is_required_and_has_its_limit(): void
    {
        $missingBenefit = ['role' => 'Mitglied', 'goal' => 'etwas'];

        $response = $this->as('manager')->gql(self::UPDATE, ['id' => $this->task->id, 'in' => ['userStory' => $missingBenefit]]);
        $this->assertStringContainsString('"benefit"', $response->json('errors.0.message'));

        $tooLongRole = self::STORY;
        $tooLongRole['role'] = str_repeat('a', 81);
        $this->as('manager')->gql(self::UPDATE, ['id' => $this->task->id, 'in' => ['userStory' => $tooLongRole]])
            ->assertGraphQLValidationKeys(['input.userStory.role']);

        $this->assertNull(Task::query()->find($this->task->id)->user_story);
    }
}
