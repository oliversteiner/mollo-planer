<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\LabelSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Creates an own label set or changes one: name, description and the
 * labels in the given order. Labels with an id are kept (and stay on their
 * tasks); labels without one are added; labels of the set that are missing
 * are deleted, also from the tasks.
 */
class SaveLabelSet
{
    use ResolvesViewer;

    /**
     * @param  array{input: array{name: string, description?: string|null, labels: list<array<string, mixed>>}}  $args
     */
    public function create(mixed $root, array $args, GraphQLContext $context): LabelSet
    {
        $viewer = $this->viewer($context);

        return DB::transaction(function () use ($args, $viewer): LabelSet {
            $set = new LabelSet;
            $set->creator()->associate($viewer);

            return $this->save($set, $args['input']);
        });
    }

    /**
     * @param  array{id: int|string, input: array{name: string, description?: string|null, labels: list<array<string, mixed>>}}  $args
     */
    public function update(mixed $root, array $args): LabelSet
    {
        return DB::transaction(fn (): LabelSet => $this->save(LabelSet::query()->lockForUpdate()->findOrFail($args['id']), $args['input']));
    }

    /**
     * @param  array{name: string, description?: string|null, labels: list<array<string, mixed>>}  $input
     *
     * @throws ValidationException
     */
    private function save(LabelSet $set, array $input): LabelSet
    {
        $labels = $input['labels'];
        $names = array_map(fn (array $label) => mb_strtolower(trim((string) $label['name'])), $labels);

        if (count($names) !== count(array_unique($names))) {
            throw ValidationException::withMessages(['input.labels' => __('labels.duplicate_name')]);
        }

        $set->fill([
            'name' => trim($input['name']),
            'description' => isset($input['description']) && trim($input['description']) !== '' ? trim($input['description']) : null,
        ]);
        $set->save();

        $existing = $set->labels()->get()->keyBy(fn ($label) => (int) $label->getKey());
        $kept = [];

        foreach ($labels as $index => $label) {
            if (isset($label['id']) && ! $existing->has((int) $label['id'])) {
                throw ValidationException::withMessages(["input.labels.{$index}.id" => __('labels.label_other_set')]);
            }

            if (isset($label['id'])) {
                $kept[] = (int) $label['id'];
            }
        }

        // Delete first, so a new label may take the name of a removed one.
        $set->labels()->whereNotIn('id', $kept)->get()->each->delete();

        // Free the names of kept labels, which may swap their names.
        foreach ($kept as $id) {
            $existing[$id]->forceFill(['name' => "~{$id}~"])->save();
        }

        foreach ($labels as $position => $label) {
            $attributes = [
                'name' => trim((string) $label['name']),
                'category' => isset($label['category']) && trim((string) $label['category']) !== '' ? trim((string) $label['category']) : null,
                'color' => $label['color'],
                'position' => $position,
            ];

            if (isset($label['id'])) {
                $existing[(int) $label['id']]->fill($attributes)->save();
            } else {
                $set->labels()->create($attributes);
            }
        }

        return $set->load('labels');
    }
}
