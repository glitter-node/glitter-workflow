<?php

namespace Modules\Glitter\Workflow\DTO\Actions;

use Modules\Glitter\Workflow\Contracts\ActionConfiguration;
use Modules\Glitter\Workflow\Enums\WorkflowActionType;

/**
 * 사전 등록된 연결을 참조하는 웹훅 액션 설정.
 *
 * URL과 비밀값은 워크플로 정의에 저장하지 않습니다.
 */
final readonly class WebhookActionConfiguration implements ActionConfiguration
{
    /**
     * @param  array<string, string>  $bodyBindings  요청 필드 => 트리거 필드 키
     */
    public function __construct(
        public string $connectionKey,
        public array $bodyBindings = [],
    ) {}

    public function type(): WorkflowActionType
    {
        return WorkflowActionType::Webhook;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'connection_key' => $this->connectionKey,
            'body_bindings' => $this->bodyBindings,
        ];
    }
}
