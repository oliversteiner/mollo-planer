<?php

namespace Tests\Feature\GraphQL;

use App\Models\Comment;

/**
 * „bearbeitet“ follows the text, not updated_at (docs/fix-m2.md F5.6):
 * deleting and restoring a comment leave editedAt alone.
 */
class CommentEditedTest extends GraphQLTestCase
{
    private const COMMENTS = 'query ($id: ID!) { task(id: $id) { comments { id editedAt } } }';

    private const UPDATE = 'mutation ($id: ID!, $in: UpdateCommentInput!) { updateComment(id: $id, input: $in) { editedAt } }';

    private Comment $comment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comment = Comment::factory()->for($this->task)->for($this->users['member'], 'author')->create(['body' => $this->doc('Erst')]);
    }

    public function test_a_new_comment_is_not_edited(): void
    {
        $this->as('member')->gql(self::COMMENTS, ['id' => $this->task->id])
            ->assertJsonPath('data.task.comments.0.editedAt', null);
    }

    public function test_deleting_and_restoring_leave_it_unedited(): void
    {
        $this->travel(1)->hour();
        $deletionId = $this->as('member')->gql('mutation ($id: ID!) { deleteComment(id: $id) { deletionId } }', ['id' => $this->comment->id])
            ->json('data.deleteComment.deletionId');
        $this->travel(1)->hour();
        $this->as('member')->gql('mutation ($id: ID!) { restoreFromTrash(deletionId: $id) { id } }', ['id' => $deletionId])
            ->assertJsonMissingPath('errors');

        $this->assertNull($this->comment->fresh()->edited_at);
        $this->as('member')->gql(self::COMMENTS, ['id' => $this->task->id])
            ->assertJsonPath('data.task.comments.0.editedAt', null);
    }

    public function test_changing_the_text_marks_it_edited(): void
    {
        $this->travel(1)->hour();

        $editedAt = $this->as('member')->gql(self::UPDATE, ['id' => $this->comment->id, 'in' => ['body' => $this->doc('Neu')]])
            ->assertJsonMissingPath('errors')
            ->json('data.updateComment.editedAt');

        $this->assertNotNull($editedAt);
        $this->assertTrue($this->comment->fresh()->edited_at->isSameMinute(now()));
    }

    public function test_saving_the_same_text_does_not(): void
    {
        $this->as('member')->gql(self::UPDATE, ['id' => $this->comment->id, 'in' => ['body' => $this->doc('Erst')]])
            ->assertJsonPath('data.updateComment.editedAt', null);
    }
}
