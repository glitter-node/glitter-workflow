<?php

namespace Modules\Glitter\Workflow\Services\Runtime;

use DateTimeInterface;
use Modules\Glitter\Workflow\Enums\WorkflowActionType;

/** Running action recovery is deliberately action-type specific. */
final class ActionRunningRecoveryPolicy
{
    public const STALE_SECONDS = 600;
    public const MAX_DELAY_ATTEMPTS = 3;
    public const HOLD = 'hold';
    public const RECLAIM = 'reclaim';
    public const EXHAUST = 'exhaust';

    public static function decision(WorkflowActionType $type, ?DateTimeInterface $startedAt, int $attempts, DateTimeInterface $now): string
    {
        if ($type !== WorkflowActionType::Delay || $startedAt === null || $startedAt->getTimestamp() >= $now->getTimestamp() - self::STALE_SECONDS) {
            return self::HOLD;
        }

        return $attempts >= self::MAX_DELAY_ATTEMPTS ? self::EXHAUST : self::RECLAIM;
    }
}
