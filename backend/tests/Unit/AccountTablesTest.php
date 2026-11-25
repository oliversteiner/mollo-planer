<?php

namespace Tests\Unit;

use App\Core\Accounts\Local\AccountTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Every table of the schema is sorted in for the local snapshots of accounts (Auftrag 45 A): account data, or not with
 * a reason. A new table fails here until someone decides; so a new kind of credential is not left out of the snapshots.
 */
#[Group('mariadb')]
class AccountTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_table_is_sorted_in_as_account_data_or_not(): void
    {
        // Only this database: a MariaDB server lists the tables of all its databases (Auftrag 57).
        $tables = Schema::getTableListing(schema: Schema::getCurrentSchemaListing(), schemaQualified: false);
        $sorted = [...array_keys(AccountTables::ACCOUNT), ...array_keys(AccountTables::NOT_ACCOUNT)];

        $missing = array_values(array_diff($tables, $sorted));
        sort($missing);

        $this->assertSame([], $missing, "These tables are not in App\\Core\\Accounts\\Local\\AccountTables (ACCOUNT or NOT_ACCOUNT):\n".implode("\n", $missing));
        $this->assertSame([], array_values(array_intersect(array_keys(AccountTables::ACCOUNT), array_keys(AccountTables::NOT_ACCOUNT))), 'a table is in both lists');
        $this->assertSame([], array_values(array_diff(array_keys(AccountTables::ACCOUNT), $tables)), 'ACCOUNT names a table the schema does not have');
    }
}
