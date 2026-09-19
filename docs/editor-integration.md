# Admin editor integration status

The current G7 module frontend build contract requires a module `package.json`
and `module:build` output. Glitter Workflow 0.3.0 has no module package or
component entrypoint, and the JSON layout engine consumes the registered
component manifest rather than importing arbitrary module React files. A
module-local React editor therefore cannot be safely registered from this
source-only production checkout without an upstream/module build integration.

The current editor remains the supported JSON layout subset. Dynamic rows,
recursive groups, and specialized conflict UI are intentionally not faked.

## Action availability

The create and edit JSON layouts expose only the executable 0.9.1 actions:
`webhook` and `delay`. `notification` is intentionally absent and is also
rejected by authoritative server validation; UI omission is not the security
boundary. Notification remains reserved until a verified G7 recipient,
authorization, and idempotent-delivery contract exists.

## Delay configuration

`DelayActionConfiguration` and `WorkflowDefinitionValidator` use one canonical
representation: `{ "delay_seconds": <positive integer> }`. The current create
and edit layouts bind `action_plan.0.config.delay_seconds`; no minutes/hours/
days representation is stored or accepted by the authoritative server contract.
