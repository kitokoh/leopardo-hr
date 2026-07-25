# ADR 0010 - Marketplace plugin permissions, billing gating and webhooks model

## Status

Accepted.

**Date**: 2026-07-25

## Context

PA2-STR-004 asks for a marketplace architecture note covering "plugins,
permissions, billing, future webhooks". ADR 0004
(`0004-open-core-marketplace-boundaries.md`) already decided *that* the
marketplace is exposed through public contracts (API, signed webhooks,
scopes) rather than direct code import, and
`docs/GUIDES/GUIDE_OPEN_CORE_MARKETPLACE.md` sketched a *proposed* scope
table and plugin manifest shape. What was still missing was how those
proposals map onto the pieces that already exist in code today:

- `Feature` (`api/app/Modules/Billing/Domain/Models/Feature.php`) already
  stores a `permissions` array and exposes a `toManifestArray()` used by
  `FeatureManifestController` to serve `/api/v1/features/manifest` —
  effectively an existing "capability manifest" mechanism, currently
  scoped to first-party mobile feature gating.
- `FeaturePlanMatrix` (`feature_plan_matrix` table) already gates a
  `feature_key` on/off per subscription `plan`, with an optional
  `limit_value` — the existing billing-gating primitive.
- `WebhookEndpoint` / `WebhookController` (`AVAILABLE_EVENTS`,
  dead-letter + replay from PA2-API-006) already implement signed,
  retried, dead-lettered webhook delivery per tenant.
- `Partner` / `Commission` (`api/app/Modules/Billing/Domain/Models/`)
  already model a partner/referral relationship with commission payouts,
  which is the natural billing counterpart for a paid marketplace listing.
- Sanctum tokens already carry an `abilities` array
  (`api/app/Http/Middleware/TenantMiddleware.php`,
  `TokenAutoRefreshMiddleware.php`), and ability strings already follow a
  `resource.action` convention (`tenant_schema:...` prefix style, and the
  scope table in the marketplace guide: `companies.read`,
  `employees.read`, ...).

This ADR closes the gap between the strategic guide (aspirational) and
the code (partial, first-party only) by specifying how a **third-party
marketplace plugin** would plug into these existing primitives without
introducing a second, competing permissions/billing/webhook system.

## Decision

### Plugin identity and manifest

A marketplace plugin is registered platform-side as a manifest, reusing
the `Feature` shape rather than inventing a new table:

```json
{
  "key": "plugin.acme_payroll_export",
  "publisher": "Acme Ltd.",
  "version": "1.2.0",
  "support_url": "https://acme.example/support",
  "scopes": ["employees.read", "payroll.read", "webhooks.manage"],
  "webhooks_consumed": ["payroll.validated", "employee.archived"],
  "endpoints_called": ["https://acme.example/hooks/leopardo"],
  "data_policy_url": "https://acme.example/data-policy",
  "sandbox": true
}
```

- `key` is namespaced with a `plugin.` prefix so first-party and
  marketplace features stay trivially distinguishable in the same
  `features` table and in `/api/v1/features/manifest` output.
- `scopes` reuses the exact ability strings from the scope table in
  `GUIDE_OPEN_CORE_MARKETPLACE.md` (`companies.read`, `employees.read`,
  `attendance.read`, `attendance.write`, `tasks.read`, `tasks.write`,
  `documents.read`, `documents.write`, `payroll.read`,
  `webhooks.manage`). No new scope vocabulary is introduced here; this
  ADR fixes those strings as the canonical scope names.
- A plugin's manifest is reviewed and stored server-side before any
  tenant can install it (see "Security review gate" below); tenants never
  submit or edit `scopes` themselves.

### Permissions: scopes ride on Sanctum abilities, not a new ACL

- A marketplace plugin installation issues a dedicated Sanctum personal
  access token scoped with `createToken($name, $manifest['scopes'])`,
  exactly like `ApiTokenController` already does for first-party API
  tokens. There is no parallel permission engine.
- `TenantMiddleware` already resolves `tenant_schema` / `tenant_company`
  from token abilities; this ADR adds no new middleware. A plugin token
  is a normal tenant-scoped Sanctum token whose `abilities` are exactly
  the manifest's `scopes` (plus the existing `tenant_*` binding
  abilities). Route-level authorization keeps using `$request->user()`
  and `tokenCan()` / policy checks like any other authenticated request.
- **No implicit global scope.** A plugin token that only has
  `employees.read` cannot call a `payroll.*` endpoint even if the
  installing user's own role could — this mirrors the existing rule in
  the marketplace guide ("Aucun scope global n'est implicite") and is
  enforced the same way first-party feature `permissions` are checked in
  `FeatureManifestController::userHasFeaturePermissions()`.
