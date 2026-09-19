<?php

namespace Modules\Glitter\Workflow\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Glitter\Workflow\Models\WorkflowExecution;

interface ExecutionRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?WorkflowExecution;
}
