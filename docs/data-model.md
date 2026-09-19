# Glitter Workflow 데이터 모델

## 모델

<!-- @generated:models START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 모델 | 테이블 | fillable | 관계 | 특성 |
|---|---|---|---|---|
| `HasWorkflowUuid` | (규약) | - | - | - |
| `Workflow` | `glitter_workflows` | 10 | creator→User, updater→User, executions→WorkflowExecution | - |
| `WorkflowAcceptance` | `glitter_workflow_acceptances` | 14 | executions→WorkflowExecution | - |
| `WorkflowActionExecution` | `glitter_workflow_action_executions` | 16 | execution→WorkflowExecution | - |
| `WorkflowExecution` | `glitter_workflow_executions` | 20 | acceptance→WorkflowAcceptance, workflow→Workflow, actionExecutions→WorkflowActionExecution | - |
<!-- @generated:models END -->

- `Workflow`: 사용자가 편집하는 정의. UUID를 공개 route key로 사용한다.
- `WorkflowExecution`: 한 트리거와 한 워크플로 버전에 대한 기계 실행 원장.
- `WorkflowActionExecution`: 실행 계획 내 순차 액션 하나의 상태 원장.

세 모델은 G7 `UniqueIdServiceInterface`로 UUID를 생성한다. JSON 필드는 array cast, 상태는 backed Enum cast, 시각은 datetime cast를 사용한다. 원시 Eloquent 모델은 JSON 필드에 저장하지 않는다.

## 소유 테이블

<!-- @generated:tables START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 테이블 | 모델 |
|---|---|
| `glitter_workflow_acceptances` | `WorkflowAcceptance` |
| `glitter_workflow_action_executions` | `WorkflowActionExecution` |
| `glitter_workflow_executions` | `WorkflowExecution` |
| `glitter_workflows` | `Workflow` |
<!-- @generated:tables END -->

### `glitter_workflows`

UUID, 이름, 안정 `trigger_key`, trigger config, 조건 트리, 순차 action plan, 정의 version, 활성 상태, 생성/수정 관리자와 timestamps를 가진다. `trigger_key + is_active`, `created_by`, `updated_by` 인덱스를 둔다.

### `glitter_workflow_executions`

삭제 가능한 workflow FK와 실행 시점 snapshot/version, trigger subject, SHA-256 deduplication key, redacted context, 상태/예약·시작·종료 시각/시도 횟수, correlation/causation/depth, 안전한 오류를 가진다. 워크플로 삭제 시 FK는 null이 되지만 snapshot과 원장은 유지된다.

`workflow_id + deduplication_key` portable unique index가 같은 워크플로 이벤트의 중복 생성을 줄인다. null 의미는 데이터베이스 표준 동작을 따른다. trigger/status/scheduled, workflow/created, correlation, causation, status/created 조회 인덱스를 둔다.

### `glitter_workflow_action_executions`

execution FK, action index/type/snapshot/config hash, 상태·시도·시각, 고유 idempotency key, redacted result metadata와 안전한 오류를 가진다. `execution_id + action_index`는 유일하며 부모 실행 삭제를 묵시적으로 cascade하지 않는다.

## 마이그레이션

<!-- @generated:migrations START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
마이그레이션 7개.

| 파일 | 생성 테이블 | 변경 테이블 | down() |
|---|---|---|---|
| `2026_09_18_000001_create_glitter_workflows_table.php` | `glitter_workflows` | `glitter_workflows` | ✅ |
| `2026_09_18_000002_create_glitter_workflow_executions_table.php` | `glitter_workflow_executions` | `glitter_workflow_executions` | ✅ |
| `2026_09_18_000003_create_glitter_workflow_action_executions_table.php` | `glitter_workflow_action_executions` | `glitter_workflow_action_executions` | ✅ |
| `2026_09_18_000010_create_glitter_workflow_acceptances_table.php` | `glitter_workflow_acceptances` | - | ✅ |
| `2026_09_18_000011_add_acceptance_id_to_glitter_workflow_executions.php` | - | `glitter_workflow_executions` | ✅ |
| `2026_09_18_000012_add_runtime_execution_deduplication_index.php` | - | `glitter_workflow_executions` | ✅ |
| `2026_09_18_000013_add_claim_token_to_glitter_workflow_action_executions.php` | - | `glitter_workflow_action_executions` | ✅ |
<!-- @generated:migrations END -->

