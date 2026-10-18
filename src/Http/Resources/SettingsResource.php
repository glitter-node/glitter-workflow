<?php

namespace Modules\Glitter\Workflow\Http\Resources;

use App\Helpers\PermissionHelper;
use App\Http\Resources\BaseApiResource;
use Illuminate\Http\Request;

final class SettingsResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        $limits = is_array($this->limits ?? null) ? $this->limits : [];
        $connections = is_array($this->connections ?? null) ? $this->connections : [];

        return [
            'max_actions_per_workflow' => (int) ($limits['max_actions_per_workflow'] ?? 0),
            'max_condition_depth' => (int) ($limits['max_condition_depth'] ?? 0),
            'max_workflow_recursion_depth' => (int) ($limits['max_workflow_recursion_depth'] ?? 0),
            'connections' => ['count' => count($connections)],
            'abilities' => [
                'can_update' => PermissionHelper::check('glitter-workflow.settings.update', $request->user()),
            ],
        ];
    }
}
