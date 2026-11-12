<?php

namespace Tests\Feature\GraphQL;

use App\Mcp\Support\GraphQLRunner;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Validator\DocumentValidator;
use InvalidArgumentException;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The operations that the MCP tools and the plan importer run by name. Each
 * file holds one operation with the same name as the file, and it must be
 * valid against the schema. A renamed field or argument fails here, not in
 * production.
 */
class OperationFilesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function operationNames(): array
    {
        $names = [];

        foreach (glob(dirname(__DIR__, 3).'/graphql/operations/*.graphql') ?: [] as $file) {
            $name = basename($file, '.graphql');
            $names[$name] = [$name];
        }

        return $names;
    }

    #[DataProvider('operationNames')]
    public function test_operation_is_valid_against_the_schema(string $name): void
    {
        $document = Parser::parse(file_get_contents(GraphQLRunner::path($name)));

        $operations = [];

        foreach ($document->definitions as $definition) {
            if ($definition instanceof OperationDefinitionNode) {
                $operations[] = $definition->name?->value;
            }
        }

        $this->assertSame([$name], $operations, "The file {$name}.graphql must hold exactly the operation {$name}.");

        $schema = $this->app->make(SchemaBuilder::class)->schema();
        $errors = array_map(
            fn ($error) => $error->getMessage(),
            DocumentValidator::validate($schema, $document),
        );

        $this->assertSame([], $errors, "The operation {$name} does not match the schema.");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'missing file' => ['DoesNotExist'],
            'path traversal' => ['../.env'],
            'space' => ['Create Project'],
        ];
    }

    #[DataProvider('invalidNames')]
    public function test_unknown_or_unsafe_operation_names_are_rejected(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        GraphQLRunner::operation($name);
    }
}
