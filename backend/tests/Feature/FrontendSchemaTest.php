<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The frontend generates its types from frontend/graphql/schema.graphql,
 * the compiled schema. It must match the schema the backend serves.
 */
class FrontendSchemaTest extends TestCase
{
    private const SNAPSHOT = __DIR__.'/../../../frontend/graphql/schema.graphql';

    public function test_frontend_schema_snapshot_is_up_to_date(): void
    {
        Artisan::call('lighthouse:print-schema', ['--sort' => true]);

        $this->assertSame(
            self::normalize(Artisan::output()),
            self::normalize((string) file_get_contents(self::SNAPSHOT)),
            'frontend/graphql/schema.graphql is outdated. Run "composer schema:frontend" in backend/.',
        );
    }

    /**
     * Console and buffered output indent differently; compare the content.
     *
     * @return list<string>
     */
    private static function normalize(string $schema): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $schema)), fn (string $line) => $line !== ''));
    }
}
