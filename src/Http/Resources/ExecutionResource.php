<?php

namespace Modules\Glitter\Workflow\Http\Resources;

use App\Http\Resources\BaseApiResource;
use BackedEnum;
use Illuminate\Http\Request;

final class ExecutionResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        $workflow = $this->workflow;
        $actions = $this->relationLoaded('actionExecutions') ? $this->actionExecutions : collect();

        return [
            'uuid' => $this->uuid,
            'workflow_uuid' => $workflow?->uuid,
            'workflow_name' => $workflow ? $this->localizedName($workflow->name) : null,
            'workflow_version' => (int) $this->workflow_version,
            'trigger_key' => $this->trigger_key,
            'status' => $this->enumValue($this->status),
            'correlation_id' => $this->correlation_id,
            'causation_id' => $this->causation_id,
            'depth' => (int) $this->depth,
            'scheduled_at' => $this->formatDateTimeStringForUser($this->scheduled_at),
            'started_at' => $this->formatDateTimeStringForUser($this->started_at),
            'finished_at' => $this->formatDateTimeStringForUser($this->finished_at),
            'created_at' => $this->formatDateTimeStringForUser($this->created_at),
            'updated_at' => $this->formatDateTimeStringForUser($this->updated_at),
            'error_code' => $this->error_code,
            'action_count' => array_key_exists('action_executions_count', $this->resource->getAttributes())
                ? (int) $this->action_executions_count
                : $actions->count(),
            'action_executions' => ActionExecutionResource::collection($actions),
            'acceptance' => $this->relationLoaded('acceptance') && $this->acceptance
                ? new AcceptanceSummaryResource($this->acceptance)
                : null,
        ];
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    private function localizedName(mixed $name): ?string
    {
        if (is_string($name)) {
            return $name;
        }
        if (! is_array($name)) {
            return null;
        }

        return $name[app()->getLocale()] ?? $name[config('app.fallback_locale', 'ko')] ?? reset($name) ?: null;
    }
}
