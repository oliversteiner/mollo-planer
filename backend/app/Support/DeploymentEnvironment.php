<?php

namespace App\Support;

/**
 * The two server environments (docs/deploy-template.md): TEST runs with APP_ENV=staging, PROD with production.
 * Both are "live": the safety rules of production hold in both (no switch for the second factor, webhooks only to
 * public hosts, no lazy-loading errors that would break a request, no demo seeders, no GraphQL introspection).
 * Only a development machine (local, testing) is not live. The one place that says so, so TEST cannot drift from
 * PROD by an `isProduction()` somewhere that nobody remembered.
 */
final class DeploymentEnvironment
{
    public const LIVE = ['production', 'staging'];

    public static function isLive(): bool
    {
        return app()->environment(self::LIVE);
    }

    /** The test server: it is marked as such in the app and in its mails. */
    public static function isTestServer(): bool
    {
        return app()->environment('staging');
    }

    /** A banner "test environment" is shown whenever this is not production (also on a development machine). */
    public static function showsTestBanner(): bool
    {
        return ! app()->environment('production');
    }
}
