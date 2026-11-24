<?php

namespace App\Support;

/**
 * "Did you mean …": the known name that looks most like a name somebody wrote. It starts the same (assignee,
 * assigneeEmail) or is one or two letters away (userstory, userStory). Used for unknown fields in a planer document and
 * for unknown parameters of the MCP tools.
 */
final class ClosestName
{
    /**
     * @param  list<string>  $known
     */
    public static function of(string $name, array $known): ?string
    {
        $wanted = strtolower($name);
        $best = null;
        $distance = PHP_INT_MAX;

        foreach ($known as $candidate) {
            $candidate = (string) $candidate;
            $lower = strtolower($candidate);
            $apart = levenshtein($wanted, $lower);
            $related = (str_starts_with($lower, $wanted) || str_starts_with($wanted, $lower)) && min(strlen($wanted), strlen($lower)) >= 3;

            if (($related || $apart <= 2) && $apart < $distance) {
                $best = $candidate;
                $distance = $apart;
            }
        }

        return $best;
    }
}
