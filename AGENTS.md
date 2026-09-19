# Glitter Workflow — 에이전트 가이드

## TL;DR (5초 요약)

Glitter Workflow는 G7의 공개 훅을 안정적인 모듈 소유 트리거로 바꾸는 오케스트레이션 모듈이다. 현재 0.18.0은 네 승인된 source Action을 동기 redaction하고 해당 stable trigger에 활성 워크플로가 있을 때만 durable acceptance로 넘긴다. 관리자 Workflow 목록은 표준 collection 페이지네이션과 생성 권한을 사용한다. 기존 bounded Workflow job은 수용 이후의 비동기 runtime을 담당한다. Notification은 예약된 unavailable 액션이며 실행 정의에서 거부된다.

## 1. 이 확장은 무엇인가

`Trigger -> Conditions -> Actions -> Execution` 모델을 G7 위에 제공한다. 워크플로 정의와 기계 실행 원장은 모듈이 소유하지만, 알림 전달·큐·권한·캐시·스토리지·Activity Log·JSON 레이아웃·확장 수명주기는 G7 계약을 재사용한다.

핵심 불변식은 안정 트리거 키, 허용 목록 기반 조건 필드, 실행 불가능한 선언형 액션, 비밀값이 제거된 스칼라 컨텍스트다. 원시 훅 인자나 Eloquent 모델은 실행 이력에 저장하지 않는다.

## 2. 디렉토리 지도

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

- `module.php`: 권한과 관리자 메뉴 선언
- `config/settings/defaults.json`: 운영 제한 기본값과 프런트엔드 노출 정책
- `database/migrations`: 모듈 소유 정의·실행 원장 스키마
- `src/Contracts`, `src/DTO`, `src/Enums`: 공개 타입 경계
- `src/Services/Triggers`: 내장 트리거 정의와 컨텍스트 어댑터
- `src/Models`: 세 소유 엔티티
- `resources/routes`, `resources/layouts`: 관리자 화면 라우트와 JSON 레이아웃. Workflow 목록은 실제 API collection을 소비한다.
- `src/lang`, `resources/lang`: 백엔드 및 프런트엔드 번역
- `docs`: 상세 설계와 확장 경계

## 3. 핵심 흐름

현재 런타임은 네 공개 G7 Action 훅을 받은 즉시 동기 redaction 어댑터로 스칼라 컨텍스트를 만들고, 안전 DTO를 acceptance 테이블에 기록한 뒤 Workflow 전용 job을 afterCommit으로 예약한다. 이후 활성 정의를 조회해 조건을 평가하고, 실행·액션 원장을 먼저 기록한 다음 G7 큐에서 순차 액션을 처리한다. 초기 외부 이벤트는 실행 UUID를 `correlation_id`로 사용하고 `causation_id=null`, `depth=0`으로 시작한다.

0.9.1에서는 네 source capture 리스너가 sync=true로 등록된다. 모듈이 비활성이면 훅 리스너는 등록되지 않는다. 수용 전 동기 경계는 안전 컨텍스 검증과 indexed enabled-trigger `exists()` 확인만 수행하며, 조건 평가·액션 실행·네트워크 I/O가 없다.

## 4. 확장점

