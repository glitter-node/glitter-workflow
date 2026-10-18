<?php

namespace Modules\Glitter\Workflow\DTO\Conditions;

use Modules\Glitter\Workflow\Contracts\ConditionNode;
use Modules\Glitter\Workflow\Enums\ConditionLogicalGroup;

/**
 * all 또는 any로 결합된 조건 그룹.
 */
final readonly class ConditionGroup implements ConditionNode
{
    /**
     * @param  array<int, ConditionNode>  $children
     */
    public function __construct(
        public ConditionLogicalGroup $logicalGroup,
        public array $children,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'group' => $this->logicalGroup->value,
            'children' => array_map(
                static fn (ConditionNode $child): array => $child->toArray(),
                $this->children,
            ),
        ];
    }
}
