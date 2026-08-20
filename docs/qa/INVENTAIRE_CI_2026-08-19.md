# 🔍 Inventaire CI — Leopardo RH (issue #5145, R2)

**Date** : 2026-08-19 · **Méthode** : GitHub Actions API — dernier run par workflow (`/actions/workflows/<file>/runs?per_page=1`)

**Synthèse** : 43 workflows — **15 rouges** sur le dernier run, 23 verts, 1 en cours, 2 jamais exécutés (baseline PHPStan, dependabot auto). La CI n'est **pas** verte — condition d'entrée du plan 60 jours (gate J16) non remplie.

| Workflow | Dernier run | Conclusion | Verdict |
|---|---|---|---|
| `actionlint.yml` | 2026-08-19 | completed | success | ✅ |
| `admin-pages-deploy-guard.yml` | 2026-08-19 | completed | failure | ❌ |
| `architecture-check.yml` | 2026-08-19 | completed | success | ✅ |
| `backend-jobs-ci.yml` | 2026-08-19 | completed | success | ✅ |
| `branch-protection-guard.yml` | 2026-08-16 | completed | success | ✅ |
| `cleanup-orphan-runs.yml` | 2026-08-19 | completed | success | ✅ |
| `codeql.yml` | 2026-08-19 | completed | success | ✅ |
| `country-catalog-check.yml` | 2026-08-19 | completed | success | ✅ |
| `coverage-gate.yml` | 2026-08-19 | in_progress | - | — |
| `database-backup.yml` | 2026-08-19 | completed | success | ✅ |
| `dependabot-updates` | null | null | - | — |
| `deploy-admin-dashboard.yml` | 2026-08-18 | completed | success | ✅ |
| `deploy-main.yml` | 2026-08-19 | completed | success | ✅ |
| `deploy-staging.yml` | 2026-08-19 | completed | success | ✅ |
| `e2e-isolated.yml` | 2026-08-18 | completed | cancelled | ⏹️ |
| `e2e-staging.yml` | 2026-08-19 | completed | success | ✅ |
| `fix-composer-lock.yml` | 2026-08-09 | completed | success | ✅ |
| `i18n-enterprise.yml` | 2026-08-19 | completed | failure | ❌ |
| `issue-governance-guard.yml` | 2026-08-19 | completed | success | ✅ |
| `k6-load-smoke.yml` | 2026-05-21 | completed | success | ✅ |
| `kiosk-ci.yml` | 2026-08-19 | completed | failure | ❌ |
| `launch-api-profile-smoke.yml` | 2026-06-06 | completed | success | ✅ |
| `launch-observability-smoke.yml` | 2026-08-19 | completed | success | ✅ |
| `lighthouse.yml` | 2026-08-19 | completed | failure | ❌ |
| `mobile-apps-ci.yml` | 2026-08-19 | completed | failure | ❌ |
| `mobile-distribute-main.yml` | 2026-08-18 | completed | failure | ❌ |
| `mobile-distribute.yml` | 2026-08-15 | completed | failure | ❌ |
| `onboarding-smoke.yml` | 2026-08-19 | completed | success | ✅ |
| `openapi-ci.yml` | 2026-08-18 | completed | failure | ❌ |
| `owasp-zap.yml` | 2026-08-19 | completed | success | ✅ |
| `payroll-ci.yml` | 2026-08-19 | completed | failure | ❌ |
| `phpstan-baseline.yml` | null | null | - | — |
| `plan-action2-claim-guard.yml` | 2026-08-19 | completed | failure | ❌ |
| `plan-action2-post-merge-audit.yml` | 2026-08-19 | completed | success | ✅ |
| `plan-action2-project.yml` | 2026-08-16 | completed | cancelled | ⏹️ |
| `plan-action2-weekly-report.yml` | 2026-08-17 | completed | success | ✅ |
| `release.yml` | 2026-08-12 | completed | failure | ❌ |
| `secret-history-scan.yml` | 2026-08-17 | completed | success | ✅ |
| `secret-scan.yml` | 2026-08-19 | completed | success | ✅ |
| `tests.yml` | 2026-08-19 | completed | failure | ❌ |
| `web-ci.yml` | 2026-08-19 | completed | failure | ❌ |
| `web-marketing-ci.yml` | 2026-08-19 | completed | failure | ❌ |
| `web-offline-ci.yml` | 2026-08-19 | completed | failure | ❌ |

## Causes racines identifiées (preuves logs)

### ❌ `tests.yml` — course de création de base de test
Run 32227264880 (2026-08-19 07:27 UTC) : `ERROR: database "leopardo_test_test_4" already exists` répété — les processus de test **parallèles** tentent de créer la même base. Conflit d'infrastructure de test (RefreshTenantDatabase/parallélisme), pas un échec métier : « Run backend unit tests » passe, « feature tests » casse à la création de base.

