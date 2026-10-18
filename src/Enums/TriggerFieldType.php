<?php

namespace Modules\Glitter\Workflow\Enums;

/**
 * 조건에서 사용할 수 있는 트리거 필드 자료형.
 */
enum TriggerFieldType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case DateTime = 'datetime';
}
