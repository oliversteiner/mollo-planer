<?php

namespace App\GraphQL\Queries;

use App\Models\Template;
use Illuminate\Database\Eloquent\Collection;

/**
 * All templates: built-in first (in their order), then own ones by name.
 */
class Templates
{
    /**
     * @return Collection<int, Template>
     */
    public function __invoke(): Collection
    {
        return Template::query()
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Template $template): array => [$template->isBuiltin() ? 0 : 1, $template->isBuiltin() ? $template->getKey() : mb_strtolower($template->name)])
            ->values();
    }
}
