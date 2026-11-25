<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company' => fake()->company(),
            'name' => fake()->name(),
            'street' => fake()->streetName(),
            'house_number' => (string) fake()->numberBetween(1, 120),
            'postal_code' => (string) fake()->numberBetween(1000, 9658),
            'city' => fake()->city(),
            'country' => 'CH',
            'email' => fake()->safeEmail(),
            'locale' => 'de',
        ];
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
