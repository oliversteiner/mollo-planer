<?php

namespace App\GraphQL\Resolvers;

use App\Models\Template;
use App\Planer\Canonical;

/**
 * Template.file: the content of a template as a planer-v2 file (canonical text),
 * to download it or to start from it.
 */
class TemplateFile
{
    public function __invoke(Template $template): string
    {
        return Canonical::text($template->content);
    }
}
