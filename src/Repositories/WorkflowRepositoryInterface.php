<?php
namespace Modules\Glitter\Workflow\Repositories;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Glitter\Workflow\Enums\TriggerKey;
use Modules\Glitter\Workflow\Models\Workflow;
interface WorkflowRepositoryInterface { public function paginate(array $filters=[],int $perPage=20): LengthAwarePaginator; public function findByUuid(string $uuid): ?Workflow; public function hasEnabledForTrigger(TriggerKey $triggerKey): bool; public function create(array $data): Workflow; public function update(Workflow $workflow,array $data,?int $version=null): bool; public function delete(Workflow $workflow): bool; }
