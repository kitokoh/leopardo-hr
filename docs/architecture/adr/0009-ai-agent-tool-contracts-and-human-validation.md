# ADR 0009 - AI agent tool contracts: permissions, audit and human validation

## Status

Accepted.

**Date**: 2026-07-25

## Context

PA2-STR-005 asks for a strategy document covering, for AI-driven business
actions: **permissions, audit, and human validation**. The underlying
mechanism already exists in code (`api/app/AI/*`, `AIGatewayController`,
`routes/ai.php`, `AIWriteActionConfirmationTest`) but was never written up
as a strategy/reference document, so a reviewer auditing "is this safe to
let an LLM call business tools" had to read five classes to find the
answer. This ADR is the missing "model/doc" artifact, matching the pattern
already used for ADR 0008 (PA2-PAY-006): describe the design that already
exists in code rather than introduce a second, competing mechanism.

Leopardo RH exposes a conversational AI assistant (`POST /api/v1/ai/chat`)
that can call structured "tools" (functions) on behalf of an authenticated
employee/manager. Some tools only **read** tenant data (e.g. list
employees, get headcount). Others **write** tenant data (e.g. create an
absence request, approve an absence, check an employee in/out). Because
the caller is an LLM and not a human clicking a button, every write path
needs an explicit permission model, an audit trail, and — for anything
that changes state — a human confirmation step before execution.

## Decision

### 1. Tool registry and permissions (RBAC-scoped, not global)

- Tools are declared in the `ai_tool_registry` table (`AIToolRegistryEntry`
  model, `database/migrations/tenant/2026_05_11_000001_create_ai_tables.php`)
  with `name`, `description`, `parameters` (JSON schema), `required_role`,
  `required_permissions`, `module`, `active`.
- `ToolRegistry::getToolsForRole()` filters the registry by a simple role
  hierarchy (`employee` < `manager` < `admin` < `super_admin`) before a
  tool is ever offered to the LLM via `getToolsAsLLMFormat()`. A tool
  requiring `manager` is never presented to an `employee` session — the
  LLM cannot "talk its way" into calling a tool it was never given.
- Every tool execution is additionally scoped to the caller's
  `company_id` inside `IntentEngine`/`WriteActionRunner` (e.g.
  `Employee::where('company_id', $companyId)->where('id', $employeeId)`),
  so even a tool the role is allowed to call cannot read or write another
  tenant's data. This mirrors the RBAC/tenant-isolation model used
  everywhere else in the backend (see ADR 0006 - Auth in Core); the AI
  gateway does not get a separate, weaker permission model.
- No tool has an implicit global scope. A tool not present (or not
  `active`) in the registry for the resolved role simply does not exist
  from the LLM's point of view.

### 2. Read vs. write tools: the confirmation gate

- `config('ai.write_tools')` (`api/config/ai.php`) is the single allowlist
  of tool names that **mutate** data: `create_absence`, `approve_absence`,
  `create_employee`, `update_employee`, `check_in_employee`,
  `check_out_employee`, `create_salary_advance`.
