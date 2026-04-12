# Branch Protection - Required Settings
# Version 4.1.12 | 2026-04-05

Apply these settings in GitHub repository rules for `main` and `develop`.

## Required protections
- Require a pull request before merging
- (Optional) Require at least 1 approval (enable only if you have 2+ humans with write access)
- (Optional) Dismiss stale approvals when new commits are pushed
- Require status checks to pass before merging
- Required checks:
  - `Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)`
  - `Mobile Flutter (Stable Channel)`
  - `Governance Gates (changelog + canonical files)`
- (Optional) `Notify Result` (redundant; require only if you really want a single "final" check)
- Require branches to be up to date before merging
- Include administrators
- Block force pushes
- Block deletions

## Critical note (avoid "Expected" checks)
- Do NOT require old/deleted check names like `Mobile Flutter (stable)`.
- Required check names MUST match `.github/workflows/tests.yml` job names exactly.

## Merge policy
- Squash merge only
- Delete branch after merge

## Why this is mandatory
- Prevents unreviewed merges
- Enforces changelog/governance discipline
- Blocks regressions without tests
