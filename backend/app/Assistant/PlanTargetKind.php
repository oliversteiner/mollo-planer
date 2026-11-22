<?php

namespace App\Assistant;

use App\Enums\Right;

enum PlanTargetKind: string
{
    case MILESTONE = 'MILESTONE';
    case TODO = 'TODO';
    case TASK = 'TASK';

    public function right(): Right
    {
        return match ($this) {
            self::MILESTONE => Right::MILESTONE_CREATE,
            self::TODO => Right::TODO_CREATE,
            self::TASK => Right::TASK_CREATE,
        };
    }
}
