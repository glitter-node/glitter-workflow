# Glitter Workflow 아키텍처

## 설계 의도

이 모듈은 업무 규칙의 오케스트레이션만 소유한다. G7가 이미 제공하는 알림 전달, Laravel 큐, 권한, 캐시, 스토리지, Activity Log, JSON 레이아웃과 확장 수명주기를 대체하지 않는다.

안전 경계는 다음과 같다.

- 워크플로는 `user.registered` 같은 모듈 소유 키를 저장한다. G7 훅 이름은 내장 어댑터의 구현 세부사항이다.
- 트리거 컨텍스트는 스키마 버전과 공개 subject, 허용된 스칼라 데이터만 가진다.
- 조건은 정의에 선언된 필드와 타입 호환 연산자만 사용할 수 있다.
- 액션은 닫힌 Enum과 타입 DTO다. 임의 코드, 이벤트, 훅, SQL, 셸 또는 동적 호출을 허용하지 않는다.
- Source capture is best-effort until durable acceptance. Only future durably accepted work with persistence/retry can have at-least-once processing attempts; no source-to-execution guarantee exists. See [delivery guarantees](delivery-guarantees.md).

## 계층 지도

```text
G7 public Action hooks
       |
       v
BuiltInTriggerDefinitionRegistry -> typed/redacted TriggerContext
       |
       v (future runtime slice)
condition validator/evaluator -> execution planner -> queued sequential actions
       |                                                |
       v                                                v
glitter_workflow_executions                 reserved notification / Core safe outbound client / queue
       |
       v
glitter_workflow_action_executions
```

Runtime Slice 3은 네 승인된 훅을 모듈 소유 sync=true 리스너로 수용한다. 어댑터는 원시 인자를 허용 필드만 포함한 버전된 컨텍스트로 변환하고, acceptance 서비스가 기존 테이블에 기록한 뒤 Workflow 전용 job을 afterCommit으로 예약한다. G7 범용 async 훅 job에는 원시 인자를 넘기지 않는다.

0.9.1의 acceptance 서비스는 안전 컨텍스트 검증 후 stable `TriggerKey`로 `(trigger_key, is_active)` 복합 인덱스를 사용하는 `exists()`를 한 번 수행한다. 해당 트리거에 enabled workflow가 없으면 acceptance와 job을 만들지 않는다. 이 확인은 조건·액션 계획을 로드하지 않고 평가, fanout, network I/O를 수행하지 않는 bounded interest snapshot이다. 실제 계획 시점의 enabled workflow 조회는 acceptance job이 다시 수행한다.

조건 트리는 `ConditionGroup(all|any)`와 `ConditionPredicate(field, operator, value)`로 구성한다. 표현식 엔진이나 레이아웃 표현식을 재사용하지 않는다. 액션 유형은 닫힌 `WorkflowActionType` Enum이 정본이며, 0.9.1에서 실행 가능한 값은 `webhook`, `delay`뿐이다. `notification`은 향후 계약을 위한 reserved/unavailable 값으로 남지만 executable workflow 정의에서는 거부된다.

## 디렉토리

<!-- @generated:directory-map START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 경로 | 역할 | 수정 시 필요한 절차 |
|---|---|---|
| `module.json` | manifest (버전 SSoT) | version 변경 시 package.json·package-lock.json·composer.json 동기화 |
| `module.php` | 진입 클래스 (선언형 표면 SSoT) | 표면 변경 시 `ext:docgen` 재실행 + 코어 최소 버전 검토 |
| `src/Http/Controllers/` | 컨트롤러 | API 표면 변경 시 `api:docgen` 재실행 |
| `src/Http/Requests/` | FormRequest (검증 SSoT) | 검증 규칙은 Service 가 아니라 여기에 둔다 |
| `src/Http/Resources/` | API 리소스 | 목록 응답은 화면이 실제로 그리는 것만 싣는다 |
| `src/Services/` | 비즈니스 로직 | Repository 인터페이스 주입 (구체 클래스 금지) |
| `src/Repositories/` | 데이터 접근 | 목록 쿼리는 컬럼 프루닝·정렬 화이트리스트 확인 |
| `src/Models/` | Eloquent 모델 | 스키마 변경 시 마이그레이션 + 업그레이드 스텝 동반 |
| `src/Listeners/` | 훅 리스너 | Repository 경유 (Model·DB 파사드 직접 접근 금지) |
| `src/Enums/` | 상태·타입·분류 | 문자열 리터럴 대신 Enum 을 SSoT 로 둔다 |
| `src/routes/` | 라우트 | 모든 라우트에 `name()` 필수 |
| `src/lang/` | 백엔드 다국어 | ko·en 동시 반영 + 번들 ja 팩 동기화 |
| `database/migrations/` | 마이그레이션 | 한국어 comment + `down()` 필수, 기설치본은 업그레이드 스텝으로 백필 |
| `upgrades/` | 업그레이드 스텝 | DB·설정 구조 변경 시 작성 (모듈/플러그인 전용) |
| `resources/layouts/` | 레이아웃 JSON | `php artisan module:update glitter-workflow --force` (빌드 불필요) |
| `resources/routes.json` | 라우트 → 레이아웃 매핑 | `php artisan module:update glitter-workflow --force` |
| `resources/routes/` | 라우트 → 레이아웃 매핑 (분할) | `php artisan module:update glitter-workflow --force` |
| `resources/js/` | 프론트 엔트리·핸들러 | `php artisan module:build` → `php artisan module:update glitter-workflow --force` |
| `editor-spec.json` | 레이아웃 편집기 스펙 | `php artisan module:update glitter-workflow --force` |
| `config/` | 확장 config | 설정 기본값은 settings 스키마와 어긋나지 않게 |
| `tests/` | 테스트 | 변경 범위만 필터 실행 |
| `CHANGELOG.md` | 변경 이력 | 버전 상향 시 항목 추가 (미기재 시 버전 상향 불가) |
| `docs/` | 개발자 문서 | 표면 변경 시 `php artisan ext:docgen` 재실행 |
<!-- @generated:directory-map END -->

