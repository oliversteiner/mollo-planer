<?php

namespace App\GraphQL\Mutations;

use App\Models\Todo;

class DeleteTodo extends DeleteItem
{
    protected static function model(): string
    {
        return Todo::class;
    }
}
