<?php

namespace Tests\Feature\GraphQL;

use App\Models\User;

/**
 * User.twoFactorConfirmed, hasPasskey and passkeys: only the person (and,
 * for the flags, admins) may learn how an account is secured.
 */
class UserSecurityTest extends GraphQLTestCase
{
    private const QUERY = '{ users { id twoFactorConfirmed hasPasskey passkeys { name lastUsedAt } } }';

    protected function setUp(): void
    {
        parent::setUp();

        $member = $this->users['member'];
        $member->forceFill(['two_factor_secret' => 'secret', 'two_factor_confirmed_at' => now()])->save();
        $member->passkeys()->forceCreate(['user_id' => $member->id, 'name' => 'Laptop', 'credential_id' => 'c1', 'credential' => [], 'created_at' => now()->subDay()]);
        $member->passkeys()->forceCreate(['user_id' => $member->id, 'name' => 'Telefon', 'credential_id' => 'c2', 'credential' => [], 'last_used_at' => now()]);
    }

    public function test_the_person_sees_flags_and_own_passkeys_newest_first(): void
    {
        $this->as('member')->gql('{ me { twoFactorConfirmed hasPasskey passkeys { name } } }')
            ->assertJsonPath('data.me', [
                'twoFactorConfirmed' => true,
                'hasPasskey' => true,
                'passkeys' => [['name' => 'Telefon'], ['name' => 'Laptop']],
            ]);
    }

    public function test_admins_see_the_flags_of_everyone_but_no_passkey_list(): void
    {
        $member = $this->userRow($this->as('admin')->gql(self::QUERY)->json('data.users'), $this->users['member']);
        $owner = $this->userRow($this->as('admin')->gql(self::QUERY)->json('data.users'), $this->users['owner']);

        $this->assertSame(['twoFactorConfirmed' => true, 'hasPasskey' => true, 'passkeys' => null], $member);
        $this->assertSame(['twoFactorConfirmed' => false, 'hasPasskey' => false, 'passkeys' => null], $owner);
    }

    public function test_others_learn_nothing_about_the_security_of_an_account(): void
    {
        $users = $this->as('owner')->gql(self::QUERY)->assertJsonMissingPath('errors')->json('data.users');

        $this->assertSame(['twoFactorConfirmed' => null, 'hasPasskey' => null, 'passkeys' => null], $this->userRow($users, $this->users['member']));
    }

    /**
     * @param  list<array<string, mixed>>  $users
     * @return array<string, mixed>
     */
    private function userRow(array $users, User $user): array
    {
        $row = collect($users)->firstWhere('id', (string) $user->id);
        unset($row['id']);

        return $row;
    }
}
