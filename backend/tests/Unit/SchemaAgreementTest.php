<?php

namespace Tests\Unit;

use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use Tests\TestCase;

/**
 * The app and spec/planer-v2.schema.json must name the same values. The
 * schema is the format that files use, so a value missing on one side is a
 * silent import or export error (docs/neubewertung-2026-11-13.md, 3.1).
 */
class SchemaAgreementTest extends TestCase
{
    /** @return array<string, mixed> */
    private function schema(): array
    {
        return json_decode((string) file_get_contents(base_path('../spec/planer-v2.schema.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_task_status_values_match_the_schema(): void
    {
        $this->assertSame(
            $this->schema()['$defs']['task']['properties']['status']['enum'],
            array_column(TaskStatus::cases(), 'value'),
        );
    }

    public function test_member_roles_match_the_schema(): void
    {
        $this->assertSame(
            $this->schema()['$defs']['role']['enum'],
            array_column(ProjectRole::cases(), 'value'),
        );
    }
}
