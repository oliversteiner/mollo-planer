<?php

namespace Database\Factories;

use App\Enums\LabelColor;
use App\Models\Label;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->lexify('label-????');

        return [
            'project_id' => Project::factory(),
            'key' => $word,
            'names' => ['de' => $word, 'en' => $word],
            'category' => null,
            'color' => fake()->randomElement(LabelColor::cases()),
            'position' => 0,
        ];
    }
}
