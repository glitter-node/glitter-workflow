<?php

namespace Modules\Glitter\Workflow\Jobs;

use App\Contracts\Outbound\SafeOutboundWebhookClientInterface;
use App\Exceptions\Outbound\SafeOutboundWebhookException;
use App\Outbound\DTO\SafeOutboundWebhookRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Glitter\Workflow\Contracts\TriggerDefinitionRegistryInterface;
use Modules\Glitter\Workflow\Enums\{ActionExecutionStatus, WorkflowExecutionStatus};
use Modules\Glitter\Workflow\Models\{WorkflowActionExecution, WorkflowExecution};
use Modules\Glitter\Workflow\Services\Runtime\ActionRunningRecoveryPolicy;
use Modules\Glitter\Workflow\Services\WorkflowSettingsService;

final class ProcessWorkflowExecutionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private const MAX_ACTION_ATTEMPTS = 3;
    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public readonly string $executionUuid) { $this->afterCommit = true; }

    public function handle(SafeOutboundWebhookClientInterface $client, WorkflowSettingsService $settings, TriggerDefinitionRegistryInterface $triggers): void
    {
        $claim = DB::transaction(function (): ?array {
            $execution = WorkflowExecution::query()->where('uuid', $this->executionUuid)->lockForUpdate()->first();
            if (! $execution || in_array($execution->status, [WorkflowExecutionStatus::Succeeded, WorkflowExecutionStatus::Failed, WorkflowExecutionStatus::Cancelled], true)) return null;
            $actions = $execution->actionExecutions()->lockForUpdate()->orderBy('action_index')->get();
            $next = $actions->first(fn (WorkflowActionExecution $action) => $action->status !== ActionExecutionStatus::Succeeded);
            if (! $next) { $execution->update(['status' => WorkflowExecutionStatus::Succeeded, 'finished_at' => now()]); return null; }
            if ($next->status === ActionExecutionStatus::Failed) { $execution->update(['status' => WorkflowExecutionStatus::Failed, 'finished_at' => now()]); return null; }
            if (! $next->action_type->isExecutable()) {
                $this->markTerminal($next, 'action_unavailable');
                return null;
            }
            if ($next->status === ActionExecutionStatus::Running) {
                $decision = ActionRunningRecoveryPolicy::decision($next->action_type, $next->started_at, $next->attempt_count, now());
                if ($decision === ActionRunningRecoveryPolicy::HOLD) return null; // Webhook receipt is unknown; never steal a claim.
                if ($decision === ActionRunningRecoveryPolicy::EXHAUST) {
                    $next->update(['status' => ActionExecutionStatus::Failed, 'error_code' => 'attempts_exhausted', 'error_message' => 'Action attempts exhausted.', 'finished_at' => now()]);
                    $execution->update(['status' => WorkflowExecutionStatus::Failed, 'finished_at' => now()]);
                    return null;
                }
                $next->update(['status' => ActionExecutionStatus::Pending]);
                $next->refresh();
            }
            if ($next->status === ActionExecutionStatus::Scheduled) {
                if ($next->action_type->value !== 'webhook' || ! $next->scheduled_at || $next->scheduled_at->isFuture()) return null;
                $next->update(['status' => ActionExecutionStatus::Pending]);
                $next->refresh();
            }
            if ($next->status !== ActionExecutionStatus::Pending) return null;
            if ($next->action_type->value === 'delay') {
                $next->update(['status' => ActionExecutionStatus::Running, 'attempt_count' => $next->attempt_count + 1, 'started_at' => now()]);
                $execution->update(['status' => WorkflowExecutionStatus::Running]);
                return ['kind' => 'delay', 'action_uuid' => $next->uuid];
            }
            if ($next->action_type->value !== 'webhook') return null;
            if ($next->attempt_count >= self::MAX_ACTION_ATTEMPTS) {
                $next->update(['status' => ActionExecutionStatus::Failed, 'error_code' => 'attempts_exhausted', 'error_message' => 'Webhook attempts exhausted.', 'finished_at' => now()]);
                $execution->update(['status' => WorkflowExecutionStatus::Failed, 'finished_at' => now()]);
                return null;
            }
            $token = (string) Str::uuid();
            $next->update(['status' => ActionExecutionStatus::Running, 'attempt_count' => $next->attempt_count + 1, 'claim_token' => $token, 'started_at' => now(), 'scheduled_at' => null]);
            $execution->update(['status' => WorkflowExecutionStatus::Running]);
            return ['kind' => 'webhook', 'action_uuid' => $next->uuid, 'claim_token' => $token];
        });

        if ($claim === null) return;
        if ($claim['kind'] === 'delay') { $this->scheduleDelay($claim['action_uuid']); return; }
        $this->dispatchWebhook($claim['action_uuid'], $claim['claim_token'], $client, $settings, $triggers);
    }

    private function scheduleDelay(string $actionUuid): void
    {
        $action = WorkflowActionExecution::query()->where('uuid', $actionUuid)->first();
        $seconds = (int) ($action?->action_snapshot['config']['delay_seconds'] ?? 0);
        if ($action === null || $seconds < 1) { $this->finishTerminal($actionUuid, null, 'invalid_delay'); return; }
        $action->update(['status' => ActionExecutionStatus::Scheduled, 'scheduled_at' => now()->addSeconds($seconds)]);
        ProcessWorkflowDelayResumeJob::dispatch($action->uuid)->delay($action->scheduled_at);
    }

    private function dispatchWebhook(string $actionUuid, string $claimToken, SafeOutboundWebhookClientInterface $client, WorkflowSettingsService $settings, TriggerDefinitionRegistryInterface $triggers): void
    {
        $action = WorkflowActionExecution::query()->with('execution')->where('uuid', $actionUuid)->first();
        if (! $action || $action->claim_token !== $claimToken || ! $action->execution) return;
        try {
            $connection = $settings->webhookConnection((string) ($action->action_snapshot['config']['connection_key'] ?? ''));
            if ($connection === null) { $this->finishTerminal($actionUuid, $claimToken, 'connection_missing'); return; }
            $body = $this->payload($action, $triggers);
            $response = $client->send(new SafeOutboundWebhookRequest($connection['url'], $body, $action->idempotency_key));
            $this->finishHttp($actionUuid, $claimToken, $response->statusCode, $response->responseBytes);
        } catch (SafeOutboundWebhookException $exception) {
            $this->finishCoreFailure($actionUuid, $claimToken, $exception->classification);
        } catch (\JsonException) {
            $this->finishTerminal($actionUuid, $claimToken, 'payload_invalid');
        } catch (\Throwable) {
            $this->finishRetryable($actionUuid, $claimToken, 'transport_failure');
        }
    }

    private function payload(WorkflowActionExecution $action, TriggerDefinitionRegistryInterface $triggers): string
    {
        $config = $action->action_snapshot['config'] ?? [];
        $bindings = is_array($config['body_bindings'] ?? null) ? $config['body_bindings'] : throw new \JsonException('Invalid bindings');
        $definition = $triggers->find($action->execution->trigger_key);
        $allowed = $definition === null ? [] : array_fill_keys(array_map(fn ($field) => $field->key, $definition->fields), true);
        $context = $action->execution->trigger_context['data'] ?? [];
        $payload = [];
        foreach ($bindings as $requestField => $triggerField) {
            if (! is_string($requestField) || ! is_string($triggerField) || ! isset($allowed[$triggerField]) || ! array_key_exists($triggerField, $context) || (! is_scalar($context[$triggerField]) && $context[$triggerField] !== null)) throw new \JsonException('Invalid binding');
            $payload[$requestField] = $context[$triggerField];
        }
        ksort($payload, SORT_STRING);
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function finishHttp(string $uuid, string $token, int $status, int $bytes): void
    {
        if ($status >= 200 && $status < 300) { $this->finishSuccess($uuid, $token, ['status_code' => $status, 'response_bytes' => $bytes]); return; }
        if ($status >= 300 && $status < 400) { $this->finishTerminal($uuid, $token, 'http_3xx'); return; }
        if ($status >= 400 && $status < 500 && ! in_array($status, [408, 425, 429], true)) { $this->finishTerminal($uuid, $token, 'http_4xx'); return; }
        $this->finishRetryable($uuid, $token, $status >= 500 ? 'http_5xx' : 'http_4xx');
    }

    private function finishCoreFailure(string $uuid, string $token, string $code): void
    {
        if (in_array($code, ['timeout', 'transport_failure', 'dns_failure'], true)) { $this->finishRetryable($uuid, $token, $code); return; }
        if (in_array($code, ['tls_failure', 'invalid_target', 'prohibited_destination', 'proxy_not_supported', 'response_too_large'], true)) { $this->finishTerminal($uuid, $token, $code); return; }
        $this->finishTerminal($uuid, $token, $code);
    }

    private function finishSuccess(string $uuid, string $token, array $result): void
    {
        $executionUuid = DB::transaction(function () use ($uuid, $token, $result): ?string {
            $action = WorkflowActionExecution::query()->where('uuid', $uuid)->lockForUpdate()->first();
            if (! $action || $action->status !== ActionExecutionStatus::Running || $action->claim_token !== $token) return null;
            $action->update(['status' => ActionExecutionStatus::Succeeded, 'claim_token' => null, 'finished_at' => now(), 'result_metadata' => $result, 'error_code' => null, 'error_message' => null]);
            return $action->execution->uuid;
        });
        if ($executionUuid !== null) self::dispatch($executionUuid)->afterCommit();
    }

    private function finishTerminal(string $uuid, ?string $token, string $code): void
    {
        DB::transaction(function () use ($uuid, $token, $code): void {
            $action = WorkflowActionExecution::query()->with('execution')->where('uuid', $uuid)->lockForUpdate()->first();
            if (! $action || ($token !== null && $action->claim_token !== $token)) return;
            $this->markTerminal($action, $code);
        });
    }

    private function markTerminal(WorkflowActionExecution $action, string $code): void
    {
        $action->update(['status' => ActionExecutionStatus::Failed, 'claim_token' => null, 'finished_at' => now(), 'error_code' => $code, 'error_message' => $code]);
        $action->execution->update(['status' => WorkflowExecutionStatus::Failed, 'finished_at' => now(), 'error_code' => $code, 'error_message' => $code]);
    }

    private function finishRetryable(string $uuid, string $token, string $code): void
    {
        $retry = DB::transaction(function () use ($uuid, $token, $code): ?array {
            $action = WorkflowActionExecution::query()->with('execution')->where('uuid', $uuid)->lockForUpdate()->first();
            if (! $action || $action->status !== ActionExecutionStatus::Running || $action->claim_token !== $token) return null;
            if ($action->attempt_count >= self::MAX_ACTION_ATTEMPTS) { $action->update(['status' => ActionExecutionStatus::Failed, 'claim_token' => null, 'finished_at' => now(), 'error_code' => 'attempts_exhausted', 'error_message' => 'attempts_exhausted']); $action->execution->update(['status' => WorkflowExecutionStatus::Failed, 'finished_at' => now(), 'error_code' => 'attempts_exhausted', 'error_message' => 'attempts_exhausted']); return null; }
            $when = now()->addSeconds(60 * $action->attempt_count);
            $action->update(['status' => ActionExecutionStatus::Scheduled, 'claim_token' => null, 'scheduled_at' => $when, 'error_code' => $code, 'error_message' => $code]);
            return ['execution_uuid' => $action->execution->uuid, 'when' => $when];
        });
        if ($retry !== null) self::dispatch($retry['execution_uuid'])->delay($retry['when']);
    }
}
