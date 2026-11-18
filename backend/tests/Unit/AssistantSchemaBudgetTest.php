<?php

namespace Tests\Unit;

use App\Assistant\AssistantSchema;
use Tests\TestCase;

/**
 * The Claude API turns the answer schema into a grammar and refuses one that is too large ("compiled grammar too
 * large", docs/fix-m6.md F10.3). The tests with a fake client cannot see that. This test holds the size of the schema
 * at the last state a real call accepted; when the schema grows it fails. Then run `php artisan assistant:ping`
 * (a real call) and raise the numbers only if it says OK.
 */
class AssistantSchemaBudgetTest extends TestCase
{
    /** Last verified by a real call: 2026-11-18, `assistant:ping` OK. */
    private const MAX = ['properties' => 41, 'nullable' => 17, 'enums' => 28, 'depth' => 15];

    /** @return array{properties: int, nullable: int, enums: int, depth: int} */
    private function measure(mixed $node, int $depth = 0): array
    {
        $stats = ['properties' => 0, 'nullable' => 0, 'enums' => 0, 'depth' => $depth];

        if (! is_array($node)) {
            return $stats;
        }

        if (isset($node['properties'])) {
            $stats['properties'] += count($node['properties']);
        }

        if (isset($node['type']) && is_array($node['type']) && in_array('null', $node['type'], true)) {
            $stats['nullable']++;
        }

        if (isset($node['anyOf'])) {
            $stats['nullable']++;
        }

        if (isset($node['enum'])) {
            $stats['enums'] += count($node['enum']);
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $sub = $this->measure($child, $depth + 1);
                $stats['properties'] += $sub['properties'];
                $stats['nullable'] += $sub['nullable'];
                $stats['enums'] += $sub['enums'];
                $stats['depth'] = max($stats['depth'], $sub['depth']);
            }
        }

        return $stats;
    }

    public function test_the_schema_stays_within_what_the_api_accepted(): void
    {
        $now = $this->measure(AssistantSchema::answer());

        foreach (self::MAX as $what => $max) {
            $this->assertLessThanOrEqual($max, $now[$what], "The answer schema grew ({$what}: {$now[$what]}, allowed {$max}). Run `php artisan assistant:ping`; only when it says OK raise MAX in this test.");
        }
    }

    public function test_the_schema_has_no_field_for_the_template(): void
    {
        $this->assertArrayNotHasKey('template', AssistantSchema::answer()['properties']);
    }
}
