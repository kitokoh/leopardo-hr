# Branch Protection - Required Settings
# Version 4.1.4 | 2026-04-04

Apply these settings in GitHub repository rules for `main` and `develop`.

## Required protections
- Require a pull request before merging
- Require at least 1 approval
- Dismiss stale approvals when new commits are pushed
- Require status checks to pass before merging
- Required checks:
  - `Backend (PHP 8.3 + PostgreSQL 16 + Redis 7)`
  - `Mobile Flutter (stable)`
  - `Governance Gates (changelog + canonical files)`
  - `Notify Result`
- Require branches to be up to date before merging
- Include administrators
- Restrict force pushes
- Restrict deletions

## Merge policy
- Squash merge only
- Delete branch after merge

## Why this is mandatory
- Prevents unreviewed merges
- Enforces changelog/governance discipline
- Blocks regressions without tests
