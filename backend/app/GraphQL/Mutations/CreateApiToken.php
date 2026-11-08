<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Support\McpTokens;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * A personal access token for Claude (MCP). Only reachable through the
 * GraphQL route, so only in a strongly authenticated session.
 */
class CreateApiToken
{
    use ResolvesViewer;

    /**
     * @param  array{name: string}  $args
     * @return array{token: PersonalAccessToken, plainTextToken: string}
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $viewer = $this->viewer($context);

        return DB::transaction(function () use ($viewer, $args): array {
            $token = McpTokens::create($viewer, trim($args['name']));

            return ['token' => $token->accessToken, 'plainTextToken' => $token->plainTextToken];
        });
    }
}
