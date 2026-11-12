<?php

namespace Tests\Feature\GraphQL;

use App\Models\User;
use App\Support\AccountReadiness;
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

    public function test_admins_see_approval_and_mail_state_of_every_user(): void
    {
        $waiting = User::factory()->unapproved()->create(['name' => 'Eva Neu']);

        $users = $this->as('admin')->gql('{ users { id approved emailVerified } }')->json('data.users');

        $this->assertContains(['id' => (string) $waiting->id, 'approved' => false, 'emailVerified' => true], $users);
        $this->assertContains(['id' => (string) $this->users['member']->id, 'approved' => true, 'emailVerified' => true], $users);
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
            ->assertJsonPath('errors.0.extensions.code', AccountReadiness::CODE)
            ->assertJsonPath('errors.0.extensions.reason', AccountReadiness::REASON_APPROVAL);

        $this->lockedRequest($waiting, 'query Viewer { me { id } }', 'Viewer')
            ->assertOk()
            ->assertJsonPath('data.me.id', (string) $waiting->id);
    }

    public function test_unconfirmed_account_is_told_to_confirm_the_mail_first(): void
    {
        $unconfirmed = User::factory()->unapproved()->unverified()->create();

        $this->lockedRequest($unconfirmed, '{ projects { id } }')
            ->assertForbidden()
            ->assertJsonPath('errors.0.extensions.reason', AccountReadiness::REASON_EMAIL);
    }

    public function test_unconfirmed_mail_is_asked_before_the_second_factor(): void
    {
        $unconfirmed = User::factory()->unapproved()->unverified()->create();

        $this->lockedRequest($unconfirmed, 'query Viewer { me { id } }', 'Viewer', strong: false)
            ->assertForbidden()
            ->assertJsonPath('errors.0.extensions.code', AccountReadiness::CODE)
            ->assertJsonPath('errors.0.extensions.reason', AccountReadiness::REASON_EMAIL);
    }

    public function test_confirmed_mail_without_second_factor_is_sent_to_setup_before_approval(): void
    {
        $waiting = User::factory()->unapproved()->create();

        $this->lockedRequest($waiting, 'query Viewer { me { id } }', 'Viewer', strong: false)
            ->assertForbidden()
            ->assertJsonPath('errors.0.extensions.code', 'SECOND_FACTOR_SETUP_REQUIRED');
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
    private function lockedRequest(User $user, string $query, ?string $operationName = null, bool $strong = true): TestResponse
    {
        $request = $this->actingAs($user)->withHeader('Referer', 'http://localhost');

        if ($strong) {
            $request = $request->withSession([StrongAuthentication::SESSION_KEY => (string) $user->id]);
        }

        return $request->postJson(route('graphql'), ['query' => $query, 'operationName' => $operationName]);
    }
}
