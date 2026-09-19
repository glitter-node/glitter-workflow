# Glitter Workflow 개발자 문서

## 문서 목차

<!-- @generated:doc-toc START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 문서 | 내용 |
|---|---|
| [architecture.md](architecture.md) | 설계 의도·계층 지도·디렉토리 맵 |
| [extension-points.md](extension-points.md) | 발행/구독 훅·미들웨어·채널·스케줄 |
| [data-model.md](data-model.md) | 모델·소유 테이블·마이그레이션·Enum |
| [settings.md](settings.md) | 설정 스키마·권한·메뉴·라우트·의존 관계 |
| [frontend.md](frontend.md) | 레이아웃·액션 핸들러·전역 진입점·에셋 |
| [editor-spec.md](editor-spec.md) | 레이아웃 편집기에 선언한 팔레트·컨트롤·샘플 데이터 |
| [../AGENTS.md](../AGENTS.md) | 에이전트·확장개발자 진입점 |
| [../README.md](../README.md) | 사람(도입검토자·운영자) 진입점 |
<!-- @generated:doc-toc END -->

<!-- @generated:stats START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
**훅 수**: 4 · **구독 훅 수**: 9 · **라우트 수**: 12 · **모델 수**: 5 · **테이블 수**: 4 · **마이그레이션 수**: 7 · **레이아웃 수**: 9 · **핸들러 수**: 0
<!-- @generated:stats END -->

현재 bundled 버전은 0.18.0이다. 네 승인된 트리거의 동기 redaction/capture, enabled-trigger interest gate, acceptance 이후 bounded runtime과 API 기반 관리자 Workflow·Execution 관찰 화면을 제공한다. Workflow 목록은 status/trigger 필터, 이름·public UUID 검색, deterministic ordering과 서버 페이지네이션을 지원한다. Execution 목록은 status/trigger 필터와 safe UUID·생성 시각 표시를 제공하며, Detail은 기존 safe projection의 trace metadata를 표시한다.

실행 가능한 액션은 `delay`와 `webhook`이며 `notification`은 reserved/unavailable이다. Settings에서는 `max_actions_per_workflow`와 `max_condition_depth`만 현재 검증된 범위로 수정할 수 있고 recursion depth는 `0` read-only이다. connection editor, secrets/auth 관리, 관리자 retry/cancel, arbitrary code/SQL/shell 실행과 production canary 도구는 제공하지 않는다.

관리자 및 API의 public Workflow identity는 UUID이다. 내부 numeric ID, raw context/snapshot/config/result, payload/body, credentials/secrets, exception message와 stack trace는 public projection과 관리자 화면에 노출하지 않는다.

Foundation correction contracts: [settings](settings.md), [delivery guarantees and source failure policy](delivery-guarantees.md), and [layout regression coverage](frontend.md#foundation-regression-sources).
