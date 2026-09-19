# Glitter Workflow 설정과 관리자 표면

## 설정 스키마

<!-- @generated:settings-schema START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
_`getSettingsSchema()` 선언이 없습니다._

기본값 파일: `config/settings/defaults.json`
<!-- @generated:settings-schema END -->

`config/settings/defaults.json`이 모듈 설정 기본값의 정본이다. `connections.json`에는 관리자가 저장한 폐쇄형 Webhook 연결 맵만 보관하며 frontend 설정이나 workflow/action/execution 스냅샷에는 노출되지 않는다. 이 레지스트리의 JSON 루트는 항상 객체/맵이다. 비어 있으면 정식 표현은 `{}`이며, 0.8.0 설치본의 레거시 `[]`는 읽기 호환을 위해 빈 맵으로 해석되고 0.8.1 모듈 업데이트에서만 `{}`로 정규화된다.

Webhook 연결은 `connections.<connection_key>.url` 형태이며 키는 소문자 영숫자·`_`·`-`만 허용한다. URL은 HTTPS, host 존재, userinfo·fragment 없음으로 조기 검증하고, 최종 DNS/SSRF/TLS/redirect/port 검증은 Core safe outbound client가 담당한다. v1 연결에는 인증정보, 헤더, 서명, timeout, proxy 또는 기타 transport 옵션이 없다.

| 키 | 기본값 | 절대 상한 | 의미 |
|---|---:|---:|---|
| `limits.max_actions_per_workflow` | 25 | 100 | 한 정의에 허용할 순차 액션 수 |
| `limits.max_condition_depth` | 5 | 10 | 중첩 all/any 조건 깊이 |
| `limits.max_workflow_recursion_depth` | 0 | 0 | v0.1 재귀 실행 금지 |
| `connections.<key>.url` | `{}` | key 64자 / URL 2048바이트 | 비공개 Core Webhook endpoint reference |

`Modules\Glitter\Workflow\Services\WorkflowSettingsService` implements all seven
methods of `App\Contracts\Extension\ModuleSettingsInterface`. It is the authoritative
service for future CRUD/runtime consumers. Inject this service or use
`module_setting('glitter-workflow', 'limits.max_actions_per_workflow')` for reads;
business services must not read JSON themselves.

Current integration, verified from source:

- `ModuleSettingsService::discoverModuleService()` discovers this exact concrete
  class by the `glitter-workflow` naming convention and delegates reads to it.
- `WorkflowServiceProvider::$storageServices` uses the base provider's contextual
  `StorageInterface` binding for this module. No global storage binding is replaced.
- `AbstractModule::getSettingsDefaultsPath()` discovers the existing defaults file;
- `WorkflowSettingsService::initializeDefaults()` runs through the module install lifecycle before G7's generic category initializer. It creates both category files and preserves the empty connections registry as `{}`. The generic initializer remains responsible for other modules.
- The service reads that same category file via `StorageInterface::get()` and
  reads the bundled/installed defaults relative to its own class location. It has
  no database dependency and keeps no stale process-local settings cache.
- G7's generic `ModuleSettingsService::save()` / `reset()` still target
  `setting.json` and do not delegate writes. They are **not** this module's write
  API. The authorized Admin Settings update endpoint calls this service's
  `saveSettings()` with an explicit allowlist of the two effective scalar limits.
- A successful service save refreshes the same-process G7 settings mirror through
  `g7_refresh_module_settings_config()`, following the first-party Page settings
  service. This is not a cross-process invalidation broadcast. Future long-lived
  business services must read this authoritative service when resolving limits.

`limits` and `connections` are private module categories. Actions accept native integers 1..100, condition
depth 1..10, and recursion exactly 0. Defaults are 25, 5, 0. JSON strings, floats,
booleans, arrays, objects, explicit null, negative values and out-of-range values
are invalid settings; numeric strings are not a settings input contract.

On reads, missing files, malformed JSON or non-object category data use defaults.
A missing field and an explicitly null field are detected separately; both resolve
to the validated default because none of these limits is nullable. Invalid saved
fields also use defaults, never an unchecked caller fallback. Invalid/missing
schema defaults use `WorkflowLimits::DEFAULTS`; those fallback constants are
regression-checked against defaults.json. Absolute bounds are code-owned and
cannot be increased by editing settings. Recursion always resolves to zero.

