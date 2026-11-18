<?php

namespace Tests\Unit;

use App\Planer\Canonical;
use App\Planer\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PHP and tools/planer must agree on every planer file in spec/fixtures
 * (docs/auftrag-m4.md §3.3): the same kinds of issue at the same paths
 * (spec/fixtures/expected/<name>.issues.json, which tools/planer checks too),
 * and the same canonical text, byte for byte. A file without an expectation
 * makes the test fail.
 */
class PlanerFilesAgreementTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function fixtures(): array
    {
        $cases = [];

        foreach (glob(dirname(__DIR__, 3).'/spec/fixtures/*.planer.json') ?: [] as $file) {
            $cases[basename($file)] = [$file];
        }

        return $cases;
    }

    public function test_there_are_fixtures(): void
    {
        $this->assertGreaterThanOrEqual(8, count(self::fixtures()));
    }

    #[DataProvider('fixtures')]
    public function test_validator_reports_what_the_expectation_file_says(string $file): void
    {
        $expectedFile = dirname($file).'/expected/'.basename($file, '.planer.json').'.issues.json';
        $this->assertFileExists($expectedFile, 'Expectation file missing for '.basename($file));

        $actual = collect(Validator::validate(json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)))
            ->map(fn (array $issue): array => ['severity' => $issue['severity'], 'path' => $issue['path']])
            ->unique()
            ->sortBy(fn (array $issue): string => $issue['severity'].'|'.$issue['path'])
            ->values()
            ->all();
        $expected = collect(json_decode((string) file_get_contents($expectedFile), true, 512, JSON_THROW_ON_ERROR))
            ->sortBy(fn (array $issue): string => $issue['severity'].'|'.$issue['path'])
            ->values()
            ->all();

        $this->assertSame($expected, $actual, basename($file));
    }

    #[DataProvider('fixtures')]
    public function test_canonical_text_equals_the_file_byte_for_byte(string $file): void
    {
        $text = (string) file_get_contents($file);
        $document = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

        if (str_contains($file, 'planer-schema-')) {
            $this->markTestSkipped('The canonical form is for files that pass the schema.');
        }

        $this->assertSame($text, Canonical::text($document), basename($file));
    }
}
