<?php

namespace Database\Factories;

use App\Models\Template;
use App\Planer\TemplateContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An own (not built-in) template with one label and no outline.
 *
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $labels = [['key' => 'one', 'names' => ['de' => 'Eins', 'en' => 'One'], 'category' => null, 'color' => 'blue']];

        return [
            'name' => $name,
            'description' => null,
            'locale' => 'de',
            'content' => TemplateContent::blank($name, null, 'de', $labels),
        ];
    }
}
