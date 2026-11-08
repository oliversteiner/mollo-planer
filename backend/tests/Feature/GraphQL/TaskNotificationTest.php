<?php

namespace Tests\Feature\GraphQL;

use App\Enums\Locale;
use App\Models\Comment;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use App\Services\ProjectMembership;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Who gets which mail, in which language, and that it is queued after commit.
 */
class TaskNotificationTest extends GraphQLTestCase
{
    private const ASSIGN = 'mutation ($id: ID!, $u: ID) { assignTask(id: $id, assigneeId: $u) { id } }';

    private const CREATE = 'mutation ($in: CreateTaskInput!) { createTask(input: $in) { id } }';

    private const COMMENT = 'mutation ($in: AddCommentInput!) { addComment(input: $in) { id } }';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    // ─── Assignment ──────────────────────────────────────────────

    public function test_new_task_with_assignee_mails_the_assignee(): void
    {
        $this->as('manager')->gql(self::CREATE, ['in' => [
            'todoId' => $this->todo->id, 'title' => 'Neuer Task', 'assigneeId' => $this->users['member']->id,
        ]])->assertJsonMissingPath('errors');

        Notification::assertSentTo($this->users['member'], TaskAssigned::class,
            fn (TaskAssigned $n) => $n->task->title === 'Neuer Task' && $n->actorName === 'Chiara Manager');
        Notification::assertCount(1);
    }

    public function test_new_task_without_assignee_mails_nobody(): void
    {
        $this->as('manager')->gql(self::CREATE, ['in' => ['todoId' => $this->todo->id, 'title' => 'Neuer Task']]);

        Notification::assertNothingSent();
    }

    public function test_assignment_mails_the_new_assignee_once(): void
    {
        $this->as('owner')->gql(self::ASSIGN, ['id' => $this->task->id, 'u' => $this->users['member']->id]);
        $this->as('owner')->gql(self::ASSIGN, ['id' => $this->task->id, 'u' => $this->users['member']->id]);

        Notification::assertSentToTimes($this->users['member'], TaskAssigned::class, 1);
    }

    public function test_unassigning_mails_nobody(): void
    {
        $this->assign('member');

        $this->as('owner')->gql(self::ASSIGN, ['id' => $this->task->id, 'u' => null]);

        Notification::assertNothingSent();
    }

    public function test_assigning_yourself_sends_no_mail(): void
    {
        $this->as('manager')->gql(self::ASSIGN, ['id' => $this->task->id, 'u' => $this->users['manager']->id]);

        Notification::assertNothingSent();
    }

    public function test_no_mail_when_turned_off(): void
    {
        $this->users['member']->forceFill(['notify_by_email' => false])->save();

        $this->as('owner')->gql(self::ASSIGN, ['id' => $this->task->id, 'u' => $this->users['member']->id]);

        Notification::assertNothingSent();
    }

    // ─── Comments ────────────────────────────────────────────────

    public function test_comment_mails_assignee_and_earlier_commenters_but_not_the_author(): void
    {
        $this->assign('member');
        $this->commentBy('owner');
        $this->commentBy('manager');

        Notification::fake();

        $this->as('manager')->gql(self::COMMENT, ['in' => ['taskId' => $this->task->id, 'body' => $this->doc('Neu')]])
            ->assertJsonMissingPath('errors');

        Notification::assertSentTo($this->users['member'], TaskCommented::class);
        Notification::assertSentTo($this->users['owner'], TaskCommented::class);
        Notification::assertNotSentTo($this->users['manager'], TaskCommented::class);
        Notification::assertCount(2);
    }

    public function test_comment_on_unassigned_task_without_other_commenters_mails_nobody(): void
    {
        $this->as('member')->gql(self::COMMENT, ['in' => ['taskId' => $this->task->id, 'body' => $this->doc('Hallo')]]);

        Notification::assertNothingSent();
    }

    public function test_removed_members_and_users_with_mails_off_get_no_comment_mail(): void
    {
        $this->commentBy('member');
        $this->commentBy('manager');
        app(ProjectMembership::class)->remove($this->users['admin'], $this->project, $this->users['member']);
        $this->users['manager']->forceFill(['notify_by_email' => false])->save();

        $this->as('owner')->gql(self::COMMENT, ['in' => ['taskId' => $this->task->id, 'body' => $this->doc('Neu')]]);

        Notification::assertNothingSent();
    }

