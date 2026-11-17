<?php

namespace Tests\Feature\Plan;

use App\Services\ProjectPlanAssistant;
use Tests\TestCase;

/**
 * The schema the chat assistant shows to Claude (its answer schema). It is
 * fixed here, so a refactoring of the plan structure cannot change what the AI sees.
 */
class PlanSchemaSnapshotTest extends TestCase
{
    public function test_assistant_answer_schema_is_unchanged(): void
    {
        $this->assertSame($this->fixture('claude-schema.json'), ProjectPlanAssistant::schema());
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../Fixtures/plan/'.$name), true, flags: JSON_THROW_ON_ERROR);
    }
}
