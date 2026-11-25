<?php

namespace App\Core\Pdf;

use Illuminate\Support\Facades\Storage;

/**
 * An uploaded letterhead (Auftrag 39 F): a PDF on the private disk with one page (page 1 only) or two pages (page 1,
 * then page 2 for every following page).
 */
final readonly class Letterhead
{
    public function __construct(public string $path, public int $pages, public string $fileName, public string $uploadedAt) {}

    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->path);
    }

    public function exists(): bool
    {
        return Storage::disk('local')->exists($this->path);
    }

    /**
     * @return array{path: string, pages: int, fileName: string, uploadedAt: string}
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, mixed>|null  $values
     */
    public static function fromArray(?array $values): ?self
    {
        if (! is_array($values) || ! is_string($values['path'] ?? null)) {
            return null;
        }

        return new self($values['path'], (int) ($values['pages'] ?? 1), (string) ($values['fileName'] ?? 'briefpapier.pdf'), (string) ($values['uploadedAt'] ?? ''));
    }
}
