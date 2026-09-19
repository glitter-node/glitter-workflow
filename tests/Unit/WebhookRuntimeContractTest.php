<?php

namespace Modules\Glitter\Workflow\Tests\Unit;

use App\Contracts\Extension\StorageInterface;
use DateTimeImmutable;
use Modules\Glitter\Workflow\Enums\WorkflowActionType;
use Modules\Glitter\Workflow\Services\Runtime\ActionRunningRecoveryPolicy;
use Modules\Glitter\Workflow\Services\WorkflowSettingsService;
use PHPUnit\Framework\TestCase;

final class WebhookRuntimeContractTest extends TestCase
{
    public function test_webhook_uses_only_the_public_core_client_and_stable_delivery_identity(): void
    {
        $source = file_get_contents(__DIR__.'/../../src/Jobs/ProcessWorkflowExecutionJob.php');
        self::assertStringContainsString('SafeOutboundWebhookClientInterface', $source);
        self::assertStringContainsString('new SafeOutboundWebhookRequest($connection[\'url\'], $body, $action->idempotency_key)', $source);
        self::assertStringNotContainsString('Http::', $source);
        self::assertStringNotContainsString('curl_', $source);
        self::assertStringNotContainsString('dns_get_record', $source);
    }

    public function test_claim_then_http_then_claim_guard_contract_is_present(): void
    {
        $source = file_get_contents(__DIR__.'/../../src/Jobs/ProcessWorkflowExecutionJob.php');
        self::assertStringContainsString("'claim_token' => \$token", $source);
        self::assertStringContainsString("\$action->claim_token !== \$token", $source);
        self::assertStringContainsString('DB::transaction(function (): ?array', $source);
        self::assertStringContainsString('$client->send(', $source);
        self::assertStringContainsString("'status' => ActionExecutionStatus::Scheduled", $source);
    }

    public function test_retry_and_terminal_classification_contract_is_bounded(): void
    {
        $source = file_get_contents(__DIR__.'/../../src/Jobs/ProcessWorkflowExecutionJob.php');
        foreach (['MAX_ACTION_ATTEMPTS = 3', '408, 425, 429', "'http_3xx'", "'http_4xx'", "'http_5xx'", "'timeout'", "'transport_failure'", "'dns_failure'", "'tls_failure'", "'response_too_large'"] as $needle) self::assertStringContainsString($needle, $source);
    }

    public function test_connection_registry_and_payload_contract_are_closed(): void
    {
        $settings = file_get_contents(__DIR__.'/../../src/Services/WorkflowSettingsService.php');
        $job = file_get_contents(__DIR__.'/../../src/Jobs/ProcessWorkflowExecutionJob.php');
        self::assertStringContainsString("'connections.json'", $settings);
        self::assertStringContainsString("array_keys(\$connection) !== ['url']", $settings);
        self::assertStringContainsString('ksort($payload, SORT_STRING)', $job);
        self::assertStringContainsString('is_scalar($context[$triggerField])', $job);
        self::assertStringContainsString("['status_code' => \$status, 'response_bytes' => \$bytes]", $job);
    }

    public function test_connection_normalization_rejects_any_userinfo_and_keeps_plain_https(): void
    {
        $service = new WorkflowSettingsService($this->createMock(StorageInterface::class));
        $method = new \ReflectionMethod($service, 'normalizeConnections');

        foreach (['https://user@example.com/webhook', 'https://user:pass@example.com/webhook', 'https://:pass@example.com/webhook'] as $url) {
            self::assertSame([], $method->invoke($service, ['partner' => ['url' => $url]]));
        }
        self::assertSame(['partner' => ['url' => 'https://example.com/webhook']], $method->invoke($service, ['partner' => ['url' => 'https://example.com/webhook']]));
    }

    public function test_legacy_and_canonical_empty_connection_registries_read_as_empty_maps(): void
    {
        foreach (['[]', '{}'] as $connectionsJson) {
            $storage = $this->createMock(StorageInterface::class);
            $storage->method('get')->willReturnCallback(static fn (string $category, string $path): ?string => match ($path) {
                'limits.json' => null,
                'connections.json' => $connectionsJson,
                default => null,
            });

            $service = new WorkflowSettingsService($storage);
            self::assertSame([], $service->getSettings('connections'));
        }
    }

