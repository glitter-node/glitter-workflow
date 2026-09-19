# Delivery guarantees and source failure policy

Runtime Slice 3, as corrected in 0.9.1, registers four synchronous source-capture listeners. This document separates best-effort source capture from committed Workflow acceptance and its existing asynchronous processing.

## Three distinct boundaries

1. **Source capture / handoff is best-effort.** The source service writes its
   state and invokes a G7 Action. A minimal synchronous Workflow listener
   validates/redacts the arguments into a scalar TriggerContext and persists a
   Workflow acceptance. A process crash, earlier listener failure, mapping failure or queue
   persistence failure can lose the event after the source has committed. There
   is no source replay cursor, acknowledgement protocol or transactional outbox
   in this source-capture contract. No source-to-execution at-least-once or exactly-once claim
   is valid.
2. **Durable acceptance begins only when safe workflow work is persisted and its
   owning transaction commits in a verified durable backend.** A dispatch call,
   in-memory callback, pending after-commit callback, sync queue or uncommitted
   queue row is not this boundary. Persistence failures before this point are
   capture failures, not failures of an already accepted execution. There may be
   no execution record available to retry.
3. **Execution/action processing follows acceptance.** Only accepted durable work,
   with an operational worker, acknowledgement and retry/redelivery policy, can
   support at-least-once processing attempts. Attempts may duplicate; finite
   retry limits may end in failure. This does not guarantee successful actions,
   notification delivery or exactly-once external effects.
   Deduplication/idempotency fields prepare for this requirement but do not
   establish a delivery guarantee by themselves.

## Preserve synchronous redaction

The implemented capture boundary is:

```text
G7 source Action
  -> minimal Workflow listener (sync => true)
  -> TriggerContext mapping / redaction
  -> safe scalar TriggerContext DTO
  -> committed Workflow acceptance row
  -> Workflow-owned after-commit job
```

`app/Extension/HookListenerRegistrar.php::applySubscribedHooks()` directly registers an
Action callback for `sync => true`. The default queued path wraps raw arguments
in `DispatchHookListenerJob`, which must not receive registration tokens, user
update request/snapshot data, board secrets or raw models for Workflow capture.
Do not switch the first listener to that generic asynchronous path.

`HookManager::doAction()` executes callbacks in priority order with `try/finally`
cleanup, without catching listener exceptions. A failure also prevents later
callbacks from running. Redaction and safe queue handoff are the only intended
synchronous Workflow work; no matching/planning, network action or executor
belongs there. Raw hook arguments must never be serialized to repair a delivery
failure.

## Source-specific behavior verified from current code

The transaction statements below describe service-owned transactions. A caller
may supply an outer transaction: none of these Actions alone guarantees that
all ambient transactions have committed. Future handoff must defer acceptance
appropriately and must not run actions against rolled-back source data.

| Action | Exact source point | Uncaught synchronous listener failure |
|---|---|---|
| `core.auth.after_register` | `app/Services/AuthService.php::register()`, after `DB::transaction()` has created the user, consents, roles and auth token, and after permission cache flushing | No local catch surrounds the Action. The failure propagates and interrupts the response path; already committed registration/token data remains. No automatic Workflow capture retry exists. |
| `core.user.after_update` | `app/Services/UserService.php::updateUser()`, after its transaction updates profile, token/role state and optional withdrawal writes; after role cache flushing | The surrounding `catch (Exception)` rethrows `ValidationException`, `CannotDeleteSuperAdminException` and `PermissionEscalationException`; other Exceptions are logged and wrapped as a generic `ValidationException`. `Error`/other non-Exception Throwables are not caught. Later after-effects and the fresh-user return are skipped. Already committed changes remain. |
| `sirsoft-board.post.after_create` | `modules/_bundled/sirsoft-board/src/Services/PostService.php::createPost()`, after post insertion, attachment linking and `DB::commit()` | A local `catch (\Exception)` logs the hook failure and continues to cache invalidation and return of the created post. It does not catch `Error`/non-Exception Throwables. Committed post creation is not rolled back; capture can be lost without a failed creation response. |
| `sirsoft-board.comment.after_create` | `modules/_bundled/sirsoft-board/src/Services/CommentService.php::createComment()`, immediately after `CommentRepository::create()` and before `invalidateStatsCache()` | No local catch or service-owned transaction. The repository uses `Comment::create($data)`. Failure propagates and skips the statistics-cache invalidation/normal return. Under ordinary autocommit the comment remains; with an outer transaction its caller decides rollback/commit. This is not a universal after-commit hook. |

Sources above were inspected read-only. Comments in source describing intent do
not override the actual exception types or transaction placement.

Runtime Slice 3 keeps source-native exception semantics: capture listeners do not catch
mapping or persistence failures. Auth registration and comment creation can return an
error after a source write; user update may translate the error; board post creation
logs an Exception and returns its committed post. No pre-acceptance retry queue or
module-owned failure log is created. A failed capture must not be reported as accepted.

