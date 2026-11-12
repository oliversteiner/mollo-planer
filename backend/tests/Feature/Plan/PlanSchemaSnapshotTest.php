<?php

namespace Tests\Feature\Plan;

use App\Mcp\Tools\AddToPlanTool;
use App\Mcp\Tools\CreateProjectPlanTool;
use App\Services\ProjectPlanAssistant;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Tests\TestCase;

/**
 * The schemas the plan tools show to Claude: the answer schema of the chat
 * assistant and the input schemas of the two MCP tools. They are fixed here,
 * so a refactoring of the plan structure cannot change what the AI sees.
 */
class PlanSchemaSnapshotTest extends TestCase
{
    public function test_assistant_answer_schema_is_unchanged(): void
    {
        $this->assertSame($this->fixture('claude-schema.json'), ProjectPlanAssistant::schema());
    }

    public function test_create_project_plan_input_schema_is_unchanged(): void
    {
        $this->assertSame($this->fixture('mcp-create-schema.json'), $this->mcpSchema(CreateProjectPlanTool::class));
    }

    public function test_add_to_plan_input_schema_is_unchanged(): void
    {
        $this->assertSame($this->fixture('mcp-add-schema.json'), $this->mcpSchema(AddToPlanTool::class));
    }

    /**
     * @return array<string, mixed>
     */
    private function mcpSchema(string $tool): array
    {
        return array_map(fn ($type) => $type->toArray(), app($tool)->schema(new JsonSchemaTypeFactory));
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../Fixtures/plan/'.$name), true, flags: JSON_THROW_ON_ERROR);
    }
}
