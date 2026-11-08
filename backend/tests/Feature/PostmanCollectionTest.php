<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StrongAuthentication;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Every GraphQL request of the Postman collection (v3 YAML in docs/postman) runs
 * without errors against fresh demo data, so the collection stays in step
 * with the schema and the demo IDs.
 *
 * DatabaseMigrations instead of RefreshDatabase: the collection expects the
 * IDs of a freshly migrated database (1, 2, ...). MariaDB keeps
 * auto-increment counters across rolled-back test transactions.
 */
class PostmanCollectionTest extends TestCase
{
    use DatabaseMigrations;

    private const COLLECTION = __DIR__.'/../../../docs/postman/Projektverwaltung (FCA DB)';

    /**
     * The requests run one after the other on the same data, as in Postman.
     */
    public function test_every_graphql_request_runs_without_errors_on_demo_data(): void
    {
        Notification::fake();
        $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--no-interaction' => true])->assertSuccessful();
        // As the admin, so the admin folder runs too. Permissions are tested elsewhere.
        $user = User::where('is_admin', true)->firstOrFail();
        $requests = self::graphqlRequests();
        $this->assertNotEmpty($requests);

        foreach ($requests as $name => $graphql) {
            $variables = json_decode(self::substitute(($graphql['variables'] ?? '') ?: '{}'), true, flags: JSON_THROW_ON_ERROR);

            $response = $this->actingAs($user)
                ->withSession([StrongAuthentication::SESSION_KEY => (string) $user->id])
                ->withHeader('Referer', 'http://localhost')
                ->postJson('/graphql', ['query' => $graphql['query'], 'variables' => $variables]);

            $this->assertSame(200, $response->status(), $name);
            $this->assertNull($response->json('errors'), $name.': '.json_encode($response->json('errors'), JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * GraphQL requests of all folders, in the order Postman shows them.
     *
     * @return array<string, array{query: string, variables?: string}>
     */
    private static function graphqlRequests(): array
    {
        $requests = [];

        foreach (self::sortedByOrder(glob(self::COLLECTION.'/*', GLOB_ONLYDIR), fn (string $folder) => $folder.'/.resources/definition.yaml') as $folder) {
            foreach (self::sortedByOrder(glob($folder.'/*.request.yaml'), fn (string $file) => $file) as $file) {
                $request = Yaml::parseFile($file);

                if (($request['body']['type'] ?? null) === 'graphql') {
                    $requests[basename($folder).' / '.basename($file, '.request.yaml')] = $request['body']['content'];
                }
            }
        }

        return $requests;
    }

    /**
     * @param  list<string>  $paths
     * @param  \Closure(string): string  $definition  YAML file holding the "order" of a path
     * @return list<string>
     */
    private static function sortedByOrder(array $paths, \Closure $definition): array
    {
        return collect($paths)
            ->sortBy(fn (string $path) => Yaml::parseFile($definition($path))['order'] ?? PHP_INT_MAX)
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function variables(): array
    {
        return Yaml::parseFile(self::COLLECTION.'/.resources/definition.yaml')['variables'];
    }

    private static function substitute(string $text): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', fn (array $match) => self::variables()[$match[1]], $text);
    }
}
