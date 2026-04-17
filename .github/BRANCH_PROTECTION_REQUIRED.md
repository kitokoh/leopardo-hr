# Branch Protection - Required Settings
# Version 4.1.14 | 2026-04-17

Apply these settings in GitHub repository rules for `main` and `develop`.

## Required protections
- Require a pull request before merging
- (Optional) Require at least 1 approval (enable only if you have 2+ humans with write access)
- (Optional) Dismiss stale approvals when new commits are pushed
- Require status checks to pass before merging
- Required checks:
  - `Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)`
  - `Backend Security (Composer Audit)`
  - `Mobile Flutter (Stable Channel)`
  - `Governance Gates (changelog + canonical files)`
  - `Dependency Review (PR Security)`
  - `CodeQL (Backend)`
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
- Merge only after PR checks are green; deployment starts after merge on `main`

## Repository security settings
- Enable **Dependabot alerts**
- Enable **Dependabot security updates**
- Enable **Secret scanning**
- Enable **Push protection for secrets**
- Enable **Code scanning** results in the Security tab

## Why this is mandatory
- Prevents unreviewed merges
- Enforces changelog/governance discipline
- Blocks regressions without tests
- Bloque aussi les dependances vulnerables et les regressions de qualite avant deploiement
- Ajoute une analyse statique de securite du code backend avant merge
