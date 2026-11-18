<?php

namespace App\Mcp\Support;

use InvalidArgumentException;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;

/**
 * Runs a GraphQL operation of this app as the user of the MCP request.
 * The MCP tools use the same schema as the frontend, so the same
 * permissions (@can), validation, status automation and mails apply.
 */
class GraphQLRunner
{
    /** @var array<string, string> Operation text by name, read once per request */
    private static array $operations = [];

    public function __construct(
        private readonly GraphQL $graphQL,
        private readonly CreatesContext $contextFactory,
    ) {}

    /**
     * Runs the named operation from graphql/operations/{name}.graphql. Its
     * file name and operation name are the same, and the test suite checks
     * every file against the schema.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed> The "data" of the result
     *
     * @throws GraphQLFailed with a message the AI client can act on
     */
    public function runNamed(string $operation, array $variables = []): array
    {
        return $this->run(self::operation($operation), $variables);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed> The "data" of the result
     *
     * @throws GraphQLFailed with a message the AI client can act on
     */
    public function run(string $query, array $variables = []): array
    {
        $result = $this->graphQL->executeQueryString($query, $this->contextFactory->generate(request()), $variables);

        if (! empty($result['errors'])) {
            throw GraphQLFailed::fromErrors($result['errors']);
        }

        return $result['data'] ?? [];
    }

    /**
     * @throws InvalidArgumentException when no operation file has this name
     */
    public static function operation(string $name): string
    {
        if (isset(self::$operations[$name])) {
            return self::$operations[$name];
        }

        $path = self::path($name);

        if (! is_file($path)) {
            throw new InvalidArgumentException("Unknown GraphQL operation [{$name}].");
        }

        return self::$operations[$name] = file_get_contents($path);
    }

    /**
     * Path of an operation file. Names are plain letters only, so no path can be built from the input.
     *
     * @throws InvalidArgumentException
     */
    public static function path(string $name): string
    {
        if (preg_match('/^[A-Za-z]+$/', $name) !== 1) {
            throw new InvalidArgumentException("Unknown GraphQL operation [{$name}].");
        }

        return base_path("graphql/operations/{$name}.graphql");
    }
}
