<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * deploy/files-restore.sh replaces the uploaded files with an archive (Auftrag 64 B): it asks first, backs the current state
 * up, and swaps the folder only after both. php is a stand-in that writes down how it was called.
 */
class DeployFilesRestoreTest extends TestCase
{
    private string $root;

    private string $uploads;

    private string $archive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/deploy-files-restore-'.bin2hex(random_bytes(4));
        $this->uploads = $this->root.'/app/backend/storage/app';
        mkdir($this->root.'/bin', 0777, true);
        mkdir($this->root.'/app/deploy', 0777, true);
        mkdir($this->uploads.'/private/invoices', 0777, true);
        foreach (['files-backup.sh', 'files-restore.sh'] as $script) {
            copy(base_path('../deploy/'.$script), $this->root.'/app/deploy/'.$script);
            chmod($this->root.'/app/deploy/'.$script, 0755);
        }
        file_put_contents($this->root.'/app/backend/.env', 'DEPLOY_BACKUP_DIR='.$this->root."/backups\nAPP_ENV=staging\nDEPLOY_PHP=".$this->root."/bin/php\n");
        file_put_contents($this->root.'/bin/php', "#!/usr/bin/env bash\necho \"PHP \$*\" >> \"\$LOG\"\nexit 0\n");
        chmod($this->root.'/bin/php', 0755);

        // An archive of the old state, made by the backup script itself.
        file_put_contents($this->uploads.'/private/invoices/R-1.pdf', 'old invoice');
        $this->assertSame(0, $this->script('files-backup.sh', 'daily')->getExitCode());
        $archives = glob($this->root.'/backups/files/daily/planer-files-*.tar.gz') ?: [];
        $this->archive = $this->root.'/old.tar.gz';
        copy($archives[0], $this->archive);
        (new Process(['rm', '-rf', $this->root.'/backups']))->run();

