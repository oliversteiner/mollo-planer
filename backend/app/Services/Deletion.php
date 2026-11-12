<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The one place where a project element is deleted. Today this is final;
 * the trash (Papierkorb) will change this place only. Observers and model
 * hooks run as before, for example the gap closing of milestones.
 */
final class Deletion
{
    public function delete(Model $model): Model
    {
        return DB::transaction(function () use ($model): Model {
            $model->delete();

            return $model;
        });
    }
}
