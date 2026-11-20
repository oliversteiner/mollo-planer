<?php

namespace Tests\Feature\GraphQL;

use App\Models\User;

/**
 * User.isAgent (task 31): the interface marks agent accounts with a badge.
 */
class AgentFieldTest extends GraphQLTestCase
{
    public function test_a_user_says_whether_it_is_an_agent(): void
    {
        $agent = User::factory()->agent()->create(['name' => 'Claude']);

        $users = collect($this->as('admin')->gql('{ users { name isAgent } }')->assertJsonMissingPath('errors')->json('data.users'))->pluck('isAgent', 'name');

        $this->assertTrue($users['Claude']);
        $this->assertFalse($users['Ben Owner']);
        $this->assertNotNull($agent);
    }
}
