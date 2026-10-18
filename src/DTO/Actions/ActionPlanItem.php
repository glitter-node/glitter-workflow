<?php

namespace Modules\Glitter\Workflow\DTO\Actions;

use Modules\Glitter\Workflow\Contracts\ActionConfiguration;
/** 순차 액션 계획의 단일 항목. */
final readonly class ActionPlanItem
{
    public function __construct(
        public int $index,
        public ActionConfiguration $configuration,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'type' => $this->configuration->type()->value,
            'config' => $this->configuration->toArray(),
        ];
    }
}
