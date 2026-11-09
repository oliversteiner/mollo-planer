<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\LabelSet;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Copies a set as an own set of the viewer ("Kopie von …"). Built-in
 * names are copied in the language of the request, as shown.
 */
class DuplicateLabelSet
{
    use ResolvesViewer;

    /**
     * @param  array{id: int|string}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): LabelSet
    {
        $viewer = $this->viewer($context);

        return DB::transaction(function () use ($args, $viewer): LabelSet {
            $source = LabelSet::with('labels')->findOrFail($args['id']);

            $copy = new LabelSet([
                'name' => $this->uniqueName(mb_substr(__('labels.copy_of', ['name' => $source->displayName()]), 0, 60)),
                'description' => $source->displayDescription(),
            ]);
            $copy->creator()->associate($viewer);
            $copy->save();

            foreach ($source->labels as $label) {
                $copy->labels()->create([
                    'name' => $label->displayName(),
                    'category' => $label->displayCategory(),
                    'color' => $label->color,
                    'position' => $label->position,
                ]);
            }

            return $copy->load('labels');
        });
    }

    /**
     * "Kopie von Event", then "Kopie von Event (2)", ...
     */
    private function uniqueName(string $name): string
    {
        $candidate = $name;

        for ($number = 2; LabelSet::query()->whereNull('key')->where('name', $candidate)->exists(); $number++) {
            $candidate = mb_substr($name, 0, 54)." ({$number})";
        }

        return $candidate;
    }
}
