<?php

namespace App\GraphQL\Mutations;

use App\Models\LabelSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes an own set with its labels (and so their use on tasks), as long
 * as no project uses it. Who may is checked by the policy (@can).
 */
class DeleteLabelSet
{
    /**
     * @param  array{id: int|string}  $args
     *
     * @throws ValidationException
     */
    public function __invoke(mixed $root, array $args): LabelSet
    {
        return DB::transaction(function () use ($args): LabelSet {
            $set = LabelSet::query()->lockForUpdate()->findOrFail($args['id']);
            $inUse = $set->projects()->count();

            if ($inUse > 0) {
                throw ValidationException::withMessages(['id' => __('labels.set_in_use', ['count' => $inUse])]);
            }

            $set->delete();

            return $set;
        });
    }
}