## Missing durable source-capture contract

The four existing after Actions do not provide atomic source-write plus event
persistence. The inspected before Actions and create-data Filters are also
insufficient: registration/update hooks run before the final write state,
post before/filter hooks run before insertion and attachment completion, and
comment before/filter hooks run before insertion/final depth determination.
They cannot expose the finalized subject/change event at a reusable atomic
completion boundary. Starting transactions in listeners, changing raw write
payloads or intercepting protected internals is not a supported correction.

A future reusable **synchronous transactional Action contract**, or an upstream
transactional event/outbox API with equivalent guarantees, would need:

| Source | Minimum execution point required upstream |
|---|---|
| Auth registration | Inside `AuthService::register()`'s transaction after user/consent/role/token writes have succeeded and before returning from its transaction closure. |
| User update | Inside `UserService::updateUser()`'s transaction after all profile/token/role/withdrawal writes succeed, before the closure returns. |
| Board post | Inside `PostService::createPost()` after post creation and attachment linking, immediately before its commit. |
| Board comment | An upstream transaction enclosing `CommentRepository::create()` and a completion point after insert/final depth assignment but before that same transaction commits. The current service does not supply this transaction. |

The contract must expose the source kind, stable event identity (distinct updates
of the same subject must be distinguishable), finalized public subject identity,
event/schema version, occurrence time, board slug where applicable, and the
minimal allowlisted scalar fields needed by the four adapters. It must identify
the owning connection/transaction and document nested-transaction semantics,
rollback behavior and failure propagation. It need not expose raw request data,
auth tokens, passwords, content or other unnecessary personal information.

Glitter Workflow would synchronously validate/redact that context and append
module-owned outbox work on the **same connection and transaction**, without
network calls. Source mutation and outbox insertion must commit or roll back
together; errors cannot be swallowed while claiming atomic capture. A later
relay could transfer committed safe records into durable queue work with stable
IDs and retries. An upstream-owned outbox with a supported consumer/replay API
could provide an equivalent boundary.

These completion/outbox contracts are requirements, not existing hook names or
APIs. None is introduced or claimed to exist here. No outbox, protected-module
patch or listener workaround is implemented. Stronger capture guarantees require
upstream support and a separate reviewed runtime slice.

## Runtime Slice 1A

`board.post.created` is now captured synchronously from
`sirsoft-board.post.after_create`, redacted to `board.post.created@1`, and
persisted as a pending `glitter_workflow_acceptances` row. This acceptance
transaction occurs after the board transaction has committed and is not atomic
with it. Capture remains best effort until acceptance commit. Queue processing,
matching, execution planning, and action execution are not implemented. The
other three built-in triggers were completed by Runtime Slice 3.

## Runtime Slice 1B

Committed acceptances are processed with bounded, atomic claims and stale-claim
recovery. Active `board.post.created` definitions are evaluated strictly and
fan out to immutable workflow execution and pending action-plan rows. Planning
is redelivery-safe through the acceptance/workflow/version uniqueness key.
Actions themselves remain unexecuted.

## Operational recovery

`glitter-workflow:recover` replays bounded queue handoffs from durable state.
It supports `--dry-run` and `--limit` (maximum 200 per category), emits only
counts, and never executes actions directly. Pending acceptances, eligible
executions, and due scheduled delays are redispatched with UUID-only jobs.
Stale-running Webhook actions are excluded from recovery. A legacy unavailable
Notification action may be redispatched only so the runtime can terminal-fail
its action and execution with `action_unavailable`; it is never delivered.
Due scheduled Webhook retries are redispatched through the bounded action state
machine; recovery can duplicate publication;
atomic job state remains authoritative and no exactly-once queue guarantee is
claimed.

## Webhook action (0.8.0)

Webhook은 Core `SafeOutboundWebhookClientInterface`만 사용한다. workflow action에는 `connection_key`와 `body_bindings`만 영속화되며, endpoint URL은 모듈 settings의 비공개 connection registry에서 실행 시점에 해석한다.

Delay와 Webhook의 stale-running 처리는 의도적으로 다르다. Delay는 10분 stale threshold 후 기존 bounded reclaim 경로를 사용하지만, Webhook은 원격 receipt가 unknown이므로 fresh/stale 모두 자동 reclaim 또는 resend하지 않는다.

각 claimed action attempt는 Core HTTP 요청을 정확히 한 번 수행한다. 기존 `idempotency_key`가 항상 `X-G7-Delivery-Id`가 되며 재시도에서도 바뀌지 않는다. 2xx는 성공이고, 3xx와 일반 4xx 및 TLS/목적지 정책 오류는 terminal failure다. 408/425/429, 5xx, timeout, transport failure, DNS failure는 최대 세 번까지 60초 단위 bounded backoff로 재시도한다.

