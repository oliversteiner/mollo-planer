<?php

namespace App\Core\Accounts;

use App\Enums\Right;
use App\Models\Role;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates an agent account (Claude, a person's assistant), the one way for the command app:create-agent and the
 * mutation createAgent (docs/verwaltung-agenten-anfasser.md §4.1): system role Mitglied, never an admin, a random
 * password nobody knows, verified and approved at once, no mail to the account. It has no web access
 * (RefuseAgentLogin, EnsureNotAgent); the way in is a token for the connector. Made by an admin in the app, it is
 * logged (ids, never the address) and the admins are told by mail; the command logs on its own.
 */
class AgentAccounts
{
    /**
     * @return array{name: list<string>, email: list<string>}
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function create(string $name, string $email, ?User $admin = null): User
    {
        $input = Validator::make(['name' => trim($name), 'email' => trim($email)], self::rules())->validate();

        return DB::transaction(function () use ($input, $admin): User {
            $agent = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'role' => Role::resolveOrFail(Role::SCOPE_SYSTEM, 'mitglied'),
                'password' => Str::password(64),
            ]);
            $agent->forceFill(['is_agent' => true, 'email_verified_at' => now(), 'approved_at' => now()])->save();

            if ($admin !== null) {
                $this->record($admin, $agent);
            }

            return $agent;
        });
    }

    private function record(User $admin, User $agent): void
    {
        Log::warning('Agent account created.', [
            'admin_id' => $admin->getKey(),
            'agent_id' => $agent->getKey(),
            'at' => now()->toIso8601String(),
        ]);

        $admins = User::query()
            ->whereIn('role_id', Rights::roleIdsWith(Right::USERS_MANAGE))
            ->whereNotNull('approved_at')
            ->get();

        foreach ($admins as $recipient) {
            $recipient->notify((new AgentAccountCreated($admin->name, $agent->name))->locale($recipient->accountLocale()));
        }
    }
}
