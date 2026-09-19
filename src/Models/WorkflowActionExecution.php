<?php

namespace Modules\Glitter\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Glitter\Workflow\Enums\ActionExecutionStatus;
use Modules\Glitter\Workflow\Enums\WorkflowActionType;
use Modules\Glitter\Workflow\Models\Concerns\HasWorkflowUuid;

/** 개별 액션 실행 상태 모델. */
class WorkflowActionExecution extends Model
{
    use HasWorkflowUuid;

    protected $table = 'glitter_workflow_action_executions';

    protected $fillable = [
        'uuid',
        'execution_id',
        'action_index',
        'action_type',
        'action_snapshot',
        'config_hash',
        'status',
        'attempt_count',
        'claim_token',
        'scheduled_at',
        'started_at',
        'finished_at',
        'idempotency_key',
        'result_metadata',
        'error_code',
        'error_message',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'action_index' => 'integer',
            'action_type' => WorkflowActionType::class,
            'action_snapshot' => 'array',
            'status' => ActionExecutionStatus::class,
            'attempt_count' => 'integer',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'result_metadata' => 'array',
        ];
    }

    /** 상위 워크플로 실행을 반환합니다. */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'execution_id');
    }
}
