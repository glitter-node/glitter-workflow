# Changelog

## [0.18.0] - 2026-09-19

### Changed

- Made Workflow list pagination ordering deterministic with `created_at DESC` and an internal `id DESC` tie-breaker.
- Kept the public UUID and API contract unchanged.

## [0.17.0] - 2026-09-19

### Added

- Added validated Workflow List keyword search across workflow names and public UUIDs.
- Preserved status/trigger filters, page reset, and pagination query state.

## [0.16.0] - 2026-09-19

### Added

- Added safe execution trace metadata to the admin Execution detail view using the existing allowlisted execution, action, and acceptance projections.
- Preserved read-only behavior and excluded raw context, snapshots, payloads, credentials, exception messages, and stack traces.

## [0.15.1] - 2026-09-19

### Fixed

- Separated the read-only recursion depth and connection count displays in Admin Settings into distinct block-level rows.

## [0.15.0] - 2026-09-19

### Added

- Added full execution UUID and created timestamp columns to the admin Execution list.

## [0.14.0] - 2026-09-19

### Added

- Added validated status and trigger filters to the admin Execution list.
- Preserved filter values across pagination and reset to page one when a filter changes.

## [0.13.0] - 2026-09-19

### Added

- Added validated server-side status and trigger filters to the admin Workflow list.
- Added Korean and English filter controls while preserving existing pagination, EmptyState, and UUID navigation behavior.

## [0.12.0] - 2026-09-19

### Added

- Added authenticated updates for `max_actions_per_workflow` and `max_condition_depth` in the Admin Settings screen and API.
- Kept recursion depth and connection settings read-only; retry, cancel, and notification actions remain unavailable.

## [0.11.0] - 2026-09-19

### Added

- Added read-only administrator execution list/detail observability and settings views with safe allowlisted API responses.

### Fixed

- Corrected webhook execution observability to expose only validated HTTP status and response byte summaries, and aligned execution-list fallback data with the collection envelope.

## [0.10.6] - 2026-09-19

### Fixed

- Corrected the Detail trigger label placeholder so it renders as a static label.

## [0.10.5] - 2026-09-19

### Fixed

- Localized the admin sidebar label as "워크플로 관리" for Korean while preserving "Glitter Workflow" for English.

## [0.10.4] - 2026-09-19

### Fixed

- Corrected Detail translation placeholders so dynamic values render instead of literal `:value`.
- Normalized the user-facing module name, description, and admin sidebar label.

## [0.10.3] - 2026-09-19

### Fixed

- Corrected Create → Detail navigation after workflow creation.

## [0.10.2] - 2026-09-19

### Fixed

- Corrected the Create and Edit visible save actions to render the translated label through a supported Button child.

## [0.10.1] - 2026-09-19

### Fixed

- Corrected the admin Workflow Create and Edit presentations to use the verified G7 `form-group` and `form-label` field structure while preserving controlled values, actions, and save mutations.

## [0.10.0] - 2026-09-18

### Added

- Added resource-level `can_update`, `can_delete`, and `can_activate` abilities.
- Added translated status/metadata, nested display-safe condition summaries, and ordered redacted action summaries that never return webhook URLs, headers, body values, secrets, or credentials.
- Added explicit G7 `apiCall` mutations for create and edit with allowlisted payloads, UUID navigation, stale-version preservation, and no automatic retry.
- Completed create, detail, edit, activate, deactivate, and delete actions with confirmation, refetch, ability/state gates, and localized errors on the three admin Workflow screens.
- Added focused static contracts for the three CRUD/lifecycle layouts.

## [0.9.3] - 2026-09-18

### Added

- Added the admin Workflow list with translated loading, empty, populated, and error behavior, permission-gated create entry points, UUID row navigation, and server-side pagination.
- Added editor-only samples for populated, empty, and create-forbidden Workflow list previews.

### Changed

- Changed the Workflow list API to the standard `BaseApiCollection` response with pagination metadata and `abilities.can_create`.

## [0.9.2] - 2026-09-18

### Fixed

