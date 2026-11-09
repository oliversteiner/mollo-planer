<?php

namespace Database\Factories;

use App\Models\LabelSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An own (not built-in) label set.
 *
 * @extends Factory<LabelSet>
 */
class LabelSetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
        ];
    }
}
