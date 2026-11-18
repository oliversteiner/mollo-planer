<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Sets project_id from the parent on every save (schema-review A1). The value
 * never comes from input: project_id is not fillable, and the parent decides.
 * A model using this trait implements parentProjectId().
 */
trait DerivesProject
{
    public static function bootDerivesProject(): void
    {
        static::saving(function (Model $model): void {
            $model->setAttribute('project_id', $model->parentProjectId());
        });
    }

    /**
     * The project of the parent (the milestone of a todo, the todo of a task).
     */
    abstract protected function parentProjectId(): int|string;
}
