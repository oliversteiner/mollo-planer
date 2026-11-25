<?php

namespace Tests\Unit;

use App\Core\Accounts\AccountReferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Keeps the directory of references to accounts complete (Auftrag 37 E). A migration that adds a foreign key to `users`, or a
 * column that looks like a reference to one, does not pass until App\Core\Accounts\AccountReferences lists it: whoever moves
 * or exports accounts later then does not miss a table.
 */
#[Group('mariadb')]
class AccountReferencesTest extends TestCase
{
    use RefreshDatabase;

    /** Columns that look like a reference to an account. */
    private const LOOKS_LIKE_A_REFERENCE = '/^(user_id|owner_id|[a-z]+_user_id|[a-z_]+_by)$/';

    public function test_every_foreign_key_to_users_is_listed(): void
    {
        $missing = array_values(array_diff($this->foreignKeysToUsers(), $this->listed(AccountReferences::FOREIGN)));

        $this->assertSame([], $missing, "These foreign keys to `users` are not in App\\Core\\Accounts\\AccountReferences:\n".implode("\n", $missing));
    }

    public function test_every_listed_foreign_key_exists(): void
    {
        $gone = array_values(array_diff($this->listed(AccountReferences::FOREIGN), $this->foreignKeysToUsers()));

        $this->assertSame([], $gone, "These entries of AccountReferences are not foreign keys to `users` (any more):\n".implode("\n", $gone));
    }

    public function test_every_listed_column_exists(): void
    {
        $gone = [];
        foreach (AccountReferences::all() as $entry) {
            if (! Schema::hasColumn($entry['table'], $entry['column'])) {
                $gone[] = $entry['table'].'.'.$entry['column'];
            }
        }

        $this->assertSame([], $gone, "These columns of AccountReferences do not exist:\n".implode("\n", $gone));
    }

    public function test_a_column_that_looks_like_a_reference_is_listed(): void
    {
        $unlisted = [];
        foreach ($this->appTables() as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                if (preg_match(self::LOOKS_LIKE_A_REFERENCE, $column) !== 1) {
                    continue;
                }
                if (! in_array($column, AccountReferences::columnsOf($table), true) && ! in_array($table.'.'.$column, self::NOT_ACCOUNTS, true)) {
                    $unlisted[] = $table.'.'.$column;
                }
            }
        }

        $this->assertSame([], $unlisted, "These columns look like a reference to an account but are not in AccountReferences (add them, or add them to NOT_ACCOUNTS in this test if they point somewhere else):\n".implode("\n", $unlisted));
    }

    public function test_the_kind_of_every_entry_is_known_and_each_entry_is_listed_once(): void
    {
        $seen = [];
        foreach (AccountReferences::all() as $entry) {
            $this->assertContains($entry['kind'], [AccountReferences::FOREIGN, AccountReferences::PLAIN]);
            $key = $entry['table'].'.'.$entry['column'];
            $this->assertArrayNotHasKey($key, $seen, "$key is listed twice");
            $seen[$key] = true;
        }
    }

    /**
     * Columns that look like a reference but point at another table (not at an account).
     *
     * @var list<string>
     */
    private const NOT_ACCOUNTS = [];

    /** @return list<string> "table.column" of every entry of the given kind */
    private function listed(string $kind): array
    {
        $names = [];
        foreach (AccountReferences::all() as $entry) {
            if ($entry['kind'] === $kind) {
                $names[] = $entry['table'].'.'.$entry['column'];
            }
        }
        sort($names);

        return $names;
    }

    /** @return list<string> "table.column" of every foreign key that points at `users` */
    private function foreignKeysToUsers(): array
    {
        $names = [];
        foreach ($this->appTables() as $table) {
            foreach (Schema::getForeignKeys($table) as $key) {
                if ($key['foreign_table'] === 'users') {
                    foreach ($key['columns'] as $column) {
                        $names[] = $table.'.'.$column;
                    }
                }
            }
        }
        sort($names);

        return array_values(array_unique($names));
    }

    /** @return list<string> */
    private function appTables(): array
    {
        return array_values(array_filter(
            // Only this database: a MariaDB server lists the tables of all its databases (Auftrag 57).
            array_column(Schema::getTables(Schema::getCurrentSchemaListing()), 'name'),
            fn (string $table): bool => $table !== 'users' && $table !== 'migrations',
        ));
    }
}
