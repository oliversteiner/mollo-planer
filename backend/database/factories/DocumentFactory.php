<?php

namespace Database\Factories;

use App\Enums\DocumentAccess;
use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates the document and its first version (revision 1), like saving does.
 *
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'owner_id' => User::factory(),
            'title' => fake()->sentence(3),
            'kind' => DocumentKind::OTHER,
            'content' => fake()->paragraph()."\n",
            'project_access' => DocumentAccess::READ,
            'revision' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Document $document): void {
            $document->versions()->create([
                'revision' => $document->revision,
                'title' => $document->title,
                'kind' => $document->kind,
                'content' => $document->content,
                'created_by' => $document->owner_id,
            ]);
        });
    }

    public function access(DocumentAccess $access): static
    {
        return $this->state(['project_access' => $access]);
    }
}
