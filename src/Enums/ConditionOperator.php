<?php

namespace Modules\Glitter\Workflow\Enums;

/** 허용된 조건 비교 연산자. */
enum ConditionOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case In = 'in';
    case NotIn = 'not_in';
    case Exists = 'exists';
    case NotExists = 'not_exists';
    case GreaterThan = 'greater_than';
    case GreaterThanOrEqual = 'greater_than_or_equal';
    case LessThan = 'less_than';
    case LessThanOrEqual = 'less_than_or_equal';
    case Before = 'before';
    case After = 'after';
}
