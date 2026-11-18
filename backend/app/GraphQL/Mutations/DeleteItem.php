<?php

namespace App\GraphQL\Mutations;

use App\Services\Deletion;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared resolver of the delete mutations of project elements. Each mutation
 * names its model in a small subclass; the deletion itself is App\Services\Deletion.
 * Who may delete is checked by the policy (@can) before this runs.
 */
abstract class DeleteItem
{
    /**
     * @return class-string<Model>
     */
    abstract protected static function model(): string;

    /**
     * @param  array{id: int|string}  $args
     */
    public function __invoke(mixed $root, array $args): Model
    {
        $model = (static::model())::query()->findOrFail($args['id']);

        return app(Deletion::class)->delete($model);
    }
}
