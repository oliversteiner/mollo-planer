<?php

namespace App\GraphQL\Mutations;

use App\Models\Task;

class DeleteTask extends DeleteItem
{
    protected static function model(): string
    {
        return Task::class;
    }
}
