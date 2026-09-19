<?php

namespace Modules\Glitter\Workflow\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Modules\Glitter\Workflow\Http\Requests\ExecutionListRequest;
use Modules\Glitter\Workflow\Http\Resources\ExecutionCollection;
use Modules\Glitter\Workflow\Http\Resources\ExecutionResource;
use Modules\Glitter\Workflow\Services\ExecutionObservabilityService;

final class ExecutionController extends AdminBaseController
{
    public function __construct(private readonly ExecutionObservabilityService $service) {}

    public function index(ExecutionListRequest $request)
    {
        $validated = $request->validated();

        return $this->successWithResource(
            'common.success',
            new ExecutionCollection($this->service->paginate(
                $request->filters(),
                (int) ($validated['per_page'] ?? 20),
            )),
        );
    }

    public function show(string $execution)
    {
        return $this->successWithResource('common.success', new ExecutionResource($this->service->get($execution)));
    }
}
