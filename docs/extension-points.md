# Glitter Workflow 확장점

## 발행 훅

<!-- @generated:hooks-published START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
발행 훅 4종 / 호출 지점 5곳. 이 중 4종은 `getHooks()` 선언에 없어 소스에서 자동 감지한 것입니다 — 선언에 추가하면 유형과 설명이 함께 실립니다. 훅 이름이 상수·변수로 조립된 호출이 1곳 있어 호출 위치가 표에 다 실리지 않을 수 있습니다.

| 훅 이름 | 유형 | 설명 | 발행 위치 |
|---|---|---|---|
| `core.module_settings.after_save` | action | — | `src/Http/Controllers/Admin/WorkflowSettingsController.php:29` |
| `glitter-workflow.workflow.after_create` | action | — | `src/Services/WorkflowDefinitionService.php:8` |
| `glitter-workflow.workflow.after_delete` | action | — | `src/Services/WorkflowDefinitionService.php:11` |
| `glitter-workflow.workflow.after_update` | action | — | `src/Services/WorkflowDefinitionService.php:9` |
<!-- @generated:hooks-published END -->

0.9.1은 Workflow 정의 변경에 대한 모듈 소유 Action을 발행한다. 임의 훅 발행은 지원하지 않는다.

## 구독 훅

<!-- @generated:hooks-subscribed START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 훅 이름 | 유형 | 리스너 | 메서드 | 우선순위 |
|---|---|---|---|---|
| `core.auth.after_register` | action (미선언) | `UserRegisteredWorkflowCaptureListener` | `capture` | 30 |
| `core.user.after_update` | action (미선언) | `UserUpdatedWorkflowCaptureListener` | `capture` | 30 |
| `glitter-workflow.workflow.after_activate` | action (미선언) | `WorkflowActivityLogListener` | `activate` | 20 |
| `glitter-workflow.workflow.after_create` | action (미선언) | `WorkflowActivityLogListener` | `create` | 20 |
| `glitter-workflow.workflow.after_deactivate` | action (미선언) | `WorkflowActivityLogListener` | `deactivate` | 20 |
| `glitter-workflow.workflow.after_delete` | action (미선언) | `WorkflowActivityLogListener` | `delete` | 20 |
| `glitter-workflow.workflow.after_update` | action (미선언) | `WorkflowActivityLogListener` | `update` | 20 |
| `sirsoft-board.comment.after_create` | action (미선언) | `BoardCommentWorkflowCaptureListener` | `capture` | 30 |
| `sirsoft-board.post.after_create` | action (미선언) | `BoardPostWorkflowCaptureListener` | `capture` | 30 |
<!-- @generated:hooks-subscribed END -->

Runtime Slice 3은 아래 네 공개 Action 훅을 모듈 소유 sync=true 리스너로 등록한다. 자동 생성 구독 목록에는 모듈의 다른 공식 리스너도 함께 표시된다.

| 안정 트리거 키 / 스키마 | G7 source hook | 검증된 source 인자 | 어댑터가 보존하는 필드 |
|---|---|---|---|
| `user.registered@1` | `core.auth.after_register` | `User`, registration context | `user.uuid`, `status`, `language`, `country`, `created_at` |
| `user.updated@1` | `core.user.after_update` | `User`, original request data, old snapshot | `user.uuid`, `status`, `language`, `country`, `updated_at` |
| `board.post.created@1` | `sirsoft-board.post.after_create` | `Post`, board slug, options | `post.id`, `board_id`, `board_slug`, `actor_user_id`, `category`, `is_notice`, `is_secret`, `status`, `parent_id`, `depth`, `created_at` |
| `board.comment.created@1` | `sirsoft-board.comment.after_create` | `Comment`, board slug | `comment.id`, `board_id`, `board_slug`, `post_id`, `actor_user_id`, `parent_id`, `is_secret`, `status`, `depth`, `created_at` |

These Actions follow source writes, but are not a durable event stream. Registration/update/post hooks run after their service-owned transactions complete; comment creation has no service-owned transaction and fires after repository create, before statistics cache invalidation. An ambient caller transaction can still be open. Exception handling differs by source; see [the exact delivery and failure contract](delivery-guarantees.md).

등록 context에는 검증 토큰과 원본 입력, 수정 인자에는 비밀번호를 포함할 수 있는 요청 데이터와 과거 스냅샷이 있으므로 이 인자를 저장하지 않는다. 게시글 options 역시 영속 컨텍스트에서 제외한다.

외부 모듈이나 플러그인이 Glitter Workflow 트리거 정의를 등록하는 공개 계약은 현재 G7에도 이 모듈에도 없다. `BuiltInTriggerDefinitionRegistry`는 내장 정의 전용이다. 제3자 등록이 필요하면 별도 버전 계약, 충돌 정책, 필드 스키마 검증과 redaction 검증을 먼저 설계해야 하며 내부 container binding을 교체하는 방식은 지원하지 않는다.

