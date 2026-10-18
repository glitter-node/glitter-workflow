<?php

namespace Modules\Glitter\Workflow\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\UserUpdatedTriggerAdapter;
use Modules\Glitter\Workflow\Services\WorkflowAcceptanceService;

final class UserUpdatedWorkflowCaptureListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        return [
            'core.user.after_update' => [
                'method' => 'capture',
                'priority' => 30,
                'sync' => true,
            ],
        ];
    }

    public function handle(...$args): void
    {
        $this->capture(...$args);
    }

    public function capture(...$args): void
    {
        $context = app(UserUpdatedTriggerAdapter::class)->map(...$args);
        app(WorkflowAcceptanceService::class)->accept($context);
    }
}