- Added Korean and English translations for all active admin layout titles, descriptions, and selector labels while preserving existing route-title keys and selector values.
- Added a deterministic source check for admin layout translation syntax, namespace, required keys, and locale parity.

## [0.9.1] - 2026-09-18

### Fixed

- Made `WorkflowActionType` the authoritative executable-action policy: only Delay and Webhook are executable, while Notification is rejected during create, update, and activation and terminal-fails defensively as `action_unavailable` if legacy data reaches runtime.
- Added an indexed, stable-trigger interest check before durable acceptance so source events with no enabled matching workflow create neither an acceptance row nor a processing job.

### Changed

- Removed Notification from the JSON admin action selectors. It remains reserved until G7 provides a verified recipient, authorization, and idempotent-delivery contract.
- Kept processing-time enabled-workflow lookup authoritative and documented capture-time interest-check race semantics. No migration, cache, frontend build, or Core change is required.

## [0.9.0] - 2026-09-18

### Added

- Completed synchronous module-owned capture for the four approved trigger keys: user registration, user update, board post creation, and board comment creation.
- Kept raw source hook arguments out of generic queued hook jobs; persisted only versioned, allowlisted scalar/null trigger contexts before dispatching the existing after-commit acceptance job.
- Added acceptance boundary validation and regression sources for hook mapping, redaction, rejected context shapes, and unchanged duplicate behavior.

### Changed

- Documented source-specific capture failures and the remaining best-effort source-to-acceptance window. No Core, protected module, queue configuration, or database schema change is part of this slice.

## [0.8.1] - 2026-09-18

### Fixed

- Canonicalized the private Webhook connection registry as a JSON object: empty registries persist as `{}` while the installed 0.8.0 `[]` representation remains readable and is normalized by the module update lifecycle.

## [0.8.0] - 2026-09-18

### Added

- Added executable Webhook actions through G7 Core's safe outbound client only.
- Added closed, non-frontend-exposed Webhook connection settings and server-generated action claim ownership.

### Changed

- Webhook delivery is bounded at-least-once with a stable delivery identity; stale running Webhooks are deliberately not auto-reclaimed.
- Notification actions remain unavailable.

## [0.1.1] - 2026-09-18

### Fixed

- Connected categorized operational settings to G7's module settings contract with validated defaults and absolute bounds; recursion remains disabled.
- Preserved canonical numeric-string board parent and actor IDs, and validated the uncast comment post ID without truncation or overflow.
- Used the public user UUID for both user trigger subjects.
- Distinguished best-effort source capture from future durable queue acceptance and retry, including source-specific failure behavior.
- Added seven layout rendering regressions, server inheritance coverage, and settings/adapter regression sources.

### Changed

- Kept this correction source-only: no CRUD, source listener, job, executor, installation or activation.
- Retained G7 and board minimum versions: this correction uses existing contracts; no bundled extension declares a dependency on glitter-workflow.

## [0.1.0] - 2026-09-18

### Added

- Added the Glitter Workflow foundation metadata, permissions, settings, admin route shells, and Korean/English translations.
- Added stable trigger definitions and redacted versioned context adapters for user registration/update and board post/comment creation.
- Added typed condition and action contracts without an execution runtime.
- Added module-owned workflow, execution, and action execution schema definitions.
- Documented at-least-once execution semantics, G7 integration boundaries, and security exclusions.

## [0.2.0] - 2026-09-18
- Added validated workflow-definition CRUD API with separate activation permissions.
- Added atomic definition version checks and administrator activity logging.

## [0.3.0] - 2026-09-18
- Completed the bounded admin WHEN / IF / THEN definition editor surface.

## [0.4.0] - 2026-09-18
- Added durable acceptance storage and synchronous board post capture.

## [0.5.0] - 2026-09-18
- Added acceptance processing, strict condition matching, execution snapshots, and action planning.

## [0.6.0] - 2026-09-18
- Added sequential delay action execution with durable scheduling.

## [0.6.1] - 2026-09-18
- Corrected planning handoff and bounded stale action recovery.

## [0.7.0] - 2026-09-18
- Added bounded operational recovery for pending runtime handoffs.
