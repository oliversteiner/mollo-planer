<?php

namespace App\Services;

use App\Enums\Right;
use App\Models\User;
use App\Notifications\AgentAccessChange;
use App\Support\McpTokens;
use App\Support\Rights;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * An admin makes or revokes the connector access of an agent account in the user administration (task 32).
 * Only for agent accounts: a person makes their own access (createApiToken), nobody acts in their name. The
 * token itself is made by McpTokens::createForAgent, as in the command app:user-token. Every step is logged
 * (who, which agent, which access, never the token) and the admins are told by mail.
 */
class AgentAccess
{
    /**
     * @throws ValidationException when the account is not an agent
     */
    public function create(User $admin, User $agent, string $name): NewAccessToken
    {
        $this->ensureAgent($agent);

        $token = DB::transaction(function () use ($admin, $agent, $name): NewAccessToken {
            $token = McpTokens::createForAgent($agent, trim($name));
            $this->record($admin, $agent, AgentAccessChange::CREATED, $token->accessToken);

            return $token;
        });

        return $token;
    }

    /**
     * Null when the access is not one of this agent's.
     *
     * @throws ValidationException when the account is not an agent
     */
    public function revoke(User $admin, User $agent, int|string $tokenId): ?PersonalAccessToken
    {
        $this->ensureAgent($agent);

        return DB::transaction(function () use ($admin, $agent, $tokenId): ?PersonalAccessToken {
            $token = McpTokens::revoke($agent, $tokenId);

            if ($token !== null) {
                $this->record($admin, $agent, AgentAccessChange::REVOKED, $token);
            }

            return $token;
        });
    }

    private function ensureAgent(User $agent): void
    {
        if (! $agent->isAgent()) {
            throw ValidationException::withMessages(['userId' => __('graphql.agent_access.not_an_agent', ['name' => $agent->name])]);
        }
    }

    private function record(User $admin, User $agent, string $kind, PersonalAccessToken $token): void
    {
        // The log gets no part of the token, only the fact.
        Log::warning('Connector access of an agent account changed.', [
            'action' => $kind,
            'admin_id' => $admin->getKey(),
            'agent_id' => $agent->getKey(),
            'access_name' => $token->name,
            'at' => now()->toIso8601String(),
        ]);

        $admins = User::query()
            ->whereIn('role_id', Rights::roleIdsWith(Right::USERS_MANAGE))
            ->whereNotNull('approved_at')
            ->get();

        foreach ($admins as $recipient) {
            $recipient->notify((new AgentAccessChange($kind, $admin->name, $agent->name, $token->name))->locale($recipient->accountLocale()));
        }
    }
}
