<?php

namespace Modules\Glitter\Workflow\Tests\Unit;

use App\Services\LayoutService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class FoundationLayoutInheritanceTest extends TestCase
{
    #[Test]
    public function all_seven_shells_merge_into_the_current_admin_base(): void
    {
        $root = dirname(__DIR__, 5);
        $parent = json_decode(file_get_contents(
            $root.'/templates/_bundled/sirsoft-admin_basic/layouts/_admin_base.json',
        ), true, 512, JSON_THROW_ON_ERROR);
        $service = (new ReflectionClass(LayoutService::class))->newInstanceWithoutConstructor();
        $merge = new ReflectionMethod(LayoutService::class, 'mergeComponents');

        foreach ([
            'admin_workflow_list', 'admin_workflow_create', 'admin_workflow_detail',
            'admin_workflow_edit', 'admin_execution_list', 'admin_execution_detail',
            'admin_workflow_settings',
        ] as $name) {
            $child = json_decode(file_get_contents(
                dirname(__DIR__, 2).'/resources/layouts/admin/'.$name.'.json',
            ), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($parent['layout_name'], $child['extends']);
            $merged = $merge->invoke($service, $parent['components'], $child['slots']);
            $content = $this->findNode($merged, $name.'_content');
            $this->assertNotNull($content, $name);
            $this->assertSame($child['slots']['content'][0], $content);
        }
    }

    #[Test]
    public function create_and_edit_forms_keep_verified_field_wrappers_and_mutation_contracts(): void
    {
        $expectations = [
            'admin_workflow_create' => [
                'method' => 'POST',
                'target' => '/api/modules/glitter-workflow/admin/workflows',
                'success_path' => '/admin/glitter-workflow/workflows/{{response.data.uuid}}',
            ],
            'admin_workflow_edit' => [
                'method' => 'PUT',
                'target' => '/api/modules/glitter-workflow/admin/workflows/{{route?.uuid}}',
                'success_path' => '/admin/glitter-workflow/workflows/{{route?.uuid}}',
            ],
        ];

        $fields = [
            'name_field' => ['$t:glitter-workflow.admin.workflows.name', 'name', 'Input'],
            'trigger_field' => ['$t:glitter-workflow.admin.workflows.when', 'trigger_key', 'Select'],
            'group_field' => ['$t:glitter-workflow.admin.workflows.if_group', 'condition_tree.group', 'Select'],
            'action_field' => ['$t:glitter-workflow.admin.workflows.then', 'action_plan.0.type', 'Select'],
            'delay_field' => ['$t:glitter-workflow.admin.workflows.delay_seconds', 'action_plan.0.config.delay_seconds', 'Input'],
        ];

        foreach ($expectations as $name => $expectation) {
            $layout = json_decode((string) file_get_contents(
                dirname(__DIR__, 2).'/resources/layouts/admin/'.$name.'.json',
            ), true, 512, JSON_THROW_ON_ERROR);
            $form = $this->findNode($layout['slots']['content'], 'workflow_form');
            $this->assertNotNull($form);

            foreach ($fields as $wrapperId => [$label, $controlName, $controlType]) {
                $wrapper = $this->findNode($form['children'], $wrapperId);
                $this->assertNotNull($wrapper, $name.' '.$wrapperId);
                $this->assertSame('Div', $wrapper['name']);
                $this->assertSame('form-group', $wrapper['props']['className']);
                $this->assertCount(2, $wrapper['children']);
                $this->assertSame('Label', $wrapper['children'][0]['name']);
                $this->assertSame('form-label', $wrapper['children'][0]['props']['className']);
                $this->assertSame($label, $wrapper['children'][0]['text']);
                $this->assertSame($controlType, $wrapper['children'][1]['name']);
                $this->assertSame($controlName, $wrapper['children'][1]['props']['name']);
                $this->assertArrayHasKey('value', $wrapper['children'][1]['props']);
                $this->assertSame('change', $wrapper['children'][1]['actions'][0]['type']);
                $this->assertSame('setState', $wrapper['children'][1]['actions'][0]['handler']);
            }

            $save = $this->findNode($form['children'], 'save');
            $this->assertNotNull($save);
            $request = $save['actions'][0];
            $this->assertSame('apiCall', $request['handler']);
            $this->assertSame($expectation['target'], $request['target']);
            $this->assertSame($expectation['method'], $request['params']['method']);
            $this->assertSame($expectation['success_path'], $request['onSuccess'][0]['params']['path']);
            $this->assertSame('{{_local.form?.name ?? \'\'}}', $request['params']['body']['name']);

            $body = $request['params']['body'];
            $this->assertSame('{{_local.form?.action_plan ?? []}}', $body['action_plan']);
            if ($name === 'admin_workflow_edit') {
                $this->assertSame('{{_local.form?.version}}', $body['version']);
                $this->assertSame('{{error.status === 409}}', $request['onError'][0]['if']);
            } else {
                $this->assertArrayNotHasKey('version', $body);
            }
        }
    }

    private function findNode(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (($node['id'] ?? null) === $id) {
                return $node;
            }
            $found = $this->findNode($node['children'] ?? [], $id);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