<!-- @generated:extension-points-summary START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 확장점 | 수 | 상세 |
|---|---|---|
| 발행 훅 | 4개 | [발행 훅](docs/extension-points.md#발행-훅) |
| 구독 훅 | 9개 | [구독 훅](docs/extension-points.md#구독-훅) |
| 훅 리스너 | 5개 | [훅 리스너](docs/extension-points.md#훅-리스너) |
| 레이아웃 확장 | 0개 | [레이아웃 확장](docs/extension-points.md#레이아웃-확장) |
| 미들웨어 | 0개 | [미들웨어](docs/extension-points.md#미들웨어) |
| 브로드캐스트 채널 | 0개 | [브로드캐스트 채널](docs/extension-points.md#브로드캐스트-채널) |
| 스케줄 | 0개 | [스케줄](docs/extension-points.md#스케줄) |
| 알림 정의 | 0개 | [알림 정의](docs/extension-points.md#알림-정의) |
<!-- @generated:extension-points-summary END -->


자동 생성 확장점 수치는 현재 bundled 0.18.0 선언형 표면을 반영한다. 네 개의 동기 source capture 리스너는 그중 승인된 외부 source Action 구독이다.

이 모듈은 Workflow 정의 변경에 대한 모듈 소유 Action만 발행하며, 임의 훅 발행 액션은 제공하지 않는다. Notification은 검증된 G7 recipient·authorization·idempotent-delivery 계약이 있기 전까지 unavailable이며, 별도 메일·DB·브로드캐스트 전달기를 만들지 않는다.

## 5. 수정 시 동반 의무

- 트리거 필드를 바꾸면 정의, 어댑터, 스키마 버전, 한·영 번역, 문서와 계약 테스트를 함께 갱신한다.
- 테이블이나 상태를 바꾸면 마이그레이션, 모델 cast, Enum, 데이터 모델 문서와 업그레이드 경로를 함께 검토한다.
- 권한·메뉴·라우트·설정을 바꾸면 `module.php`, JSON 선언과 문서를 함께 갱신한다.
- 표시 문구는 레이아웃에 직접 쓰지 않고 `resources/lang/{locale}.json` 키로 둔다.
- 행동 변경 시 `module.json` 버전을 검토한다.
- 번들 소스만 수정하고 활성 디렉토리와 생성 자산은 직접 수정하지 않는다.
- 보호 모듈의 훅 계약은 읽기 전용으로 재검증하며 내부 클래스나 비공개 구현에 의존하지 않는다.

## 6. 금지 패턴

- 원시 훅 이름을 워크플로 식별자로 저장
- 원시 모델·요청·훅 인자·인증 정보·비밀값을 실행 컨텍스트에 직렬화
- `eval`, 스크립트, SQL, 셸, 동적 callable/class/method 또는 임의 이벤트 실행
- 허용 목록 밖 조건 필드나 임의 객체/JSONPath 접근
- 별도 알림 전달·활동 로그·권한·캐시·스토리지 구현
- 웹훅 URL을 검증 없이 직접 실행하거나 내부·메타데이터 네트워크 접근 허용
- 워크플로 재귀 실행 또는 exactly-once 보장 주장
- 보호 모듈이나 설치/활성 디렉토리 직접 수정

## 7. 테스트 실행

<!-- @generated:test-commands START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 종류 | 개수 | 위치 |
|---|---|---|
| PHPUnit | 9개 | `modules/_bundled/glitter-workflow/tests` |
| Vitest | 8개 | `vitest.config.ts` |
| Playwright | 0개 | — |
| 시나리오 매니페스트 | 1개 | `tests/scenarios` |

```bash
# PHPUnit (변경 범위만) (Bash)
php vendor/bin/phpunit modules/_bundled/glitter-workflow/tests --filter='<대상클래스>'

# Vitest (확장 디렉토리에서) (PowerShell)
cd modules/_bundled/glitter-workflow && powershell -Command "npm run test:run -- <대상>"

```

무필터 전체 실행은 금지되어 있습니다 — 변경 범위에 걸리는 대상만 지정해 실행합니다.
<!-- @generated:test-commands END -->

운영 체크아웃에서는 구조적 격리가 입증되기 전 PHPUnit, Vitest, E2E 또는 Laravel 부트스트랩 테스트를 실행하지 않는다. 순수 PHP lint와 JSON 파싱은 허용한다. 데이터베이스 마이그레이션, 확장 설치·활성화·업데이트, 큐·캐시·세션 변경은 금지한다. PHP는 항상 `/usr/local/bin/php83`을 사용한다.

## 8. 문서 목차

<!-- @generated:docs-index START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 문서 | 내용 | 상태 |
|---|---|---|
| [docs/README.md](docs/README.md) | 문서 통합 목차와 실측 집계 | ✅ |
| [docs/architecture.md](docs/architecture.md) | 설계 의도·계층 지도·디렉토리 맵 | ✅ |
| [docs/extension-points.md](docs/extension-points.md) | 발행/구독 훅·미들웨어·채널·스케줄 | ✅ |
| [docs/data-model.md](docs/data-model.md) | 모델·소유 테이블·마이그레이션·Enum | ✅ |
| [docs/settings.md](docs/settings.md) | 설정 스키마·권한·메뉴·라우트·의존 관계 | ✅ |
| [docs/frontend.md](docs/frontend.md) | 레이아웃·액션 핸들러·전역 진입점·에셋 | ✅ |
| [docs/editor-spec.md](docs/editor-spec.md) | 레이아웃 편집기에 선언한 팔레트·컨트롤·샘플 데이터 | ✅ |
| [CHANGELOG.md](CHANGELOG.md) | 변경 이력 | ✅ |
<!-- @generated:docs-index END -->

상세 목차는 [docs/README.md](docs/README.md)를 참고한다. 운영자용 소개는 [README.md](README.md), 릴리스 변경은 [CHANGELOG.md](CHANGELOG.md)가 정본이다.

## Foundation correction verification (0.1.1)

Current test sources include two PHPUnit files and seven layout rendering test
files under `resources/js/__tests__/layouts/`, with a module-local Vitest config.
The generated inventory above is historical and has not been regenerated in this
source-only correction. Use the actual files and `docs/frontend.md` as the current
test inventory. Production testing remains forbidden until structural isolation
is proven; the root PHPUnit bootstrap currently deletes config cache and can
restore .env. Settings consumers must use `WorkflowSettingsService`; source
capture is best-effort until durable acceptance (see `docs/delivery-guarantees.md`).
