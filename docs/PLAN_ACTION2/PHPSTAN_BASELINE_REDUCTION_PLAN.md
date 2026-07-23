# PHPStan baseline reduction plan (PA2-ARCH-005)

Status: in progress — living document, update the counts below whenever
`api/phpstan-baseline.neon` or `api/phpstan-modules-baseline.neon` changes.

## Why this exists

`docs/PLAN_ACTION2/08_AUDIT_ARCHITECTURE_TECH.md` (section 6, PA2-ARCH-005)
flagged `api/phpstan-baseline.neon` as a large accumulated debt file
(3914 lines, 1168 ignored error occurrences across 652 distinct
error/path pairs at the time of this ticket) with no tracked reduction
plan and no guard against it silently growing on new PRs that touch
already-baselined modules.

The acceptance criterion is: *"plan de reduction par module, suivi du
delta a chaque PR touchant un module ancien"* — a per-module reduction
plan, with the delta tracked on every PR that touches an already-baselined
("old") module.

This document is the plan. `dev-hub/tools/check-phpstan-baseline-delta.sh`
(wired into `.github/workflows/architecture-check.yml`) is the enforcement:
it fails a PR if the ignored-error count for a module under `app/Core/*`,
`app/Modules/*`, or `app/Shared/*` **increases** relative to the PR's base
commit, whenever that PR also touches a PHP file inside that same module.
It only compares modules the PR actually touches, so unrelated PRs are
never blocked by pre-existing debt elsewhere; it never blocks a *decrease*
or a same-count PR, only a net increase — the one-way ratchet a reduction
plan needs.

## Current baseline snapshot (2026-07-23)

Counted directly from `api/phpstan-baseline.neon` (level `max`, applies to
`app`, `routes`, `tests`) and `api/phpstan-modules-baseline.neon` (level 5,
applies only to `app/Core`, `app/Modules`, `app/Shared` via
`phpstan-modules.neon`, the config actually run as a blocking CI gate in
`architecture-check.yml`).

### `phpstan-baseline.neon` (level max — `phpstan.neon`, not currently run in CI as a gate)

| Area | Ignored errors | Distinct error/path entries |
|---|---|---|
| `tests/**` | 717 | 317 |
| `app/Http/**` (legacy controllers, pre-module-migration) | 172 | 125 |
| `app/Modules/Cameras/**` | 116 | 96 |
| `app/Services/**` (legacy, pre-module-migration) | 94 | 82 |
| `routes/**` | 40 | 8 |
| `app/Providers/**` | 10 | 8 |
| `app/Jobs/**` | 8 | 6 |
| `app/Mail/**` | 6 | 5 |
| `app/Support/**` | 3 | 3 |
| `app/Rules/**` | 1 | 1 |
| `app/Traits/**` | 1 | 1 |
| **Total** | **1168** | **652** |

`level: max` on the full legacy surface (including `app/Http` and
`app/Services`, which predate the Clean Architecture module migration) is
materially stricter than what CI actually enforces today (see below). Most
of this file's size is inherent to running `max` against code that was
never written against that level; reducing it module-by-module is real
work, not a quick win, which is why this plan phases it instead of
promising a single PR fix.

### `phpstan-modules-baseline.neon` (level 5 — `phpstan-modules.neon`, **blocking** in `architecture-check.yml` today)

This is the file that actually matters for velocity: it is included by
`phpstan-modules.neon`, which the `phpstan-modules` job in
`architecture-check.yml` runs as a hard gate on every PR touching `main`.

| Module | Ignored errors | Distinct entries |
|---|---|---|
| `app/Modules/HR` | 9 | 6 |
| `app/Modules/Cameras` | 7 | 7 |
| `app/Modules/Attendance` | 6 | 6 |
| `app/Modules/Cabinet` | 6 | 6 |
| `app/Modules/EdgeSync` | 5 | 5 |
| `app/Modules/Billing` | 5 | 4 |
| `app/Shared` | 3 | 3 |
| `app/Core/Auth` | 2 | 2 |
| `app/Core/Feature` | 2 | 2 |
| `app/Modules/Notification` | 2 | 2 |
| `app/Modules/Payroll` | 2 | 2 |
| `app/Modules/Onboarding` | 1 | 1 |
| `app/Modules/Planning` | 1 | 1 |
| `app/Modules/Platform` | 1 | 1 |
| `app/Modules/SmartAttendance` | 1 | 1 |
| **Total** | **53** | **53** |

## Reduction phases

Ordered by ratio of debt to module size / how frequently the module is
touched by active `PA2-*` work (higher-traffic modules get fixed first,
since that is where the anti-regression guard below pays off soonest).

