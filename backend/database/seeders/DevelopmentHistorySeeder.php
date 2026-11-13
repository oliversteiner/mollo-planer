<?php

namespace Database\Seeders;

use App\Enums\TaskStatus;
use App\Models\LabelSet;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Support\DevelopmentHistory\HistoryMarkdown;
use App\Support\DevelopmentHistory\HistoryValidator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates the project "Mollo Planer · Entwicklung" from docs/entwicklung.md:
 * versions as milestones, work contexts as todos, features, bugs, plans and
 * research as tasks (with their kind as label). Local only.
 *
 *   php artisan db:seed --class=DevelopmentHistorySeeder
 *
 * Runs once: if the project exists, nothing is changed.
 */
class DevelopmentHistorySeeder extends Seeder
{
    public const PROJECT_NAME = 'Mollo Planer · Entwicklung';

    /** Label key of the built-in set "Softwareentwicklung" for each task kind. */
    private const LABEL_OF_KIND = [
        'feature' => 'feature',
        'bug' => 'bug',
        'planning' => 'planning',
        'research' => 'research',
        'design' => 'design',
        'blocked' => 'blocked',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DevelopmentHistorySeeder must not run in production.');
        }

        if (Project::query()->where('name', self::PROJECT_NAME)->exists()) {
            $this->command?->info('The project '.self::PROJECT_NAME.' already exists, nothing changed.');

            return;
        }

        $path = base_path('../docs/entwicklung.md');
        $markdown = (string) file_get_contents($path);

        $errors = array_filter(HistoryValidator::validate($markdown), fn (array $issue): bool => $issue['severity'] === 'error');
        if ($errors !== []) {
            $first = reset($errors);
            throw new RuntimeException('docs/entwicklung.md is invalid ('.count($errors)." errors). Line {$first['line']}: {$first['message']} Run: php artisan docs:validate-history");
        }

        $history = HistoryMarkdown::parse($markdown);

        DB::transaction(function () use ($history): void {
            $labelSet = LabelSet::query()->where('key', 'software_dev')->firstOrFail();

            $project = Project::create([
                'name' => self::PROJECT_NAME,
                'description' => 'Entwicklung von Mollo Planer, aus docs/entwicklung.md.',
                'locale' => 'de',
                'label_set_id' => $labelSet->getKey(),
            ]);

            $labelIds = $labelSet->labels()->pluck('labels.id', 'labels.key');
            $positions = [];

            foreach ($history['milestones'] as $milestoneData) {
                $milestone = Milestone::create([
                    'project_id' => $project->getKey(),
                    'title' => $milestoneData['title'],
                    'description' => $this->richText($milestoneData['description']),
                ]);

                foreach ($milestoneData['todos'] as $todoData) {
                    $todo = Todo::create([
                        'milestone_id' => $milestone->getKey(),
                        'title' => $todoData['title'],
                        'description' => $this->richText($todoData['description']),
                    ]);

                    foreach ($todoData['tasks'] as $taskData) {
                        $status = $taskData['done'] ? TaskStatus::DONE : TaskStatus::OPEN;
                        $position = $positions[$status->value] ?? 0;
                        $positions[$status->value] = $position + 1;

                        $task = new Task([
                            'todo_id' => $todo->getKey(),
                            'title' => $taskData['title'],
                            'description' => $this->richText($taskData['description']),
                        ]);
                        $task->status = $status;
                        $task->board_position = $position;
                        $task->save();

                        $labelId = $labelIds[self::LABEL_OF_KIND[$taskData['kind']]] ?? null;
                        if ($labelId !== null) {
                            $task->labels()->attach($labelId);
                        }
                    }
                }
            }
        });

        $this->command?->info('Development history created from docs/entwicklung.md.');
    }

    /**
     * Plain text as a TipTap document, one paragraph per line (never HTML).
     *
     * @return array<string, mixed>|null
     */
    private function richText(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $paragraphs = array_map(
            fn (string $line): array => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $line]]],
            array_values(array_filter(array_map('trim', explode("\n", $text)), fn (string $line): bool => $line !== '')),
        );

        return ['type' => 'doc', 'content' => $paragraphs];
    }
}
