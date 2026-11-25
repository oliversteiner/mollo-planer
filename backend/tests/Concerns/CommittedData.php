<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Schema;

/**
 * For tests whose rows must really be committed (Auftrag 57): a FULLTEXT index of InnoDB sees a row only after the
 * commit, and the Postman collection expects the IDs of a fresh database (1, 2, …), which a rolled-back transaction
 * does not give back on MariaDB. Instead of DatabaseMigrations (a whole run of the migrations before the test, and one
 * more for the next test with RefreshDatabase), the schema is built once per run, like RefreshDatabase does:
 *
 * - before the test: the schema, if this run has none yet;
 * - before and after the test: every table emptied (TRUNCATE, so the IDs start at 1 again), and the rows the migrations
 *   themselves write (roles, functions, their rights) put back as they were. The test starts from a fresh database, and
 *   the next test finds the state the migrations left, the same RefreshDatabase starts from.
 *
 * An in-memory SQLite database (the default suite) is new for every test anyway: there this works like RefreshDatabase
 * without the transaction.
 */
trait CommittedData
{
    /** @var array<string, array<string, list<array<string, mixed>>>> per connection: table => the rows the migrations wrote */
    private static array $migratedRows = [];

    public function setUpCommittedData(): void
    {
        if ($this->usesInMemoryDatabase()) {
            $this->artisan('migrate', ['--no-interaction' => true]);
            $this->app[Kernel::class]->setArtisan(null);

            return;
        }

        $this->useTheDatabaseOfThisProcess();

        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', ['--no-interaction' => true]);
            $this->app[Kernel::class]->setArtisan(null);
            RefreshDatabaseState::$migrated = true;
        }

        $connection = DB::connection()->getName();
        self::$migratedRows[$connection] ??= $this->rowsOfTheMigrations();

        // Before: the IDs start at 1 (the transactions of RefreshDatabase before do not give their counters back on
        // MariaDB). After: the next test finds the state the migrations left.
        $this->restoreTheMigratedState(self::$migratedRows[$connection]);
        $this->beforeApplicationDestroyed(fn () => $this->restoreTheMigratedState(self::$migratedRows[$connection]));
    }

    /**
     * In a parallel run (ParaTest) every process has a database of its own, «<name>_test_<token>». Laravel switches to it
     * only for its own database traits, not for this one: without this the test would write into the database of the
     * run instead of the one of its process. Made here the way Laravel makes it, if this is the first test of the process.
     */
    private function useTheDatabaseOfThisProcess(): void
    {
        $token = ParallelTesting::token();

        if ($token === false) {
            return;
        }

        $default = (string) config('database.default');
        $database = (string) config("database.connections.{$default}.database");

        if (str_ends_with($database, "_test_{$token}")) {
            return;
        }

        $ownDatabase = "{$database}_test_{$token}";

        try {
            $this->switchDatabase($default, $ownDatabase);
            Schema::hasTable('migrations');
        } catch (QueryException) {
            $this->switchDatabase($default, $database);
            Schema::createDatabase($ownDatabase);
            $this->switchDatabase($default, $ownDatabase);
            RefreshDatabaseState::$migrated = false;
        }
    }

    private function switchDatabase(string $connection, string $database): void
    {
        config(["database.connections.{$connection}.database" => $database]);
        DB::purge($connection);
    }

    private function usesInMemoryDatabase(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() === ':memory:';
    }

    /**
     * The tables of this database only (a MariaDB server lists the tables of all its databases), without the
     * migrations table.
     *
     * @return list<string>
     */
    private function tablesOfThisDatabase(): array
    {
        $tables = Schema::getTableListing(schema: Schema::getCurrentSchemaListing(), schemaQualified: false);

        return array_values(array_filter($tables, fn (string $table): bool => $table !== 'migrations'));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function rowsOfTheMigrations(): array
    {
        $rows = [];

        foreach ($this->tablesOfThisDatabase() as $table) {
            $content = DB::table($table)->get()->map(fn (object $row): array => (array) $row)->all();

            if ($content !== []) {
                $rows[$table] = array_values($content);
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $rows
     */
    private function restoreTheMigratedState(array $rows): void
    {
        Schema::withoutForeignKeyConstraints(function () use ($rows): void {
            foreach ($this->tablesOfThisDatabase() as $table) {
                DB::table($table)->truncate();
            }

            foreach ($rows as $table => $content) {
                foreach (array_chunk($content, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        });
    }
}
