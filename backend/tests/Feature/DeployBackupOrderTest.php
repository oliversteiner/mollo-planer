<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * deploy/deploy.sh backs the database up BEFORE it replaces the code (Auftrag 37 B): if the dump fails, the old code stays,
 * so it still matches the old schema. A real Git repository stands in for the server, php and mysqldump are stand-ins.
 */
class DeployBackupOrderTest extends TestCase
{
    private string $root;

    private string $oldCommit;

    private string $newCommit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/deploy-order-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/bin', 0777, true);
        mkdir($this->root.'/www/planer.example.test', 0777, true);

        $this->git($this->root, 'init', '--quiet', '--bare', '-b', 'dev', 'origin.git');
        $this->git($this->root, 'clone', '--quiet', 'origin.git', 'work');
        $work = $this->root.'/work';
        mkdir($work.'/deploy', 0777, true);
        copy(base_path('../deploy/deploy.sh'), $work.'/deploy/deploy.sh');
        copy(base_path('../deploy/db-backup.sh'), $work.'/deploy/db-backup.sh');
        chmod($work.'/deploy/deploy.sh', 0755);
        chmod($work.'/deploy/db-backup.sh', 0755);
        file_put_contents($work.'/version.txt', "old\n");
        $this->commit($work, 'old');
        $this->oldCommit = trim($this->git($work, 'rev-parse', '--short', 'HEAD'));
        $this->git($work, 'push', '--quiet', 'origin', 'HEAD:dev');
        file_put_contents($work.'/version.txt', "new\n");
        $this->commit($work, 'new');
        $this->newCommit = trim($this->git($work, 'rev-parse', '--short', 'HEAD'));
        $this->git($work, 'push', '--quiet', 'origin', 'HEAD:dev');

        // The "server": a clone that stands on the old commit.
        $this->git($this->root, 'clone', '--quiet', '-b', 'dev', 'origin.git', 'app');
        $this->git($this->root.'/app', 'reset', '--hard', '--quiet', $this->oldCommit);
        mkdir($this->root.'/app/backend', 0777, true);
        file_put_contents($this->root.'/app/backend/.env', implode("\n", [
            'APP_ENV=staging', 'APP_URL=http://127.0.0.1:9', 'FRONTEND_URL=https://planer.example.test',
            'DEPLOY_BRANCH=dev', 'DEPLOY_WEB_ROOT='.$this->root.'/www/planer.example.test', 'DEPLOY_BACKUP_DIR='.$this->root.'/backups',
            'DEPLOY_PHP='.$this->root.'/bin/php', 'DB_HOST=db', 'DB_PORT=3306', 'DB_DATABASE=planer', 'DB_USERNAME=u', 'DB_PASSWORD=p',
        ])."\n");

        mkdir($this->root.'/app/backend/storage', 0777, true);
        file_put_contents($this->root.'/app/backend/storage/oauth-private.key', 'private');
        chmod($this->root.'/app/backend/storage/oauth-private.key', 0600);
        file_put_contents($this->root.'/app/backend/storage/oauth-public.key', 'public');

        file_put_contents($this->root.'/bin/php', "#!/usr/bin/env bash\nexit 0\n");
        chmod($this->root.'/bin/php', 0755);
        file_put_contents($this->root.'/bin/mysqldump', <<<'SH'
            #!/usr/bin/env bash
            if [ "$MODE" = fail ]; then echo "mysqldump: Got error: 2002" >&2; exit 2; fi
            echo "INSERT INTO t VALUES (1);"; echo "-- Dump completed on 2026-11-24"
            SH);
        chmod($this->root.'/bin/mysqldump', 0755);
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->root]))->run();

        parent::tearDown();
    }

    public function test_a_failing_backup_leaves_the_old_code_and_the_site_untouched(): void
    {
        $result = $this->deploy('fail');

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('Datenbanksicherung ist fehlgeschlagen', $result['output']);
        $this->assertStringNotContainsString('Code aktualisieren', $result['output']);
        $this->assertStringNotContainsString('Migrationen ausführen', $result['output']);
        $this->assertSame($this->oldCommit, trim($this->git($this->root.'/app', 'rev-parse', '--short', 'HEAD')), 'still on the old commit');
        $this->assertSame("old\n", file_get_contents($this->root.'/app/version.txt'));
        $this->assertSame([], glob($this->root.'/backups/db/*') ?: []);
    }

    public function test_a_good_backup_is_made_before_the_code_changes_and_carries_the_new_commit(): void
    {
        $result = $this->deploy('ok');

        $this->assertLessThan(
            strpos($result['output'], 'Code aktualisieren'),
            strpos($result['output'], 'Datenbank sichern'),
            $result['output'],
        );
        $this->assertStringContainsString('Migrationen ausführen', $result['output'], $result['output']);
        $this->assertNotEmpty(glob($this->root.'/backups/db/planer-*-'.$this->newCommit.'.sql.gz'), 'named after the commit that is deployed');
        $this->assertSame($this->newCommit, trim($this->git($this->root.'/app', 'rev-parse', '--short', 'HEAD')));
    }

    /** @return array{exit: int, output: string} */
    private function deploy(string $mode): array
    {
        $archive = $this->root.'/frontend.tar.gz';
        mkdir($this->root.'/frontend', 0777, true);
        file_put_contents($this->root.'/frontend/index.html', '<html></html>');
        (new Process(['tar', '-czf', $archive, '-C', $this->root.'/frontend', '.']))->mustRun();
        $process = Process::fromShellCommandline('bash "$ROOT/app/deploy/deploy.sh" < "$ARCHIVE"', null, [
            'ROOT' => $this->root, 'ARCHIVE' => $archive, 'MODE' => $mode, 'HOME' => $this->root,
            'PATH' => $this->root.'/bin:'.getenv('PATH'),
        ]);
        $process->run();

        return ['exit' => (int) $process->getExitCode(), 'output' => $process->getOutput().$process->getErrorOutput()];
    }

    private function commit(string $dir, string $message): void
    {
        $this->git($dir, 'add', '-A');
        $this->git($dir, '-c', 'user.name=t', '-c', 'user.email=t@example.test', 'commit', '--quiet', '-m', $message);
    }

    private function git(string $dir, string ...$arguments): string
    {
        $process = new Process(['git', ...$arguments], $dir);
        $process->mustRun();

        return $process->getOutput();
    }
}
