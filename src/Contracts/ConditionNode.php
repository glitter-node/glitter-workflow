<?php

namespace Modules\Glitter\Workflow\Contracts;

/**
 * 조건 트리 노드 직렬화 계약.
 */
interface ConditionNode
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
