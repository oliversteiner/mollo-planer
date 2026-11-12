<?php

namespace Tests\Feature\GraphQL;

use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Tests\TestCase;

/**
 * Every mutation of the schema must appear in the lists of the access tests:
 * the refused mutations of OutsiderAccessTest or the working ones of
 * MutationTest. A new mutation that nobody checks fails here, by name.
 */
class MutationCoverageTest extends TestCase
{
    /**
     * Mutations that cannot be sent as a plain query: the avatar upload is a
     * multipart request, tested with its own helper in AvatarTest.
     */
    private const EXEMPT = ['updateMyAvatar'];

    public function test_every_schema_mutation_is_covered_by_an_access_test(): void
    {
        $schemaMutations = $this->app->make(SchemaBuilder::class)->schema()->getMutationType()->getFieldNames();

        $covered = collect([...OutsiderAccessTest::refusedMutations(), ...MutationTest::customMutations()])
            ->map(fn (array $case) => self::rootField($case[1]))
            ->unique()
            ->values()
            ->all();

        $missing = array_values(array_diff($schemaMutations, $covered, self::EXEMPT));
        sort($missing);

        $this->assertSame([], $missing, 'Mutations without an access test: '.implode(', ', $missing));
    }

    public function test_the_lists_do_not_name_mutations_the_schema_lacks(): void
    {
        $schemaMutations = $this->app->make(SchemaBuilder::class)->schema()->getMutationType()->getFieldNames();

        $listed = collect([...OutsiderAccessTest::refusedMutations(), ...MutationTest::customMutations()])
            ->map(fn (array $case) => self::rootField($case[1]))
            ->unique()
            ->values()
            ->all();

        $unknown = array_values(array_diff($listed, $schemaMutations));

        $this->assertSame([], $unknown, 'Listed mutations the schema does not have: '.implode(', ', $unknown));
        $this->assertSame([], array_values(array_diff(self::EXEMPT, $schemaMutations)), 'EXEMPT names a mutation the schema does not have.');
    }

    /**
     * The first field of the selection set: "mutation ($id: ID!) { updateTodo(...) }" gives "updateTodo".
     */
    private static function rootField(string $mutation): string
    {
        preg_match('/mutation\b[^{]*\{\s*(\w+)/', $mutation, $match);

        return $match[1] ?? '';
    }
}
