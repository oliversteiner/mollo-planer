<?php

namespace App\Planer;

/**
 * Cheaper views of a planer-v2 document for an AI client (Auftrag 43 A): the same data, less of it. Both return an
 * excerpt ("excerpt": true), so the document can only be written back with import mode "partial", where what is
 * missing means "unchanged", never "empty".
 */
final class PlanView
{
    /**
     * The plan from a distance: the project, the revision and per milestone its id, title, release, the number of todos
     * and the number of tasks per status. No todos, no tasks, no descriptions.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function outline(array $document): array
    {
        $milestones = array_map(function (array $milestone): array {
            $counts = [];
            $todos = $milestone['todos'] ?? [];

            foreach ($todos as $todo) {
                foreach ($todo['tasks'] ?? [] as $task) {
                    $status = (string) ($task['status'] ?? 'planned');
                    $counts[$status] = ($counts[$status] ?? 0) + 1;
                }
            }

            return array_filter([
                'id' => $milestone['id'],
                'title' => $milestone['title'],
                'version' => $milestone['release']['version'] ?? null,
                'released' => ($milestone['release']['status'] ?? null) === 'released' ? true : null,
                'dueDate' => $milestone['dueDate'] ?? null,
                'todos' => count($todos),
                'tasks' => $counts,
            ], fn (mixed $value): bool => $value !== null);
        }, $document['milestones'] ?? []);

        return [
            'format' => $document['format'],
            'revision' => $document['revision'],
            'excerpt' => true,
            'project' => array_filter([
                'name' => $document['project']['name'] ?? null,
                'locale' => $document['project']['locale'] ?? null,
            ], fn (mixed $value): bool => $value !== null),
            'milestones' => $milestones,
        ];
    }

    /**
     * The document without any description (project, milestones, todos, tasks).
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function withoutDescriptions(array $document): array
    {
        unset($document['project']['description']);

        foreach ($document['milestones'] ?? [] as $mi => $milestone) {
            unset($document['milestones'][$mi]['description']);

            foreach ($milestone['todos'] ?? [] as $ti => $todo) {
                unset($document['milestones'][$mi]['todos'][$ti]['description']);

                foreach ($todo['tasks'] ?? [] as $ki => $task) {
                    unset($document['milestones'][$mi]['todos'][$ti]['tasks'][$ki]['description']);
                }
            }
        }

        $document['excerpt'] = true;

        return $document;
    }
}