원격 수신 뒤 local success commit 전에 worker가 중단될 수 있으므로 exactly-once는 보장하지 않는다. 같은 delivery ID의 중복 원격 수신은 가능하며 수신자가 deduplicate해야 한다. stale-running Webhook은 receipt가 unknown이므로 자동 reclaim/re-send하지 않는다. recovery command는 due retry와 pending action만 재dispatch하며 scheduler는 제공하지 않는다.

결과에는 `status_code`와 `response_bytes`만 저장한다. request body, response body, response headers, URL, credentials와 exception detail은 result/error history에 저장하지 않는다.


## Runtime Slice 3: Trigger Capture Completion (0.9.1)

The module now registers exactly four source capture listeners through
Module::getHookListeners(). Each declares method=capture, priority=30 and
sync=true for its approved G7 Action:

| Stable schema | Source Action | Stored data keys |
|---|---|---|
| user.registered@1 | core.auth.after_register | user.uuid, user.status, user.language, user.country, user.created_at |
| user.updated@1 | core.user.after_update | user.uuid, user.status, user.language, user.country, user.updated_at |
| board.post.created@1 | sirsoft-board.post.after_create | post.id, post.board_id, post.board_slug, post.actor_user_id, post.category, post.is_notice, post.is_secret, post.status, post.parent_id, post.depth, post.created_at |
| board.comment.created@1 | sirsoft-board.comment.after_create | comment.id, comment.board_id, comment.board_slug, comment.post_id, comment.actor_user_id, comment.parent_id, comment.is_secret, comment.status, comment.depth, comment.created_at |

The user registration context (verification_token, registration_data, IP,
user agent and any nested input), user-update request and prior snapshot, and
board post options are discarded before persistence. Post/comment content is
not copied. Acceptance validates the closed key, schema, subject identity,
exact field set and scalar/null field types before its DB transaction.

HookListenerRegistrar sends these sync=true Actions directly to HookManager.
Raw hook arguments are never serialized into App\Jobs\DispatchHookListenerJob.
Acceptance creation uses the existing glitter_workflow_acceptances table and
schedules ProcessWorkflowAcceptanceJob with afterCommit. A returned row inside
an ambient outer transaction is not committed acceptance until that outer
transaction commits. Once committed, Workflow owns the asynchronous
processing and recovery boundary.

The existing acceptance contract does not supply a stable source event ID:
source_event_id remains null. In 0.9.1, a successful source-hook invocation
first performs one indexed existence query for an enabled workflow with the
same stable TriggerKey. No matching enabled workflow means no acceptance row
and no processing job. When interest exists, each successful source-hook
invocation creates a new acceptance row. Processing retries are deduplicated by the
existing acceptance/workflow/version key; source-hook re-emission is not
deduplicated. Source operation to committed acceptance is neither exactly-once
nor guaranteed at-least-once. A crash, earlier listener failure, mapper error,
DB error or source-specific swallowed exception can lose capture. Stronger
atomic source-event/outbox semantics require separate upstream source contracts;
Gnuboard7 issue #139 addresses generic async listener QoS, not that boundary.

The four source services retain the distinct failure and transaction positions
shown above. Workflow does not catch capture exceptions or claim that a
committed source operation was rolled back after a capture failure.

## Capture-time interest gate (0.9.1)

The module-owned interest gate uses `TriggerKey`, never a raw G7 hook name. It
executes `exists()` with `trigger_key = <stable key>` and `is_active = true`,
backed by the existing `gw_trigger_active_idx (trigger_key, is_active)` index.
It does not load action plans, iterate workflows, evaluate conditions, plan
actions, dispatch external work, or perform network I/O. No cache is used, so
there is no stale-negative cache window or invalidation contract.

This is a best-effort capture optimization based on the enabled-workflow state
observed at capture time, not a locking or delivery guarantee:

- If the query observes interest and that workflow is deactivated before
  processing, an acceptance may be created unnecessarily. The processing job's
  authoritative enabled-workflow query prevents execution of the inactive
  workflow and marks the acceptance processed.
- If the query observes no interest and a workflow activates immediately
  afterward, that source event is not captured.
- If workflows are added, removed, activated, deactivated, or moved between
  triggers after acceptance, processing uses the authoritative state it sees at
  planning time. One source event still creates at most one acceptance, not one
  acceptance per interested workflow.

No distributed lock, source replay cursor, exactly-once, or source-to-acceptance
at-least-once guarantee is introduced. Once created and committed, acceptance
remains the Workflow-owned asynchronous boundary.

## Executable action availability (0.9.1)

`WorkflowActionType` is the authoritative availability policy. `delay` and
`webhook` are executable. `notification` is reserved and unavailable: create,
update, and activation validation reject it. If legacy or directly imported
data nevertheless reaches the runtime, the existing terminal failure path marks
both action and execution failed with `action_unavailable`. It does not dispatch
a Notification. Notification remains unavailable until a verified G7
recipient, authorization, and idempotent-delivery contract exists.
