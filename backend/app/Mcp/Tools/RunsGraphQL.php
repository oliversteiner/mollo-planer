<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\GraphQLFailed;
use App\Mcp\Support\GraphQLRunner;
use Closure;
use Laravel\Mcp\Response;

/**
 * Tools act through the app's GraphQL schema as the token's owner, so
 * every permission and rule of the app applies. A failed operation
 * becomes an error response the AI client can read and act on.
 */
trait RunsGraphQL
{
    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    protected function operation(string $name, array $variables = []): array
    {
        return app(GraphQLRunner::class)->runNamed($name, $variables);
    }

    /**
     * @param  Closure(): Response  $work
     */
    protected function respond(Closure $work): Response
    {
        try {
            return $work();
        } catch (GraphQLFailed $failure) {
            return Response::error($failure->getMessage());
        }
    }
}
