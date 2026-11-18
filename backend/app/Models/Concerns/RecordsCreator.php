<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Stores who created the row (schema-review A2). The value comes from the
 * login; created_by is not fillable, so input cannot set it. Rows created
 * without a login (seeders, the console) keep it empty.
 */
trait RecordsCreator
{
    public static function bootRecordsCreator(): void
    {
        static::creating(function (Model $model): void {
            $model->setAttribute('created_by', Auth::id());
        });
    }
}