        // The state now: one invoice changed, one new, one more folder.
        file_put_contents($this->uploads.'/private/invoices/R-1.pdf', 'changed');
        file_put_contents($this->uploads.'/private/invoices/R-2.pdf', 'new since');
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->root]))->run();

        parent::tearDown();
    }

    public function test_it_shows_environment_and_file_and_replaces_the_folder_after_the_name_is_typed(): void
    {
        $result = $this->restore($this->archive, "staging\n");

        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('Umgebung:   staging', $result['output']);
        $this->assertStringContainsString('Datei:      '.$this->archive, $result['output']);
        $this->assertSame('old invoice', file_get_contents($this->uploads.'/private/invoices/R-1.pdf'));
        $this->assertFileDoesNotExist($this->uploads.'/private/invoices/R-2.pdf', 'what came after is gone');
        $this->assertSame([], glob($this->root.'/app/backend/storage/.restore.*') ?: [], 'nothing left behind');
        $this->assertSame([], glob($this->root.'/app/backend/storage/app.replaced.*') ?: []);
    }

    public function test_it_backs_the_current_state_up_first_and_switches_maintenance_around_the_swap(): void
    {
        $this->restore($this->archive, "staging\n");

        $safety = glob($this->root.'/backups/files/planer-files-*-vor-restore.tar.gz') ?: [];
        $this->assertCount(1, $safety);
        $this->assertStringContainsString('R-2.pdf', (string) shell_exec('tar -tzf '.escapeshellarg($safety[0])), 'the state from before is in it');
        $this->assertSame(['PHP '.$this->root.'/app/backend/artisan down --retry=60', 'PHP '.$this->root.'/app/backend/artisan up'], array_values(array_filter(explode("\n", $this->log()))));
    }

    public function test_a_wrong_name_changes_nothing(): void
    {
        $result = $this->restore($this->archive, "production\n");

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('Nichts wurde geändert', $result['output']);
        $this->assertSame('changed', file_get_contents($this->uploads.'/private/invoices/R-1.pdf'));
        $this->assertSame([], glob($this->root.'/backups/files/*') ?: [], 'no safety copy either');
    }

    public function test_a_file_that_is_not_a_backup_is_refused(): void
    {
        $foreign = $this->root.'/foreign.tar.gz';
        mkdir($this->root.'/other', 0777, true);
        file_put_contents($this->root.'/other/x.txt', 'x');
        (new Process(['tar', '-czf', $foreign, '-C', $this->root, 'other']))->mustRun();
        $broken = $this->root.'/broken.tar.gz';
        file_put_contents($broken, 'no archive');

        foreach ([$foreign, $broken, $this->root.'/missing.tar.gz'] as $file) {
            $result = $this->restore($file, "staging\n");

            $this->assertNotSame(0, $result['exit'], $file);
            $this->assertStringContainsString('Nichts wurde geändert', $result['output']);
        }
        $this->assertSame('changed', file_get_contents($this->uploads.'/private/invoices/R-1.pdf'));
    }

    /**
     * A tar whose listing is far larger than a pipe, so that it is still writing when a reader that stops at the first hit
     * has gone (Auftrag 73). A real archive of this size would only show it by chance (and not with every tar); this one
     * fails the same way every time. Everything but the listing goes to the real tar. $foreign: before «app» come
     * entries outside of it.
     */
    private function tarWithALongListing(bool $foreign): void
    {
        $real = trim((string) shell_exec('command -v tar'));
        $before = $foreign ? "seq 1 100000 | sed 's#^#other/file-with-a-longish-name-#'\n" : '';
        file_put_contents($this->root.'/bin/tar', "#!/usr/bin/env bash\ncase \"\$*\" in\n*-tzf*)\n{$before}printf 'app/\\n'\nseq 1 100000 | sed 's#^#app/file-with-a-longish-name-#'\n;;\n*) exec {$real} \"\$@\";;\nesac\n");
        chmod($this->root.'/bin/tar', 0755);
    }

    public function test_an_archive_with_a_long_listing_is_restored_every_time(): void
    {
        $this->tarWithALongListing(false);

        for ($run = 1; $run <= 5; $run++) {
            $result = $this->restore($this->archive, "staging\n");

            $this->assertSame(0, $result['exit'], "run {$run}: ".$result['output']);
        }
        $this->assertSame('old invoice', file_get_contents($this->uploads.'/private/invoices/R-1.pdf'));
    }

    public function test_paths_outside_of_app_are_refused_also_when_there_are_very_many_of_them(): void
    {
        $this->tarWithALongListing(true);

        for ($run = 1; $run <= 5; $run++) {
            $result = $this->restore($this->archive, "staging\n");

            $this->assertNotSame(0, $result['exit'], "run {$run}");
            $this->assertStringContainsString('ausserhalb von «app»', $result['output'], "run {$run}");
        }
        $this->assertSame('changed', file_get_contents($this->uploads.'/private/invoices/R-1.pdf'));
    }

    public function test_if_the_safety_copy_fails_nothing_is_restored(): void
    {
        $real = trim((string) shell_exec('command -v tar'));
        file_put_contents($this->root.'/bin/tar', "#!/usr/bin/env bash\ncase \"\$*\" in *-czf*) echo 'tar: error' >&2; exit 2;; esac\nexec $real \"\$@\"\n");
        chmod($this->root.'/bin/tar', 0755);

        $result = $this->restore($this->archive, "staging\n");

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('Nichts wurde zurückgespielt', $result['output']);
        $this->assertSame('changed', file_get_contents($this->uploads.'/private/invoices/R-1.pdf'));
        $this->assertStringNotContainsString('artisan down', $this->log());
    }

    public function test_without_a_file_it_says_how_it_is_called(): void
    {
        $process = new Process(['bash', $this->root.'/app/deploy/files-restore.sh'], null, $this->environment());
        $process->run();

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('Aufruf: files-restore.sh <datei>', $process->getErrorOutput());
    }

    /** @return array{exit: int, output: string} */
    private function restore(string $file, string $input): array
    {
        @unlink($this->root.'/log.txt');
        (new Process(['rm', '-rf', $this->root.'/backups']))->run();
        $process = new Process(['bash', $this->root.'/app/deploy/files-restore.sh', $file], null, $this->environment());
        $process->setInput($input);
        $process->run();

        return ['exit' => (int) $process->getExitCode(), 'output' => $process->getOutput().$process->getErrorOutput()];
    }

    private function script(string $script, string $arguments): Process
    {
        $process = new Process(['bash', '-c', 'bash "$ROOT/app/deploy/'.$script.'" '.$arguments], null, ['ROOT' => $this->root, 'PATH' => getenv('PATH'), 'HOME' => $this->root]);
        $process->run();

        return $process;
    }

    /** @return array<string, string> */
    private function environment(): array
    {
        return ['PATH' => $this->root.'/bin:'.getenv('PATH'), 'LOG' => $this->root.'/log.txt', 'HOME' => $this->root, 'TMPDIR' => $this->root];
    }

    private function log(): string
    {
        return (string) @file_get_contents($this->root.'/log.txt');
    }
}
