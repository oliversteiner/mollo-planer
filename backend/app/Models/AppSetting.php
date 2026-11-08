<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One application setting. Read and written only through
 * App\Support\AppSettings, which knows the keys and defaults.
 */
#[Fillable(['key', 'value'])]
class AppSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
