# CI/CD Workflows — Leopardo HR

> Cartographie vérifiée le 2026-09-01 (audit #6606) : table complète générée depuis
> les fichiers `.github/workflows/*.yml` du repo. Garde : ce README doit rester
> aligné sur la liste réelle des workflows (re-générer la table après tout ajout/
> suppression de workflow).

## 🔁 Composite actions partagées (`.github/actions/`)

Ces actions composites remplacent les anciens workflows reutilisables `_setup-php.yml`
et `_setup-flutter.yml` (supprimés, voir `CHANGELOG.md` v4.23.4). Elles sont appelées
depuis les steps des workflows ci-dessous, pas declenchees directement.

| Action | Rôle |
|---|---|
| `.github/actions/setup-backend-db` | PHP + Composer + bootstrap migrations multi-tenant (public + shared_tenants) contre postgres/redis |
| `.github/actions/setup-flutter-android` | Java 17 + Flutter pour les builds Android (utilisee par `mobile-apps-ci.yml`, `mobile-distribute.yml`, `deploy-main.yml`) |

---

## 🛡 Gardes anti-régression du job Hygiene Guards (`architecture-check.yml`)

| Garde | Détecte | Issue |
|---|---|---|
| `check-public-routes.sh` | Route publique canonique perdue dans `route:list` (controller non routé) | #5519 |
| `check-duplicate-use-imports.sh` | Double `use` / alias `as` dupliqué dans `routes/**` + `Providers/**` | #5519 |
| `check-providers-syntax.sh` | `*ServiceProvider.php` avec erreur de syntaxe (`php -l`) | #5519 |

---

## 📋 Cartographie complète (50 workflows, 2026-09-01)

Légende déclencheurs : **PR** = `pull_request` · **push** = `push` (branche cible entre crochets) ·
**manuel** = `workflow_dispatch` · **cron** = `schedule` · **merge_group** = `merge_group` ·
**wr→X** = `workflow_run` déclenché par le workflow X.

### CI — Pull requests & branches

| Fichier | Déclencheur | Rôle |
|---|---|---|
| `tests.yml` | manuel, PR, push[main] | Tests backend (PHPUnit/Pest) + mobile Flutter |
| `coverage-gate.yml` | manuel, PR, push[main], merge_group | Seuil de couverture PHP (back-end coverage gate) |
| `backend-jobs-ci.yml` | manuel, PR, push[main] | Tests des Jobs/Queues Laravel |
| `architecture-check.yml` | PR, push[main], merge_group | Règles d'architecture DDD + Hygiene Guards (voir plus haut) |
| `openapi-ci.yml` | PR, push[main] | Validation spec OpenAPI (lint + couverture routes) |
| `i18n-enterprise.yml` | PR, push[main] | Sync/validation i18n multi-apps (corrigé #6606 : `leopardo_hr/lib/**` était fusionné dans une autre entrée `paths:` et ne déclenchait jamais) |
| `pr-issue-guard.yml` | PR | Chaque PR référence une issue (`Closes/Fixes/Resolves #N`) |
| `issue-governance-guard.yml` | cron, manuel | Garde de gouvernance des issues (quotas, libellés) |
| `fix-feat-ratio-guard.yml` | PR, manuel | Ratio fix/feat par fenêtre (vars `FIX_FEAT_RATIO_*`) |
| `fix-feat-ratio-report.yml` | cron, manuel | Rapport hebdo du ratio fix/feat |
| `branch-hygiene.yml` | cron, manuel | Nettoyage branches mortes/stale |
| `branch-protection-guard.yml` | manuel, PR | Vérifie la protection de branche (token `BRANCH_PROTECTION_TOKEN`) |
| `bc-batch-branch-protocol.yml` | cron, manuel | Protocole de branches BC (batch) |
| `crm-branch-protocol.yml` | cron, manuel | Protocole de branches CRM |
| `merge-health-ci.yml` | PR, push[main], manuel | Merge Health Guard (santé avant merge) |
| `merge-quota-guard.yml` | PR, manuel | Quota quotidien de merges (var `MERGE_DAILY_QUOTA`) |
| `post-merge-conventions-audit.yml` | push[main] | Audit des conventions post-merge |
| `country-catalog-check.yml` | PR, push[main] | Catalogue pays cohérent |

### CI — Modules applicatifs

| Fichier | Déclencheur | Rôle |
|---|---|---|
| `accounting-ci.yml` | PR, push[main], manuel | Module Gate Comptabilité |
| `payroll-ci.yml` | PR, push[main], manuel | Payroll CI — Golden & Conformité |
| `kiosk-ci.yml` | PR, push[main], manuel | CI ZKTeco Kiosk (i18n + feedback + 27 tests Python bridge) |
| `web-ci.yml` | manuel, push[main], PR | Lint + test + E2E Vue.js (admin-dashboard) |
| `web-marketing-ci.yml` | manuel, push[main], PR | Lint + test + E2E Next.js (vitrine) |
| `web-offline-ci.yml` | PR, push[main], manuel | Web Offline CI — PWA Edge |
| `mobile-apps-ci.yml` | manuel, PR, push[main] | Build + tests Flutter (7 apps) |
| `e2e-isolated.yml` | manuel, PR | E2E admin/backend isolé |
| `onboarding-smoke.yml` | manuel, PR, push[main] | Onboarding Smoke Test |
| `lighthouse.yml` | manuel, PR, push[main], cron | Lighthouse CI (perf web) |

### 🚀 Déploiement

| Fichier | Déclencheur | Rôle |
|---|---|---|
| `deploy-main.yml` | push[main], manuel | Déploiement production (Render) |
| `deploy-staging.yml` | push[main], manuel | Déploiement staging (hook Render staging ; **aucun** `workflow_run`) — fail-fast si `STAGING_API_URL`/`RENDER_STAGING_DEPLOY_HOOK_URL` absents (#1485) |
| `deploy-admin-dashboard.yml` | manuel, push[main] | Déploiement admin-dashboard Cloudflare Pages |
| `mobile-distribute.yml` | push, manuel | Build + distribution Firebase APK/IPA |
| `mobile-distribute-main.yml` | push[main], manuel | Distribution mobile depuis main |
| `release.yml` | push, manuel | Création de release GitHub (tags v*.*.*) |

### 🔍 Qualité / observabilité / post-déploiement

| Fichier | Déclencheur | Rôle |
|---|---|---|
| `e2e-staging.yml` | manuel, wr→**Deploy - Leopardo RH** (`deploy-main.yml`) | E2E Playwright Prod Smoke — **attention** : le nom « staging » est trompeur, il tourne après le déploiement **prod** (pas staging) |
| `owasp-zap.yml` | manuel, wr→**Deploy - Leopardo RH** (`deploy-main.yml`) | Scan de sécurité OWASP ZAP Baseline post-déploiement prod |
| `k6-load-smoke.yml` | manuel | Smoke de charge k6 |
| `queue-supervision.yml` | cron, manuel | Supervision queues prod (vars `QUEUE_MAX_*` + secrets DB queue) |
| `queue-worker-fallback.yml` | cron, manuel | Fallback worker queues GH Actions |
| `ci-observability.yml` | cron, manuel | Observabilité CI |
| `cleanup-orphan-runs.yml` | cron, manuel, PR | Nettoyage runs orphelins |
| `database-backup.yml` | manuel, cron | Backup & restore drill (S3 + age) |
| `launch-api-profile-smoke.yml` | manuel | Smoke de profilage API |
| `launch-observability-smoke.yml` | manuel, cron | Smoke observabilité |
| `admin-pages-deploy-guard.yml` | manuel, cron | Garde de dérive déploiement admin (Cloudflare) |
| `fix-composer-lock.yml` | manuel | Régénère `composer.lock` |
| `phpstan-baseline.yml` | manuel | Régénère les baselines PHPStan |
| `secret-scan.yml` | manuel, PR, push[main] | Détection de secrets commités (TruffleHog) |
| `secret-history-scan.yml` | cron, manuel | Scan d'historique des secrets (hebdo) |
| `codeql.yml` | PR, push[main], cron | Analyse de sécurité CodeQL |
| `actionlint.yml` | PR, push[main], merge_group | Lint des workflows + shellcheck |

### 🚫 Fantômes supprimés (existent plus dans le repo)

`api-lint.yml` (remplacé par `openapi-ci.yml`), `dependabot.yml` (c'est une config Dependabot, pas un workflow — voir `.github/dependabot.yml`), `mobile-test.yml` (remplacé par `mobile-apps-ci.yml`). Aucun de ces fichiers n'existe dans `.github/workflows/`.

---

## ⚠️ Points d'attention (audit #6606)

1. **`e2e-staging.yml` ne valide PAS le staging** : son `workflow_run` cible **« Deploy - Leopardo RH »** (`deploy-main.yml`), donc les E2E/ZAP tournent après la **prod**. Le staging n'a aucune validation E2E post-déploiement. À corriger côté CI (renommer le workflow ou ajouter un vrai run staging).
2. **13 repo `vars` et 8 secrets queue** documentés dans `docs/CI_CD_SECRETS.md` (voir sections dédiées) — notamment `PROD_API_BASE_URL`/`PROD_ADMIN_URL`/`PROD_WEB_URL` qui font échouer les E2E en fail-closed s'ils manquent.
