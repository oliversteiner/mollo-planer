<?php

namespace App\GraphQL\Scalars;

use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateScalar;

/**
 * DateTime with its time zone (docs/fix-m5.md F8.1). Lighthouse's own scalar writes "2026-11-18 02:13:49",
 * the time in UTC without saying so, and a browser reads that text as local time. This one writes ISO 8601
 * with the zone ("2026-11-18T02:13:49+00:00") and reads that as well as the old form (taken as UTC, the
 * zone of the app).
 */
class DateTime extends DateScalar
{
    protected function format(Carbon $carbon): string
    {
        return $carbon->copy()->utc()->toIso8601String();
    }

    protected function parse(string $value): Carbon
    {
        return Carbon::parse($value, 'UTC');
    }
}
