<?php

namespace App\Core\Accounts;

/**
 * The directory of everything that points at an account (Auftrag 37 E, docs/starter-kit.md): every column that holds the id
 * of a row in `users`. The starter kit needs it to move or merge accounts, and later exports need it to know what belongs to
 * a person. Not listed: `personal_access_tokens` (Sanctum, points at the account through `tokenable_type` and `tokenable_id`). The test AccountReferencesTest keeps it complete: a new foreign key to `users`, or a new column that looks like
 * a reference (user_id, owner_id, assigned_user_id, ..._by), fails until it is listed here.
 */
final class AccountReferences
{
    /** The database holds the link (a foreign key), so a deleted account is handled by the key (cascade, set null). */
    public const FOREIGN = 'foreign';

    /** The column holds an account id without a foreign key (framework tables); the code has to look after it. */
    public const PLAIN = 'plain';

    /**
     * @return list<array{table: string, column: string, kind: string}>
     */
    public static function all(): array
    {
        $entries = [];

        foreach (self::REFERENCES as $table => $columns) {
            foreach ($columns as $column => $kind) {
                $entries[] = ['table' => $table, 'column' => $column, 'kind' => $kind];
            }
        }

        return $entries;
    }

    /**
     * @return list<string> the columns of $table that point at an account
     */
    public static function columnsOf(string $table): array
    {
        return array_keys(self::REFERENCES[$table] ?? []);
    }

    /**
     * @return list<string> the tables that hold at least one reference
     */
    public static function tables(): array
    {
        return array_keys(self::REFERENCES);
    }

    /**
     * table => column => kind. Sorted by table name.
     *
     * @var array<string, array<string, string>>
     */
    private const REFERENCES = [
        'comments' => [
            'deleted_by' => self::FOREIGN,
            'user_id' => self::FOREIGN,
        ],
        'document_shares' => [
            'user_id' => self::FOREIGN,
        ],
        'document_versions' => [
            'created_by' => self::FOREIGN,
        ],
        'documents' => [
            'deleted_by' => self::FOREIGN,
            'owner_id' => self::FOREIGN,
            'updated_by' => self::FOREIGN,
        ],
        'ideas' => [
            'deleted_by' => self::FOREIGN,
            'user_id' => self::FOREIGN,
        ],
        'milestones' => [
            'created_by' => self::FOREIGN,
            'deleted_by' => self::FOREIGN,
        ],
        'mcp_tool_calls' => [
            'user_id' => self::PLAIN,
        ],
        'oauth_access_tokens' => [
            'user_id' => self::PLAIN,
        ],
        'oauth_auth_codes' => [
            'user_id' => self::PLAIN,
        ],
        'oauth_clients' => [
            'owner_id' => self::PLAIN,
            'registered_by' => self::PLAIN,
        ],
        'oauth_device_codes' => [
            'user_id' => self::PLAIN,
        ],
        'passkeys' => [
            'user_id' => self::FOREIGN,
        ],
        'project_ai_contexts' => [
            'updated_by' => self::FOREIGN,
        ],
        'project_user' => [
            'user_id' => self::FOREIGN,
        ],
        'projects' => [
            'deleted_by' => self::FOREIGN,
        ],
        'sessions' => [
            'user_id' => self::PLAIN,
        ],
        'tasks' => [
            'assigned_user_id' => self::FOREIGN,
            'created_by' => self::FOREIGN,
            'deleted_by' => self::FOREIGN,
        ],
        'templates' => [
            'created_by' => self::FOREIGN,
        ],
        'todos' => [
            'created_by' => self::FOREIGN,
            'deleted_by' => self::FOREIGN,
        ],
    ];
}
