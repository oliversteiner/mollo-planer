<?php

namespace Tests\Feature\GraphQL;

use App\Http\Middleware\EnsureAccountReady;
use App\Models\User;
use App\Support\StrongAuthentication;
use Illuminate\Testing\TestResponse;

/**
 * Self-registered accounts: admins approve or reject them, and the API stays
 * locked for an account until its mail address is confirmed and it is approved.
 */
class AccountApprovalTest extends GraphQLTestCase
{
    private const APPROVE = 'mutation ($id: ID!) { approveUser(id: $id) { id } }';

    private const REJECT = 'mutation ($id: ID!) { rejectUser(id: $id) }';

    public function test_admin_approves_a_verified_account(): void
    {
        $waiting = User::factory()->unapproved()->create();

        $this->as('admin')->gql(self::APPROVE, ['id' => $waiting->id])
            ->assertJsonPath('data.approveUser.id', (string) $waiting->id);

        $this->assertTrue($waiting->fresh()->isApproved());
    }

    public function test_an_account_with_unconfirmed_mail_cannot_be_approved(): void
    {
        $waiting = User::factory()->unapproved()->unverified()->create();

        $this->assertUnauthorized($this->as('admin')->gql(self::APPROVE, ['id' => $waiting->id]));
        $this->assertFalse($waiting->fresh()->isApproved());
    }

    public function test_members_cannot_approve(): void
    {
        $waiting = User::factory()->unapproved()->create();

        $this->assertUnauthorized($this->as('member')->gql(self::APPROVE, ['id' => $waiting->id]));
        $this->assertFalse($waiting->fresh()->isApproved());
    }

    public function test_admin_rejects_a_waiting_account_and_it_is_deleted(): void
    {
        $waiting = User::factory()->unapproved()->create();

        $this->as('admin')->gql(self::REJECT, ['id' => $waiting->id])
            ->assertJsonPath('data.rejectUser', true);

        $this->assertNull(User::find($waiting->id));
    }

    public function test_approved_accounts_are_not_rejected_here(): void
    {
        $member = $this->users['member'];

        $this->assertUnauthorized($this->as('admin')->gql(self::REJECT, ['id' => $member->id]));
        $this->assertNotNull(User::find($member->id));
    }

    public function test_waiting_account_only_gets_the_viewer_query(): void
    {
        $waiting = User::factory()->unapproved()->create();

        $this->lockedRequest($waiting, '{ projects { id } }')
            ->assertForbidden()
            ->assertJsonPath('errors.0.extensions.code', EnsureAccountReady::CODE)
            ->assertJsonPath('errors.0.extensions.reason', EnsureAccountReady::REASON_APPROVAL);

        $this->lockedRequest($waiting, 'query Viewer { me { id } }', 'Viewer')
            ->assertOk()
            ->assertJsonPath('data.me.id', (string) $waiting->id);
    }

    public function test_unconfirmed_account_is_told_to_confirm_the_mail_first(): void
    {
        $unconfirmed = User::factory()->unapproved()->unverified()->create();

        $this->lockedRequest($unconfirmed, '{ projects { id } }')
            ->assertForbidden()
            ->assertJsonPath('errors.0.extensions.reason', EnsureAccountReady::REASON_EMAIL);
    }

    public function test_approved_account_passes_the_lock(): void
    {
        $this->lockedRequest($this->users['member'], '{ projects { id } }')
            ->assertOk()
            ->assertJsonMissingPath('errors');
    }

    /**
     * A request as the frontend sends it: strong session, optional operation name.
     */
    private function lockedRequest(User $user, string $query, ?string $operationName = null): TestResponse
    {
        return $this->actingAs($user)
            ->withSession([StrongAuthentication::SESSION_KEY => (string) $user->id])
            ->withHeader('Referer', 'http://localhost')
            ->postJson(route('graphql'), ['query' => $query, 'operationName' => $operationName]);
    }
}