세 설치 마이그레이션은 Laravel Schema builder를 사용하고 MySQL/MariaDB에서 동작하도록 database enum, partial index, generated column을 사용하지 않는다. FK 정의 전 comment 규칙과 한국어 column/table comment를 따른다. 이 감사에서는 운영 데이터베이스에 실행하지 않았다.

워크플로 정의 변경은 version을 증가시키고 실행에 전체 snapshot을 저장하는 것이 향후 서비스 책임이다. JSON 구조 검증과 오류 redaction/truncation도 저장 서비스에서 강제해야 한다.

## Enum

<!-- @generated:enums START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| Enum | backing | case 수 | case |
|---|---|---|---|
| `AcceptanceStatus` | `string` | 0 | - |
| `ActionExecutionStatus` | `string` | 7 | `pending`, `scheduled`, `running`, `succeeded`, `failed`, `cancelled`, `skipped` |
| `ConditionLogicalGroup` | `string` | 2 | `all`, `any` |
| `ConditionOperator` | `string` | 12 | `equals`, `not_equals`, `in`, `not_in`, `exists`, `not_exists`, `greater_than`, `greater_than_or_equal`, `외 4개` |
| `TriggerFieldType` | `string` | 4 | `string`, `integer`, `boolean`, `datetime` |
| `TriggerKey` | `string` | 4 | `user.registered`, `user.updated`, `board.post.created`, `board.comment.created` |
| `WorkflowActionType` | `string` | 3 | `notification`, `webhook`, `delay` |
| `WorkflowExecutionStatus` | `string` | 7 | `pending`, `scheduled`, `running`, `succeeded`, `failed`, `cancelled`, `skipped` |
<!-- @generated:enums END -->

- `TriggerKey`: 네 안정 키
- `TriggerFieldType`: string, integer, boolean, datetime
- `ConditionLogicalGroup`: all, any
- `ConditionOperator`: equals, not_equals, in, not_in, exists, not_exists, 수치 비교 4개, before, after
- `WorkflowActionType`: notification, webhook, delay. The enum remains the closed
  persisted vocabulary, but 0.9.1 executable values are only webhook and delay;
  notification is reserved/unavailable and rejected before activation.
- 실행/액션 상태: pending, scheduled, running, succeeded, failed, cancelled, skipped

DB 컬럼은 portable string이며 모델 cast가 backed Enum을 강제한다.

## Repository

<!-- @generated:repositories START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 클래스 | 종류 | 설명 |
|---|---|---|
| `ExecutionRepository` | 구현 | - |
| `ExecutionRepositoryInterface` | 인터페이스 | - |
| `WorkflowRepository` | 구현 | - |
| `WorkflowRepositoryInterface` | 인터페이스 | - |
<!-- @generated:repositories END -->

Foundation Slice에는 repository가 없다. Eloquent 모델만 정의하며 CRUD·claim·state transition 서비스는 다음 슬라이스에서 설계한다. 실행 claim에는 경쟁 상태와 at-least-once 재전달을 견디는 원자적 상태 전이가 필요하다.

## 멱등성과 상관관계

Source capture is best-effort. At-least-once processing attempts apply only to future durably accepted work under a verified persistent queue/retry contract; Foundation implements no such processing. See [delivery guarantees](delivery-guarantees.md). execution UUID, 워크플로별 deduplication key, action별 idempotency key, config hash를 보존하지만 외부 시스템의 멱등 지원 없이는 exactly-once를 보장하지 않는다. 최초 외부 트리거 실행은 `correlation_id = execution uuid`, `causation_id = null`, `depth = 0`이다.

## Trigger subject identity

For both `user.registered@1` and `user.updated@1`, execution `subject_type` is
`core.user` and `subject_id` is the public UUID, identical to `context.user.uuid`
(the DTO uses the flat data key `user.uuid`). It is never the internal User primary
key. The existing string subject column already supports UUIDs; no schema change,
data migration or production database access is required. Board subject types
and their public numeric post/comment IDs are unchanged.
