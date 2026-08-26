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
  - `Backend Quality (Pint + PHP Syntax + PHPStan/Larastan)`
  - `Backend Coverage Gate`
  - `Mobile Flutter (Stable Channel)`
  - `Governance Gates (changelog + canonical files)`
  - `Dependency Review (PR Security)`
  - `CodeQL (Actions)`
- (Optional) `Notify Result` (redundant; require only if you really want a single "final" check)
- Require branches to be up to date before merging
- Include administrators
- Block force pushes
- Block deletions

## État réel vérifié le 2026-08-26 (issue #5634)
- **`Include administrators` n'est PAS actif sur main** (API : `enforce_admins: false`) alors que
  ce document le recommande — les admins (dont les agents agissant avec le token owner) peuvent
  merger avec des checks rouges. C'est la cause racine des « main cassé 2× en 6 jours »
  (rétro pilotes J6). **Recommandation : activer `enforce_admins=true` une fois les checks
  requis verts sur main** (le flipper maintenant bloquerait tout merge tant que main est rouge).
- Checks requis ACTUELS sur main (vérifiés API) : `Backend Coverage (PHP 8.4 + PostgreSQL 16)`,
  `PHPStan — Strict (Core/Modules/Shared, level 8)`, `Module Structure Validator`,
  `Frontend — ESLint + TypeScript`, `actionlint (+ shellcheck)`. La liste ci-dessus (historique)
  contient des noms obsolètes (`Backend Coverage Gate`, `Mobile Flutter (Stable Channel)`…) —
  ne pas les exiger tels quels (cf. note ci-dessous).

## Critical note (avoid "Expected" checks)
- Do NOT require old/deleted check names like `Mobile Flutter (stable)`.
- Required check names MUST match the actual GitHub check names emitted by the workflows.
- `CodeQL (Actions)` scans GitHub Actions workflow security, not PHP application code.

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
- Ajoute une analyse statique de securite des workflows avant merge
