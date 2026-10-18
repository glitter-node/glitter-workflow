<?php

namespace Modules\Glitter\Workflow\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Glitter\Workflow\Models\WorkflowExecution;

final class ExecutionRepository implements ExecutionRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = WorkflowExecution::query()
            ->with(['workflow', 'acceptance'])
            ->withCount('actionExecutions');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['trigger_key'])) {
            $query->where('trigger_key', $filters['trigger_key']);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function findByUuid(string $uuid): ?WorkflowExecution
    {
        return WorkflowExecution::query()
            ->with(['workflow', 'acceptance', 'actionExecutions'])
            ->where('uuid', $uuid)
            ->first();
    }
}
