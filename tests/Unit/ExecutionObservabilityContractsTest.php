<?php

namespace Modules\Glitter\Workflow\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExecutionObservabilityContractsTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    #[Test]
    public function routes_are_get_only_and_do_not_expose_mutations(): void
    {
        $source = file_get_contents($this->root().'/src/routes/api.php');

        $this->assertStringContainsString("Route::get('/',[ExecutionController::class,'index'])", $source);
        $this->assertStringContainsString("Route::get('/{execution}',[ExecutionController::class,'show'])", $source);
        $this->assertStringContainsString("Route::get('admin/settings',[WorkflowSettingsController::class,'show'])", $source);
        $this->assertStringContainsString("Route::put('admin/settings',[WorkflowSettingsController::class,'update'])", $source);
        $this->assertStringNotContainsString("Route::put('/{execution}'", $source);
        $this->assertStringNotContainsString("Route::patch('/{execution}'", $source);
        $this->assertStringNotContainsString("Route::delete('/{execution}'", $source);
        $this->assertStringNotContainsString("Route::post('admin/settings'", $source);
    }

    #[Test]
    public function resources_use_safe_allowlists(): void
    {
        $resource = file_get_contents($this->root().'/src/Http/Resources/ExecutionResource.php');
        $action = file_get_contents($this->root().'/src/Http/Resources/ActionExecutionResource.php');
        $acceptance = file_get_contents($this->root().'/src/Http/Resources/AcceptanceSummaryResource.php');
        $settings = file_get_contents($this->root().'/src/Http/Resources/SettingsResource.php');

        foreach ([$resource, $acceptance, $settings] as $source) {
            $this->assertStringNotContainsString("'trigger_context' =>", $source);
            $this->assertStringNotContainsString("'workflow_snapshot' =>", $source);
            $this->assertStringNotContainsString("'error_message' =>", $source);
            $this->assertStringNotContainsString("'claim_token' =>", $source);
        }
        foreach ([
            'workflow_uuid',
            'workflow_version',
            'correlation_id',
            'causation_id',
            'depth',
            'scheduled_at',
            'created_at',
            'updated_at',
            'error_code',
        ] as $field) {
            $this->assertStringContainsString("'$field'", $resource, $field);
        }
        foreach (['scheduled_at', 'error_code', 'delay_seconds'] as $field) {
            $this->assertStringContainsString("'$field'", $action, $field);
        }
        foreach (['uuid', 'source_key', 'available_at', 'claimed_at', 'processed_at', 'error_code'] as $field) {
            $this->assertStringContainsString("'$field'", $acceptance, $field);
        }
        $this->assertStringNotContainsString("'action_snapshot' =>", $action);
        $this->assertStringNotContainsString("'result_metadata' =>", $action);
        $this->assertStringNotContainsString("'error_message' =>", $action);
        $this->assertStringContainsString("'status_code'", $action);
        $this->assertStringContainsString("'response_bytes'", $action);
        $this->assertStringContainsString('safeInteger', $action);
        $this->assertStringNotContainsString("'delivery_status'", $action);
        $this->assertStringContainsString("'connections' => ['count' => count($connections)]", $settings);
    }

    #[Test]
    public function execution_layouts_are_read_only_and_use_the_new_data_sources(): void
    {
        $layouts = [
            'admin_execution_list' => '/api/modules/glitter-workflow/admin/executions',
            'admin_execution_detail' => '/api/modules/glitter-workflow/admin/executions/{{route?.uuid}}',
        ];

        foreach ($layouts as $name => $endpoint) {
            $source = file_get_contents($this->root().'/resources/layouts/admin/'.$name.'.json');
            $this->assertStringContainsString('"method": "GET"', $source, $name);
            $this->assertStringContainsString($endpoint, $source, $name);
            $this->assertStringNotContainsString('"name": "Input"', $source, $name);
            $this->assertStringNotContainsString('"name": "Button"', $source, $name);
            $this->assertStringNotContainsString('apiCall', $source, $name);
            $this->assertStringNotContainsString('retry', strtolower($source), $name);
            $this->assertStringNotContainsString('cancel', strtolower($source), $name);
        }

        $this->assertStringContainsString('"name": "EmptyState"', file_get_contents($this->root().'/resources/layouts/admin/admin_execution_list.json'));

        $list = json_decode(file_get_contents($this->root().'/resources/layouts/admin/admin_execution_list.json'), true, 512, JSON_THROW_ON_ERROR);
        $nodes = $this->collectNodes($list['slots']['content']);
        $grid = $this->findNode($nodes, 'executions_data_grid');
        $this->assertNotNull($grid);
        $this->assertSame(
            ['uuid', 'workflow_name', 'trigger_key', 'status', 'created_at', 'started_at', 'finished_at'],
            array_column($grid['props']['columns'], 'field'),
        );
        $this->assertSame('$t:glitter-workflow.admin.executions.columns.uuid', $grid['props']['columns'][0]['header']);
        $this->assertSame('$t:glitter-workflow.admin.executions.columns.created', $grid['props']['columns'][4]['header']);
        $fallback = $list['data_sources'][0]['fallback']['data'];
        $this->assertSame([], $fallback['data']);
        $this->assertSame(1, $fallback['pagination']['current_page']);
        $this->assertSame(20, $fallback['pagination']['per_page']);
        $this->assertFalse($fallback['pagination']['has_more_pages']);
    }

    #[Test]
    public function execution_detail_displays_existing_safe_trace_metadata(): void
    {
        $layout = json_decode(file_get_contents($this->root().'/resources/layouts/admin/admin_execution_detail.json'), true, 512, JSON_THROW_ON_ERROR);
        $nodes = $this->collectNodes($layout['slots']['content']);

        $trace = $this->findNode($nodes, 'execution_trace_section');
        $this->assertNotNull($trace);
        $traceNodes = $this->collectNodes($trace['children'] ?? []);

        foreach ([
            'execution_workflow_uuid' => 'workflow_uuid',
            'execution_workflow_version' => 'workflow_version',
            'execution_correlation_id' => 'correlation_id',
            'execution_causation_id' => 'causation_id',
            'execution_depth' => 'depth',
            'execution_scheduled_at' => 'scheduled_at',
            'execution_created_at' => 'created_at',
            'execution_updated_at' => 'updated_at',
            'execution_error_code' => 'error_code',
        ] as $nodeId => $field) {
            $node = $this->findNode($traceNodes, $nodeId);
            $this->assertNotNull($node, $nodeId);
            $this->assertSame('Span', $node['name'], $nodeId);
            $this->assertStringContainsString('execution?.data?.'.$field, $node['text'], $nodeId);
        }

        $grid = $this->findNode($nodes, 'action_executions_grid');
        $this->assertNotNull($grid);
        $this->assertSame([
            'action_index',
            'action_type',
            'status',
            'attempts',
            'scheduled_at',
            'started_at',
            'finished_at',
            'error_code',
            'delay_seconds',
            'status_code',
            'response_bytes',
        ], array_column($grid['props']['columns'], 'field'));
        $this->assertStringContainsString("row.action_type === 'delay'", json_encode($grid, JSON_THROW_ON_ERROR));

        $acceptance = $this->findNode($nodes, 'acceptance_summary');
        $this->assertNotNull($acceptance);
        $acceptanceNodes = $this->collectNodes($acceptance['children'] ?? []);
        foreach ([
            'acceptance_uuid' => 'acceptance?.uuid',
            'acceptance_source' => 'acceptance?.source_key',
            'acceptance_status' => 'acceptance?.status',
            'acceptance_attempts' => 'acceptance?.attempts',
            'acceptance_available_at' => 'acceptance?.available_at',
            'acceptance_claimed_at' => 'acceptance?.claimed_at',
            'acceptance_processed_at' => 'acceptance?.processed_at',
            'acceptance_error_code' => 'acceptance?.error_code',
        ] as $nodeId => $field) {
            $node = $this->findNode($acceptanceNodes, $nodeId);
            $this->assertNotNull($node, $nodeId);
            $this->assertSame('Span', $node['name'], $nodeId);
            $this->assertStringContainsString('execution?.data?.'.$field, $node['text'], $nodeId);
        }

        $source = json_encode($layout, JSON_THROW_ON_ERROR);
        foreach ([
            'trigger_context',
            'workflow_snapshot',
            'action_snapshot',
            'result_metadata',
            'request_body',
            'response_body',
            'claim_token',
            'error_message',
            'stack_trace',
            'secret',
            'credential',
            'exception',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source, $forbidden);
        }
    }

    #[Test]
    public function settings_layout_allows_only_permission_gated_scalar_updates(): void
    {
        $layout = json_decode(file_get_contents($this->root().'/resources/layouts/admin/admin_workflow_settings.json'), true, 512, JSON_THROW_ON_ERROR);
        $source = json_encode($layout, JSON_THROW_ON_ERROR);
        $nodes = $this->collectNodes($layout['slots']['content']);
        $inputs = array_values(array_filter($nodes, static fn (array $node): bool => ($node['name'] ?? null) === 'Input'));
        $inputNames = array_values(array_map(static fn (array $node): ?string => $node['props']['name'] ?? null, $inputs));
        sort($inputNames);

        $this->assertSame(['max_actions_per_workflow', 'max_condition_depth'], $inputNames);
        $this->assertStringContainsString('settings?.data?.abilities?.can_update === true', $source);
        $this->assertStringContainsString('settings?.data?.abilities?.can_update !== true', $source);
        $this->assertStringContainsString('max_workflow_recursion_depth', $source);
        $this->assertStringContainsString('connections?.count', $source);
        $this->assertStringNotContainsString('retry', strtolower($source));
        $this->assertStringNotContainsString('cancel', strtolower($source));

        foreach ([
            'max_recursion_depth_field' => 'max_recursion_depth',
            'connections_count_field' => 'connections_count',
        ] as $fieldContainerId => $valueId) {
            $container = $this->findNode($nodes, $fieldContainerId);
            $this->assertNotNull($container, $fieldContainerId);
            $this->assertSame('Div', $container['name'], $fieldContainerId);
            $this->assertSame('form-group', $container['props']['className'] ?? null, $fieldContainerId);
            $value = $this->findNode($this->collectNodes($container['children'] ?? []), $valueId);
            $this->assertNotNull($value, $valueId);
            $this->assertSame('Span', $value['name'], $valueId);
        }

        $save = $this->findNode($nodes, 'save');
        $this->assertNotNull($save);
        $this->assertSame('{{settings?.data?.abilities?.can_update === true}}', $save['if']);

        $sequence = $save['actions'][0];
        $this->assertSame('sequence', $sequence['handler']);
        $apiCall = $sequence['params']['actions'][1];
        $this->assertSame('apiCall', $apiCall['handler']);
        $this->assertSame('PUT', $apiCall['params']['method']);
        $this->assertSame([
            'max_actions_per_workflow',
            'max_condition_depth',
        ], array_keys($apiCall['params']['body']));
        $this->assertStringContainsString('"dataSourceId":"settings"', json_encode($apiCall['onSuccess'], JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function locales_have_matching_observability_keys(): void
    {
        $en = json_decode(file_get_contents($this->root().'/resources/lang/en.json'), true, 512, JSON_THROW_ON_ERROR);
        $ko = json_decode(file_get_contents($this->root().'/resources/lang/ko.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            array_keys($en['admin']['executions']),
            array_keys($ko['admin']['executions']),
        );
        $this->assertSame(
            array_keys($en['admin']['settings']),
            array_keys($ko['admin']['settings']),
        );
        $this->assertArrayHasKey('save', $en['admin']['settings']);
        $this->assertArrayHasKey('save', $ko['admin']['settings']);
        $this->assertNotSame('', trim($en['admin']['settings']['save']));
        $this->assertNotSame('', trim($ko['admin']['settings']['save']));
    }

    #[Test]
    public function settings_update_keeps_an_explicit_two_field_allowlist(): void
    {
        $request = file_get_contents($this->root().'/src/Http/Requests/UpdateWorkflowSettingsRequest.php');
        $controller = file_get_contents($this->root().'/src/Http/Controllers/Admin/WorkflowSettingsController.php');
        $resource = file_get_contents($this->root().'/src/Http/Resources/SettingsResource.php');

        $this->assertStringContainsString("'max_actions_per_workflow'", $request);
        $this->assertStringContainsString("'max_condition_depth'", $request);
        $this->assertStringNotContainsString("'max_workflow_recursion_depth'", $request);
        $this->assertStringNotContainsString("'connections'", $request);
        $this->assertStringContainsString('WorkflowLimits::accepts', $request);
        $this->assertStringContainsString('$request->scalarSettings()', $controller);
        $this->assertStringContainsString("'glitter-workflow.settings.update'", file_get_contents($this->root().'/src/routes/api.php'));
        $this->assertStringContainsString("'connections' => ['count' => count($connections)]", $resource);
        $this->assertStringContainsString("'can_update'", $resource);
    }

    /** @param array<int, array<string, mixed>> $nodes @return array<int, array<string, mixed>> */
    private function collectNodes(array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $result[] = $node;
            $result = [...$result, ...$this->collectNodes($node['children'] ?? [])];
        }

        return $result;
    }

    /** @param array<int, array<string, mixed>> $nodes */
    private function findNode(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $id) {
                return $node;
            }
        }

        return null;
    }
}
