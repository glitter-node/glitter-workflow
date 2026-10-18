<?php

namespace Modules\Glitter\Workflow\Http\Resources;

use App\Http\Resources\BaseApiResource;
use Illuminate\Http\Request;

/** Workflow 정의를 안전한 관리자 응답으로 직렬화합니다. */
class WorkflowResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->getLocalizedField('name'),
            'trigger_key' => $this->trigger_key,
            'trigger_config' => $this->trigger_config ?? [],
            'condition_tree' => $this->condition_tree,
            'action_plan' => $this->actionSummary(),
            'condition_summary' => $this->conditionSummary($this->condition_tree),
            'action_summary' => $this->actionSummary(),
            'status' => $this->is_active ? 'active' : 'inactive',
            'metadata' => [
                'version' => (int) $this->version,
                'is_active' => (bool) $this->is_active,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
            'version' => $this->version,
            'is_active' => (bool) $this->is_active,
            'creator' => $this->whenLoaded('creator', fn () => [
                'uuid' => $this->creator->uuid,
                'name' => $this->creator->name,
            ]),
            'updater' => $this->whenLoaded('updater', fn () => [
                'uuid' => $this->updater->uuid,
                'name' => $this->updater->name,
            ]),
            ...$this->formatTimestamps(),
            ...$this->resourceMeta($request),
        ];
    }

    protected function abilityMap(): array
    {
        return [
            'can_update' => 'glitter-workflow.workflows.update',
            'can_delete' => 'glitter-workflow.workflows.delete',
            'can_activate' => 'glitter-workflow.workflows.activate',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function actionSummary(): array
    {
        return collect($this->action_plan ?? [])->values()->map(function (mixed $action, int $index): array {
            $action = is_array($action) ? $action : [];
            $type = is_string($action['type'] ?? null) ? $action['type'] : 'unknown';
            $config = is_array($action['config'] ?? null) ? $action['config'] : [];
            $summary = [
                'index' => (int) ($action['index'] ?? $index),
                'type' => $type,
            ];

            if ($type === 'delay') {
                $summary['delay_seconds'] = (int) ($config['delay_seconds'] ?? 0);
            }

            if ($type === 'webhook') {
                $summary['connection_key'] = is_string($config['connection_key'] ?? null)
                    ? $config['connection_key']
                    : null;
                $summary['body_bindings'] = array_values(array_filter(
                    array_keys(is_array($config['body_bindings'] ?? null) ? $config['body_bindings'] : []),
                    'is_string',
                ));
            }

            return $summary;
        })->all();
    }

    /** @return array<string, mixed> */
    private function conditionSummary(mixed $node, ?int $order = null): array
    {
        if (! is_array($node)) {
            return ['kind' => 'group', 'group' => 'all', 'children' => []];
        }

        if (array_key_exists('group', $node)) {
            return [
                'kind' => 'group',
                'group' => in_array($node['group'] ?? null, ['all', 'any'], true) ? $node['group'] : 'all',
                'children' => collect(is_array($node['children'] ?? null) ? $node['children'] : [])
                    ->values()
                    ->map(fn (mixed $child, int $childOrder): array => $this->conditionSummary($child, $childOrder))
                    ->values()
                    ->all(),
            ];
        }

        return [
            'kind' => 'predicate',
            'order' => $order ?? (int) ($node['order'] ?? 0),
            'field' => is_string($node['field'] ?? null) ? $node['field'] : null,
            'operator' => is_string($node['operator'] ?? null) ? $node['operator'] : null,
            'value' => $node['value'] ?? null,
        ];
    }
}
