<?php

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\Todo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'milestone_id' => Milestone::factory(),
            'title' => fake()->sentence(3),
        ];
    }
}