### ❌ `payroll-ci.yml` — gate coverage Payroll < 80 % et/ou tests module
Jobs en échec : `Coverage Payroll ≥ 80 %` + `Tests module Payroll`. Le gate est volontairement bloquant (spec S-4) — détail des logs à lire.

### ❌ CI web ×3 (`web-marketing-ci.yml`, `web-ci.yml`, `web-offline-ci.yml`)
Lint + E2E Playwright en échec (Funnel E2E, Web E2E). À détailler par job ; vérifier la dépendance aux gates Vercel free tier (#4868).

### ❌ `openapi-ci.yml` — lint du contrat OpenAPI (dernier run 2026-08-18)
Miroir `dev-hub/openapi/v1.yaml` vs source `api/openapi.yaml` — écart de contrat à régénérer.

### ❌ `mobile-apps-ci.yml` — « Mobile apps split guard » + builds
Split guard en échec — à détailler.

### ❌ Divers : `lighthouse`, `kiosk-ci`, `i18n-enterprise`, `admin-pages-deploy-guard`, `plan-action2-claim-guard`, `release`
Une issue fille par cause racine, conformément à l'épic #5145.

### ⚠️ Jobs jamais exécutés ou sans run récent
- `phpstan-baseline.yml` : aucun run — vérifier le déclencheur.
- `k6-load-smoke.yml` : dernier run 2026-05-21 — cadence à décider.

## Verdict par famille

| Famille | Verdict |
|---|---|
| Tests backend (`tests.yml`, `backend-jobs-ci`, `coverage-gate`) | ❌ rouge (course base de test) |
| Paie (`payroll-ci`) | ❌ rouge (gate 80 % / tests) |
| Web (vitrine/admin/offline) | ❌ rouge (lint + E2E) |
| Mobile | ❌ rouge (split guard + build) |
| Sécurité (codeql, secret-scan, secret-history, owasp-zap) | ✅ vert |
| Déploiements (deploy-main, deploy-admin, deploy-staging) | ✅ vert |
| Docs/OpenAPI | ❌ rouge (openapi-ci) |

## Prochaines étapes (issues filles de #5145)
1. `tests.yml` : course `leopardo_test_test_4` → sérialiser/paramétrer les bases de test (P1)
2. `payroll-ci` : lire les logs du job Coverage → gap de couverture ou flaky (P1)
3. CI web : diagnostiquer lint + E2E, vérifier #4868 (P1)
4. `openapi-ci` : régénérer le miroir SDK (P2)
5. Jobs jamais exécutés : vérifier les déclencheurs (P2)
6. `lighthouse`/`kiosk`/`i18n`/`admin-pages-guard`/`claim-guard`/`release` : une issue fille par cause (P2/P3)

---
*Inventaire généré depuis les issues #5144/#5145 — 2026-08-19. À re-snapshotter chaque vendredi (bilan KPI).*

---

## Appendice — Inventaire détaillé (analyse neo/5153)

# Inventaire CI — 2026-08-19

**Issue** : #5145  
**Date** : 2026-08-19  
**Total workflows** : 43

---

## Résumé

| Statut | Compte |
|--------|--------|
| 🟢 GREEN (dernier run success) | 24 |
| 🔴 RED (dernier run failure) | 15 |
| ⚪ CANCELLED / NO RUNS | 4 |

**Objectif** : 5 runs consécutifs verts sur `tests.yml` + `coverage-gate.yml`.

---

## Workflows critiques — RED

### 1. `tests.yml` — Tests - Leopardo RH ⚠️ PRIORITÉ 1
- **Dernier run** : failure sur `main` (#7008)
- **Cause racine** : Pint (PHP formatter) échec sur fichiers PHP modifiés + feature tests qui échouent
- **Jobs en échec** : `Backend (PHP 8.4 + PostgreSQL 16)`, `Backend Quality (Pint)`, `Backend Coverage`
- **Action** : Identifier et corriger les fichiers PHP qui ne passent pas Pint ; débloquer les feature tests

### 2. `coverage-gate.yml` — Backend Coverage Gate ⚠️ PRIORITÉ 1
- **Dernier run** : failure (#7761, #7762)
- **Cause racine** : tests backend qui échouent (bloquant la couverture)
- **Action** : lié à `tests.yml` — se résout en même temps

### 3. `payroll-ci.yml` — Payroll CI ⚠️ PRIORITÉ 1
- **Dernier run** : failure (#1551)
- **Jobs en échec** : `Tests module Payroll`, `Coverage Payroll ≥ 80 %`
- **Cause racine** : tests paie échouent (probablement pre-existing avant #5149)
- **Action** : lancer les tests payroll localement pour identifier la cause

### 4. `i18n-enterprise.yml` — I18N Enterprise ⚠️ PRIORITÉ 1
- **Dernier run** : failure sur mes PRs
- **Cause racine** : checksum mismatch dans `versions.json` (web/admin catalogs pas resynchronisés)
- **Correctif identifié** : lancer `sync-web.js` + `sync-backend.js` → validate.js vert ✓ (déjà appliqué sur branche courante)
- **Action** : inclure dans les PRs concernées

### 5. `kiosk-ci.yml` — Kiosk CI ⚠️ PRIORITÉ 2
- **Dernier run** : failure (#55 sur neo/2755-i18n-batch1-attendance-b3x9k)
- **Historique** : #54 success → #55 failure → FLAKY
- **Cause racine** : bridge Python tests `test_bridge_security.py` — flakiness intermittente (timing/ports)
- **Tests locaux** : 27/27 passent ✓
- **Action** : `pytest-rerunfailures` ou timeout sur les ports ; créer issue fille P2

### 6. `mobile-apps-ci.yml` — Mobile Apps CI Flutter ⚠️ PRIORITÉ 2
- **Dernier run** : failure
- **Cause racine** : build Flutter (probable dépendance manquante ou code Dart cassé)
- **Action** : créer issue fille pour traiter séparément

### 7. `openapi-ci.yml` — OpenAPI CI
- **Dernier run** : failure
- **Cause racine** : dérive spec OpenAPI vs implémentation
- **Action** : issue fille P2

### 8. `web-ci.yml`, `web-marketing-ci.yml`, `web-offline-ci.yml` — Web CI
- **Dernier run** : failure (3 workflows)
- **Cause racine** : probablement timeouts Playwright / Vercel free-tier quota
- **Action** : issue fille commune

### 9. `plan-action2-claim-guard.yml` — PLAN_ACTION2 Claim Guard
- **Dernier run** : failure
- **Cause racine** : governance check — peut se résoudre avec la branche correcte
- **Action** : investiguer

---

## Workflows GREEN (24)

| Workflow | Dernier run |
|----------|-------------|
| Actionlint | 2026-08-19 ✓ |
| Architecture Quality | 2026-08-19 ✓ |
| Backend Jobs CI | 2026-08-19 ✓ |
| Branch Protection Guard | 2026-08-16 ✓ |
| CI Queue - Orphan Cleanup | 2026-08-19 ✓ |
| CodeQL | 2026-08-19 ✓ |
| Country Catalog Check | 2026-08-19 ✓ |
| Database Backup | 2026-08-19 ✓ |
| Dependabot Updates | 2026-08-18 ✓ |
| Deploy - Leopardo RH | 2026-08-19 ✓ |
| Deploy Admin Dashboard | 2026-08-18 ✓ |
| Deploy Staging | 2026-08-19 ✓ |
| E2E Playwright Prod Smoke | 2026-08-19 ✓ |
| Fix Composer Lock | 2026-08-09 ✓ |
| Issue Governance Guard | 2026-08-19 ✓ |
| Launch API Profile Smoke | 2026-06-06 ✓ (⚠️ run old) |
| Launch Observability Smoke | 2026-08-19 ✓ |
| OWASP ZAP | 2026-08-19 ✓ |
| Onboarding Smoke | 2026-08-19 ✓ |
| PLAN_ACTION2 Post-Merge Audit | 2026-08-19 ✓ |
| PLAN_ACTION2 Weekly Report | 2026-08-17 ✓ |
| Secret Scan | 2026-08-19 ✓ |
| Secret History Scan | 2026-08-17 ✓ |
| k6 Load Smoke | 2026-05-21 ✓ (⚠️ run très old) |

---

## Workflows CANCELLED / NO RUNS (4)

| Workflow | État | Note |
|----------|------|------|
| E2E Isolated | cancelled | Timeout habituel |
| PLAN_ACTION2 Project Sync | cancelled | Workflow de sync |
| PHPStan Baseline | no runs | Workflow manuel |
| Lighthouse CI | failure | Quota Lighthouse |

---

## `continue-on-error` — inventaire

```bash
grep -rn "continue-on-error: true" .github/workflows/ | grep -v "#"
```
Résultats à compléter après investigation des workflows RED.

---

## Issues filles créées (DoD #5145)

- [ ] Issue P1 : corriger Pint + feature tests `tests.yml` (cause racine à identifier)
- [ ] Issue P1 : sync i18n dans toutes les PRs touchant `shared/i18n/` (`sync-web.js` + `sync-backend.js`)
- [ ] Issue P2 : flakiness bridge Python tests kiosk-ci.yml
- [ ] Issue P2 : mobile Flutter CI failure
- [ ] Issue P2 : OpenAPI drift

---

## Prochaine vérification

Objectif : **5 runs consécutifs verts** sur `tests.yml` + `coverage-gate.yml`.  
Date cible : J7 (2026-08-26).
