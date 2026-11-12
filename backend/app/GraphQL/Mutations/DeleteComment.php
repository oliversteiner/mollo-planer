<?php

namespace App\GraphQL\Mutations;

use App\Models\Comment;

class DeleteComment extends DeleteItem
{
    protected static function model(): string
    {
        return Comment::class;
    }
}
