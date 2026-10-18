<?php

namespace Modules\Glitter\Workflow\Contracts;

use Modules\Glitter\Workflow\Enums\WorkflowActionType;

/**
 * 워크플로 액션 설정 직렬화 계약.
 */
interface ActionConfiguration
{
    public function type(): WorkflowActionType;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
