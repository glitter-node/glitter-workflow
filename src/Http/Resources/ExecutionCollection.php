<?php

namespace Modules\Glitter\Workflow\Http\Resources;

use App\Http\Resources\BaseApiCollection;
use Illuminate\Http\Request;

final class ExecutionCollection extends BaseApiCollection
{
    public $collects = ExecutionResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            ...$this->paginationMeta(),
        ];
    }
}
