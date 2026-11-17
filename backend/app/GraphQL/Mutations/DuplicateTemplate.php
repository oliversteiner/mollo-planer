<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Template;
use App\Planer\TemplateContent;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Copies a template with its outline as an own template of the viewer
 * ("Kopie von …"). The name of a built-in template is copied in the
 * language of the request, as shown.
 */
class DuplicateTemplate
{
    use ResolvesViewer;

    /**
     * @param  array{id: int|string}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): Template
    {
        $viewer = $this->viewer($context);

        return DB::transaction(function () use ($args, $viewer): Template {
            $source = Template::query()->findOrFail($args['id']);

            $name = $this->uniqueName(mb_substr(__('labels.copy_of', ['name' => $source->displayName()]), 0, 60));
            $description = $source->displayDescription();

            $copy = new Template([
                'name' => $name,
                'description' => $description,
                'locale' => $source->locale,
                'content' => TemplateContent::withMeta($source->content, $name, $description),
            ]);
            $copy->creator()->associate($viewer);
            $copy->save();

            return $copy;
        });
    }

    /**
     * "Kopie von Event", then "Kopie von Event (2)", ...
     */
    private function uniqueName(string $name): string
    {
        $candidate = $name;

        for ($number = 2; Template::query()->where('name', $candidate)->exists(); $number++) {
            $candidate = mb_substr($name, 0, 54)." ({$number})";
        }

        return $candidate;
    }
}
