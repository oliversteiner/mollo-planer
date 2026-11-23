<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * deploy/deploy.sh stops when the keys of the OAuth server are missing (acceptance of Auftrag 41, F4): the block is taken out of
 * the script and run in a folder that looks like the app folder of a server.
 */
class DeployOAuthKeysCheckTest extends TestCase
{
    private string $backend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backend = sys_get_temp_dir().'/deploy-keys-'.bin2hex(random_bytes(4));
        mkdir($this->backend.'/storage', 0777, true);
        file_put_contents($this->backend.'/.env', "APP_ENV=staging\n");
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->backend]))->run();

        parent::tearDown();
    }

    public function test_it_stops_without_keys_and_names_the_command(): void
    {
        $result = $this->runBlock();

        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('php artisan passport:keys', $result['output']);
        $this->assertStringContainsString('Nichts wurde geändert.', $result['output']);
    }

    public function test_it_stops_when_only_one_of_the_two_files_exists(): void
    {
        file_put_contents($this->backend.'/storage/oauth-public.key', 'public');

        $this->assertSame(1, $this->runBlock()['exit']);
    }

    public function test_it_goes_on_with_both_files_and_the_private_one_closed(): void
    {
        $this->keys('600');

        $this->assertSame(0, $this->runBlock()['exit']);

        chmod($this->backend.'/storage/oauth-private.key', 0400);
        $this->assertSame(0, $this->runBlock()['exit']);
    }

    public function test_it_stops_when_the_private_key_can_be_read_by_others(): void
    {
        foreach (['644', '640', '660', '604'] as $mode) {
            $this->keys($mode);
            $result = $this->runBlock();

            $this->assertSame(1, $result['exit'], $mode);
            $this->assertStringContainsString("Rechte {$mode}", $result['output']);
        }
    }

    public function test_the_keys_in_the_env_file_are_enough(): void
    {
        file_put_contents($this->backend.'/.env', "APP_ENV=staging\nPASSPORT_PRIVATE_KEY=\"-----BEGIN PRIVATE KEY-----\nPASSPORT_PUBLIC_KEY=\"-----BEGIN PUBLIC KEY-----\n");

        $this->assertSame(0, $this->runBlock()['exit']);
    }

    public function test_it_never_makes_the_keys_itself(): void
    {
        $this->runBlock();

        $this->assertFileDoesNotExist($this->backend.'/storage/oauth-private.key');
        $this->assertFileDoesNotExist($this->backend.'/storage/oauth-public.key');
    }

    private function keys(string $privateMode): void
    {
        file_put_contents($this->backend.'/storage/oauth-private.key', 'private');
        file_put_contents($this->backend.'/storage/oauth-public.key', 'public');
        chmod($this->backend.'/storage/oauth-private.key', octdec($privateMode));
    }

    /** @return array{exit: int, output: string} */
    private function runBlock(): array
    {
        $script = (string) file_get_contents(base_path('../deploy/deploy.sh'));
        preg_match('/^env_value\(\) \{.*?^\}/ms', $script, $envValue);
        preg_match('/^fail\(\) \{.*?^\}/ms', $script, $fail);
        preg_match('/# --- oauth-keys-check begin.*?# --- oauth-keys-check end/s', $script, $block);
        $this->assertNotEmpty($envValue);
        $this->assertNotEmpty($fail);
        $this->assertNotEmpty($block);

        $process = new Process(['bash', '-c', 'set -euo pipefail; BACKEND="$1"; '.$envValue[0]."\n".$fail[0]."\n".$block[0], 'bash', $this->backend]);
        $process->run();

        return ['exit' => (int) $process->getExitCode(), 'output' => $process->getOutput().$process->getErrorOutput()];
    }
}
