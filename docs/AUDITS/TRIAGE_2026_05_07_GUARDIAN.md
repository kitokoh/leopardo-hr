## Guardian Daily PR Triage — 2026-05-07

### ✅ Mergeable
- **Branch `feat/scout-authenticated-guardrails-test-640514022885247161`** — 🧪 Scout: [MVP regression test] - authenticated guardrails
  - Reason: Clean merge, includes critical security regression tests for session revocation (archived/suspended/expired status), no feature creep.
  - Action: Merge now.
- **Branch `sentinel/evaluation-security-hardening-13420226204407793637`** — 🛡️ Sentinel: Evaluation security hardening and tenant isolation
  - Reason: Clean merge, hardens RBAC and tenant isolation for the Evaluation module (FormRequests + Policies), includes robust regression tests.
  - Action: Merge now.
- **Branch `janitor/root-log-hygiene-11962499849136235831`** — chore: remove accidental CI logs from root and update .gitignore
  - Reason: Clean merge, improves hygiene by removing binary log artifacts accidentally tracked in the root.
  - Action: Merge now.

### 🛠️ Needs Small Fix
- **Branch `palette/auth-ux-enhancements-16838490731817362955`** — 🎨 Palette: Improve auth UX with tactile feedback and accessibility labels
  - Problem: PR is technically sound but blocked by missing `CHANGELOG.md` entries for critical scope (haptics and semantics).
  - Next step: Needs a tiny CHANGELOG.md update for auth UX.
- **Branch `codex/ci-cd-incremental`** — fix(web): add vue eslint configuration
  - Problem: Missing `CHANGELOG.md` entry for CI scope change.
  - Next step: Needs a tiny CHANGELOG.md update for ESLint config.

### ⛔ Do Not Merge
- **Branch `palette/improve-history-accessibility-1392791774178107559`**
  - Risk: **HIGH**
  - Reason: Severe merge conflicts and version drift. Risks reverting recent i18n changes.
- **Branch `scout/salary-advance-security-test-16458298079036426877`**
  - Risk: **MEDIUM**
  - Reason: Overlaps with newer Sentinel hardening and has merge conflicts.
- **Branch `contractor/harden-contract-guards-4.1.87-10572855889870769793`**
  - Risk: **HIGH**
  - Reason: Massive conflicts and outdated version (v4.1.87).
- **Branch `fix/cross-tenant-email-collision-1381698075372384106`**
  - Risk: **MEDIUM**
  - Reason: Risks reverting optimizations and contains potentially breaking ID cast changes.

### 🗑️ Close / Recreate
- **Branch `bolt/optimize-hr-referentials-17196052419913291124`** (Already merged)
- **Branch `sentinel/harden-salary-advance-v4187-4039245529817649518`** (Already merged)
- **Branch `release/merge-all-features-v4186`** (Obsolete)

### Summary
- Merge now: 3
- Fix first: 2
- Reject/close: 10
- Human validation needed: 3
