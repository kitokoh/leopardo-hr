# Branch Protection — Required Settings

> Miroir de `BRANCH_PROTECTION_REQUIRED.md` (racine) — la racine reste la
> source de vérité documentaire, synchronisée avec le référentiel machine de la
> garde #2011 (`dev-hub/tools/branch-protection-canonical.json`).

## Protection réelle de `main` (vérifiée via API le 2026-09-05)

- `enforce_admins` : activé (aucun push direct, même admin)
- Branche à jour avant merge (`strict`) : activé
- Reviews : non requises (`required_approving_review_count` absent) — à activer quand l'équipe dépasse 2 développeurs actifs
- `allow_force_pushes` / `allow_deletions` : désactivés
- Merge Queue GitHub : non configurée (0 ruleset) — à activer si > 15 PRs/jour

### Les 5 required checks (bloquent le merge)

| Check requis | Workflow émetteur |
|---|---|
| `Backend Coverage (PHP 8.4 + PostgreSQL 16)` | `coverage-gate.yml` |
| `PHPStan — Strict (Core/Modules/Shared, level 8)` | `architecture-check.yml` |
| `Module Structure Validator` | `architecture-check.yml` |
| `Frontend — ESLint + TypeScript` | `architecture-check.yml` |
| `actionlint (+ shellcheck)` | `actionlint.yml` |

### Portes qualité & sécurité exécutées sur chaque PR (non bloquantes au merge, mais surveillées)

- **CodeQL (Actions)** — analyse de sécurité du code (workflow `codeql.yml`, hebdomadaire + PR)
- **Backend Quality (Pint + PHP Syntax + PHPStan/Larastan)** — formatage Pint + lint PHP + analyse statique diff-scopée (workflow `tests.yml`)
- Backend Security (Composer Audit) — audit des dépendances
- TruffleHog Secret Scan — détection de secrets
- Dependency Review (PR Security)
- Semgrep OSS
- Governance Gates (changelog + canonical files) — `dev-hub/tools/check-governance.ps1`
- OWASP ZAP Baseline (non bloquant, flag `-I`)
- Ratio fix/feat (`fix-feat-ratio-guard.yml`) — signal fort, non requis

> Les portes ci-dessus s'ajoutent aux 5 required checks ; seuls les 5 required
> checks bloquent effectivement le merge (protection réelle, cf. garde #2011).
