<?php

namespace Modules\Glitter\Workflow\Enums;

/** 조건 그룹 논리 연산자. */
enum ConditionLogicalGroup: string
{
    case All = 'all';
    case Any = 'any';
}
