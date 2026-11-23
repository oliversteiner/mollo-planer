<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ConfirmsPassword;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\User;
use App\Services\AgentAccess;
use Laravel\Sanctum\PersonalAccessToken;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * An admin makes a connector access for an agent account. Right (@can), strong session (the GraphQL route),
 * recent password confirmation (here) and an agent as the target (AgentAccess). The plain token is only in
 * this answer.
 */
class CreateAgentToken
{
    use ConfirmsPassword;
    use ResolvesViewer;

    /**
     * @param  array{userId: int|string, name: string}  $args
     * @return array{token: PersonalAccessToken, plainTextToken: string}
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $admin = $this->viewer($context);
        $this->requireRecentPasswordConfirmation();

        $token = app(AgentAccess::class)->create($admin, User::findOrFail($args['userId']), $args['name']);

        return ['token' => $token->accessToken, 'plainTextToken' => $token->plainTextToken];
    }
}