- Revoking a plugin (uninstall) deletes its Sanctum token(s), which is
  immediate and requires no extra bookkeeping — consistent with how
  Sanctum tokens are already revoked elsewhere in the codebase.

### Billing: gate plugin availability with `FeaturePlanMatrix`, gate plugin revenue with `Partner`/`Commission`

Two distinct billing questions, two existing mechanisms, no new ones:

1. **"Can this tenant install/use this plugin at all?"** — reuse
   `FeaturePlanMatrix`. A row `{feature_key: 'plugin.acme_payroll_export',
   plan: 'pro', enabled: true}` gates the plugin exactly like any other
   first-party feature is gated per subscription plan today. A tenant on
   a plan without that row (or with `enabled=false`) never sees the
   plugin as installable, checked at install time and at token-issuance
   time, not only in UI.
2. **"Does the publisher get paid for this install?"** — reuse
   `Partner` + `Commission`. A plugin publisher is modeled as a
   `Partner` (`type` distinguishes referral partners from marketplace
   publishers), and each paid plugin activation creates a `Commission`
   row linked to the underlying `Payment`, following the exact same
   `applied_rate` / `net_amount` / `status` lifecycle already used for
   referral commissions. This avoids building a second payout ledger
   for marketplace revenue.
- Free/open-source plugins skip step 2 entirely: no `Partner` /
  `Commission` rows are created, only the `FeaturePlanMatrix` gate (which
  may simply always be `enabled: true`) applies.

### Webhooks: marketplace plugins are webhook *consumers*, using the model already shipped

- The webhook delivery model is already implemented and does not change
  for marketplace plugins: `WebhookEndpoint` (per-company, HTTPS-only via
  `NotPrivateUrl`), `AVAILABLE_EVENTS`
  (`api/app/Modules/Billing/Interfaces/Api/V1/WebhookController.php`),
  HMAC signature + timestamp header contract documented in
  `docs/GUIDES/GUIDE_INTEGRATION_PARTENAIRES.md`, retry backoff
  (30s/120s/600s), auto-disable after 10 cumulative failures, and
  dead-letter + replay (PA2-API-006, already shipped).
- A marketplace plugin subscribes to events the same way any partner
  integration does: it must hold the `webhooks.manage` scope to call
  `POST /api/v1/webhooks` and register a `WebhookEndpoint` for the events
  it declared in `webhooks_consumed`. The plugin manifest's
  `webhooks_consumed` list is validated against `AVAILABLE_EVENTS` at
  review time so a plugin cannot silently claim to listen for an event
  the platform does not actually emit.
- Event *names* proposed in the marketplace guide
  (`salary_advance.requested`, `task.assigned`, `document.generated`,
  ...) are aspirational until the corresponding domain action actually
  dispatches them through `ProcessWebhook` / `DispatchWebhook`. This ADR
  does not add those events; it only fixes that whenever they do ship,
  they are added to the single `AVAILABLE_EVENTS` constant rather than a
  marketplace-specific event list, so first-party partners and
  marketplace plugins share one event catalog.

### Security review gate

Before a plugin manifest is activated for any tenant:

1. Manual review of requested `scopes` against the plugin's stated
   purpose (least privilege — a payroll export plugin has no business
   requesting `documents.write`).
2. `endpoints_called` and `webhooks_consumed` are recorded and must match
   what the plugin actually calls/receives in sandbox testing.
3. `sandbox: true` plugins can only be installed on non-production
   tenants until explicitly promoted, consistent with ADR 0004's rule
   that "une extension qui ecrit des donnees doit etre testee sur
   sandbox avant activation production".
4. Only after review does the plugin's `Feature` row move to
   `status: active`, which is what makes it eligible for install (see
   `Feature::scopeActive()`).

## Consequences

- No new database tables are introduced by this ADR. Marketplace plugins
  are `Feature` rows with a `plugin.` key prefix, gated by the existing
  `FeaturePlanMatrix`, authenticated via scoped Sanctum tokens, and
  metered/paid via the existing `Partner`/`Commission` models.
- The scope vocabulary in `GUIDE_OPEN_CORE_MARKETPLACE.md` is now
  normative for plugin manifests, not just illustrative; adding a new
  scope means updating that table and this ADR together.
- `AVAILABLE_EVENTS` remains the single source of truth for webhook
  event names available to both first-party partners and marketplace
  plugins; no parallel "marketplace events" list should be created.
- Revoking a plugin is a token revocation + `FeaturePlanMatrix`/`Feature`
  status change, not a data-migration exercise.
- This ADR does not implement plugin installation UI, an admin review
  workflow UI, or new event emissions — those remain separate,
  independently schedulable tickets that can now be scoped against a
  fixed permissions/billing/webhooks model instead of an open design
  question.
