<?php

namespace App\Assistant;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;

/**
 * How many drafts a person may ask for (docs/auftrag-m6.md §4.5): per hour and per day, from
 * services.anthropic.limits. A day is the calendar day in Europe/Zurich. Replaces the throttle
 * the old throttle. The message says from when it works again.
 */
class AssistantLimits
{
    public const ZONE = 'Europe/Zurich';

    /**
     * Counts one draft, or refuses with the time from which it works again.
     *
     * @throws AssistantError
     */
    public function hit(User $user): void
    {
        $limits = $this->limits();
        $hour = "assistant-hour:{$user->getKey()}";
        $day = "assistant-day:{$user->getKey()}";

        foreach ([[$day, $limits['day'], 'day'], [$hour, $limits['hour'], 'hour']] as [$key, $max, $kind]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $again = Carbon::now(self::ZONE)->addSeconds(RateLimiter::availableIn($key))->format('H:i');

                throw new AssistantError(__("assistant.limit_{$kind}", ['max' => $max, 'time' => $again]));
            }
        }

        RateLimiter::hit($hour, 3600);
        RateLimiter::hit($day, max(1, (int) Carbon::now(self::ZONE)->diffInSeconds(Carbon::now(self::ZONE)->endOfDay(), false)));
    }

    /**
     * @return array{hour: int, day: int}
     */
    private function limits(): array
    {
        return [
            'hour' => (int) config('services.anthropic.limits.hour', 30),
            'day' => (int) config('services.anthropic.limits.day', 200),
        ];
    }
}
