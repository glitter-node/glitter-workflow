# Glitter Workflow 프런트엔드

## 레이아웃

<!-- @generated:layouts START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
레이아웃 9개 (루트: `resources/layouts`).

| 그룹 | 개수 |
|---|---|
| `(root)` | 2개 |
| `admin` | 7개 |

| 레이아웃 | 그룹 | 종류 | extends |
|---|---|---|---|
| `admin_execution_detail` | `admin` | 화면 | `_admin_base` |
| `admin_execution_list` | `admin` | 화면 | `_admin_base` |
| `admin_workflow_create` | `admin` | 화면 | `_admin_base` |
| `admin_workflow_detail` | `admin` | 화면 | `_admin_base` |
| `admin_workflow_edit` | `admin` | 화면 | `_admin_base` |
| `admin_workflow_list` | `admin` | 화면 | `_admin_base` |
| `admin_workflow_settings` | `admin` | 화면 | `_admin_base` |
| `workflow_editor` | `(root)` | 화면 | - |
| `workflow_list` | `(root)` | 화면 | - |
<!-- @generated:layouts END -->

다음 7개 관리자 JSON 레이아웃이 `_admin_base`를 상속한다.

| 레이아웃 | 목적 | 권한 |
|---|---|---|
| `admin_workflow_list` | 정의 목록, 빈 상태, 생성 CTA, 서버 페이지네이션 | workflows.read |
| `admin_workflow_create` | 생성 셸 | workflows.create |
| `admin_workflow_detail` | 상세 셸 | workflows.read |
| `admin_workflow_edit` | 수정 셸 | workflows.update |
| `admin_execution_list` | 실행 UUID·생성 시각·상태·트리거 목록, 빈 상태, 서버 페이지네이션 | executions.read |
| `admin_execution_detail` | 실행 상세 셸 | executions.read |
| `admin_workflow_settings` | 설정 셸 | settings.read |

일곱 관리자 레이아웃과 관리자 라우트의 표시 문구는 `$t:glitter-workflow.admin.*` 키를 사용한다. G7은 활성 모듈의 `resources/lang/{locale}.json`을 모듈 식별자 아래에 병합한다. 한·영 키와 표시 필드의 번역 참조는 `node scripts/check-admin-layout-i18n.mjs`로 검사한다. 생성·수정 셸의 트리거·조건·액션 선택값은 번역 라벨과 별개로 기존 API 값을 유지한다. Notification은 선택지에 노출하지 않는다.

`admin_workflow_list`는 `GET /api/modules/glitter-workflow/admin/workflows`를 자동 조회한다. `status`(active/inactive), `trigger_key`(기존 네 안정 키), `search`(워크플로 이름 또는 public UUID) query를 서버에서 허용하며, 응답의 `data.data`를 Name/Trigger/Status 열에 표시한다. `data.pagination`의 `current_page`, `per_page`, `last_page`, `has_more_pages`를 DataGrid 서버 페이지네이션에 연결한다. 검색과 필터 변경은 page를 1로 초기화하고 기존 query를 병합해 같은 목록 경로를 replace 탐색한다. 행 클릭은 내부 ID가 아닌 `uuid`로 기존 상세 경로를 연다.

`admin_execution_list`는 `GET /api/modules/glitter-workflow/admin/executions`를 자동 조회한다. 실행 상태 enum과 기존 네 가지 `trigger_key`를 서버에서 검증하며, 두 필터를 함께 선택하면 AND 조건으로 적용한다. DataGrid에는 API의 safe `uuid` 전체 값과 `created_at`을 각각 실행 UUID·생성 시각으로 표시하고, 기존 Workflow/Trigger/Status/Started/Completed 열을 유지한다. `created_at`은 API가 제공하는 기존 사용자 시간대 형식을 그대로 소비하며 레이아웃에서 별도 변환하지 않는다. 필터 변경은 page를 1로 초기화하고 기존 query를 병합하며, DataGrid 페이지 이동에서는 선택한 필터를 보존한다. 실행 데이터가 없거나 필터 결과가 비어도 번역된 EmptyState를 표시한다.

`admin_execution_detail`은 기존 safe projection만 사용해 실행의 Workflow UUID·버전, correlation/causation ID, depth, 예약·생성·수정 시각, error code를 별도 trace metadata 행으로 표시한다. Action execution에는 기존 `scheduled_at`·`error_code`·delay action의 `delay_seconds`를, acceptance에는 UUID·source key·available/claimed/processed 시각·error code를 추가 표시한다. 모든 시각은 API의 기존 사용자 시간대 형식을 그대로 사용하며, raw context/snapshot/config/result metadata, payload, credential, exception 또는 stack trace는 표시하지 않는다. 값이 없는 nullable metadata는 빈 값으로 유지하고, 기존 UUID/workflow/trigger/status/lifecycle, action, acceptance 상태 표시와 read-only 상세 화면 동작을 보존한다.

