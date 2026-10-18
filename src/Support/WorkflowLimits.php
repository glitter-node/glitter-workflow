<?php

namespace Modules\Glitter\Workflow\Support;

/**
 * Absolute bounds and fail-safe normalization for operational limits.
 */
final class WorkflowLimits
{
    public const ABSOLUTE_MAX_ACTIONS = 100;

    public const ABSOLUTE_MAX_CONDITION_DEPTH = 10;

    public const ABSOLUTE_MAX_RECURSION_DEPTH = 0;

    public const ERROR_MESSAGE_MAX_LENGTH = 1000;

    public const DEFAULTS = [
        'max_actions_per_workflow' => 25,
        'max_condition_depth' => 5,
        'max_workflow_recursion_depth' => 0,
    ];

    public const MAXIMUMS = [
        'max_actions_per_workflow' => self::ABSOLUTE_MAX_ACTIONS,
        'max_condition_depth' => self::ABSOLUTE_MAX_CONDITION_DEPTH,
        'max_workflow_recursion_depth' => self::ABSOLUTE_MAX_RECURSION_DEPTH,
    ];

    public static function accepts(string $key, mixed $value): bool
    {
        return array_key_exists($key, self::MAXIMUMS)
            && is_int($value)
            && $value >= ($key === 'max_workflow_recursion_depth' ? 0 : 1)
            && $value <= self::MAXIMUMS[$key];
    }

    /** @return array<string, int> */
    public static function normalize(array $values, array $defaults = self::DEFAULTS): array
    {
        $normalized = [];

        foreach (self::DEFAULTS as $key => $fallback) {
            $default = $defaults[$key] ?? null;
            $default = self::accepts($key, $default) ? $default : $fallback;
            $value = array_key_exists($key, $values) ? $values[$key] : $default;
            $normalized[$key] = self::accepts($key, $value) ? $value : $default;
        }

        return $normalized;
    }
}