    public function test_admin_who_commented_without_membership_gets_mails(): void
    {
        $this->commentBy('admin');

        $this->as('member')->gql(self::COMMENT, ['in' => ['taskId' => $this->task->id, 'body' => $this->doc('Neu')]]);

        Notification::assertSentTo($this->users['admin'], TaskCommented::class);
    }

    // ─── Queue, language, content ────────────────────────────────

    public function test_mails_are_queued_after_commit_in_the_project_language(): void
    {
        $this->project->update(['locale' => Locale::EN]);

        $this->as('owner')->gql(self::ASSIGN, ['id' => $this->task->id, 'u' => $this->users['member']->id]);

        Notification::assertSentTo($this->users['member'], TaskAssigned::class, function (TaskAssigned $n, array $channels) {
            $mail = $n->toMail($this->users['member']);

            return $channels === ['mail']
                && $n->afterCommit === true
                && $n->locale === 'en'
                && str_starts_with($mail->subject, '[FCA DB] Task assigned:');
        });
    }

    public function test_german_mail_contains_project_todo_and_link_to_the_task(): void
    {
        $this->task->update(['due_date' => '2026-12-24']);

        $this->as('owner')->gql(self::ASSIGN, ['id' => $this->task->id, 'u' => $this->users['member']->id]);

        Notification::assertSentTo($this->users['member'], TaskAssigned::class, function (TaskAssigned $n) {
            app()->setLocale($n->locale);
            $mail = $n->toMail($this->users['member']);
            $text = implode("\n", $mail->introLines);

            return str_contains($text, 'Ben Owner hat dir den Task')
                && str_contains($text, 'ToDo: '.$this->todo->title)
                && str_contains($text, 'Fällig am 24.12.2026.')
                && $mail->actionUrl === 'http://localhost:3000/projects/'.$this->project->id.'/kanban?task='.$this->task->id;
        });
    }

    public function test_markdown_in_titles_is_not_rendered_as_link(): void
    {
        $this->task->update(['title' => '[Klick](https://evil.example)']);

        $html = (string) (new TaskAssigned($this->task->fresh(), 'Ben Owner'))
            ->toMail($this->users['member'])
            ->render();

        $this->assertStringNotContainsString('href="https://evil.example"', $html);
        $this->assertStringContainsString('[Klick](https://evil.example)', $html);
    }

    /**
     * Every free-text value in a task mail is escaped: project, milestone,
     * todo and task titles, the actor and the recipient.
     */
    #[DataProvider('taskNotifications')]
    public function test_html_in_titles_and_names_is_escaped(string $notification): void
    {
        $script = '<script>alert("x")</script>';
        $this->project->update(['name' => 'Projekt '.$script]);
        $this->milestone->update(['title' => 'Milestone '.$script]);
        $this->todo->update(['title' => 'ToDo '.$script]);
        $this->task->update(['title' => 'Task '.$script]);
        $this->users['member']->update(['name' => 'David '.$script]);

        $html = (string) (new $notification($this->task->fresh(), 'Ben '.$script))
            ->toMail($this->users['member']->fresh())
            ->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function taskNotifications(): array
    {
        return ['assigned' => [TaskAssigned::class], 'commented' => [TaskCommented::class]];
    }

    public function test_should_send_checks_again_when_the_queue_runs(): void
    {
        $notification = new TaskCommented($this->task, 'Ben Owner');
        $member = $this->users['member'];

        $this->assertTrue($notification->shouldSend($member, 'mail'));

        $member->forceFill(['notify_by_email' => false])->save();
        $this->assertFalse($notification->shouldSend($member, 'mail'));

        $member->forceFill(['notify_by_email' => true])->save();
        $this->project->members()->detach($member);
        $this->assertFalse($notification->shouldSend($member->fresh(), 'mail'));
    }

    private function assign(string $name): void
    {
        $this->task->assigned_user_id = $this->users[$name]->id;
        $this->task->save();
    }

    private function commentBy(string $name): void
    {
        $comment = new Comment(['body' => $this->doc('Alt')]);
        $comment->task()->associate($this->task);
        $comment->author()->associate($this->users[$name]);
        $comment->save();
    }
}
