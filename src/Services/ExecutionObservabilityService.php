<?php

namespace Modules\Glitter\Workflow\Services;

use Modules\Glitter\Workflow\Models\WorkflowExecution;
use Modules\Glitter\Workflow\Repositories\ExecutionRepositoryInterface;

/** Read-only execution queries for the administrator observability surface. */
final class ExecutionObservabilityService
{
    public function __construct(private readonly ExecutionRepositoryInterface $repository) {}

    public function paginate(array $filters = [], int $perPage = 20)
    {
        $perPage = min(max($perPage, 1), 100);

        return $this->repository->paginate($filters, $perPage);
    }

    public function get(string $uuid): WorkflowExecution
    {
        return $this->repository->findByUuid($uuid) ?? abort(404, 'Execution not found.');
    }
}
