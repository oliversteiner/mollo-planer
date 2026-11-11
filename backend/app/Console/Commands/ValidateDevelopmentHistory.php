<?php

namespace App\Console\Commands;

use App\Support\DevelopmentHistory\HistoryValidator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Checks the development history before it is seeded.
 */
#[Signature('docs:validate-history {path? : Markdown file, default docs/entwicklung.md}')]
#[Description('Check docs/entwicklung.md for structure and content errors (line by line)')]
class ValidateDevelopmentHistory extends Command
{
    public function handle(): int
    {
        $path = (string) ($this->argument('path') ?? base_path('../docs/entwicklung.md'));

        if (! is_file($path)) {
            $this->components->error("Datei nicht gefunden: {$path}");

            return self::FAILURE;
        }

        $issues = HistoryValidator::validate((string) file_get_contents($path));

        foreach ($issues as $issue) {
            $message = "Zeile {$issue['line']}: {$issue['message']}";
            $issue['severity'] === 'error' ? $this->error($message) : $this->warn($message);
        }

        $errors = count(array_filter($issues, fn (array $i): bool => $i['severity'] === 'error'));
        $warnings = count($issues) - $errors;

        if ($errors > 0) {
            $this->components->error("{$errors} Fehler, {$warnings} Hinweise. Der Seed würde fehlschlagen.");

            return self::FAILURE;
        }

        $this->components->info("Keine Fehler. {$warnings} Hinweise.");

        return self::SUCCESS;
    }
}
