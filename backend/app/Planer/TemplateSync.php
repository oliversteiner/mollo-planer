<?php

namespace App\Planer;

use App\Models\Template;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Writes the built-in templates, the files spec/templates/*.planer.json, into
 * the table templates (docs/auftrag-m3.md 3.2). The file name without
 * ".planer.json" is the key. Safe to run again: a template is matched by its
 * key and set to the file, so nothing changes when the file did not.
 */
final class TemplateSync
{
    /** The built-in templates in the order they are shown; others follow by name. */
    private const ORDER = ['software-development', 'event', 'podcast'];

    public static function directory(): string
    {
        return dirname(base_path()).'/spec/templates';
    }

    /**
     * @return list<string> The keys written
     *
     * @throws RuntimeException When a file is not valid; nothing is written then
     */
    public static function sync(?string $directory = null): array
    {
        $files = glob(($directory ?? self::directory()).'/*.planer.json') ?: [];
        usort($files, static function (string $a, string $b): int {
            $position = static fn (string $file): int => ($index = array_search(basename($file, '.planer.json'), self::ORDER, true)) === false ? count(self::ORDER) : $index;

            return [$position($a), $a] <=> [$position($b), $b];
        });

        $templates = [];
        foreach ($files as $file) {
            $content = json_decode((string) file_get_contents($file), true);
            $key = basename($file, '.planer.json');
            $problems = is_array($content) ? TemplateContent::problems($content) : ['not a JSON document'];

            if ($problems !== []) {
                throw new RuntimeException("{$key}: ".implode('; ', $problems));
            }

            $templates[$key] = $content;
        }

        DB::transaction(function () use ($templates): void {
            foreach ($templates as $key => $content) {
                $name = (string) $content['project']['name'];

                // An own template that already has this name makes way.
                Template::query()->where('name', $name)->where(fn ($query) => $query->whereNull('key')->orWhere('key', '!=', $key))
                    ->get()->each(fn (Template $other) => $other->forceFill(['name' => self::freeName($name)])->save());

                $template = Template::query()->firstOrNew(['key' => $key]);
                $template->forceFill([
                    'key' => $key,
                    'name' => $name,
                    'description' => $content['project']['description'] ?? null,
                    'locale' => $content['project']['locale'],
                    'content' => $content,
                    'created_by' => null,
                ])->save();
            }
        });

        return array_keys($templates);
    }

    private static function freeName(string $name): string
    {
        $base = Str::limit($name, 50, '');

        for ($number = 2; ; $number++) {
            $candidate = "{$base} ({$number})";

            if (! Template::query()->where('name', $candidate)->exists()) {
                return $candidate;
            }
        }
    }
}