`admin_workflow_create`는 G7 `apiCall`로 `name`, `trigger_key`, `trigger_config`, `condition_tree`, `action_plan`만 POST하고 성공 응답의 `data.uuid`로 상세 화면으로 이동한다. `admin_workflow_edit`는 UUID로 정의를 조회하고 현재 `version`을 포함한 동일한 다섯 정의 필드만 PUT한다. `is_active`는 전송하지 않으며 409 stale 응답에서는 로컬 입력을 유지하고 자동 재전송하지 않는다. `admin_workflow_detail`은 resource-level `abilities`와 `is_active` 상태에 따라 수정·삭제·활성화·비활성화 액션을 표시하고, 삭제 확인·성공 refetch/이동·오류 토스트를 사용한다.

`WorkflowResource`는 현재 로케일의 `name`, `status`, `metadata`, 중첩 `condition_summary`, 순서를 보존한 `action_summary`를 제공한다. 조건 요약은 all/any 그룹과 predicate의 order/field/operator/value를 유지한다. 액션 요약은 delay의 index/type/delay_seconds와 webhook의 index/type/connection_key/body binding key만 제공하며 URL·headers·body 값·secret·credential은 응답하지 않는다. `abilities`에는 `can_update`, `can_delete`, `can_activate`가 포함된다.

Collection 응답의 `data.abilities.can_create`가 `true`일 때만 PageHeader와 빈 상태에 생성 CTA가 나타난다. 성공한 빈 결과는 번역된 EmptyState로 표시하고, 403과 그 밖의 API 오류는 `showErrorPage`로 content를 교체하므로 빈 결과와 구분된다. 최초 조회는 transition overlay와 progressive loading을 사용한다.

목록 API의 성공 응답 계약은 다음과 같다. 각 항목은 `WorkflowResource`의 `uuid`, `name`, `trigger_key`, `trigger_config`, `condition_tree`, `action_plan`, `version`, `is_active`, 로드된 `creator`/`updater`, `created_at`, `updated_at`을 유지하며 내부 숫자 ID를 노출하지 않는다.

```json
{
  "success": true,
  "message": "...",
  "data": {
    "data": [],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "from": null,
      "to": null,
      "has_more_pages": false,
      "last_page": 1,
      "total": 0
    },
    "abilities": {
      "can_create": true
    }
  }
}
```

## 액션 핸들러

<!-- @generated:handlers START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
_등록하는 액션 핸들러가 없습니다._
<!-- @generated:handlers END -->

모듈 전용 프런트엔드 handler는 없다. 관리자 Workflow 화면은 G7 기본 `Form`, `PageHeader`, `EmptyState`, `DataGrid`와 `navigate`/`apiCall`/`refetchDataSource`/`showErrorPage` handler만 사용하며 execution retry/cancel/history UI를 노출하지 않는다.

## 전역 진입점

<!-- @generated:frontend-entry START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
_프론트 엔트리포인트가 없습니다._
<!-- @generated:frontend-entry END -->

`globalThis.GlitterWorkflow` 이름은 향후 비특권 identity/capability 네임스페이스로 예약한다. 현재 프런트엔드 JavaScript가 필요하지 않아 전역 객체를 만들지 않는다.

향후 전역이 추가되더라도 `execute()`, `runAction()`, `emitHook()`, `retry()`, `cancel()` 또는 이에 준하는 특권 작업을 노출할 수 없다. 권한 있는 작업은 인증된 G7 API와 서버 권한 검사를 거쳐야 한다.

## 에셋

<!-- @generated:assets START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 경로 | 구분 |
|---|---|
| `editor-spec.json` | 레이아웃 편집기 스펙 (manifest) |

로딩 설정: `{"strategy":"global","priority":100,"dependencies":[]}`
<!-- @generated:assets END -->

모듈 소유 JS/CSS와 build 산출물은 없다. 따라서 `public/build`, 활성 모듈 디렉토리 또는 생성 자산을 수정할 이유가 없다. 프런트엔드 코드를 추가하는 미래 슬라이스에서는 번들 소스에서 시작해 G7 빌드·확장 lifecycle을 따라야 한다.

## Foundation regression sources

All seven layouts have individual tests in
`resources/js/__tests__/layouts/{layout_name}.test.tsx`, using the current
`@core/template-engine/__tests__/utils/layoutTestUtils` `createLayoutTest()`.
The module-local `vitest.config.ts` uses jsdom and explicit aliases into the
current bundled admin template and core. No root test config or setup is imported.
`foundationLayoutSupport.ts` registers the actual admin basic components plus
`PageHeader`, `EmptyState`, and `DataGrid` through the existing mock-registry helper. English and Korean module
translations are passed under the `glitter-workflow` namespace.

Tests assert layout identity, content structure, known component types, the
current `_admin_base` content slot, translated title/description rendering,
Workflow list data source, permission conditions, columns, UUID navigation and
server pagination bindings. `createLayoutTest()` renders the content slot; it
does not resolve server-side inheritance. `FoundationLayoutInheritanceTest`
therefore separately invokes the actual `LayoutService::mergeComponents()` slot
inheritance algorithm through Reflection, following the existing core merge-unit
test convention. It uses the current bundled `_admin_base` and all seven child
JSON files without constructing an application, repositories or storage. This
checks slot inheritance, not database-backed layout resolution or merge hooks.

Execution in this live checkout was skipped: production isolation was not proven.
In particular the PHP test bootstrap deletes production config cache and contains
.env restoration logic. The complete transitive JS runner/import filesystem and
network isolation was not established. No test bootstrap was run, and no assets
were built or published: this correction adds tests, not frontend runtime code.
