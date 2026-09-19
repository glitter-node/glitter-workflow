<?php

namespace Modules\Glitter\Workflow\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Modules\Glitter\Workflow\Http\Requests\StoreWorkflowRequest;
use Modules\Glitter\Workflow\Http\Requests\UpdateWorkflowRequest;
use Modules\Glitter\Workflow\Http\Requests\WorkflowListRequest;
use Modules\Glitter\Workflow\Http\Resources\WorkflowCollection;
use Modules\Glitter\Workflow\Http\Resources\WorkflowResource;
use Modules\Glitter\Workflow\Services\WorkflowDefinitionService;

class WorkflowController extends AdminBaseController
{
    public function __construct(private WorkflowDefinitionService $service) {}

    public function index(WorkflowListRequest $request)
    {
        $validated = $request->validated();

        return $this->successWithResource(
            'common.success',
            new WorkflowCollection($this->service->list(
                $request->filters(),
                (int) ($validated['per_page'] ?? 20),
            )),
        );
    }

    public function store(StoreWorkflowRequest $request)
    {
        return $this->successWithResource(
            'common.success',
            new WorkflowResource($this->service->create($request->validated())),
            201,
        );
    }

    public function show(string $workflow)
    {
        return $this->successWithResource('common.success', new WorkflowResource($this->service->get($workflow)));
    }

    public function update(UpdateWorkflowRequest $request, string $workflow)
    {
        return $this->successWithResource(
            'common.success',
            new WorkflowResource($this->service->update($this->service->get($workflow), $request->validated())),
        );
    }

    public function destroy(string $workflow)
    {
        $this->service->delete($this->service->get($workflow));

        return $this->success(null);
    }

    public function activate(string $workflow)
    {
        return $this->successWithResource(
            'common.success',
            new WorkflowResource($this->service->setActive($this->service->get($workflow), true)),
        );
    }

    public function deactivate(string $workflow)
    {
        return $this->successWithResource(
            'common.success',
            new WorkflowResource($this->service->setActive($this->service->get($workflow), false)),
        );
    }
}
