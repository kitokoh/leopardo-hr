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
