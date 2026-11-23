<?php

namespace App\Tools;

use App\Models\User;
use App\Tools\Support\GraphQLFailed;
use App\Tools\Support\GraphQLRunner;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A tool, described once (task 34): name, description, input schema and a handler that gets the PERSON and the
 * arguments. Two thin adapters offer it: the MCP server (routes/ai.php) and the Claude tool use of the assistant
 * (App\Assistant\ToolBridge). A new tool is written here and entered in ToolRegistry, nowhere else.
 *
 * A tool acts through the app's GraphQL schema (or a service) as the given person, so every right and rule of the app
 * applies. It does not read the person from the request: the caller names them.
 */
abstract class Tool
{
    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * The input as a JSON Schema (see Schema::input()).
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return Schema::input();
    }

    /** Reads only: the AI client may call it without asking. */
    public function readOnly(): bool
    {
        return false;
    }

    /**
     * Does the work as this person. Arguments that do not fit throw a ValidationException (the adapters turn it
     * into the message the AI client reads); a failed GraphQL operation becomes an error result in call().
     *
     * @param  array<string, mixed>  $arguments
     */
    abstract public function handle(User $actor, array $arguments): ToolResult;

    /**
     * The one entry for the adapters: handle(), with a failed GraphQL operation as an error result with the message
     * an AI client can act on. A ValidationException is not caught here: each adapter shows it its own way.
     *
     * @param  array<string, mixed>  $arguments
     */
    final public function call(User $actor, array $arguments): ToolResult
    {
        try {
            return $this->handle($actor, $arguments);
        } catch (GraphQLFailed $failure) {
            return ToolResult::error(Str::limit($failure->getMessage(), 400));
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @return array<string, mixed> What was validated
     *
     * @throws ValidationException
     */
    protected function validate(array $arguments, array $rules, array $messages = []): array
    {
        return Validator::validate($arguments, $rules, $messages);
    }

    /**
     * A GraphQL operation of this app as the person.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed> The "data" of the answer
     */
    protected function graphql(User $actor, string $query, array $variables = []): array
    {
        return app(GraphQLRunner::class)->run($query, $variables, $actor);
    }

    /**
     * A named operation (graphql/operations/<name>.graphql) as the person.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    protected function operation(User $actor, string $name, array $variables = []): array
    {
        return app(GraphQLRunner::class)->runNamed($name, $variables, $actor);
    }
}