1. **Phase 1 — already-enforced modules baseline (`phpstan-modules-baseline.neon`), highest-traffic first.**
   `app/Modules/Payroll` (2), `app/Modules/HR` (9), `app/Modules/Attendance`
   (6) are touched by nearly every `PA2-COUNTRY-*`/`PA2-ATT-*`/`PA2-PAY-*`
   ticket in flight. Target: drive each to 0 the next time a PR touches
   that module for feature work, rather than as a dedicated cleanup PR —
   this plan's guard (below) makes that the path of least resistance
   automatically, since touching the module without regressing its count
   is already required.
2. **Phase 2 — remaining low-traffic modules in `phpstan-modules-baseline.neon`.**
   `app/Modules/Cameras`, `app/Modules/Cabinet`, `app/Modules/EdgeSync`,
   `app/Modules/Billing`, `app/Shared`, `app/Core/Auth`, `app/Core/Feature`,
   `app/Modules/Notification`, `app/Modules/Onboarding`,
   `app/Modules/Planning`, `app/Modules/Platform`,
   `app/Modules/SmartAttendance`. Same rule: next PR touching the module
   drives its count down, never up.
3. **Phase 3 — `app/Http` and `app/Services` (legacy, pre-migration).**
   These 266 combined ignored errors live entirely in code that predates
   the module migration (`docs/PLAN_ACTION2/08_AUDIT_ARCHITECTURE_TECH.md`
   already tracks migrating remaining `app/Http`/`app/Services` code into
   `app/Modules/*` as a separate concern). Do not spend effort fixing
   PHPStan findings in a controller that is about to be deleted by a
   migration PR; migrate first, then it falls under Phase 1/2's per-module
   rule automatically as part of `app/Modules/*`.
4. **Phase 4 — `app/Modules/Cameras` under `phpstan.neon` (max) and
   `tests/**`.** Highest remaining raw counts (116 and 717 respectively),
   lowest priority: `tests/**` findings are almost always PHPStan being
   overly strict about test doubles/fixtures rather than real bugs, and
   `phpstan.neon` (level max) is not the CI-enforced config today. Revisit
   once Phases 1-3 are clear and only if `phpstan.neon` becomes a blocking
   gate (tracked separately, not part of this ticket's scope).

## Delta guard (enforcement)

`dev-hub/tools/check-phpstan-baseline-delta.sh <base_sha> <head_sha> [api_dir]`:

- Parses `count:`/`path:` pairs out of both baseline files at `base_sha`
  and `head_sha` (via `git show`), buckets them per module using the same
  `app/Core/<X>`, `app/Modules/<X>`, `app/Shared` boundary used in the
  tables above.
- Diffs the PR to find which modules under `app/Core`, `app/Modules`, or
  `app/Shared` have a **PHP file change** (added/modified/deleted) in
  `api/app/**`.
- For every touched module, compares its baseline-file error count at
  `head_sha` vs `base_sha`. Fails if it increased. Passes (including when
  a module isn't touched at all, or when its count decreased or stayed
  equal) otherwise.
- Wired as the `phpstan-baseline-delta` step in the `module-structure-check`
  job of `.github/workflows/architecture-check.yml`, alongside the sibling
  `check-strict-types-new-files.sh` (PA2-ARCH-009) and
  `check-hardcoded-accented-messages.sh` (PA2-I18N-007) guards, following
  the same "diff-scoped, never blocks unrelated pre-existing debt" pattern.

## Updating this document

Whenever `api/phpstan-baseline.neon` or `api/phpstan-modules-baseline.neon`
is regenerated or hand-edited, re-run the counting snippet below from the
`api/` directory and refresh the tables above (and the "Current baseline
snapshot" date):

```bash
python3 - <<'PYEOF'
import re
from collections import defaultdict

def module_of(path):
    parts = path.split('/')
    if parts[0] == 'app' and len(parts) >= 2 and parts[1] in ('Core', 'Modules'):
        return '/'.join(parts[0:3]) if len(parts) >= 3 else '/'.join(parts[0:2])
    return '/'.join(parts[0:2]) if parts[0] == 'app' else parts[0]

for fname in ['phpstan-baseline.neon', 'phpstan-modules-baseline.neon']:
    content = open(fname).read()
    entries = re.findall(r"count:\s*(\d+)\s*\n\s*path:\s*(\S+)", content)
    counts = defaultdict(int)
    for c, p in entries:
        counts[module_of(p)] += int(c)
    print(fname, dict(sorted(counts.items(), key=lambda x: -x[1])))
PYEOF
```
