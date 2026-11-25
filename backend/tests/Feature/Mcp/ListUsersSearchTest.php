<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\MolloPlanerServer;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\GraphQL\GraphQLTestCase;
use Tests\McpTools;

/**
 * % and _ in the search of list_users mean themselves (Auftrag 42 E), in the list of accounts and in the members of a project.
 */
#[Group('mariadb')]
class ListUsersSearchTest extends GraphQLTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->users['owner']->forceFill(['name' => 'Rabatt 100% Ben'])->save();
        $this->users['manager']->forceFill(['name' => 'Rabatt 100 Chiara'])->save();
        $this->users['member']->forceFill(['name' => 'Da_vid Member'])->save();
        User::query()->whereKey($this->users['stranger']->id)->update(['name' => 'Dax vid Stranger', 'approved_at' => now()]);
    }

    /** @return list<string> */
    private function names(array $arguments, string $as = 'admin'): array
    {
        $response = MolloPlanerServer::actingAs($this->users[$as])->tool(McpTools::of('list_users'), $arguments);
        $response->assertOk();
        $text = (string) ((fn (): array => $this->content())->call($response)[0] ?? '');

        return array_column(json_decode($text, true, 512, JSON_THROW_ON_ERROR)['data'], 'name');
    }

    public function test_a_percent_sign_in_the_search_is_no_wildcard(): void
    {
        $this->assertSame(['Rabatt 100% Ben'], $this->names(['query' => '100%']));
        $this->assertSame([], $this->names(['query' => '%%%zzz']));
    }

    public function test_an_underscore_in_the_search_is_no_wildcard_for_one_character(): void
    {
        $this->assertSame(['Da_vid Member'], $this->names(['query' => 'a_v']));
    }

    public function test_the_escape_character_itself_and_the_search_in_the_members_of_a_project_work_too(): void
    {
        $this->users['member']->forceFill(['name' => 'Da!vid Member'])->save();

        $this->assertSame(['Da!vid Member'], $this->names(['query' => 'a!v']));
        $this->assertSame(['Rabatt 100% Ben'], $this->names(['project_id' => (string) $this->project->id, 'query' => '100%'], 'owner'));
        $this->assertSame(['Rabatt 100% Ben'], $this->names(['project_id' => (string) $this->project->id, 'query' => '%'], 'manager'));
    }
}
