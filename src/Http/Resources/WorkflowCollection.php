<?php

namespace Modules\Glitter\Workflow\Http\Resources;

use App\Http\Resources\BaseApiCollection;
use Illuminate\Http\Request;

class WorkflowCollection extends BaseApiCollection
{
    public $collects = WorkflowResource::class;

    protected function abilityMap(): array
    {
        return [
            'can_create' => 'glitter-workflow.workflows.create',
        ];
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            ...$this->paginationMeta(),
            'abilities' => $this->resolveCollectionAbilities($request),
        ];
    }
}
