<?php

namespace Tests\Feature;

use App\Support\AppSettings;
use App\Support\DeploymentEnvironment;
use App\Webhooks\WebhookTarget;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DevelopmentHistorySeeder;
use Database\Seeders\E2eSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * TEST runs with APP_ENV=staging (docs/deploy-template.md): everything that production locks stays locked there.
 * Only a development machine (local, testing) is open.
 */
class StagingEnvironmentTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function environments(): array
    {
        return ['production' => ['production', true], 'staging' => ['staging', true], 'local' => ['local', false], 'testing' => ['testing', false]];
    }

    #[DataProvider('environments')]
    public function test_only_the_servers_are_live(string $environment, bool $live): void
    {
        $this->app->detectEnvironment(fn () => $environment);

        $this->assertSame($live, DeploymentEnvironment::isLive());
        $this->assertSame(! $live, AppSettings::twoFactorSwitchable(), 'the second factor cannot be switched off on a server');
        $this->assertSame(! $live, WebhookTarget::reachable('localhost'), 'webhooks go to public hosts only on a server');
    }

    public function test_the_test_banner_is_for_everything_but_production(): void
    {
        foreach (['production' => false, 'staging' => true, 'local' => true] as $environment => $banner) {
            $this->app->detectEnvironment(fn () => $environment);

            $this->assertSame($banner, DeploymentEnvironment::showsTestBanner(), $environment);
        }
    }

    #[DataProvider('seeders')]
    public function test_no_demo_data_on_the_test_server(string $seeder): void
    {
        $this->app->detectEnvironment(fn () => 'staging');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('production or staging');
        $this->app->make($seeder)->run();
    }

    /** @return array<string, array{string}> */
    public static function seeders(): array
    {
        return ['DemoSeeder' => [DemoSeeder::class], 'DevelopmentHistorySeeder' => [DevelopmentHistorySeeder::class], 'E2eSeeder' => [E2eSeeder::class]];
    }
}
