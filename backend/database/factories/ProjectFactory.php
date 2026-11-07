<?php

namespace Database\Factories;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'locale' => 'de',
        ];
    }

    /**
     * Add a user to the project with the given role.
     */
    public function withMember(User $user, ProjectRole $role = ProjectRole::Member): static
    {
        return $this->hasAttached($user, ['role' => $role->value], 'members');
    }
}
