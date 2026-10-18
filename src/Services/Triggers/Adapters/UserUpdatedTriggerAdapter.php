<?php

namespace Modules\Glitter\Workflow\Services\Triggers\Adapters;

use App\Models\User;
use InvalidArgumentException;
use Modules\Glitter\Workflow\Contracts\TriggerContextAdapterInterface;
use Modules\Glitter\Workflow\DTO\Triggers\TriggerContext;
use Modules\Glitter\Workflow\Enums\TriggerKey;

/** core.user.after_update 인자 어댑터. */
final class UserUpdatedTriggerAdapter extends AbstractTriggerContextAdapter implements TriggerContextAdapterInterface
{
    public function map(mixed ...$arguments): TriggerContext
    {
        $user = $arguments[0] ?? null;

        if (! $user instanceof User || ! is_array($arguments[1] ?? null)
            || ! is_array($arguments[2] ?? null)
            || ! is_string($user->uuid) || $user->uuid === '') {
            throw new InvalidArgumentException(__('glitter-workflow::messages.invalid_trigger_arguments'));
        }

        // $arguments[1]의 원본 요청과 $arguments[2]의 스냅샷은 비밀번호·PII가
        // 포함될 수 있으므로 영속 컨텍스트에 복사하지 않습니다.
        return new TriggerContext(
            key: TriggerKey::UserUpdated,
            schemaVersion: 1,
            subjectType: 'core.user',
            subjectId: $user->uuid,
            data: [
                'user.uuid' => (string) $user->uuid,
                'user.status' => $this->stringValue($user->status),
                'user.language' => $this->stringValue($user->language),
                'user.country' => $this->stringValue($user->country),
                'user.updated_at' => $this->dateTimeValue($user->updated_at),
            ],
        );
    }
}
