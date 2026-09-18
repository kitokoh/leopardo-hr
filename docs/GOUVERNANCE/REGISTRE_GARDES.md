# REGISTRE_GARDES — Catalogue des gardes & contrôles du dépôt

> Créé le 2026-09-09 (issue #7120). Toute nouvelle garde (script `dev-hub/tools/*` ou
> workflow `.github/workflows/*`) ajoute une ligne ci-dessous **dans la même PR**.
> Source d'inventaire : `ls dev-hub/tools` + cartographie `.github/workflows/README.md`.
> Vocabulaire : **bloque** = check requis ou fail-closed ; **rapporte** = artefact/notice non bloquant.

## 1. Gouvernance PR & issues

| Garde | Vérifie | Bloque / rapporte |
|---|---|---|
| `pr-issue-guard.yml` + `check-pr-closes-issue.sh` | chaque PR référence une issue (`Closes #N` dans le body) — sauf docs:/chore: | bloque |
| `check-issue-claim-unique.sh` | une issue n'est pas réclamée par 2 PR ouvertes (#5442) | bloque |
| `issue-governance-guard.yml` (`check-issues-*`) | issues fermées sans merge / laissées ouvertes par des PR mergées | rapporte |
| `check-no-claim-marker.sh` | pas de claim marker orphelin | rapporte |
| `check-pr-runs-missing.sh` | PR sans runs CI | rapporte |
| `check-pr-subset-of-lot.sh` (+ `--self-test`) | avant de fermer une PR « couvert par le lot #N » : tout fichier et toute ligne ajoutée de la PR existent dans le lot (#7581) | manuel (outil de preuve), appelé à la clôture |

## 2. Branches & merge

| Garde | Vérifie | Bloque / rapporte |
|---|---|---|
| `branch-protection-guard.yml` + `check-branch-protection.sh` | protection réelle de `main` = canonique (`dev-hub/tools/branch-protection-canonical.json`) | rapporte (horaire) |
| `bc-batch-branch-protocol.yml` + `check-bc-batch-branch-protocol.sh` | une branche `bc/*` par BC, cohérence labels, `Closes #N` | rapporte (quotidien) |
| `check-crm-branch-protocol.sh` | programme CRM : 1 issue = 1 branche = 1 PR | rapporte |
| `branch-hygiene.yml` | hygiène des branches | rapporte |
| `merge-quota-guard.yml` | quota de merges/jour | rapporte (signal fort) |
| `merge-health-ci.yml` + `check-merge-health.sh` | santé des merges (rouge main, etc.) | rapporte |
| `fix-feat-ratio-{guard,report}.yml` | ratio fix/feat ≤ 2,5 (verrou > 3,5) | rapporte |

## 3. Architecture & découpage DDD

| Garde | Vérifie | Bloque / rapporte |
|---|---|---|
| `architecture-check.yml` (`check-*-*.sh` associés) | DDD : placement couche Application (`check-application-layer-placement.sh`), pureté couches (`check-layer-purity.sh`), isolation modules (`check-module-isolation.sh`, `check-*boundary*`), BC registry | bloque |
| `check-bounded-context-registry.sh` / `-dependencies.sh` | registre BC-XX et dépendances inter-BC | bloque |
| `check-duplicate-use-imports.sh`, `check-orphan-interfaces.sh`, `check-unrouted-controllers.sh` | dette d'isolation / orphelins | bloque |
| `check-ai-vendor-boundary.sh` | frontière fournisseurs IA | bloque |

## 4. Backend & données (Laravel/PHP)

| Garde | Vérifie | Bloque / rapporte |
|---|---|---|
| `coverage-gate.yml` (backend ≥ 65 %) | seuil coverage PHPUnit | bloque |
| PHPStan Strict (niveau 8) + `check-phpstan-baseline-*.sh` | analyse statique, dette baseline | bloque |
| `check-migration-basename-collisions.sh` + `check-migrations-*` | collisions de préfixes, conventions BC, quotes SQL, schéma tenant | bloque |
| `check-env-example-parity.sh` / `-safety.sh` | `.env.example` ↔ code, pas de secret | bloque |
| `check-duplicate-routes.sh` + `check-route-owner-guard.sh` + `check-routes-tenant-platform.sh` | routes dupliquées, propriété, isolation tenant | bloque |
| `check-test-schema-drift.sh`, `check-tests-deterministic.sh` | dérive schéma tests, déterminisme | bloque |
| OpenAPI : `check-openapi-coverage.sh`, `check-openapi-route-coverage.py` | routes → spec | bloque |
| `check-golden-tests-required.sh` + `check-golden-journeys.sh` | golden tests Payroll/Accounting obligatoires | bloque |

## 5. Mobile & Flutter

| Garde | Vérifie | Bloque / rapporte |
|---|---|---|
| `validate-mobile-apps-split.ps1` (+ 10 `validate-mobile-*`) | convergence F-27 : tout partage dans `leopardo_core`, zéro copie | bloque |
| `check-mobile-duplicated-drift.sh` | fichiers dupliqués entre apps | bloque |
| `validate-mobile-color-tokens.ps1` | hex hors palette mobile | bloque |
| `check-mobile-l10n-sync.sh`, `check-mobile-manifest-routes.sh`, `check-mobile-number-locale.sh` | i18n, routes, locales | bloque |
| `mobile-apps-ci.yml` | analyze + build APK debug | bloque |

## 6. Design & tokens

