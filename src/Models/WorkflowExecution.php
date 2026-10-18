<?php

namespace Modules\Glitter\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Glitter\Workflow\Enums\WorkflowExecutionStatus;
use Modules\Glitter\Workflow\Models\Concerns\HasWorkflowUuid;

/** 워크플로 기계 실행 원장 모델. */
class WorkflowExecution extends Model
{
    use HasWorkflowUuid;

    protected $table = 'glitter_workflow_executions';

    protected $fillable = [
        'uuid',
        'workflow_id',
        'acceptance_id',
        'workflow_version',
        'workflow_snapshot',
        'trigger_key',
        'trigger_subject_type',
        'trigger_subject_id',
        'deduplication_key',
        'trigger_context',
        'status',
        'scheduled_at',
        'started_at',
        'finished_at',
        'attempts',
        'correlation_id',
        'causation_id',
        'depth',
        'error_code',
        'error_message',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'workflow_version' => 'integer',
            'workflow_snapshot' => 'array',
            'trigger_context' => 'array',
            'status' => WorkflowExecutionStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'attempts' => 'integer',
            'depth' => 'integer',
        ];
    }

    public function acceptance(): BelongsTo { return $this->belongsTo(WorkflowAcceptance::class, 'acceptance_id'); }

    /** 실행 당시 원본 워크플로를 반환합니다. */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    /** 순차 액션 실행 상태를 반환합니다. */
    public function actionExecutions(): HasMany
    {
        return $this->hasMany(WorkflowActionExecution::class, 'execution_id')
            ->orderBy('action_index');
    }
}
