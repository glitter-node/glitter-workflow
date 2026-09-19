# Glitter Workflow 편집기 스펙

## 선언 요약

<!-- @generated:editor-spec-summary START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 항목 | 값 |
|---|---|
| manifest | `modules/_bundled/glitter-workflow/editor-spec.json` |
| 형태 | 단일 파일 (인라인) |
| 스펙 버전 | `1.0.0` |
| 스타일 시스템 | - |
| 다크 모드 전략 | - |

> 단일 파일 · 프리뷰 샘플 1 · 페이지 상태 1
<!-- @generated:editor-spec-summary END -->

0.18.0은 `editor-spec.json`에 관리자 Workflow 목록의 편집기 전용 API sample을 제공한다. 런타임 fallback, 공개 demo, seed 또는 운영 데이터로 사용하지 않는다.

## 선언 블록

<!-- @generated:editor-spec-blocks START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 블록 | 역할 | 항목 수 | 출처 |
|---|---|---|---|
| `sampleData.byDataSourceId` | 레이아웃 `data_sources` ID 로 붙는 프리뷰 응답 | 1 | `editor-spec.json (인라인)` |
| `states.groups` | 상태 변종을 적용할 범위(라우트·베이스 레이아웃) | 1 | `editor-spec.json (인라인)` |
<!-- @generated:editor-spec-blocks END -->

커스텀 컴포넌트·handler·action recipe는 선언하지 않는다. `sampleData`와 route-scoped `states`만 사용한다.

## 컴포넌트 팔레트

<!-- @generated:editor-spec-palette START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
_이 확장은 `componentPalette` 를 선언하지 않습니다 — 편집기 팔레트에 추가되는 항목이 없습니다._
<!-- @generated:editor-spec-palette END -->

모듈 전용 컴포넌트와 팔레트 항목은 없다. 목록 레이아웃은 활성 관리자 템플릿의 `Div`, `Button`, `Icon`, `Span`, `PageHeader`, `EmptyState`, `DataGrid`를 사용한다.

## 샘플 데이터와 페이지 상태

<!-- @generated:editor-spec-samples START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 자리 | 역할 | 개수 | ID |
|---|---|---|---|
| `sampleData.byDataSourceId` | 레이아웃 `data_sources` ID 로 붙는 프리뷰 응답 | 1 | `workflows` |
| `sampleData.byEndpointPattern` | 엔드포인트 패턴으로 붙는 프리뷰 응답 | 미선언 | - |
| `states.groups` | 상태 변종을 적용할 범위(라우트·베이스 레이아웃) | 1 | `*/admin/glitter-workflow/workflows` |

**프리뷰 샘플이 없는 `data_source` 3개** — 편집기 캔버스에서 이 자리만 빈 화면이 됩니다. 실제 화면은 정상이라 오류도 경고도 남지 않습니다.

`execution` · `executions` · `workflow`
<!-- @generated:editor-spec-samples END -->

`workflows` sample은 실제 collection의 `data`, `pagination`, `abilities.can_create` shape를 따른다. route-scoped 상태는 populated, empty, no-create 세 가지이며 모두 편집기 프리뷰 전용이다. 기존 루트 `workflow` data source는 이번 관리자 목록 범위가 아니므로 별도 sample을 추가하지 않는다.

## 수정 시 동반 의무

<!-- @generated:editor-spec-obligations START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 이런 변경을 했다면 | 편집기 스펙에서 함께 할 일 |
|---|---|
| 컴포넌트를 새로 만들었다 | `componentPalette` 에 항목 추가 · `componentCapabilities` 에 편집 역량 선언 · `nesting` 에 담길 자리 규정 |
| 레이아웃에 `data_sources` 를 추가했다 | `sampleData` 에 같은 ID 로 프리뷰 응답 추가 (없으면 편집기 캔버스만 빈 화면) |
| `_global.*` 을 새로 읽는다 | `sampleGlobal` 에 baseline 값 추가 |
| 빈 목록·오류 같은 화면 변종을 추가했다 | `states` 에 변종 추가 · `stateLabels` 에 친화 명칭 |
| 새 액션·조건 패턴을 도입했다 | `actionRecipes` / `conditionRecipes` 에 친화 명칭 등록 |

편집기 스펙은 JSON 이므로 빌드가 필요 없습니다. 다만 편집기 서빙은 **활성 디렉토리만** 읽으므로(`_bundled` 폴백 없음) 편집 후 반드시 반영합니다:

```bash
php artisan module:update glitter-workflow --force
```
<!-- @generated:editor-spec-obligations END -->

sample을 변경할 때는 실제 API collection shape, route path, 번역된 state label을 함께 맞추고 편집기 정적 검사를 수행한다. 활성 경로를 직접 수정하지 않는다.
