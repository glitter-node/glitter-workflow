<?php

namespace Modules\Glitter\Workflow\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowListFilterContractsTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    #[Test]
    public function request_and_repository_use_only_the_allowlisted_filters(): void
    {
        $request = file_get_contents($this->root().'/src/Http/Requests/WorkflowListRequest.php');
        $controller = file_get_contents($this->root().'/src/Http/Controllers/Admin/WorkflowController.php');
        $repository = file_get_contents($this->root().'/src/Repositories/WorkflowRepository.php');

        $this->assertStringContainsString("Rule::in(['active', 'inactive'])", $request);
        $this->assertStringContainsString('Rule::enum(TriggerKey::class)', $request);
        $this->assertStringContainsString("'per_page' => ['nullable', 'integer', 'min:1', 'max:100']", $request);
        $this->assertStringContainsString("'page' => ['nullable', 'integer', 'min:1']", $request);
        $this->assertStringContainsString('$request->filters()', $controller);
        $this->assertStringNotContainsString('$request->all()', $controller);
        $this->assertStringContainsString("where('is_active',true)", $repository);
        $this->assertStringContainsString("where('is_active',false)", $repository);
        $this->assertStringContainsString("where('trigger_key',\$filters['trigger_key'])", $repository);
        $this->assertStringContainsString("'search' => ['nullable', 'string', 'max:255']", $request);
        $this->assertStringContainsString("'search' => \$validated['search'] ?? null", $request);
        $this->assertStringContainsString("trim(\$this->input('search'))", $request);
        $this->assertStringContainsString('KeywordSearch::applyAny', $repository);
        $this->assertStringContainsString("['name','uuid']", $repository);
        $this->assertStringNotContainsString("['id',", $repository);
        $this->assertStringNotContainsString("['workflow_id',", $repository);
        $this->assertStringContainsString('->paginate(min(max($perPage,1),100))', $repository);
    }

    #[Test]
    public function workflow_list_ordering_is_deterministic_after_created_at(): void
    {
        $repository = file_get_contents($this->root().'/src/Repositories/WorkflowRepository.php');

        $this->assertStringContainsString(
            "->latest('created_at')->orderByDesc('id')->paginate(min(max($perPage,1),100))",
            $repository,
        );
    }

    #[Test]
    public function workflow_list_layout_preserves_get_pagination_and_resets_page_on_filters(): void
    {
        $layout = json_decode(file_get_contents($this->root().'/resources/layouts/admin/admin_workflow_list.json'), true, 512, JSON_THROW_ON_ERROR);
        $source = json_encode($layout, JSON_THROW_ON_ERROR);
        $children = $layout['slots']['content'][0]['children'];
        $filters = $children[1];

        $this->assertSame('/api/modules/glitter-workflow/admin/workflows', $layout['data_sources'][0]['endpoint']);
        $this->assertSame('GET', $layout['data_sources'][0]['method']);
        $this->assertArrayHasKey('status', $layout['data_sources'][0]['params']);
        $this->assertArrayHasKey('trigger_key', $layout['data_sources'][0]['params']);
        $this->assertArrayHasKey('search', $layout['data_sources'][0]['params']);
        $this->assertSame(['', 'active', 'inactive'], array_column($filters['children'][0]['children'][1]['props']['options'], 'value'));
        $this->assertSame(['', 'user.registered', 'user.updated', 'board.post.created', 'board.comment.created'], array_column($filters['children'][1]['children'][1]['props']['options'], 'value'));
        $this->assertSame('1', $filters['children'][0]['children'][1]['actions'][0]['params']['query']['page']);
        $this->assertSame('1', $filters['children'][1]['children'][1]['actions'][0]['params']['query']['page']);
        $this->assertSame('SearchBar', $filters['children'][2]['name']);
        $this->assertSame("{{_local.searchQuery || ''}}", $filters['children'][2]['props']['value']);
        $this->assertSame('1', $filters['children'][2]['actions'][1]['params']['query']['page']);
        $this->assertTrue($filters['children'][2]['actions'][1]['params']['mergeQuery']);
        $this->assertStringContainsString('_local.searchQuery', $filters['children'][2]['actions'][1]['params']['query']['search']);
        $this->assertSame('', $layout['state']['searchQuery']);
        $this->assertStringContainsString('query.search', $layout['init_actions'][0]['params']['_local']['searchQuery']);
        $this->assertStringContainsString('serverSidePagination', $source);
        $this->assertStringContainsString('/admin/glitter-workflow/workflows/{{$args[0].uuid}}', $source);
        $this->assertStringNotContainsString('"method": "POST"', $source);
        $this->assertStringNotContainsString('"method": "PUT"', $source);
        $this->assertStringNotContainsString('"method": "DELETE"', $source);
    }

    #[Test]
    public function locales_have_matching_workflow_filter_keys(): void
    {
        $en = json_decode(file_get_contents($this->root().'/resources/lang/en.json'), true, 512, JSON_THROW_ON_ERROR);
        $ko = json_decode(file_get_contents($this->root().'/resources/lang/ko.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            array_keys($en['admin']['workflows']['index']['filters']),
            array_keys($ko['admin']['workflows']['index']['filters']),
        );
        $this->assertSame(
            array_keys($en['admin']['workflows']['index']['filters']['status']),
            array_keys($ko['admin']['workflows']['index']['filters']['status']),
        );
        $this->assertSame(
            array_keys($en['admin']['workflows']['index']['filters']['trigger']),
            array_keys($ko['admin']['workflows']['index']['filters']['trigger']),
        );
        $this->assertSame(
            array_keys($en['admin']['workflows']['index']['filters']['search']),
            array_keys($ko['admin']['workflows']['index']['filters']['search']),
        );
        $this->assertNotSame('', trim($en['admin']['workflows']['index']['filters']['search']['placeholder']));
        $this->assertNotSame('', trim($ko['admin']['workflows']['index']['filters']['search']['placeholder']));
    }
}
