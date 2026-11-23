<?php

namespace App\Tools\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Nuwave\Lighthouse\Auth\AuthServiceProvider;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;

/**
 * Runs a GraphQL operation of this app as a given person (task 34): the tools use the same schema as the frontend,
 * so the same permissions (@can), validation, status automation and mails apply. Without an actor it is the user
 * of the current request (the MCP token owner); with an actor the operation runs as that person, also when
 * nobody is signed in on the request (the assistant of a later phase).
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
    public function runNamed(string $operation, array $variables = [], ?User $actor = null): array
    {
        return $this->run(self::operation($operation), $variables, $actor);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed> The "data" of the result
     *
     * @throws GraphQLFailed with a message the AI client can act on
     */
    public function run(string $query, array $variables = [], ?User $actor = null): array
    {
        $execute = function () use ($query, $variables, $actor): array {
            $context = $this->contextFactory->generate(request());

            if ($actor !== null) {
                $context->setUser($actor);
            }

            $result = $this->graphQL->executeQueryString($query, $context, $variables);

            if (! empty($result['errors'])) {
                throw GraphQLFailed::fromErrors($result['errors']);
            }

            return $result['data'] ?? [];
        };

        return $actor === null ? $execute() : $this->signedInAs($actor, $execute);
    }

    /**
     * Makes the person the signed-in user of the guards GraphQL asks (@guard) while the work runs, and puts back what
     * was there. A person who is already signed in on the request changes nothing.
     *
     * @template T
     *
     * @param  \Closure(): T  $work
     * @return T
     */
    private function signedInAs(User $actor, \Closure $work): mixed
    {
        $before = [];

        foreach (AuthServiceProvider::guards() as $name) {
            $guard = Auth::guard($name);
            $previous = $guard->user();

            if ($previous instanceof User && $previous->is($actor)) {
                continue;
            }

            $before[$name] = $previous;
            $guard->setUser($actor);
        }

        try {
            return $work();
        } finally {
            foreach ($before as $name => $previous) {
                $guard = Auth::guard($name);

                if ($previous !== null) {
                    $guard->setUser($previous);
                } elseif (method_exists($guard, 'forgetUser')) {
                    $guard->forgetUser();
                }
            }
        }
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
