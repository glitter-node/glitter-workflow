<?php

namespace Modules\Glitter\Workflow\Services\Triggers\Adapters;

use BackedEnum;
use DateTimeInterface;

/**
 * 트리거 컨텍스트 스칼라 정규화 공통 기능.
 */
abstract class AbstractTriggerContextAdapter
{
    /** Enum 또는 문자열 상태를 안전한 문자열로 바꿉니다. */
    protected function stringValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** 날짜 값을 ISO 8601 문자열로 바꿉니다. */
    protected function dateTimeValue(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DATE_ATOM) : null;
    }

    /** Accept canonical decimal integers within the native PHP integer range. */
    protected function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || ! preg_match('/\A(?:0|-?[1-9][0-9]*)\z/', $value)) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer === false ? null : $integer;
    }
}
