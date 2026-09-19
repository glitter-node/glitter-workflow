<?php

namespace Modules\Glitter\Workflow\DTO\Conditions;

use Modules\Glitter\Workflow\Contracts\ConditionNode;
use Modules\Glitter\Workflow\Enums\ConditionOperator;

/**
 * 허용 목록 필드 하나에 대한 타입 조건.
 */
final readonly class ConditionPredicate implements ConditionNode
{
    public function __construct(
        public string $field,
        public ConditionOperator $operator,
        public mixed $value = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'operator' => $this->operator->value,
            'value' => $this->value,
        ];
    }
}
