<?php

namespace Modules\Glitter\Workflow\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExecutionListFilterContractsTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    #[Test]
    public function execution_list_request_uses_closed_status_and_trigger_filters(): void
    {
        $request = file_get_contents($this->root().'/src/Http/Requests/ExecutionListRequest.php');
        $controller = file_get_contents($this->root().'/src/Http/Controllers/Admin/ExecutionController.php');
        $repository = file_get_contents($this->root().'/src/Repositories/ExecutionRepository.php');
        $service = file_get_contents($this->root().'/src/Services/ExecutionObservabilityService.php');

        $this->assertStringContainsString('Rule::enum(WorkflowExecutionStatus::class)', $request);
        $this->assertStringContainsString('Rule::enum(TriggerKey::class)', $request);
        $this->assertStringContainsString("'per_page' => ['nullable', 'integer', 'min:1', 'max:100']", $request);
        $this->assertStringContainsString("'page' => ['nullable', 'integer', 'min:1']", $request);
        $this->assertStringContainsString('$request->filters()', $controller);
        $this->assertStringNotContainsString('$request->all()', $controller);
        $this->assertStringContainsString("'status' => \$validated['status'] ?? null", $request);
        $this->assertStringContainsString("'trigger_key' => \$validated['trigger_key'] ?? null", $request);
        $this->assertStringContainsString("\$filters['status']", $repository);
        $this->assertStringContainsString("\$filters['trigger_key']", $repository);
        $this->assertStringContainsString('return $this->repository->paginate($filters, $perPage);', $service);
        $this->assertStringContainsString("->orderByDesc('created_at')", $repository);
        $this->assertStringContainsString("->orderByDesc('id')", $repository);
        $this->assertStringContainsString('->paginate(min(max($perPage, 1), 100))', $repository);
    }

    #[Test]
    public function execution_list_layout_preserves_filters_pagination_and_uuid_navigation(): void
    {
        $layout = json_decode(file_get_contents($this->root().'/resources/layouts/admin/admin_execution_list.json'), true, 512, JSON_THROW_ON_ERROR);
        $source = json_encode($layout, JSON_THROW_ON_ERROR);
        $children = $layout['slots']['content'][0]['children'];
        $filters = $children[1];
        $status = $filters['children'][0]['children'][1];
        $trigger = $filters['children'][1]['children'][1];

        $this->assertSame('/api/modules/glitter-workflow/admin/executions', $layout['data_sources'][0]['endpoint']);
        $this->assertSame('GET', $layout['data_sources'][0]['method']);
        $this->assertArrayHasKey('status', $layout['data_sources'][0]['params']);
        $this->assertArrayHasKey('trigger_key', $layout['data_sources'][0]['params']);
        $this->assertSame(['', 'pending', 'scheduled', 'running', 'succeeded', 'failed', 'cancelled', 'skipped'], array_column($status['props']['options'], 'value'));
        $this->assertSame(['', 'user.registered', 'user.updated', 'board.post.created', 'board.comment.created'], array_column($trigger['props']['options'], 'value'));
        $this->assertSame('1', $status['actions'][0]['params']['query']['page']);
        $this->assertSame('1', $trigger['actions'][0]['params']['query']['page']);
        $this->assertTrue($status['actions'][0]['params']['mergeQuery']);
        $this->assertTrue($trigger['actions'][0]['params']['mergeQuery']);
        $this->assertStringContainsString('serverSidePagination', $source);
        $this->assertStringContainsString('/admin/glitter-workflow/executions/{{$args[0].uuid}}', $source);
        $this->assertStringNotContainsString('"method": "POST"', $source);
        $this->assertStringNotContainsString('"method": "PUT"', $source);
        $this->assertStringNotContainsString('"method": "DELETE"', $source);
    }

    #[Test]
    public function execution_filter_locales_have_matching_keys(): void
    {
        $en = json_decode(file_get_contents($this->root().'/resources/lang/en.json'), true, 512, JSON_THROW_ON_ERROR);
        $ko = json_decode(file_get_contents($this->root().'/resources/lang/ko.json'), true, 512, JSON_THROW_ON_ERROR);
        $enFilters = $en['admin']['executions']['index']['filters'];
        $koFilters = $ko['admin']['executions']['index']['filters'];

        $this->assertSame(array_keys($enFilters), array_keys($koFilters));
        $this->assertSame(array_keys($enFilters['status']), array_keys($koFilters['status']));
        $this->assertSame(array_keys($enFilters['trigger']), array_keys($koFilters['trigger']));
        $this->assertNotSame('', $enFilters['status']['label']);
        $this->assertNotSame('', $koFilters['status']['label']);
    }
}
