# QA Session — Expert 14 (session 2, 2026-08-16)

> Continuation de l'audit 360° + consolidation + implémentation.

## Phase 1 — Nouveaux constats vérifiés

| Constat | Preuve | Issue |
|---|---|---|
| SDK JS/Python périmés vs openapi.yaml (edge .sha256, /departments, /reports/*) | `generate-openapi-sdk.mjs --check` → échec | #4103 |
| validate-and-sync rouge — drift catalogues générés (6 fichiers : web fr.json pricing périmé, admin clé résiduelle, ARB manquants) | sync scripts locaux → 6 fichiers modifiés | #4117 |
| PayrollCalculatorCoverageTest + GoldenDzProrataOvertimeTest périmés vs #2685 (4327.0≠4327.01, 6923.2≠6923.21, 4846.24≠4846.25) | calcul exact sous Python | #4119 |
| validate-and-sync rouge — 28 PNG LFS-trackés commités en blobs pleins (git-lfs ≥3.7 les réécrit) | log CI `Encountered 28 files that should have been pointers` | #4124 |
| E2E marketing-funnel flaky (hero vs signup selon le run) | 2 runs locaux, tests différents | env. sandbox lent (hydration/cold compile) |

## Phase 2 — Clôtures avec preuve (déjà corrigés sur main)

- #4105 (blade login ARIA — d2eb7d89), #3961 (deploy concurrency — 094d26db), #3920 (case-studies honnêtes — 43e58b28), #3964 (install.sh pipefail — #4086), #3937 (window.confirm — f69ec3a3, ConfirmDialog partagé).

## Phase 3 — Implémentation (PRs)

| PR | Sujet | Statut |
|---|---|---|
| #4112 | SDK resync (Closes #4103) | ouverte |
| #4114 | .env.local.example SITE_URL domaine de marque (Closes #3918) | ouverte |
| #4115 | timeouts couverture PHPUnit (Closes #4111) | ouverte |
| #4116 | leopardo_hr iOS PRODUCT_NAME (Closes #4087) | ouverte |
| #4118 | resync catalogues i18n (Closes #4117) | ouverte |
| #4121 | attentes overtime réalignées #2685 (Closes #4119) — débloque Payroll + #4108 | ouverte |
| #4122 | E2E recruitment vs route supprimée #3837 (Closes #4106) | ouverte |

## Leçons

- **Toujours `git branch --show-current` avant commit/amend** — un amend est parti sur la mauvaise branche (corrigé).
- Les échecs CI partagés (PHPStan drift → #4108, composer.lock → #4110, E2E flaky, PNG LFS) touchent TOUTES les PRs — vérifier qu'un échec est spécifique à son PR avant de le corriger.
- `git lfs migrate import --no-rewrite` est la voie propre pour #4124 mais impacte les agents sans git-lfs → PR dédiée prudente.
