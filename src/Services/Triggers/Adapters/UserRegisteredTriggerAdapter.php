<?php

namespace Modules\Glitter\Workflow\Services\Triggers\Adapters;

use App\Models\User;
use InvalidArgumentException;
use Modules\Glitter\Workflow\Contracts\TriggerContextAdapterInterface;
use Modules\Glitter\Workflow\DTO\Triggers\TriggerContext;
use Modules\Glitter\Workflow\Enums\TriggerKey;

/** core.auth.after_register 인자 어댑터. */
final class UserRegisteredTriggerAdapter extends AbstractTriggerContextAdapter implements TriggerContextAdapterInterface
{
    public function map(mixed ...$arguments): TriggerContext
    {
        $user = $arguments[0] ?? null;

        if (! $user instanceof User || ! is_array($arguments[1] ?? null)
            || ! is_string($user->uuid) || $user->uuid === '') {
            throw new InvalidArgumentException(__('glitter-workflow::messages.invalid_trigger_arguments'));
        }

        return new TriggerContext(
            key: TriggerKey::UserRegistered,
            schemaVersion: 1,
            subjectType: 'core.user',
            subjectId: $user->uuid,
            data: [
                'user.uuid' => (string) $user->uuid,
                'user.status' => $this->stringValue($user->status),
                'user.language' => $this->stringValue($user->language),
                'user.country' => $this->stringValue($user->country),
                'user.created_at' => $this->dateTimeValue($user->created_at),
            ],
        );
    }
}
