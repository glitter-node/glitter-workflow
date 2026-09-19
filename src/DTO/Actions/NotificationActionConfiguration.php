<?php

namespace Modules\Glitter\Workflow\DTO\Actions;

use Modules\Glitter\Workflow\Contracts\ActionConfiguration;
use Modules\Glitter\Workflow\Enums\WorkflowActionType;

/**
 * G7 알림 정의를 사용하는 알림 액션 설정.
 *
 * 훅 이름이나 callable은 받지 않습니다. 실행기는 definitionType으로 G7 알림
 * 정의를 조회하고 소유 확장과 hook_prefix를 그 정의에서 해석해야 합니다.
 */
final readonly class NotificationActionConfiguration implements ActionConfiguration
{
    /**
     * @param  array<string, string>  $variableBindings  알림 변수 => 트리거 필드 키
     */
    public function __construct(
        public string $definitionType,
        public string $recipientField,
        public array $variableBindings = [],
    ) {}

    public function type(): WorkflowActionType
    {
        return WorkflowActionType::Notification;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'definition_type' => $this->definitionType,
            'recipient_field' => $this->recipientField,
            'variable_bindings' => $this->variableBindings,
        ];
    }
}
