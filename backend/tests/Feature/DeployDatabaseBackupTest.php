<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * deploy/db-backup.sh dumps the database (Auftrag 37 A and B): the script runs in a folder that looks like a server, with a
 * stand-in for mysqldump that records how it was called. deploy.sh calls it before the code is replaced (see DeployOrderTest).
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
        mkdir($this->root.'/app/deploy', 0777, true);
        mkdir($this->root.'/app/backend', 0777, true);
        foreach (['db-backup.sh', 'files-backup.sh'] as $script) {
            copy(base_path('../deploy/'.$script), $this->root.'/app/deploy/'.$script);
            chmod($this->root.'/app/deploy/'.$script, 0755);
        }
        mkdir($this->root.'/app/backend/storage/app/private', 0777, true);
        file_put_contents($this->root.'/app/backend/storage/app/private/invoice.pdf', 'pdf');
        file_put_contents($this->root.'/app/backend/.env', 'DEPLOY_BACKUP_DIR='.$this->root."/backups\nAPP_ENV=staging\nDB_HOST=db.example\nDB_PORT=3307\nDB_DATABASE=planer_test\nDB_USERNAME=planer\nDB_PASSWORD=\"se\"cr\\et\$x\"\n");
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

    public function test_a_failing_dump_leaves_no_part_file_and_keeps_the_earlier_dumps(): void
    {
        mkdir($this->backups.'/db', 0700, true);
        file_put_contents($this->backups.'/db/planer-20260101-000000-old.sql.gz', 'x');

        $this->deploy('fail');

        $this->assertSame([$this->backups.'/db/planer-20260101-000000-old.sql.gz'], glob($this->backups.'/db/*') ?: []);
    }

    public function test_the_daily_backup_goes_in_its_own_folder_and_keeps_the_last_fourteen(): void
    {
        mkdir($this->backups.'/db/daily', 0700, true);
        foreach (range(1, 16) as $i) {
            $file = $this->backups.'/db/daily/'.sprintf('planer-202601%02d-030000.sql.gz', $i);
            file_put_contents($file, 'x');
            touch($file, strtotime(sprintf('2026-01-%02d 03:00:00', $i)));
        }
        file_put_contents($this->backups.'/db/planer-20260101-000000-keepme.sql.gz', 'x');

        $result = $this->run_script('daily');

        $this->assertSame(0, $result['exit'], $result['output']);
        $daily = glob($this->backups.'/db/daily/planer-*.sql.gz') ?: [];
        $this->assertCount(14, $daily);
        $this->assertFileDoesNotExist($this->backups.'/db/daily/planer-20260101-030000.sql.gz');
        $this->assertFileDoesNotExist($this->backups.'/db/daily/planer-20260103-030000.sql.gz');
        $this->assertFileExists($this->backups.'/db/daily/planer-20260104-030000.sql.gz');
        $this->assertFileExists($this->backups.'/db/planer-20260101-000000-keepme.sql.gz', 'the dumps before deployments are not touched');
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->backups.'/db/daily')), -4));
    }

    public function test_the_first_daily_backup_of_a_month_is_kept_for_six_months(): void
    {
        mkdir($this->backups.'/db/monthly', 0700, true);
        foreach (range(1, 7) as $i) {
            $file = $this->backups.'/db/monthly/'.sprintf('planer-20250%d.sql.gz', $i);
            file_put_contents($file, 'x');
            touch($file, strtotime(sprintf('2025-0%d-01 03:00:00', $i)));
        }

        $this->assertSame(0, $this->run_script('daily')['exit']);
        $month = $this->backups.'/db/monthly/planer-'.date('Ym').'.sql.gz';
        $this->assertFileExists($month);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($month)), -4));
        $this->assertCount(6, glob($this->backups.'/db/monthly/planer-*.sql.gz') ?: []);
        $this->assertFileDoesNotExist($this->backups.'/db/monthly/planer-202501.sql.gz');

        $before = filemtime($month);
        $this->assertSame(0, $this->run_script('daily')['exit']);
        $this->assertSame($before, filemtime($month), 'a second run in the same month does not replace it');
        $this->assertCount(6, glob($this->backups.'/db/monthly/planer-*.sql.gz') ?: []);
    }

    public function test_a_failing_daily_backup_exits_with_an_error(): void
    {
        $result = $this->run_script('daily', 'fail');

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('tägliche Datenbanksicherung ist fehlgeschlagen', $result['output']);
        $this->assertSame([], glob($this->backups.'/db/daily/*') ?: []);
    }

    public function test_without_a_commit_the_deploy_mode_refuses_to_run(): void
    {
        $result = $this->run_script('deploy');

        $this->assertSame(64, $result['exit']);
        $this->assertSame([], glob($this->backups.'/db/*') ?: []);
    }

    /** @return array{exit: int, output: string} */
    private function deploy(string $mode): array
    {
        return $this->run_script('deploy abc1234', $mode);
    }

    /** @return array{exit: int, output: string} */
    private function run_script(string $arguments, string $mode = 'ok'): array
    {
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

        $process = new Process(['bash', '-c', 'bash "$ROOT/app/deploy/db-backup.sh" '.$arguments.' && echo MIGRATE'], null, ['ROOT' => $this->root, 'PATH' => $this->root.'/bin:'.getenv('PATH'), 'MODE' => $mode, 'CALL_FILE' => $this->root.'/call.txt', 'HOME' => $this->root]);
        $process->run();

        return ['exit' => (int) $process->getExitCode(), 'output' => $process->getOutput().$process->getErrorOutput()];
    }
}