## 훅 리스너

<!-- @generated:listeners START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
| 리스너 | 구독 훅 | 등록 방식 | HookListenerInterface | 파일 |
|---|---|---|---|---|
| `BoardCommentWorkflowCaptureListener` | 1개 | 명시 등록 | ✅ | `src/Listeners/BoardCommentWorkflowCaptureListener.php` |
| `BoardPostWorkflowCaptureListener` | 1개 | 명시 등록 | ✅ | `src/Listeners/BoardPostWorkflowCaptureListener.php` |
| `UserRegisteredWorkflowCaptureListener` | 1개 | 명시 등록 | ✅ | `src/Listeners/UserRegisteredWorkflowCaptureListener.php` |
| `UserUpdatedWorkflowCaptureListener` | 1개 | 명시 등록 | ✅ | `src/Listeners/UserUpdatedWorkflowCaptureListener.php` |
| `WorkflowActivityLogListener` | 5개 | 명시 등록 | ✅ | `src/Listeners/WorkflowActivityLogListener.php` |
<!-- @generated:listeners END -->

현재 source capture 리스너는 네 개다. source hook 하나당 얇은 adapter listener를 `getHookListeners()`로 선언한다.

보안상 네 source capture 리스너 모두 `sync => true`다. G7 Action 리스너의 기본 비동기 경로는 `DispatchHookListenerJob`에 원시 훅 인자를 직렬화한다. 등록 context의 verification token과 수정 request/snapshot을 database queue payload에 넣지 않으려면, 원 요청 안에서 즉시 어댑터로 redaction하고 안전한 `TriggerContext`를 acceptance 테이블에 기록한 뒤 기존 Workflow job을 afterCommit으로 예약한다. 동기 구간에서는 redaction·기본 검증·안전 DTO 수용만 수행하고 네트워크 요청이나 실행 계획 전체를 수행하면 안 된다. Durable acceptance must account for any ambient transaction and the actual queue driver. An after-commit callback or a successful dispatch call alone is not durable acceptance. Source capture can be lost before persistence; Runtime Slice 3 preserves this best-effort boundary.

## 레이아웃 확장

<!-- @generated:layout-extensions START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
_레이아웃 확장이 없습니다._
<!-- @generated:layout-extensions END -->

7개 관리자 레이아웃은 `_admin_base`를 상속하며 번역 키만 표시한다. 다른 확장을 위한 layout extension slot은 발행하지 않는다.

## 미들웨어

<!-- @generated:middleware START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
_등록하는 미들웨어가 없습니다._
<!-- @generated:middleware END -->

모듈 소유 미들웨어는 없다. 관리자 인증과 권한은 G7 라우트·레이아웃 계약을 사용한다.

## 브로드캐스트 채널

<!-- @generated:channels START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
_등록하는 브로드캐스트 채널이 없습니다._
<!-- @generated:channels END -->

모듈 소유 브로드캐스트 채널은 없다. 알림 액션이 G7 알림 인프라를 통해 broadcast를 선택하더라도 이 모듈이 별도 전달 채널을 구현하지 않는다.

## 스케줄

<!-- @generated:schedules START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
_등록하는 스케줄이 없습니다._
<!-- @generated:schedules END -->

모듈 소유 스케줄 선언은 없다. delay 액션은 향후 Laravel queue job의 지연 dispatch를 사용해야 하며 cron 폴링 스케줄러를 병렬 구현하지 않는다.

현재 프로젝트의 운영 baseline은 `QUEUE_CONNECTION=database`로 기록되어 있으나, 모듈 계약은 특정 드라이버를 가정하지 않는다. 잡은 `ShouldQueue`, after-commit 의미, Laravel delay와 재시도 계약을 따라야 한다. 운영 worker 존재 여부는 배포 단계에서 별도 확인해야 한다.

## 알림 정의

<!-- @generated:notifications START — ext:docgen 이 갱신. 이 블록 안은 직접 수정하지 않는다 -->
_등록하는 알림 정의가 없습니다._
<!-- @generated:notifications END -->

0.9.1은 알림 정의나 발송 코드를 제공하지 않으며 `notification`을 reserved/unavailable 액션으로 거부한다. Notification은 검증된 G7 recipient·authorization·idempotent-delivery 계약이 있기 전까지 실행 가능 정의에 포함되지 않는다. 메일, database notification, broadcast 발송을 직접 중복 구현하지 않는다.

## Activity Log 경계

코어 Activity Log에는 워크플로 생성·수정·삭제·활성화·비활성화와 관리자의 수동 재시도·취소 같은 사용자/관리자 행위만 기록한다. 각 자동 실행과 액션 상태는 모듈의 실행 테이블에만 기록한다. Foundation Slice에는 위 관리자 mutation 서비스가 없으므로 Activity Log 훅도 아직 발행하지 않는다. CRUD 구현 시 G7의 서비스 Action 훅 → Activity Log listener 계약을 사용한다.
