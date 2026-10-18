<?php

namespace Modules\Glitter\Workflow\DTO\Triggers;

use Modules\Glitter\Workflow\Enums\ConditionOperator;
use Modules\Glitter\Workflow\Enums\TriggerFieldType;

/**
 * 조건에 노출되는 트리거 필드 정의.
 */
final readonly class TriggerFieldDefinition
{
    /**
     * @param  array<int, ConditionOperator>  $operators
     */
    public function __construct(
        public string $key,
        public TriggerFieldType $type,
        public array $operators,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'operators' => array_map(
                static fn (ConditionOperator $operator): string => $operator->value,
                $this->operators,
            ),
        ];
    }
}
