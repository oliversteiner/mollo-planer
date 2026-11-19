<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Production reads the compiled schema (bootstrap/cache/lighthouse-schema.php). A resolver class that no longer exists
 * only fails when the field is queried ("Failed to find class ... for directive @field"), and `lighthouse:cache`
 * does not notice it. So: the cache must build from the current schema, and every resolver the schema names must exist.
 */
class GraphQLSchemaCacheTest extends TestCase
{
    public function test_the_schema_cache_builds_without_error(): void
    {
        $path = sys_get_temp_dir().'/lighthouse-schema-test-'.uniqid().'.php';
        config(['lighthouse.schema_cache.path' => $path]);

        try {
            $this->artisan('lighthouse:cache')->assertSuccessful();
            $this->assertFileExists($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_every_resolver_class_named_in_the_schema_exists(): void
    {
        $missing = [];

        foreach (glob(base_path('graphql/*.graphql')) as $file) {
            preg_match_all('/\b(resolver|builder|class): "([^"]+)"/', (string) file_get_contents($file), $found, PREG_SET_ORDER);

            foreach ($found as [, $argument, $reference]) {
                $reference = str_replace('\\\\', '\\', $reference);
                [$class, $method] = array_pad(explode('@', $reference, 2), 2, $argument === 'class' ? null : '__invoke');

                if (! str_contains($class, '\\')) {
                    continue; // a built-in scalar such as "Date", not a class of ours
                }

                if (! class_exists($class) || ($method !== null && ! method_exists($class, $method))) {
                    $missing[] = basename($file).': '.$reference;
                }
            }
        }

        $this->assertSame([], $missing, 'The schema names resolvers that do not exist.');
    }
}
