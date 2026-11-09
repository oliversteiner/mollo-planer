<?php

namespace Database\Factories;

use App\Enums\LabelColor;
use App\Models\Label;
use App\Models\LabelSet;
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
        return [
            'label_set_id' => LabelSet::factory(),
            'name' => fake()->unique()->word(),
            'category' => null,
            'color' => fake()->randomElement(LabelColor::cases()),
            'position' => 0,
        ];
    }
}
