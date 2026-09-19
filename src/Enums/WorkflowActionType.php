<?php

namespace Modules\Glitter\Workflow\Enums;

/** 닫힌 워크플로 액션 유형과 현재 실행 가용성의 정본. */
enum WorkflowActionType: string
{
    case Notification = 'notification';
    case Webhook = 'webhook';
    case Delay = 'delay';

    public function isExecutable(): bool
    {
        return match ($this) {
            self::Webhook, self::Delay => true,
            self::Notification => false,
        };
    }

    /** @return array<int, string> */
    public static function executableValues(): array
    {
        return array_values(array_map(
            static fn (self $type): string => $type->value,
            array_filter(self::cases(), static fn (self $type): bool => $type->isExecutable()),
        ));
    }
}
