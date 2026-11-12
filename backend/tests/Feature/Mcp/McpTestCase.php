<?php

namespace Tests\Feature\Mcp;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared setup of the MCP tests: an owner and a member of one project
 * ("Podcast") and a stranger without access. Subclasses that need more
 * people override createProject().
 */
abstract class McpTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $member;

    protected User $stranger;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email' => 'oliver@example.ch']);
        $this->member = User::factory()->create(['email' => 'lindita@example.ch']);
        $this->stranger = User::factory()->create(['email' => 'eva@example.ch']);
        $this->project = $this->createProject();
    }

    protected function createProject(): Project
    {
        return Project::factory()
            ->withMember($this->owner, ProjectRole::OWNER)
            ->withMember($this->member, ProjectRole::MEMBER)
            ->create(['name' => 'Podcast']);
    }
}