    public function test_connection_registry_encoder_keeps_the_root_as_a_json_object(): void
    {
        $service = new WorkflowSettingsService($this->createMock(StorageInterface::class));
        $method = new \ReflectionMethod($service, 'encodeConnections');
        $normalizer = new \ReflectionMethod($service, 'normalizeConnections');

        $empty = $method->invoke($service, []);
        self::assertSame('{}', $empty);
        self::assertInstanceOf(\stdClass::class, json_decode($empty, flags: JSON_THROW_ON_ERROR));

        $connections = ['partner-api' => ['url' => 'https://example.com/webhooks/g7']];
        self::assertSame($connections, $normalizer->invoke($service, $connections));
        $encoded = $method->invoke($service, $connections);
        self::assertInstanceOf(\stdClass::class, json_decode($encoded, flags: JSON_THROW_ON_ERROR));
        self::assertSame($connections, json_decode($encoded, true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_module_install_defaults_write_canonical_connections_without_coercing_limits(): void
    {
        $writes = [];
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('exists')->willReturn(false);
        $storage->method('put')->willReturnCallback(static function (string $category, string $path, mixed $content) use (&$writes): bool {
            $writes[$path] = $content;

            return true;
        });

        $service = new WorkflowSettingsService($storage);
        self::assertTrue($service->initializeDefaults());
        self::assertSame('{}', $writes['connections.json']);
        self::assertSame([
            'max_actions_per_workflow' => 25,
            'max_condition_depth' => 5,
            'max_workflow_recursion_depth' => 0,
        ], json_decode($writes['limits.json'], true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_update_normalization_targets_only_the_legacy_empty_array(): void
    {
        require_once __DIR__.'/../../upgrades/data/0.8.1/migrations/01_NormalizeLegacyEmptyConnectionsRegistry.php';
        $migration = new \App\Upgrades\Data\Ext\Modules\GlitterWorkflow\V0_8_1\Migrations\NormalizeLegacyEmptyConnectionsRegistry();
        $method = new \ReflectionMethod($migration, 'isLegacyEmptyArray');

        self::assertTrue($method->invoke($migration, '[]'));
        self::assertFalse($method->invoke($migration, '{}'));
        self::assertFalse($method->invoke($migration, '{"partner-api":{"url":"https://example.com/webhooks/g7"}}'));
        self::assertFalse($method->invoke($migration, 'not json'));
    }

    public function test_running_recovery_is_delay_specific_and_bounded(): void
    {
        $now = new DateTimeImmutable('2026-09-18T12:00:00+00:00');
        $fresh = $now->modify('-599 seconds');
        $stale = $now->modify('-601 seconds');

        self::assertSame(ActionRunningRecoveryPolicy::HOLD, ActionRunningRecoveryPolicy::decision(WorkflowActionType::Delay, $fresh, 1, $now));
        self::assertSame(ActionRunningRecoveryPolicy::RECLAIM, ActionRunningRecoveryPolicy::decision(WorkflowActionType::Delay, $stale, 2, $now));
        self::assertSame(ActionRunningRecoveryPolicy::EXHAUST, ActionRunningRecoveryPolicy::decision(WorkflowActionType::Delay, $stale, 3, $now));
        self::assertSame(ActionRunningRecoveryPolicy::HOLD, ActionRunningRecoveryPolicy::decision(WorkflowActionType::Webhook, $stale, 1, $now));
    }

    public function test_due_webhook_and_claim_completion_guards_remain_in_the_driver(): void
    {
        $source = file_get_contents(__DIR__.'/../../src/Jobs/ProcessWorkflowExecutionJob.php');
        self::assertStringContainsString("\$next->action_type->value !== 'webhook' || ! \$next->scheduled_at || \$next->scheduled_at->isFuture()", $source);
        self::assertStringContainsString("'status' => ActionExecutionStatus::Pending", $source);
        self::assertStringContainsString("\$action->status !== ActionExecutionStatus::Running || \$action->claim_token !== \$token", $source);
    }

    public function test_recovery_uses_the_same_running_policy_and_never_reclaims_webhooks(): void
    {
        $source = file_get_contents(__DIR__.'/../../src/Services/WorkflowRecoveryService.php');

        self::assertStringContainsString('ActionRunningRecoveryPolicy::decision($next->action_type', $source);
        self::assertStringContainsString('ActionRunningRecoveryPolicy::HOLD', $source);
        self::assertStringContainsString("if(\$d->action_type->value==='webhook')continue", $source);
        self::assertStringNotContainsString("\$next->action_type->value==='notification'", $source);
        self::assertStringContainsString("\$next->action_type->isExecutable()&&\$next->status===ActionExecutionStatus::Running", $source);
    }
}
