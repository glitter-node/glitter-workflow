<?php

namespace Modules\Glitter\Workflow\Contracts;

use Modules\Glitter\Workflow\DTO\Triggers\TriggerDefinition;
use Modules\Glitter\Workflow\Enums\TriggerKey;

/**
 * 워크플로 트리거 정의 조회 계약.
 */
interface TriggerDefinitionRegistryInterface
{
    /**
     * 등록된 트리거 정의를 반환합니다.
     *
     * @return array<string, TriggerDefinition>
     */
    public function all(): array;

    /** 트리거 정의를 조회합니다. */
    public function find(TriggerKey|string $key): ?TriggerDefinition;

    /** 트리거 정의 존재 여부를 반환합니다. */
    public function has(TriggerKey|string $key): bool;
}
