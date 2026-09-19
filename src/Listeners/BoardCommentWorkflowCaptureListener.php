<?php

namespace Modules\Glitter\Workflow\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\BoardCommentCreatedTriggerAdapter;
use Modules\Glitter\Workflow\Services\WorkflowAcceptanceService;

final class BoardCommentWorkflowCaptureListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        return [
            'sirsoft-board.comment.after_create' => [
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
        $context = app(BoardCommentCreatedTriggerAdapter::class)->map(...$args);
        app(WorkflowAcceptanceService::class)->accept($context);
    }
}
