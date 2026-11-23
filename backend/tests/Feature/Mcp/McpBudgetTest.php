<?php

namespace Tests\Feature\Mcp;

use App\Enums\TaskStatus;
use App\Mcp\Servers\MolloPlanerServer;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\Todo;
use App\Support\McpTokens;
use Tests\McpTools;

/**
 * What an AI client pays (Auftrag 43 C): the fixed part (tools/list plus the instructions of the server) and the size of
 * the answers on a plan as big as Club DB (31 milestones, 113 todos, 389 tasks, 80 % of them done). The limits are
 * ceilings: they keep the cost from growing again. About four characters are one token. Run with MCP_BUDGET_REPORT=1
 * to see the numbers.
 */
class McpBudgetTest extends McpTestCase
{
    /** tools/list and instructions together. */
    private const FIXED_CEILING = 11000;

    private const OUTLINE_CEILING = 6000;

    private const LIST_TASKS_CEILING = 9500;

    private const FULL_EXPORT_CEILING = 110000;

    public function test_the_fixed_part_stays_under_the_ceiling(): void
    {
        $token = McpTokens::create($this->owner, 'Claude')->plainTextToken;
        $headers = ['Accept' => 'application/json, text/event-stream', 'Authorization' => "Bearer {$token}"];

        $tools = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $headers)->assertOk()->json('result');
        $initialize = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-03-26', 'capabilities' => new \stdClass, 'clientInfo' => ['name' => 'test', 'version' => '1']]], $headers)->assertOk()->json('result');

        $list = strlen(json_encode($tools, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $instructions = strlen((string) ($initialize['instructions'] ?? ''));
        $this->report('tools/list', $list);
        $this->report('instructions', $instructions);

        $this->assertGreaterThan(500, $instructions, 'the instructions are there');
        $this->assertLessThanOrEqual(self::FIXED_CEILING, $list + $instructions);
    }

    public function test_the_answers_on_a_big_plan_stay_under_their_ceilings(): void
    {
        $this->bigPlan();
        $project = (string) $this->project->id;

        $full = $this->answerSize('export_plan', ['project_id' => $project]);
        $outline = $this->answerSize('export_plan', ['project_id' => $project, 'view' => 'outline']);
        $short = $this->answerSize('export_plan', ['project_id' => $project, 'include_descriptions' => false]);
        $tasks = $this->answerSize('list_tasks', ['project_id' => $project, 'status' => ['open', 'in_progress']]);
        $thirty = $this->answerSize('list_tasks', ['project_id' => $project]);

        foreach (['full export' => $full, 'outline' => $outline, 'export without descriptions' => $short, 'list_tasks (open)' => $tasks, 'list_tasks (30)' => $thirty] as $name => $chars) {
            $this->report($name, $chars);
        }

        $this->assertLessThanOrEqual(self::OUTLINE_CEILING, $outline);
        $this->assertLessThanOrEqual(self::LIST_TASKS_CEILING, $thirty);
        $this->assertLessThanOrEqual(self::FULL_EXPORT_CEILING, $full);
        $this->assertLessThan($full / 10, $outline, 'the outline costs a tenth of the whole plan or less');
        $this->assertLessThan($full, $short);
    }

    /** Club DB: 31 milestones, 113 todos, 389 tasks, 312 of them done; about a fifth of the tasks have a description. */
    private function bigPlan(): void
    {
        $text = fn (string $words): array => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $words]]]]];
        $todos = collect();

        foreach (range(1, 31) as $m) {
            $milestone = Milestone::factory()->for($this->project)->create(['title' => "Meilenstein $m: Anmeldung und Zahlung vorbereiten"]);

            foreach (range(1, $m <= 25 ? 4 : 2) as $t) {
                $todos->push(Todo::factory()->for($milestone)->create(['title' => "ToDo $m.$t: Formulare, Prüfungen und Mails"]));
            }
        }

        $todos = $todos->take(113);
        $number = 0;

        foreach (range(1, 389) as $i) {
            $number++;
            Task::factory()->for($todos[$i % $todos->count()])->create([
                'title' => "Task $i: Eingabefeld validieren und Fehlermeldung anzeigen",
                'description' => $i % 5 === 0 ? $text('Die Eingabe wird geprüft, bevor sie gespeichert wird. Fehler erscheinen am Feld und nicht als Meldung oben auf der Seite.') : null,
                'status' => $i % 5 < 4 || $i > 390 ? TaskStatus::DONE : TaskStatus::OPEN,
                'commit' => $i % 5 < 4 ? 'a1b2c3'.($i % 10) : null,
                'board_position' => $i,
            ]);
        }

        $this->assertSame(389, Task::query()->count());
    }

    /** @param  array<string, mixed>  $arguments */
    private function answerSize(string $tool, array $arguments): int
    {
        $response = MolloPlanerServer::actingAs($this->owner)->tool(McpTools::of($tool), $arguments);
        $response->assertOk();

        return strlen((string) ((fn (): array => $this->content())->call($response)[0] ?? ''));
    }

    private function report(string $what, int $chars): void
    {
        if (getenv('MCP_BUDGET_REPORT')) {
            fwrite(STDERR, sprintf("  %-30s %7d characters, about %6d tokens\n", $what, $chars, intdiv($chars, 4)));
        }
    }
}
