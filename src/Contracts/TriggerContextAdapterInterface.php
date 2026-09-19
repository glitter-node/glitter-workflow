<?php

namespace Modules\Glitter\Workflow\Contracts;

use Modules\Glitter\Workflow\DTO\Triggers\TriggerContext;

/**
 * G7 훅 인자를 버전된 안전 컨텍스트로 변환하는 계약.
 */
interface TriggerContextAdapterInterface
{
    /**
     * 검증된 훅 인자만 받아 허용 목록 기반 컨텍스트를 만듭니다.
     */
    public function map(mixed ...$arguments): TriggerContext;
}