On writes, omitted fields preserve existing normalized values. An explicit null,
unknown category/key or invalid value rejects the entire request with `false`
before storage access. An empty top-level update is a successful no-op. Storage
write failure returns `false`; storage exceptions propagate. Reads do not silently
hide storage exceptions; G7's convenience helper separately catches Throwable and
returns its caller fallback, so future security decisions should inject the service.

`getFrontendSettings()` returns `[]`; both `frontend_schema.limits.expose` and `frontend_schema.connections.expose` remain false. No authorized Webhook connection administration write path exists yet, and no secrets were introduced. `getSettingsSchema()` and `getConfigValues()`
remain their empty AbstractModule defaults because this integration uses the
module-owned interface rather than the generic fallback.

## 권한

<!-- @generated:permissions START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 카테고리 | 이름 | 액션 | 라우트 키 |
|---|---|---|---|
| `workflows` | 워크플로 | `read`, `create`, `update`, `delete`, `activate` | `workflow` |
| `executions` | 실행 이력 | `read`, `retry`, `cancel` | `execution` |
| `settings` | 환경설정 | `read`, `update` | - |
<!-- @generated:permissions END -->

정확한 식별자는 다음과 같다.

- `glitter-workflow.workflows.read`
- `glitter-workflow.workflows.create`
- `glitter-workflow.workflows.update`
- `glitter-workflow.workflows.delete`
- `glitter-workflow.workflows.activate`
- `glitter-workflow.executions.read`
- `glitter-workflow.executions.retry`
- `glitter-workflow.executions.cancel`
- `glitter-workflow.settings.read`
- `glitter-workflow.settings.update`

라우트·레이아웃 권한은 화면 접근의 첫 방어선일 뿐이다. 향후 API Request/Policy/Service에서도 같은 권한과 객체 범위를 재검증해야 한다. 실행 retry/cancel은 unrestricted execution authority가 아니며 명시적인 관리자 작업만 허용해야 한다.

## 메뉴

<!-- @generated:menus START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 구분 | slug | 이름 | URL | 하위 |
|---|---|---|---|---|
| 관리자 | `glitter-workflow` | 워크플로 관리 | - | 3개 |
<!-- @generated:menus END -->

`Glitter Workflow` 상위 메뉴 아래 `Workflows`, `Executions`, `Settings` 세 항목을 선언한다. 각 항목은 대응 read 권한이 있어야 표시된다. 메뉴 이름은 현재 G7 `module.php` 다국어 배열 계약을 따른다.

## 라우트

<!-- @generated:routes START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 종류 | 파일 | URL prefix |
|---|---|---|
| `api` | `src/routes/api.php` | `/api/modules/glitter-workflow/...` |

확장 라우트는 **활성 상태인 확장의 것만** 등록됩니다. 라우트 정의를 바꾸면 라우트 캐시 재생성이 필요합니다.
<!-- @generated:routes END -->

프런트엔드 라우트는 다음 관리자 URL을 준비한다.

- `/admin/glitter-workflow/workflows`
- `/admin/glitter-workflow/workflows/create`
- `/admin/glitter-workflow/workflows/{uuid}`
- `/admin/glitter-workflow/workflows/{uuid}/edit`
- `/admin/glitter-workflow/executions`
- `/admin/glitter-workflow/executions/{uuid}`
- `/admin/glitter-workflow/settings`

`resources/routes/admin.json`에서는 현재 G7 규약에 따라 `*/admin/...`와 `:uuid`를 사용한다. Settings 화면은 두 scalar limits의 authenticated update를 지원하며 recursion depth와 connections는 read-only다.

## 의존 관계

<!-- @generated:dependencies START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
**이 확장이 의존하는 확장**

| 확장 | 유형 | 버전 제약 | 번들 |
|---|---|---|---|
| `sirsoft-board` | 모듈 | `>=1.1.2` | ✅ |

**이 확장에 의존하는 확장** (이 확장을 비활성화하면 함께 영향을 받습니다)

없음.
<!-- @generated:dependencies END -->

manifest는 G7 `>=7.0.11`, `sirsoft-board >=1.1.2`를 요구한다. 게시판 모듈은 보호된 CMS 공급 번들이며 이 모듈은 공개 Action 훅만 소비해야 한다. ecommerce 의존성과 trigger는 v0.1에 없다.

서비스 provider는 G7의 `BaseModuleServiceProvider` 자동 발견 규약으로 내장 `TriggerDefinitionRegistryInterface`를 singleton에 연결한다. 외부 확장이 이 binding을 교체하는 것은 공개 확장 계약이 아니다.
