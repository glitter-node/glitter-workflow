<?php

namespace Modules\Glitter\Workflow\Tests\Unit;

use App\Contracts\Extension\StorageInterface;
use App\Models\User;
use Modules\Glitter\Workflow\DTO\Actions\ActionPlanItem;
use Modules\Glitter\Workflow\DTO\Actions\DelayActionConfiguration;
use Modules\Glitter\Workflow\Enums\ActionExecutionStatus;
use Modules\Glitter\Workflow\Enums\ConditionLogicalGroup;
use Modules\Glitter\Workflow\Enums\ConditionOperator;
use Modules\Glitter\Workflow\Enums\TriggerKey;
use Modules\Glitter\Workflow\Enums\WorkflowActionType;
use Modules\Glitter\Workflow\Enums\WorkflowExecutionStatus;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\AbstractTriggerContextAdapter;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\BoardCommentCreatedTriggerAdapter;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\BoardPostCreatedTriggerAdapter;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\UserRegisteredTriggerAdapter;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\UserUpdatedTriggerAdapter;
use Modules\Glitter\Workflow\Services\Triggers\BuiltInTriggerDefinitionRegistry;
use Modules\Glitter\Workflow\Services\WorkflowSettingsService;
use Modules\Glitter\Workflow\Support\WorkflowLimits;
use Modules\Sirsoft\Board\Models\Comment;
use Modules\Sirsoft\Board\Models\Post;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Foundation의 닫힌 공개 계약 회귀 테스트. */
final class FoundationContractsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $sourceRoot = dirname(__DIR__, 2).'/src/';

        spl_autoload_register(static function (string $class) use ($sourceRoot): void {
            $prefix = 'Modules\\Glitter\\Workflow\\';
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $path = $sourceRoot.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($path)) {
                require_once $path;
            }
        });
    }

    #[Test]
    public function built_in_trigger_keys_map_to_verified_source_hooks(): void
    {
        $registry = new BuiltInTriggerDefinitionRegistry;

        $this->assertSame([
            'user.registered' => 'core.auth.after_register',
            'user.updated' => 'core.user.after_update',
            'board.post.created' => 'sirsoft-board.post.after_create',
            'board.comment.created' => 'sirsoft-board.comment.after_create',
        ], array_map(
            static fn ($definition): string => $definition->sourceHook,
            $registry->all(),
        ));

        $this->assertSame(
            ['user.registered@1', 'user.updated@1', 'board.post.created@1', 'board.comment.created@1'],
            array_values(array_map(
                static fn ($definition): string => $definition->schemaIdentifier(),
                $registry->all(),
            )),
        );
    }

    #[Test]
    public function foundation_uses_closed_condition_action_and_status_sets(): void
    {
        $this->assertSame(['all', 'any'], $this->values(ConditionLogicalGroup::cases()));
        $this->assertSame([
            'equals',
            'not_equals',
            'in',
            'not_in',
            'exists',
            'not_exists',
            'greater_than',
            'greater_than_or_equal',
            'less_than',
            'less_than_or_equal',
            'before',
            'after',
        ], $this->values(ConditionOperator::cases()));
        $this->assertSame(['notification', 'webhook', 'delay'], $this->values(WorkflowActionType::cases()));
        $this->assertSame(['webhook', 'delay'], WorkflowActionType::executableValues());

        $statuses = ['pending', 'scheduled', 'running', 'succeeded', 'failed', 'cancelled', 'skipped'];
        $this->assertSame($statuses, $this->values(WorkflowExecutionStatus::cases()));
        $this->assertSame($statuses, $this->values(ActionExecutionStatus::cases()));
        $this->assertSame([
            'user.registered',
            'user.updated',
            'board.post.created',
            'board.comment.created',
        ], $this->values(TriggerKey::cases()));
    }

    #[Test]
    public function action_plan_type_is_derived_from_its_typed_configuration(): void
    {
        $item = new ActionPlanItem(0, new DelayActionConfiguration(60));

        $this->assertSame([
            'index' => 0,
            'type' => 'delay',
            'config' => ['delay_seconds' => 60],
        ], $item->toArray());
    }

    #[Test]
    public function integer_normalization_is_canonical_and_range_checked(): void
    {
        $adapter = new class extends AbstractTriggerContextAdapter
        {
            public function normalize(mixed $value): ?int
            {
                return $this->integerValue($value);
            }
        };

        foreach ([123, 0, -123, PHP_INT_MAX, PHP_INT_MIN] as $value) {
            $this->assertSame($value, $adapter->normalize($value));
            $this->assertSame($value, $adapter->normalize((string) $value));
        }

        foreach ([null, 'invalid', '123.0', '1e3', true, false, 123.0, [], new \stdClass,
            ' 123', '123 ', "123\n", '+123', '0123', '-0', '',
            ((string) PHP_INT_MAX).'0', ((string) PHP_INT_MIN).'0'] as $value) {
            $this->assertNull($adapter->normalize($value));
        }
    }

    #[Test]
    public function board_parent_and_actor_numeric_strings_survive_both_adapters(): void
    {
        foreach ([
            [Post::class, BoardPostCreatedTriggerAdapter::class, 'post'],
            [Comment::class, BoardCommentCreatedTriggerAdapter::class, 'comment'],
        ] as [$modelClass, $adapterClass, $prefix]) {
            foreach (['123' => 123, 'invalid' => null, '123.5' => null] as $parent => $expected) {
                $model = $this->getMockBuilder($modelClass)->disableOriginalConstructor()
                    ->onlyMethods(['getAttribute', 'getKey'])->getMock();
                $attributes = [
                    'board_id' => 3, 'post_id' => '456', 'parent_id' => (string) $parent,
                    'user_id' => '789', 'depth' => 1, 'status' => 'published',
                ];
                $model->method('getKey')->willReturn(12);
                $model->method('getAttribute')->willReturnCallback(
                    static fn ($key) => $attributes[$key] ?? null,
                );
                $context = (new $adapterClass)->map($model, 'news');
                $this->assertSame($expected, $context->data[$prefix.'.parent_id']);
                $this->assertSame(789, $context->data[$prefix.'.actor_user_id']);
                if ($prefix === 'comment') {
                    $this->assertSame(456, $context->data['comment.post_id']);
                }
            }
        }
    }

    #[Test]
    public function both_user_subjects_use_public_uuid_without_internal_identity(): void
    {
        $uuid = '01995600-1234-7000-8000-123456789abc';
        foreach ([UserRegisteredTriggerAdapter::class, UserUpdatedTriggerAdapter::class] as $adapterClass) {
            $user = $this->getMockBuilder(User::class)->disableOriginalConstructor()
                ->onlyMethods(['getAttribute', 'getKey'])->getMock();
            $user->expects($this->never())->method('getKey');
            $user->method('getAttribute')->willReturnCallback(
                static fn ($key) => $key === 'uuid' ? $uuid : null,
            );
            $context = (new $adapterClass)->map($user, ['password' => 'excluded'], ['id' => 42]);
            $this->assertSame('core.user', $context->subjectType);
            $this->assertSame($uuid, $context->subjectId);
            $this->assertSame($uuid, $context->data['user.uuid']);
            $this->assertArrayNotHasKey('user.id', $context->data);
            $this->assertArrayNotHasKey('password', $context->data);
        }
    }

    #[Test]
    public function settings_defaults_and_absolute_bounds_cannot_be_bypassed(): void
    {
        $schema = json_decode(file_get_contents(dirname(__DIR__, 2).'/config/settings/defaults.json'), true);
        $this->assertSame(WorkflowLimits::DEFAULTS, $schema['defaults']['limits']);
        $this->assertSame(WorkflowLimits::DEFAULTS, WorkflowLimits::normalize([]));
        $this->assertSame(WorkflowLimits::MAXIMUMS, WorkflowLimits::normalize(WorkflowLimits::MAXIMUMS));

        foreach (WorkflowLimits::DEFAULTS as $key => $default) {
            foreach ([null, '123', true, 1.5, [], new \stdClass, -1, WorkflowLimits::MAXIMUMS[$key] + 1] as $value) {
                $this->assertFalse(WorkflowLimits::accepts($key, $value));
                $this->assertSame($default, WorkflowLimits::normalize([$key => $value])[$key]);
                $this->assertSame($default, WorkflowLimits::normalize([], [$key => $value])[$key]);
            }
        }
        $this->assertFalse(WorkflowLimits::accepts('max_actions_per_workflow', 0));
        $this->assertFalse(WorkflowLimits::accepts('max_condition_depth', 0));
        $this->assertSame(0, WorkflowLimits::ABSOLUTE_MAX_RECURSION_DEPTH);
        $this->assertFalse(WorkflowLimits::accepts('unknown', 1));
    }

    #[Test]
    public function admin_menu_localization_preserves_identity_and_targets(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/module.php');

        $this->assertStringContainsString(
            "'name' => ['ko' => '워크플로 관리', 'en' => 'Glitter Workflow']",
            $source,
        );
        $this->assertStringContainsString(
            "'name' => ['ko' => '워크플로 관리', 'en' => 'Workflow Management']",
            $source,
        );
        $this->assertStringContainsString("'slug' => 'glitter-workflow'", $source);
        $this->assertStringContainsString("'slug' => 'glitter-workflow-workflows'", $source);
        $this->assertStringContainsString("'url' => '/admin/glitter-workflow/workflows'", $source);
        $this->assertStringContainsString("'permission' => 'glitter-workflow.workflows.read'", $source);
    }

    #[Test]
    public function settings_service_reads_lifecycle_category_files_with_safe_fallbacks(): void
    {
        foreach ([null, 'null', 'false', '"invalid"', '{', '{"max_actions_per_workflow":null}',
            '{"max_actions_per_workflow":101,"max_workflow_recursion_depth":1}'] as $content) {
            $storage = $this->createMock(StorageInterface::class);
            $storage->method('get')->with('settings', 'limits.json')->willReturn($content);
            $storage->expects($this->never())->method('put');
            $service = new WorkflowSettingsService($storage);
            $this->assertSame(['limits' => WorkflowLimits::DEFAULTS], $service->getAllSettings());
            $this->assertSame([], $service->getFrontendSettings());
            $this->assertSame('fallback', $service->getSetting('missing', 'fallback'));
            $this->assertSame(25, $service->getSetting('limits.max_actions_per_workflow', 999));
        }

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('get')->with('settings', 'limits.json')
            ->willReturn('{"max_actions_per_workflow":7,"max_condition_depth":2}');
        $service = new WorkflowSettingsService($storage);
        $this->assertSame(7, $service->getSetting('max_actions_per_workflow'));
        $this->assertSame(2, $service->getSettings('limits')['max_condition_depth']);
        $this->assertSame(0, $service->getSetting('limits.max_workflow_recursion_depth'));
        $this->assertSame([], $service->getSettings('unknown'));
    }

    #[Test]
    public function invalid_settings_writes_are_rejected_before_storage_access(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->never())->method('get');
        $storage->expects($this->never())->method('put');
        $service = new WorkflowSettingsService($storage);
        $this->assertTrue($service->saveSettings([]));
        foreach ([['limits' => null], ['unknown' => []], ['limits' => ['unknown' => 1]],
            ['limits' => ['max_actions_per_workflow' => null]],
            ['limits' => ['max_actions_per_workflow' => 101]],
            ['limits' => ['max_workflow_recursion_depth' => 1]]] as $settings) {
            $this->assertFalse($service->saveSettings($settings));
        }
        $this->assertFalse($service->setSetting('limits.max_condition_depth', '5'));
    }

    #[Test]
    public function partial_settings_write_preserves_normalized_omitted_fields_and_reports_failure(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('get')->with('settings', 'limits.json')
            ->willReturn('{"max_actions_per_workflow":7,"max_condition_depth":2,"max_workflow_recursion_depth":9}');
        $storage->expects($this->once())->method('put')->with(
            'settings',
            'limits.json',
            $this->callback(static fn ($content): bool => json_decode($content, true) === [
                'max_actions_per_workflow' => 8,
                'max_condition_depth' => 2,
                'max_workflow_recursion_depth' => 0,
            ]),
        )->willReturn(false);
        $service = new WorkflowSettingsService($storage);
        $this->assertFalse($service->setSetting('limits.max_actions_per_workflow', 8));
    }

    /** @param array<int, \BackedEnum> $cases */
    private function values(array $cases): array
    {
        return array_map(static fn (\BackedEnum $case): string => (string) $case->value, $cases);
    }
}
