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
 * password nobody knows, verified and approved at once, no mail to the account. Its address is an identifier, not a
 * mailbox: made from the name as <slug>@agent.invalid (RFC 2606 reserves .invalid, it never receives mail), unless the
 * command app:create-agent is given one (Auftrag 58 E). It has no web access
 * (RefuseAgentLogin, EnsureNotAgent); the way in is a token for the connector. Made by an admin in the app, it is
 * logged (ids, never the address) and the admins are told by mail; the command logs on its own.
 */
class AgentAccounts
{
    /** The domain of the identifiers of agent accounts. */
    public const IDENTIFIER_DOMAIN = 'agent.invalid';

    /**
     * @return array{name: list<string>, email: list<string>}
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ];
    }

    public static function isIdentifier(string $email): bool
    {
        return str_ends_with(mb_strtolower(trim($email)), '@'.self::IDENTIFIER_DOMAIN);
    }

    /**
     * A free identifier from the name: lower case a–z, 0–9 and hyphens, umlauts written out (ä → ae);
     * <slug>-2@agent.invalid and so on when it is taken (without regard to case).
     */
    public static function identifierFor(string $name): string
    {
        $slug = Str::slug(Str::ascii($name, 'de'), '-', 'de');
        $slug = $slug === '' ? 'agent' : Str::limit($slug, 60, '');

        for ($number = 1; ; $number++) {
            $candidate = ($number === 1 ? $slug : "{$slug}-{$number}").'@'.self::IDENTIFIER_DOMAIN;

            if (! User::query()->whereRaw('LOWER(email) = ?', [$candidate])->exists()) {
                return $candidate;
            }
        }
    }

    /**
     * @param  string|null  $email  only for special cases (app:create-agent); empty: the identifier is made from the name
     *
     * @throws ValidationException
     */
    public function create(string $name, ?string $email = null, ?User $admin = null): User
    {
        $email = trim((string) $email);
        $input = Validator::make(['name' => trim($name), 'email' => $email === '' ? null : $email], self::rules())->validate();

        return DB::transaction(function () use ($input, $admin): User {
            $agent = User::create([
                'name' => $input['name'],
                'email' => $input['email'] ?? self::identifierFor($input['name']),
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
