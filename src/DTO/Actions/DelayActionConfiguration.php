<?php

namespace Modules\Glitter\Workflow\DTO\Actions;

use Modules\Glitter\Workflow\Contracts\ActionConfiguration;
use Modules\Glitter\Workflow\Enums\WorkflowActionType;

/** 지연 액션 설정. */
final readonly class DelayActionConfiguration implements ActionConfiguration
{
    public function __construct(public int $delaySeconds) {}

    public function type(): WorkflowActionType
    {
        return WorkflowActionType::Delay;
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return ['delay_seconds' => $this->delaySeconds];
    }
}
