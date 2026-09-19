<?php
namespace Modules\Glitter\Workflow\Listeners;
use App\Contracts\Extension\HookListenerInterface; use Modules\Glitter\Workflow\Services\Triggers\Adapters\BoardPostCreatedTriggerAdapter; use Modules\Glitter\Workflow\Services\WorkflowAcceptanceService;
final class BoardPostWorkflowCaptureListener implements HookListenerInterface { public static function getSubscribedHooks():array{return ['sirsoft-board.post.after_create'=>['method'=>'capture','priority'=>30,'sync'=>true]];} public function handle(...$args):void{} public function capture(...$args):void { $context=app(BoardPostCreatedTriggerAdapter::class)->map(...$args); app(WorkflowAcceptanceService::class)->accept($context); } }