- `WriteToolPolicy::requiresConfirmation($toolName)` checks that allowlist.
  `IntentEngine::executeSingleTool()` consults it **before** touching any
  domain model:
  - If the tool is a write tool, the call is never executed directly. The
    engine stores the requested action (tool name + arguments) via
    `PendingActionStore::store()` — a short-lived (default 15 minutes,
    `ai.pending_action_ttl_minutes`) cache entry scoped to
    `company_id` + `user_id` — and returns a `confirmation_required`
    payload with a human-readable `summary` (e.g. "Create a leave
    request from 2026-06-10 to 2026-06-12") plus a `pending_action_id`.
    No row is written to any table at this point.
  - If the tool is read-only, it is dispatched immediately via
    `IntentEngine::dispatchToolAction()` (e.g. `get_employees`,
    `get_headcount`, `search_employees`) — these cannot change state, so
    they do not need a human gate.
- The chat orchestrator (`Orchestrator::handle()`) surfaces every pending
  confirmation from a turn under `pending_confirmations` in the API
  response and stops the tool-calling loop for that turn — it does not
  keep iterating the LLM against a write it hasn't been allowed to run
  yet.

### 3. Human validation endpoints

- `POST /api/v1/ai/actions/{pendingActionId}/confirm` — the authenticated
  user explicitly approves the previously summarized action.
  `AIGatewayController::confirmAction()` re-validates that the pending
  action belongs to the same `company_id` **and** the same `user_id`
  that requested it (`PendingActionStore::pull()`), then executes it via
  `WriteActionRunner::run()`. A different user (even in the same
  company) cannot confirm someone else's pending action — see
  `AIWriteActionConfirmationTest::test_confirm_action_is_scoped_to_authenticated_user`.
- `POST /api/v1/ai/actions/{pendingActionId}/reject` — discards the
  pending action without ever calling the underlying write. No table is
  touched; the cache entry is simply forgotten.
- A pending action that is neither confirmed nor rejected within the TTL
  expires from cache and can no longer be executed — there is no "silent
  auto-approve" path.
- This is the same UX shape already used for human-reviewed business
  actions elsewhere in the product (manager approval of absences/salary
  advances); the AI gateway reuses "propose, then a human confirms" as
  its default posture for any state change, rather than inventing a
  separate AI-specific approval flow.

### 4. Audit trail

- `AIAuditLogger::log()` writes one row to `ai_audit_logs` for **every**
  chat turn: `company_id`, `user_id`, `conversation_id`, the prompt and
  response (truncated to 10k chars), `tools_called` (JSON array of tool
  names actually invoked in that turn, including tools that only
  returned `confirmation_required`), provider, model, token counts, an
  estimated cost in cents, duration in ms, and any error. This runs even
  when a tool call did not execute a write (it still logs that the tool
  was *proposed*), so the audit trail includes attempted, not just
  completed, actions.
- The pending-action confirm/reject/execute step itself does not need a
  second bespoke audit table: the resulting domain write (e.g. the
  `absences` row created by `WriteActionRunner::createAbsence()`) goes
  through the same Eloquent models and `created_by`/`approved_by`
  columns as a human-initiated request, so it inherits the existing
  domain audit trail (e.g. `approved_by` on `absences`) without a
  parallel "AI did this" ledger that could drift from the real state.
- `GET /api/v1/ai/tools` exposes the active tool registry (name,
  description, required role, module) so operators/support can audit
  *what an AI session in a given tenant is even allowed to attempt*
  without reading code.

### 5. What is explicitly out of scope for this ADR

- This document does not introduce new tools, new permissions, or a new
  approval mechanism. It documents the one that ships today
  (`app/AI/ToolRegistry.php`, `WriteToolPolicy.php`, `PendingActionStore.php`,
  `WriteActionRunner.php`, `IntentEngine.php`, `Orchestrator.php`,
  `AIAuditLogger.php`, `AIGatewayController.php`, `routes/ai.php`,
  `config/ai.php`).
- Marketplace/partner-facing tool contracts (third-party plugins calling
  Leopardo RH tools over the public API) are covered by ADR 0004 (open
  core / marketplace boundaries) and `docs/GUIDES/GUIDE_OPEN_CORE_MARKETPLACE.md`
  — those are a distinct, external-facing contract from the internal
  AI-assistant tool-calling described here.

## Consequences

- Any new write tool **must** be added to `config('ai.write_tools')`
  before it can execute directly; forgetting to do so is a bug, not a
  missing feature, because `WriteToolPolicy` fails closed (a tool absent
  from the allowlist that mutates data outside `WriteActionRunner` would
  bypass the confirmation gate — reviewers must check this on every PR
  that adds a new tool implementation).
- Any new tool must be registered in `ai_tool_registry` with an accurate
  `required_role`; a tool the LLM should not call for employees must not
  default to `employee`.
- Any new write tool must be implemented in `WriteActionRunner::run()`
  behind the confirm/reject flow — it must not be wired directly into
  `IntentEngine::dispatchToolAction()` (that dispatcher is reserved for
  read-only tools).
- `AIWriteActionConfirmationTest` is the regression guard for this
  contract (confirmation required, tenant/user scoping on confirm,
  reject leaves no trace, orchestrator surfaces pending confirmations).
  A future ticket adding a new write tool should extend this test file
  with an equivalent case rather than relying on manual QA.
