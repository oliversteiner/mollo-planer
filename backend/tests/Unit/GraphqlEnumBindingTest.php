<?php

namespace Tests\Unit;

use App\Enums\ItemStatus;
use App\Enums\Locale;
use App\Enums\ProjectAbility;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * graphql-php serializes a PHP enum by its case name. So every PHP case
 * must be named like the GraphQL value and carry the same @enum(value).
 */
class GraphqlEnumBindingTest extends TestCase
{
    /**
     * @return array<string, array{class-string<\BackedEnum>}>
     */
    public static function enums(): array
    {
        return [
            'ProjectRole' => [ProjectRole::class],
            'ProjectAbility' => [ProjectAbility::class],
            'ItemStatus' => [ItemStatus::class],
            'TaskStatus' => [TaskStatus::class],
            'Locale' => [Locale::class],
        ];
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     */
    #[DataProvider('enums')]
    public function test_php_enum_matches_graphql_enum(string $enum): void
    {
        $graphqlName = class_basename($enum);
        $schema = implode("\n", array_map(
            fn (string $file): string => (string) file_get_contents($file),
            glob(__DIR__.'/../../graphql/*.graphql') ?: [],
        ));

        $this->assertSame(
            1,
            preg_match('/^enum '.$graphqlName.' \{(.*?)^\}/ms', $schema, $block),
            "Enum {$graphqlName} not found in the schema.",
        );
        preg_match_all('/^\s*([A-Z_]+) @enum\(value: "([^"]+)"\)/m', $block[1], $matches);
        $inSchema = array_combine($matches[1], $matches[2]);

        $inPhp = [];
        foreach ($enum::cases() as $case) {
            $inPhp[$case->name] = $case->value;
        }

        $this->assertNotEmpty($inSchema);
        $this->assertSame($inSchema, $inPhp);
    }
}
