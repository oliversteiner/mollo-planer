<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\GraphQLFailed;

/**
 * AI clients name people by e-mail; the API wants ids.
 */
trait ResolvesPeople
{
    /**
     * Ids of all people with an account, by lower-case e-mail. Needs the
     * right to see the user list (admins, member managers).
     *
     * @return array<string, string>
     */
    protected function userIdsByEmail(): array
    {
        $users = $this->graphQL('{ users { id email } }')['users'] ?? [];

        return collect($users)->mapWithKeys(fn (array $user) => [mb_strtolower($user['email']) => $user['id']])->all();
    }

    /**
     * @param  array<string, string>  $idsByEmail
     *
     * @throws GraphQLFailed
     */
    protected function idForEmail(array $idsByEmail, string $email, string $hint): string
    {
        return $idsByEmail[mb_strtolower(trim($email))]
            ?? throw new GraphQLFailed("No person with the e-mail {$email} {$hint}");
    }
}
