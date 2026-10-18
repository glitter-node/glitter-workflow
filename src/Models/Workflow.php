<?php

namespace Modules\Glitter\Workflow\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Glitter\Workflow\Models\Concerns\HasWorkflowUuid;

/** 워크플로 정의 모델. */
class Workflow extends Model
{
    use HasWorkflowUuid;

    protected $table = 'glitter_workflows';

    protected $fillable = [
        'uuid',
        'name',
        'trigger_key',
        'trigger_config',
        'condition_tree',
        'action_plan',
        'version',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'condition_tree' => 'array',
            'action_plan' => 'array',
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** 생성자를 반환합니다. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 최종 수정자를 반환합니다. */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** 실행 이력을 반환합니다. */
    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class, 'workflow_id');
    }
}
