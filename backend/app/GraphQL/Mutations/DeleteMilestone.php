<?php

namespace App\GraphQL\Mutations;

use App\Models\Milestone;

class DeleteMilestone extends DeleteItem
{
    protected static function model(): string
    {
        return Milestone::class;
    }
}
