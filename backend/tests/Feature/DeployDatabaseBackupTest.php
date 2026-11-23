<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * deploy/deploy.sh dumps the database before the migration (Auftrag 37 A): the block of the script runs in a folder that looks
 * like a server, with a stand-in for mysqldump that records how it was called.
 */
class DeployDatabaseBackupTest extends TestCase
{
    private string $root;

    private string $backups;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/deploy-backup-'.bin2hex(random_bytes(4));
        $this->backups = $this->root.'/backups';
        mkdir($this->root.'/bin', 0777, true);
        mkdir($this->root.'/backend', 0777, true);
        file_put_contents($this->root.'/backend/.env', "APP_ENV=staging\nDB_HOST=db.example\nDB_PORT=3307\nDB_DATABASE=planer_test\nDB_USERNAME=planer\nDB_PASSWORD=\"se\"cr\\et\$x\"\n");
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->root]))->run();

        parent::tearDown();
    }

    public function test_the_dump_is_made_in_the_db_folder_with_date_time_and_commit_and_closed_rights(): void
    {
        $result = $this->deploy('ok');

        $this->assertSame(0, $result['exit'], $result['output']);
        $files = glob($this->backups.'/db/planer-*-abc1234.sql.gz') ?: [];
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/planer-\d{8}-\d{6}-abc1234\.sql\.gz$/', $files[0]);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($files[0])), -4));
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->backups.'/db')), -4));
        $this->assertStringContainsString('INSERT INTO', (string) shell_exec('gzip -dc '.escapeshellarg($files[0])));
        $this->assertStringContainsString('Backup: '.$files[0], $result['output']);
        $this->assertStringContainsString('MIGRATE', $result['output'], 'goes on to the migration');
    }

    public function test_the_credentials_go_in_a_temporary_option_file_and_nowhere_else(): void
    {
        $result = $this->deploy('ok');
        $call = (string) file_get_contents($this->root.'/call.txt');

        $this->assertStringContainsString('ARG --defaults-extra-file=', $call);
        $this->assertStringContainsString('MODE 600', $call, 'the option file is closed while it exists');
        $this->assertStringContainsString('password="se\"cr\\\\et$x"', $call, 'the password reaches the option file, escaped');
        $this->assertStringContainsString('host="db.example"', $call);
        $this->assertStringContainsString('port="3307"', $call);
        $this->assertStringContainsString('user="planer"', $call);
        $this->assertStringContainsString('ARG planer_test', $call);
        $this->assertStringContainsString('ENV_PWD_SET no', $call, 'not in the environment of the dump either');
        $argLines = implode("\n", array_filter(explode("\n", $call), fn (string $line): bool => str_starts_with($line, 'ARG ')));
        $this->assertStringNotContainsString('cr', $argLines, 'never on the command line');
        $this->assertStringNotContainsString('et$x', $result['output'], 'never in the output');
        $this->assertSame([], glob($this->backups.'/db/.options.*') ?: [], 'the option file is deleted afterwards');
    }

    public function test_a_failing_dump_stops_before_the_migration_and_leaves_no_half_dump(): void
    {
        $result = $this->deploy('fail');

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('Datenbanksicherung ist fehlgeschlagen', $result['output']);
        $this->assertStringNotContainsString('MIGRATE', $result['output']);
        $this->assertSame([], glob($this->backups.'/db/*') ?: [], 'no dump and no option file');
        $this->assertStringNotContainsString('et$x', $result['output']);
    }

    public function test_a_dump_that_stops_halfway_counts_as_failed(): void
    {
        $result = $this->deploy('truncated');

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringNotContainsString('MIGRATE', $result['output']);
        $this->assertSame([], glob($this->backups.'/db/*') ?: []);
    }

    public function test_the_last_twenty_are_kept(): void
    {
        mkdir($this->backups.'/db', 0700, true);
        foreach (range(1, 24) as $i) {
            $file = $this->backups.'/db/'.sprintf('planer-20260101-0000%02d-old%02d.sql.gz', $i, $i);
            file_put_contents($file, 'x');
            touch($file, strtotime("2026-01-01 00:00:{$i}"));
        }

        $this->assertSame(0, $this->deploy('ok')['exit']);

        $left = glob($this->backups.'/db/planer-*.sql.gz') ?: [];
        $this->assertCount(20, $left);
        $this->assertNotEmpty(glob($this->backups.'/db/planer-*-abc1234.sql.gz'), 'the new one stays');
        $this->assertFileDoesNotExist($this->backups.'/db/planer-20260101-000001-old01.sql.gz', 'the oldest go');
        $this->assertFileExists($this->backups.'/db/planer-20260101-000024-old24.sql.gz');
    }

    /** @return array{exit: int, output: string} */
    private function deploy(string $mode): array
    {
        $script = (string) file_get_contents(base_path('../deploy/deploy.sh'));
        preg_match('/^env_value\(\) \{.*?^\}/ms', $script, $envValue);
        preg_match('/^KEEP_BACKUPS=\d+/m', $script, $keep);
        preg_match('/DB_OPTIONS_FILE=""\n/', $script, $optionsVar);
        preg_match('/# --- db-backup begin.*?# --- db-backup end/s', $script, $block);
        $this->assertNotEmpty($envValue);
        $this->assertNotEmpty($keep);
        $this->assertNotEmpty($block);

        file_put_contents($this->root.'/bin/mysqldump', <<<'SH'
            #!/usr/bin/env bash
            {
              for a in "$@"; do case "$a" in --defaults-extra-file=*) f="${a#*=}";; esac; echo "ARG $a"; done
              echo "MODE $(stat -c '%a' "$f" 2>/dev/null || stat -f '%Lp' "$f")"
              echo "ENV_PWD_SET $([ -n "${MYSQL_PWD:-}" ] && echo yes || echo no)"
              cat "$f"
            } > "$CALL_FILE"
            case "$MODE" in
              fail) echo "mysqldump: Got error: 1045" >&2; exit 2;;
              truncated) echo "INSERT INTO t VALUES (1);"; exit 0;;
              *) echo "INSERT INTO t VALUES (1);"; echo "-- Dump completed on 2026-11-24"; exit 0;;
            esac
            SH);
        chmod($this->root.'/bin/mysqldump', 0755);

        $program = 'set -euo pipefail; BACKUP_DIR="$ROOT/backups"; BACKEND="$ROOT/backend"; COMMIT_SHORT=abc1234; '
            .$keep[0]."\n".($optionsVar[0] ?? '')."\n".$envValue[0]."\n".$block[0]."\necho MIGRATE\n";
        $process = new Process(['bash', '-c', $program], null, ['ROOT' => $this->root, 'PATH' => $this->root.'/bin:'.getenv('PATH'), 'MODE' => $mode, 'CALL_FILE' => $this->root.'/call.txt', 'HOME' => $this->root]);
        $process->run();

        return ['exit' => (int) $process->getExitCode(), 'output' => $process->getOutput().$process->getErrorOutput()];
    }
}
