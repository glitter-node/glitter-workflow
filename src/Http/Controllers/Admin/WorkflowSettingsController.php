<?php

namespace Modules\Glitter\Workflow\Http\Controllers\Admin;

use App\Extension\HookManager;
use App\Http\Controllers\Api\Base\AdminBaseController;
use Modules\Glitter\Workflow\Http\Requests\UpdateWorkflowSettingsRequest;
use Modules\Glitter\Workflow\Http\Resources\SettingsResource;
use Modules\Glitter\Workflow\Services\WorkflowSettingsService;

final class WorkflowSettingsController extends AdminBaseController
{
    public function __construct(private readonly WorkflowSettingsService $settings) {}

    public function show()
    {
        return $this->successWithResource('common.success', new SettingsResource($this->settings->getAllSettings()));
    }

    public function update(UpdateWorkflowSettingsRequest $request)
    {
        try {
            $settings = ['limits' => $request->scalarSettings()];

            if (! $this->settings->saveSettings($settings)) {
                return $this->error('common.failed', 422);
            }

            HookManager::doAction('core.module_settings.after_save', 'glitter-workflow', $settings, true);

            return $this->successWithResource(
                'common.success',
                new SettingsResource($this->settings->getAllSettings()),
            );
        } catch (\Throwable) {
            return $this->error('common.failed', 500);
        }
    }
}
