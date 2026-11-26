<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * deploy/db-restore.sh replaces the whole database with a dump (Auftrag 37 C): it asks first, backs the current state up,
 * and restores only after both. mysql, mysqldump and php are stand-ins that write down how they were called.
 */
class DeployRestoreTest extends TestCase
{
    private string $root;

    private string $dump;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/deploy-restore-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/bin', 0777, true);
        mkdir($this->root.'/app/deploy', 0777, true);
        mkdir($this->root.'/app/backend', 0777, true);
        foreach (['db-backup.sh', 'db-restore.sh'] as $script) {
            copy(base_path('../deploy/'.$script), $this->root.'/app/deploy/'.$script);
            chmod($this->root.'/app/deploy/'.$script, 0755);
        }
        file_put_contents($this->root.'/app/backend/.env', 'DEPLOY_BACKUP_DIR='.$this->root."/backups\nAPP_ENV=staging\nDEPLOY_PHP=".$this->root."/bin/php\nDB_HOST=db.example\nDB_PORT=3307\nDB_DATABASE=planer_test\nDB_USERNAME=planer\nDB_PASSWORD=\"se\"cr\\et\$x\"\n");

        file_put_contents($this->root.'/bin/php', "#!/usr/bin/env bash\necho \"PHP \$*\" >> \"\$LOG\"\nexit 0\n");
        file_put_contents($this->root.'/bin/mysqldump', "#!/usr/bin/env bash\nif [ \"\${DUMP_MODE:-ok}\" = fail ]; then echo 'mysqldump: error' >&2; exit 2; fi\necho 'INSERT INTO t VALUES (\"current\");'\necho '-- Dump completed on 2026-11-24'\n");
        file_put_contents($this->root.'/bin/mysql', <<<'SH'
            #!/usr/bin/env bash
            {
              for a in "$@"; do case "$a" in --defaults-extra-file=*) f="${a#*=}";; esac; echo "MYSQL_ARG $a"; done
              echo "MYSQL_MODE $(stat -c '%a' "$f" 2>/dev/null || stat -f '%Lp' "$f")"
              echo "MYSQL_PWD_ENV $([ -n "${MYSQL_PWD:-}" ] && echo yes || echo no)"
              echo "MYSQL_INPUT $(cat)"
            } >> "$LOG"
            SH);
        foreach (['php', 'mysqldump', 'mysql'] as $stub) {
            chmod($this->root.'/bin/'.$stub, 0755);
        }

        $this->dump = $this->root.'/old-dump.sql.gz';
        file_put_contents($this->dump, gzencode("INSERT INTO t VALUES ('restored');\n-- Dump completed on 2026-11-20\n"));
        // Local time of the shell, which is also the one the script shows.
        (new Process(['touch', '-t', '202611200317', $this->dump]))->mustRun();
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->root]))->run();

        parent::tearDown();
    }

    public function test_it_shows_environment_file_and_date_and_restores_after_the_name_is_typed(): void
    {
        $result = $this->restore($this->dump, "staging\n");

        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringContainsString('Umgebung:   staging', $result['output']);
        $this->assertStringContainsString('Datenbank:  planer_test auf db.example', $result['output']);
        $this->assertStringContainsString('Datei:      '.$this->dump, $result['output']);
        $this->assertStringContainsString('20.11.2026 03:17', $result['output']);
        $this->assertStringContainsString("INSERT INTO t VALUES ('restored');", $this->log());
    }

    public function test_it_backs_the_current_state_up_first_and_clears_the_caches_afterwards(): void
    {
        $this->restore($this->dump, "staging\n");

        $this->assertNotEmpty(glob($this->root.'/backups/db/planer-*-vor-restore.sql.gz'), 'the safety backup is made');
        $calls = array_values(array_filter(explode("\n", $this->log()), fn (string $line): bool => str_starts_with($line, 'PHP ')));
        $this->assertSame([
            'PHP '.$this->root.'/app/backend/artisan down --retry=60',
            'PHP '.$this->root.'/app/backend/artisan optimize:clear',
            'PHP '.$this->root.'/app/backend/artisan lighthouse:clear-cache',
            'PHP '.$this->root.'/app/backend/artisan lighthouse:cache',
            'PHP '.$this->root.'/app/backend/artisan up',
        ], $calls, 'maintenance on, restore, caches, maintenance off');
    }

    public function test_the_credentials_go_in_a_closed_option_file_and_nowhere_else(): void
    {
        $result = $this->restore($this->dump, "staging\n");
        $log = $this->log();

        $this->assertStringContainsString('MYSQL_ARG --defaults-extra-file=', $log);
        $this->assertStringContainsString('MYSQL_MODE 600', $log);
        $this->assertStringContainsString('MYSQL_PWD_ENV no', $log);
        $this->assertStringContainsString('MYSQL_ARG planer_test', $log);
        $this->assertStringNotContainsString('et$x', $result['output'], 'never in the output');
        $argLines = implode("\n", array_filter(explode("\n", $log), fn (string $line): bool => str_starts_with($line, 'MYSQL_ARG ')));
        $this->assertStringNotContainsString('cr', $argLines, 'never on the command line');
    }

    public function test_another_answer_changes_nothing(): void
    {
        foreach (["prod\n", "\n", "STAGING\n", ''] as $answer) {
            $result = $this->restore($this->dump, $answer);

            $this->assertNotSame(0, $result['exit'], "answer: $answer");
            $this->assertStringContainsString('Nichts wurde geändert', $result['output']);
            $this->assertStringNotContainsString('MYSQL_ARG', $this->log(), 'no restore');
            $this->assertSame([], glob($this->root.'/backups/db/*') ?: [], 'no backup, no change at all');
            $this->assertStringNotContainsString('artisan down', $this->log());
        }
    }

    public function test_a_failing_safety_backup_stops_before_anything_is_restored(): void
    {
        $result = $this->restore($this->dump, "staging\n", ['DUMP_MODE' => 'fail']);

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('Nichts wurde zurückgespielt', $result['output']);
        $this->assertStringNotContainsString('MYSQL_ARG', $this->log());
        $this->assertStringNotContainsString('artisan down', $this->log(), 'the site never went into maintenance');
    }

    public function test_a_missing_or_damaged_file_is_refused_before_the_question(): void
    {
        $missing = $this->restore($this->root.'/nope.sql.gz', "staging\n");
        $this->assertNotSame(0, $missing['exit']);
        $this->assertStringContainsString('gibt es nicht', $missing['output']);

        $notGzip = $this->root.'/plain.sql.gz';
        file_put_contents($notGzip, "INSERT INTO t VALUES (1);\n");
        $damaged = $this->restore($notGzip, "staging\n");
        $this->assertNotSame(0, $damaged['exit']);
        $this->assertStringContainsString('kein gültiges gzip-Archiv', $damaged['output']);

        $half = $this->root.'/half.sql.gz';
        file_put_contents($half, gzencode("INSERT INTO t VALUES (1);\n"));
        $incomplete = $this->restore($half, "staging\n");
        $this->assertNotSame(0, $incomplete['exit']);
        $this->assertStringContainsString('kein vollständiger Dump', $incomplete['output']);

        foreach ([$missing, $damaged, $incomplete] as $result) {
            $this->assertStringNotContainsString('Zur Bestätigung', $result['output']);
        }
        $this->assertStringNotContainsString('MYSQL_ARG', $this->log());
    }

    public function test_a_failing_restore_lifts_the_maintenance_mode_again(): void
    {
        file_put_contents($this->root.'/bin/mysql', "#!/usr/bin/env bash\ncat >/dev/null\necho 'ERROR 1045' >&2\nexit 1\n");

        $result = $this->restore($this->dump, "staging\n");

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('Zurückspielen fehlgeschlagen', $result['output']);
        $calls = array_values(array_filter(explode("\n", $this->log()), fn (string $line): bool => str_starts_with($line, 'PHP ')));
        $this->assertSame('PHP '.$this->root.'/app/backend/artisan up', end($calls));
        $this->assertStringNotContainsString('optimize:clear', $this->log());
    }

    public function test_without_a_file_it_says_how_it_is_called(): void
    {
        $process = new Process(['bash', $this->root.'/app/deploy/db-restore.sh'], null, $this->environment());
        $process->run();

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('Aufruf: db-restore.sh <datei>', $process->getErrorOutput());
    }

    /**
     * @param  array<string, string>  $extra
     * @return array{exit: int, output: string}
     */
    private function restore(string $file, string $input, array $extra = []): array
    {
        @unlink($this->root.'/log.txt');
        (new Process(['rm', '-rf', $this->root.'/backups']))->run();
        $process = new Process(['bash', $this->root.'/app/deploy/db-restore.sh', $file], null, $this->environment() + $extra);
        $process->setInput($input);
        $process->run();

        return ['exit' => (int) $process->getExitCode(), 'output' => $process->getOutput().$process->getErrorOutput()];
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
