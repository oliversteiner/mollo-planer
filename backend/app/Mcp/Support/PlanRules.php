<?php

namespace App\Mcp\Support;

use App\Plan\PlanSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * Validation of the plan input shared by create_project_plan and add_to_plan:
 * the same limits and formats everywhere, the same messages for the same
 * mistakes. The tools only say where the fields sit (the key prefix).
 */
final class PlanRules
{
    public const DATE_MESSAGE = 'Dates are YYYY-MM-DD, for example 2027-03-31.';

    public const STATUS_MESSAGE = 'The status of a task is open or done.';

    /**
     * @return list<string|In>
     */
    public static function title(): array
    {
        return ['required', 'string', 'min:'.PlanSchema::TITLE_MIN, 'max:'.PlanSchema::TITLE_MAX];
    }

    /**
     * @return list<string>
     */
    public static function text(): array
    {
        return ['nullable', 'string', 'max:'.PlanSchema::TEXT_MAX];
    }

    /**
     * @return list<string>
     */
    public static function date(): array
    {
        return ['nullable', 'date_format:Y-m-d'];
    }

    /**
     * Rules of a milestone, todo or task at the given key prefix (title, description, due_date).
     *
     * @return array<string, list<string>>
     */
    public static function item(string $prefix): array
    {
        return [
            $prefix.'.title' => self::title(),
            $prefix.'.description' => self::text(),
            $prefix.'.due_date' => self::date(),
        ];
    }

    /**
     * Rules of a task at the given key prefix: the item rules plus assignee and status.
     *
     * @return array<string, list<mixed>>
     */
    public static function task(string $prefix): array
    {
        return [
            ...self::item($prefix),
            $prefix.'.assignee_email' => ['nullable', 'email'],
            $prefix.'.status' => ['nullable', Rule::in(PlanSchema::TASK_STATUSES)],
        ];
    }

    /**
     * The messages of the date and status rules at the given key prefixes.
     *
     * @param  list<string>  $dateKeys
     * @param  list<string>  $statusKeys
     * @return array<string, string>
     */
    public static function messages(array $dateKeys, array $statusKeys): array
    {
        return [
            ...collect($dateKeys)->mapWithKeys(fn (string $key) => [$key.'.date_format' => self::DATE_MESSAGE])->all(),
            ...collect($statusKeys)->mapWithKeys(fn (string $key) => [$key.'.in' => self::STATUS_MESSAGE])->all(),
        ];
    }
}
