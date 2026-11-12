<?php

namespace App\GraphQL\Mutations;

use App\Models\Project;

class DeleteProject extends DeleteItem
{
    protected static function model(): string
    {
        return Project::class;
    }
}