| Garde | Vérifie | Bloque / rapporte |
|---|---|---|
| `check-design-token-sync.sh` (+ workflow `design-token-sync.yml`) | COULEURS.md ↔ AppColors ↔ Tailwind (L.07) | bloque |
| `check-web-design-tokens.sh` (+ workflow `web-design-tokens.yml`) | hex hors palette web/admin (allowlist documentée), classes legacy (V1 : avertissement) | bloque (hex) / rapporte (legacy) |
| `public-promises-guard.yml` + `check-public-promises.sh` | sur-promesses vitrine (termes à risque) | bloque/rapporte |

## 7. i18n & contenu

| Garde | Vérifie | Bloque / rapporte |
|---|---|---|
| `check-i18n-catalog-parity.sh`, `check-hardcoded-accented-messages.sh` | parité catalogues, messages hardcodés | bloque |
| `check-accounting-i18n.py`, `check-payroll-i18n.py`, `check-governance-mojibake-test.ps1` | i18n par module | bloque |
| `check-json-catalogs-parse.sh` (+ `--self-test`, branchée dans `actionlint.yml`) | **tous** les JSON/ARB suivis se parsent (#7583) : comble le trou des ~190 fichiers hors chemins i18n, qu'aucune garde ne lisait | bloque |
| `check-double-docblock.py` (`--base` pour l'A/B, `--audit` pour l'inventaire, `--strict` pour tout bloquer ; branchée dans `actionlint.yml`) | aucune NOUVELLE annotation PHPDoc désarmée par un docblock double (#7582) — en PHP seule la DERNIÈRE docblock compte, l'annotation de la première était ignorée | bloque (nouveau) |

## 8. Infra, déploiement & URLs

| Garde | Vérifie | Bloque / rapporte |
|---|---|---|
| `check-canonical-domains.sh` | registre DOMAINS.md machine-vérifiable | bloque |
| `check-render-env-parity.sh` | parité env Render dev/prod | rapporte |
| `check-public-links.sh` | smoke des URLs publiques `live` (P03) | blocage local/CI future |
| `verify-deploy-workflows` (action), `check-workflow-paths.sh` | workflows de déploiement cohérents | bloque |
| `check-deploy-gate-outcome.sh` (+ auto-test, workflow `actionlint.yml`) | verdict du gate de déploiement : `api_changed` mesuré avant le gate, « aucun run requis » qualifié `not-required` (et non `no-runs`), indécision réelle toujours rouge, et **le gate n'exige que ce que `tests.yml`/`web-ci.yml` peuvent produire** (union des prédicats ⊇ leurs `paths:`, aucun chemin venu d'ailleurs) ; durci #7577 : parité stricte `push:` ↔ `pull_request:` des workflows requis, couverture des préfixes `startsWith`, aucun relâchement des prédicats (dont `isApiPath` ↤ `front/admin-dashboard/**`, régression #7533), et `.github/paths-filters.yml` (source de `tests.yml`, pas du gate) jamais plus étroit que les déclencheurs requis — issues #7511, #7528, #7577 | bloque |
| `notification-emitter-guard.yml` + `check-notification-emitter-diff.sh` (+ auto-test) | aucun **nouvel** émetteur de production sur le modèle déprécié `AppNotification` (la garde lit le DIFF : elle ne bloque pas une correction dans un fichier qui l'importait déjà) — décision #7481 / PR #7537, ADR-0013 « Remplacée » | bloque |
| `horizontal-tools-parity.yml` + `check-horizontal-tools-parity.mjs` (+ auto-test) | symétrie stricte du catalogue des outils horizontaux entre `Company::HORIZONTAL_TOOLS` (API, fail-closed), `SELF_ACTIVATABLE_MODULE_KEYS` et `CLIENT_MODULES` **avec `href`** ; chaque flag miroir existe dans `config/feature-flags.php` — défaut réel #7476 | bloque |
| `check-android-sdk-packages.sh` (+ auto-test, `actionlint.yml` + action `setup-flutter-android`) | paquets Android SDK demandés par la CI mobile : refuse un paquet hérité/inexistant dans cmdline-tools 20.0 (`tools`) — issue #7519 | bloque |
| smokes : `smoke-post-deploy.sh`, `launch-observability-smoke.sh`, `launch-api-profile-smoke.ps1` | santé post-déploiement | manuel |

## 9. Docs & DevX

| Garde | Vérifie | Bloque / rapporte |
|---|---|---|
| `check-markdown-links.py` | liens markdown internes (fichiers) | bloque (fichiers), ancre = avertissement |
| `check-runbooks.sh`, `check-architecture-docs-parity.sh` | runbooks présents, docs ↔ architecture | bloque/rapporte |
| `check-governance.ps1`, `repository-hygiene-report.ps1` | gouvernance & hygiène globale | rapporte |

## 9bis. Front admin (console super-admin)

| Garde | Vérifie | Bloque / rapporte |
|---|---|---|
| `check-admin-action-labels.py` (+ auto-test `-test.sh`, workflow `web-ci.yml`) | libellés d'action en dur dans les templates (en-tête « Actions », « Modifier »/« Supprimer »/« Edit »/« Delete ») et actions de ligne sans nom accessible — convention unique `RowActionButton` (issue #7434) | bloque |

## 10. Sécurité

Secret scanning (TruffleHog), CodeQL, `owasp-zap.yml`, `secret-history-scan.yml`,
`check-security-threat-models.sh` — voir `.github/workflows/README.md`.

## Règle de mise à jour

Nouvelle garde → ligne dans ce registre **dans la même PR** (sinon le catalogue dérive —
constat P07). Revue du catalogue : rituel mensuel.
