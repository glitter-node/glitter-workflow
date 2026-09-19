<?php

namespace Modules\Glitter\Workflow\Tests\Unit;

use App\Contracts\Extension\StorageInterface;
use InvalidArgumentException;
use Modules\Glitter\Workflow\Enums\WorkflowActionType;
use Modules\Glitter\Workflow\Models\Workflow;
use Modules\Glitter\Workflow\Repositories\WorkflowRepositoryInterface;
use Modules\Glitter\Workflow\Services\Triggers\BuiltInTriggerDefinitionRegistry;
use Modules\Glitter\Workflow\Services\WorkflowDefinitionService;
use Modules\Glitter\Workflow\Services\WorkflowDefinitionValidator;
use Modules\Glitter\Workflow\Services\WorkflowSettingsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowDefinitionValidatorTest extends TestCase
{
    #[Test]
    public function executable_action_availability_is_authoritative_and_closed(): void
    {
        self::assertSame(['webhook', 'delay'], WorkflowActionType::executableValues());
        self::assertFalse(WorkflowActionType::Notification->isExecutable());
        self::assertTrue(WorkflowActionType::Webhook->isExecutable());
        self::assertTrue(WorkflowActionType::Delay->isExecutable());
    }

    #[Test]
    public function notification_is_rejected_while_delay_and_webhook_remain_valid(): void
    {
        $validator = $this->validator();

        self::assertSame('delay', $validator->validate($this->definition('delay', ['delay_seconds' => 60]))['action_plan'][0]['type']);
        self::assertSame('webhook', $validator->validate($this->definition('webhook', [
            'connection_key' => 'partner-api',
            'body_bindings' => ['user_id' => 'user.uuid'],
        ]))['action_plan'][0]['type']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Workflow action type is unavailable.');
        $validator->validate($this->definition('notification', [
            'definition_type' => 'reserved',
            'recipient_field' => 'user.uuid',
            'variable_bindings' => [],
        ]));
    }

    #[Test]
    public function notification_containing_legacy_workflow_is_rejected_before_activation_write(): void
    {
        $workflow = new Workflow;
        $workflow->forceFill([
            'uuid' => '01995600-1234-7000-8000-123456789abc',
            'name' => 'Legacy notification',
            'trigger_key' => 'user.registered',
            'trigger_config' => [],
            'condition_tree' => null,
            'action_plan' => [[
                'index' => 0,
                'type' => 'notification',
                'config' => ['definition_type' => 'reserved', 'recipient_field' => 'user.uuid', 'variable_bindings' => []],
            ]],
            'version' => 1,
            'is_active' => false,
        ]);

        $repository = $this->createMock(WorkflowRepositoryInterface::class);
        $repository->expects(self::never())->method('update');
        $service = new WorkflowDefinitionService($repository, $this->validator());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Workflow action type is unavailable.');
        $service->setActive($workflow, true);
    }

    #[Test]
    public function runtime_terminal_fails_any_unavailable_action_without_notification_dispatch(): void
    {
        $source = file_get_contents(__DIR__.'/../../src/Jobs/ProcessWorkflowExecutionJob.php');

        self::assertStringContainsString('! $next->action_type->isExecutable()', $source);
        self::assertStringContainsString("\$this->markTerminal(\$next, 'action_unavailable')", $source);
        self::assertStringNotContainsString("action_type->value === 'notification') return null", $source);
        self::assertStringNotContainsString('GenericNotification', $source);
        self::assertStringNotContainsString('Notification::send', $source);
    }

    #[Test]
    public function admin_action_choices_expose_only_executable_types(): void
    {
        foreach ([
            __DIR__.'/../../resources/layouts/workflow_editor.json',
            __DIR__.'/../../resources/layouts/admin/admin_workflow_create.json',
            __DIR__.'/../../resources/layouts/admin/admin_workflow_edit.json',
        ] as $path) {
            $json = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $encoded = json_encode($json, JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString('notification', $encoded);
            self::assertStringContainsString('webhook', $encoded);
            self::assertStringContainsString('delay', $encoded);
        }
    }

    private function validator(): WorkflowDefinitionValidator
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('get')->willReturn(null);

        return new WorkflowDefinitionValidator(
            new BuiltInTriggerDefinitionRegistry,
            new WorkflowSettingsService($storage),
        );
    }

    /** @return array<string, mixed> */
    private function definition(string $type, array $config): array
    {
        return [
            'name' => 'Supported workflow',
            'trigger_key' => 'user.registered',
            'trigger_config' => [],
            'condition_tree' => null,
            'action_plan' => [['type' => $type, 'config' => $config]],
        ];
    }
}
