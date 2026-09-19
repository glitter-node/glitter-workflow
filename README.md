# Glitter Workflow

**Gnuboard7 third-party module · glitter-workflow · Glitter.kr**

<!-- @generated:badges START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
<p align="center">
  <img src="https://img.shields.io/badge/version-0.18.0-0066FF?style=flat-square" alt="version 0.18.0">
  <img src="https://img.shields.io/badge/type-%EB%AA%A8%EB%93%88-555555?style=flat-square" alt="type 모듈">
  <img src="https://img.shields.io/badge/%EA%B7%B8%EB%88%84%EB%B3%B4%EB%93%9C7-%3E%3D7.0.11-1F883D?style=flat-square" alt="그누보드7 &gt;=7.0.11">
  <img src="https://img.shields.io/badge/license-MIT-8250DF?style=flat-square" alt="license MIT">
  <img src="https://img.shields.io/badge/requires-sirsoft--board-BF8700?style=flat-square" alt="requires sirsoft-board">
</p>
<!-- @generated:badges END -->


## 소개

Glitter Workflow는 Gnuboard7의 공개 계약 위에서 승인된 이벤트를 조건과 순차 액션으로 연결하고 실행 이력을 기록하는 Workflow & Automation Module이다. 현재 bundled 버전은 0.18.0이며, 관리자 Workflow·Execution 화면과 제한된 운영 설정을 제공한다.

## 주요 기능

- 네 개의 안정 트리거 키와 버전된 스칼라 컨텍스트 계약
- `all`/`any` 논리 그룹과 타입별 허용 연산자
- executable `delay`와 Core safe outbound 기반 `webhook` 액션. `notification`은 실행 정의에서 거부되는 reserved/unavailable 유형
- 워크플로 정의, 실행, 액션 실행의 세 엔티티와 상태 Enum
- 관리자 Workflow 목록의 status/trigger 필터, 이름·public UUID 검색, `created_at DESC, id DESC` deterministic ordering, 서버 페이지네이션과 UUID 상세 이동
- 관리자 Execution 목록의 status/trigger 필터, full execution UUID·생성 시각 표시, EmptyState와 안전한 Detail trace metadata
- Workflow와 Execution Detail의 safe projection 기반 운영 정보. public UUID를 사용하며 numeric DB ID는 public identity로 노출하지 않는다
- Best-effort source capture와 durable acceptance/runtime 경계. 상세 보장은 [delivery guarantees](docs/delivery-guarantees.md)를 참고한다.

네 승인된 source Action만 동기 구독한다. Notification은 검증된 G7 recipient/authorization/idempotent-delivery 계약이 없으므로 구성·활성·실행하지 않는다. 관리자 retry/cancel API, production canary 도구, arbitrary code/SQL/shell 실행 기능도 제공하지 않는다.

## 동작 방식

```text
G7 public Action hook
  -> stable trigger adapter
  -> redacted versioned scalar context
  -> allowlisted typed conditions
  -> sequential action plan
  -> workflow/action execution ledger
```

동기 redaction 후 stable trigger에 대한 enabled workflow 존재를 indexed `exists()`로 확인한다. interest가 있을 때만 acceptance를 기록하고, Workflow 전용 bounded job에서 활성 정의를 다시 조회해 매칭과 실행을 이어간다. source 작업에서 acceptance commit까지의 손실 창은 남는다. 런타임은 delay와 webhook만 실행 가능하며, notification은 reserved/unavailable이다.

## 관리자 및 설정 범위

관리자 화면은 Workflow CRUD/lifecycle, Workflow 목록 필터·검색, Execution 목록/상세 관찰, Settings 조회를 제공한다. Settings에서 `max_actions_per_workflow`와 `max_condition_depth`만 검증된 범위로 수정할 수 있으며, recursion depth는 현재 `0`인 read-only 정책이다. connection count와 private connection registry는 표시·런타임 사용 경계에 머물고, connection editor·인증정보·secret 관리 UI는 제공하지 않는다.

실행·액션 실행·acceptance 원장은 운영 관찰을 위한 안전한 projection으로 제공한다. raw context, workflow snapshot, action configuration, payload/body, result metadata, credentials, secrets, exception message와 stack trace는 관리자 응답이나 화면에 노출하지 않는다.

## 요구 사항

<!-- @generated:requirements START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 항목 | 값 |
|---|---|
| 그누보드7 코어 | `>=7.0.11` |
| PHP | `^8.2` |
| 의존 모듈 | `sirsoft-board` `>=1.1.2` |
<!-- @generated:requirements END -->

## 설치

<!-- @generated:install START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
```bash
# 번들 설치 (코어에 동봉된 소스에서 설치)
php artisan module:install glitter-workflow

# 활성화
php artisan module:activate glitter-workflow

# 업데이트 (번들 소스 기준 강제 반영)
php artisan module:update glitter-workflow --force
```
<!-- @generated:install END -->

## 관리자 설정

<!-- @generated:settings-summary START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
_별도의 관리자 설정 항목이 없습니다._
<!-- @generated:settings-summary END -->

## 사용 방법

현재 런타임은 승인된 네 트리거와 구현된 액션만 사용한다. 모듈을 활성화하기 전에는 운영 큐 불변식과 별도 배포 검증을 완료해야 한다.

## 다른 확장과의 연동

<!-- @generated:integrations START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
**이 확장이 의존하는 확장**

| 확장 | 유형 | 버전 제약 | 번들 |
|---|---|---|---|
| `sirsoft-board` | 모듈 | `>=1.1.2` | ✅ |

**이 확장에 의존하는 확장** (이 확장을 비활성화하면 함께 영향을 받습니다)

없음.
<!-- @generated:integrations END -->

## 문서

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

## 트러블슈팅

- 모듈이 비활성화된 동안에는 Workflow source capture 리스너가 등록되지 않는다.
- 수용 전 source 실패와 수용 후 Workflow job 실패의 경계는 delivery-guarantees 문서를 확인한다.
- 설치 전에는 활성 모듈 디렉토리나 런타임 메뉴에 반영되지 않는다. 번들 소스를 직접 활성 경로로 복사하지 않는다.

## 변경 이력

[CHANGELOG.md](CHANGELOG.md)를 참고한다.

## 라이선스

MIT License. Copyright © 2026 Glitter.kr. [LICENSE](LICENSE)를 참고한다.