- `src/Services/Triggers`: G7 훅과 안정 키 사이의 유일한 매핑 지점
- `src/DTO`: 직렬화 가능한 계약 데이터
- `src/Enums`: 허용 키·연산자·액션·상태의 닫힌 집합
- `src/Models`: 정의와 기계 원장
- `database/migrations`: 이 모듈만 소유하는 테이블
- `config/settings`: 운영자가 관리할 제한값
- `resources`: 관리자 라우트·레이아웃·프런트 번역
- `src/lang`: 백엔드 번역

The Foundation settings service now uses G7 contextual `StorageInterface` for category files. Future services must consume `WorkflowSettingsService` for normalized operational limits; they must not read JSON directly. No workflow runtime or cache wrapper is introduced.

## 실행 및 인과관계

외부 훅에서 시작하는 최초 실행은 새 실행 UUID를 만들고 같은 값을 `correlation_id`로 사용하며, `causation_id`는 null, `depth`는 0이다. v0.1에는 워크플로-워크플로 액션과 재귀 호출이 없다. 설정 기본값과 절대 상한 모두 최대 재귀 깊이 0을 유지한다.

실행 정의는 snapshot으로 남겨 원본 워크플로가 수정·삭제된 뒤에도 감사 가능한 기계 상태를 보존한다. 오류 메시지는 비밀값 제거 후 1,000자로 제한해야 하며, 향후 런타임에서 저장 전에 이를 강제해야 한다.

## 보안 경계

비밀번호, 해시, 인증·검증 토큰, 쿠키, Authorization 헤더, 웹훅 비밀, 게시글 비밀번호와 불필요한 개인정보는 컨텍스트나 결과 메타데이터에 저장할 수 없다. 등록 훅의 context 및 수정 훅의 원본 요청/스냅샷은 의도적으로 전부 버린다.

웹훅의 `connectionKey`는 향후 비밀 저장소/연결 레지스트리를 참조해야 한다. 사용자가 입력한 URL을 액션 snapshot에 직접 저장하거나 실행해서는 안 되며, DNS 재바인딩과 사설·루프백·링크 로컬·클라우드 메타데이터 주소를 요청 시점마다 차단해야 한다.

## Public subject and scalar normalization

Both user adapters use `subjectType=core.user`, `subjectId=user.uuid`. The current
G7 User model returns `uuid` from `getRouteKeyName()` and creates it through
`UniqueIdServiceInterface::generateUuid(): string`. Missing, empty or non-string
UUIDs are invalid trigger arguments. The context contains no internal user ID.
Trigger keys and schema identifiers remain the four Foundation `@1` contracts;
this is a correction before installation, not a new persisted context schema.

The shared integer helper accepts native PHP ints and canonical ASCII decimal
strings matching `0|-?[1-9][0-9]*`, only within PHP_INT_MIN..PHP_INT_MAX. It rejects
whitespace (without trimming), plus signs, leading zeros, negative zero, decimals,
scientific notation, booleans, floats, arrays and objects. Nullable fields return
null for null or rejected values. Board post/comment `parent_id` and
`actor_user_id` use it. Comment `post_id` also uses it and rejects the trigger
arguments if normalization fails, since that field is required. Model-owned
integer casts for board_id/depth and Eloquent integer primary keys retain their
existing treatment. Neither user adapter has an integer-helper field.
