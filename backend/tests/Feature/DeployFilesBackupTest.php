<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * deploy/files-backup.sh packs the uploaded files (Auftrag 64 A): the script runs in a folder that looks like a server.
 * db-backup.sh daily calls it, so the cron entry stays the same.
 */
class DeployFilesBackupTest extends TestCase
{
    private string $root;

    private string $backups;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/deploy-files-'.bin2hex(random_bytes(4));
        $this->backups = $this->root.'/backups';
        mkdir($this->root.'/bin', 0777, true);
        mkdir($this->root.'/app/deploy', 0777, true);
        mkdir($this->root.'/app/backend/storage/app/private/invoices', 0777, true);
        mkdir($this->root.'/app/backend/storage/logs', 0777, true);
        foreach (['db-backup.sh', 'files-backup.sh'] as $script) {
            copy(base_path('../deploy/'.$script), $this->root.'/app/deploy/'.$script);
            chmod($this->root.'/app/deploy/'.$script, 0755);
        }
        file_put_contents($this->root.'/app/backend/.env', 'DEPLOY_BACKUP_DIR='.$this->backups."\nAPP_ENV=staging\nDB_DATABASE=planer_test\n");
        file_put_contents($this->root.'/app/backend/storage/app/private/invoices/R-1.pdf', 'invoice');
        file_put_contents($this->root.'/app/backend/storage/logs/laravel.log', 'log');
        file_put_contents($this->root.'/app/backend/storage/oauth-private.key', 'secret');
        file_put_contents($this->root.'/bin/mysqldump', "#!/usr/bin/env bash\necho 'INSERT INTO t VALUES (1);'\necho '-- Dump completed on 2026-11-25'\n");
        chmod($this->root.'/bin/mysqldump', 0755);
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->root]))->run();

        parent::tearDown();
    }

    public function test_the_daily_archive_holds_only_the_app_folder_and_is_closed(): void
    {
        $result = $this->files('daily');

        $this->assertSame(0, $result['exit'], $result['output']);
        $archives = glob($this->backups.'/files/daily/planer-files-*.tar.gz') ?: [];
        $this->assertCount(1, $archives);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($archives[0])), -4));
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->backups.'/files/daily')), -4));
        $list = (string) shell_exec('tar -tzf '.escapeshellarg($archives[0]));
        $this->assertStringContainsString('app/private/invoices/R-1.pdf', $list);
        $this->assertStringNotContainsString('laravel.log', $list, 'logs stay out');
        $this->assertStringNotContainsString('oauth-private.key', $list, 'the OAuth keys stay out');
        $this->assertSame([], glob($this->backups.'/files/daily/.start.*') ?: [], 'no marker left behind');
        $this->assertFileExists($this->backups.'/files/monthly/planer-files-'.date('Ym').'.tar.gz');
    }

    public function test_without_a_change_nothing_new_is_packed_and_with_a_change_it_is(): void
    {
        $this->assertSame(0, $this->files('daily')['exit']);
        $first = glob($this->backups.'/files/daily/planer-files-*.tar.gz') ?: [];
        touch($first[0], time() - 3600);
        touch($this->root.'/app/backend/storage/app/private/invoices/R-1.pdf', time() - 7200);

        $again = $this->files('daily');
        $this->assertSame(0, $again['exit'], $again['output']);
        $this->assertStringContainsString('Keine Änderung', $again['output']);
        $this->assertCount(1, glob($this->backups.'/files/daily/planer-files-*.tar.gz') ?: []);

        file_put_contents($this->root.'/app/backend/storage/app/private/invoices/R-2.pdf', 'second');
        sleep(1);
        $this->assertSame(0, $this->files('daily')['exit']);
        $archives = glob($this->backups.'/files/daily/planer-files-*.tar.gz') ?: [];
        $this->assertGreaterThanOrEqual(1, count($archives));
        $newest = collect($archives)->sortByDesc(fn (string $file): int => filemtime($file))->first();
        $this->assertStringContainsString('R-2.pdf', (string) shell_exec('tar -tzf '.escapeshellarg($newest)));
    }

    public function test_the_last_fourteen_daily_archives_and_six_monthly_ones_are_kept(): void
    {
        mkdir($this->backups.'/files/daily', 0700, true);
        mkdir($this->backups.'/files/monthly', 0700, true);
        foreach (range(1, 16) as $i) {
            $file = $this->backups.'/files/daily/'.sprintf('planer-files-202601%02d-030000.tar.gz', $i);
            file_put_contents($file, 'x');
            touch($file, strtotime(sprintf('2026-01-%02d 03:00:00', $i)));
        }
        foreach (range(1, 7) as $i) {
            $file = $this->backups.'/files/monthly/'.sprintf('planer-files-20250%d.tar.gz', $i);
            file_put_contents($file, 'x');
            touch($file, strtotime(sprintf('2025-0%d-01 03:00:00', $i)));
        }

        $this->assertSame(0, $this->files('daily')['exit']);

        $this->assertCount(14, glob($this->backups.'/files/daily/planer-files-*.tar.gz') ?: []);
        $this->assertFileDoesNotExist($this->backups.'/files/daily/planer-files-20260103-030000.tar.gz');
        $this->assertCount(6, glob($this->backups.'/files/monthly/planer-files-*.tar.gz') ?: []);
        $this->assertFileDoesNotExist($this->backups.'/files/monthly/planer-files-202501.tar.gz');
    }

    public function test_the_safety_copy_carries_its_name_and_the_last_twenty_are_kept(): void
    {
        mkdir($this->backups.'/files', 0700, true);
        foreach (range(1, 22) as $i) {
            $file = $this->backups.'/files/'.sprintf('planer-files-20260101-0000%02d-old%02d.tar.gz', $i, $i);
            file_put_contents($file, 'x');
            touch($file, strtotime("2026-01-01 00:00:{$i}"));
        }

        $result = $this->files('deploy vor-restore');

        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertCount(20, glob($this->backups.'/files/planer-files-*.tar.gz') ?: []);
        $this->assertNotEmpty(glob($this->backups.'/files/planer-files-*-vor-restore.tar.gz'));
        $this->assertFileDoesNotExist($this->backups.'/files/planer-files-20260101-000001-old01.tar.gz');
    }

    public function test_a_wrong_call_is_refused(): void
    {
        $this->assertSame(64, $this->files('deploy')['exit']);
        $this->assertSame(64, $this->files('weekly')['exit']);
        $this->assertSame([], glob($this->backups.'/files/*') ?: []);
    }

    public function test_a_failing_pack_exits_with_an_error_and_leaves_no_part_file(): void
    {
        file_put_contents($this->root.'/bin/tar', "#!/usr/bin/env bash\necho 'tar: error' >&2\nexit 2\n");
        chmod($this->root.'/bin/tar', 0755);

        $result = $this->files('daily');

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('Sicherung der Dateien ist fehlgeschlagen', $result['output']);
        $this->assertSame([], glob($this->backups.'/files/daily/*') ?: []);
        $this->assertSame([], glob($this->backups.'/files/daily/.*.part') ?: []);
    }

    public function test_the_daily_database_backup_also_packs_the_files(): void
    {
        $result = $this->run_script('db-backup.sh', 'daily');

        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertNotEmpty(glob($this->backups.'/db/daily/planer-*.sql.gz'));
        $this->assertNotEmpty(glob($this->backups.'/files/daily/planer-files-*.tar.gz'));
    }

    public function test_if_the_files_fail_the_dump_stays_and_the_exit_is_an_error(): void
    {
        (new Process(['rm', '-rf', $this->root.'/app/backend/storage/app']))->run();

        $result = $this->run_script('db-backup.sh', 'daily');

        $this->assertNotSame(0, $result['exit']);
        $this->assertNotEmpty(glob($this->backups.'/db/daily/planer-*.sql.gz'), 'the dump is kept');
        $this->assertStringContainsString('Sicherung der Dateien ist fehlgeschlagen', $result['output']);
    }

    /** @return array{exit: int, output: string} */
    private function files(string $arguments): array
    {
        return $this->run_script('files-backup.sh', $arguments);
    }

    /** @return array{exit: int, output: string} */
    private function run_script(string $script, string $arguments): array
    {
        $process = new Process(['bash', '-c', 'bash "$ROOT/app/deploy/'.$script.'" '.$arguments], null, ['ROOT' => $this->root, 'PATH' => $this->root.'/bin:'.getenv('PATH'), 'HOME' => $this->root]);
        $process->run();

        return ['exit' => (int) $process->getExitCode(), 'output' => $process->getOutput().$process->getErrorOutput()];
    }
}
