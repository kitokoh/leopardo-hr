# Implementation Plan: Intégrité des contrats API

**Branch**: `feat/<issue>-api-contract-integrity` | **Date**: 2026-08-14 | **Spec**: `.specify/features/api-contract-integrity-2026-08-14/spec.md`

## Summary

La spec OpenAPI documente des endpoints qui n'existent pas (drift inverse) et omet des routes réelles
consommées (kiosk). Un écran admin SPA appelle un chemin erroné (404 au save). La garde CI ne couvre
qu'un sens. Correctifs documentaires + 1 fix SPA + garde CI renforcée, sans changement de routes PHP.

## Technical Context

- **Spec OpenAPI**: `api/openapi.yaml` (369 paths, format 3.0.3) — éditions chirurgicales par blocs, PAS de re-dump PyYAML (préserver format/comments, éviter un diff massif).
- **Routes réelles**: `api/routes/{api,ai}.php`, `api/routes/modules/*.php`, `api/app/Modules/{EdgeSync,SmartAttendance}/routes/*.php`.
- **Garde CI**: `dev-hub/tools/check-openapi-route-coverage.py` (parseur statique, allowlist `openapi-coverage-allowlist.txt`).
- **SPA**: `front/admin-dashboard` (Vue 3, Vite, Axios avec normalizeApiPath `/v1/*` → base `/api/v1`).
- **Validation**: `python3 dev-hub/tools/check-openapi-route-coverage.py`, parse YAML, `npm run lint` admin, scan `scripts/frontend_route_check.py`.

## Constitution Check

- Spec-first ✓ (ce document). Auto-assignation issue ✓. Un PR par issue ✓. CHANGELOG ✓.
- Pas de table/migration → pas d'impact multi-tenant. Pas de calcul paie.
- Qualité : lint admin vert, check couverture vert, YAML valide.

## Éditions OpenAPI (chirurgicales, par bloc)

### A. Renommer/corriger chemins morts
| Ligne(s) | Avant | Après |
|---|---|---|
| 10249 | `/exports/employees:` | `/export/employees:` |
| 10263 | `/exports/attendance:` | `/export/attendance:` |
| 10283 | `/exports/pay-slips:` (bloc complet) | **supprimer** (doublon de 7423 `/export/pay-slips`) |
| 10297 | `/exports/absences:` | `/export/absences:` |
| 10311 | `/exports/training:` | `/export/training:` |
| 10325 | `/exports/contracts:` | `/export/contracts:` |
| 10339 | `/exports/vehicles:` | `/export/vehicles:` |
| 10353 | `/exports/history:` | `/export/history:` |
| 11244 | `/partner/apply:` | `/growth/partner/apply:` |
| 11266 | `/partner/stats:` | `/growth/partner/stats:` |
| 11275 | `/partner/companies:` | `/growth/partner/companies:` |
| 11283 | `/partner/payout:` | `/growth/partner/payout:` |
| 11230 | `/i18n/{locale}:` | `/i18n/catalog/{locale}:` (tag Admin, param locale) + ajouter `/i18n/catalog` |
| 8647 | bloc `/bank-exports:` (get+post) | **supprimer** (routes inexistantes) |

### B. Corriger les verbes
| Ligne(s) | Path | Verbe documenté | Verbe réel |
|---|---|---|---|
| 9556 | `/loans/{employeeLoan}/approve` | post | **put** |
| 9573 | `/loans/{employeeLoan}/disburse` | post | **put** |
| 9677 | `/expense-claims/{expenseClaim}/approve` | post | **put** |
| 10151 | `/cabinet/documents/{cabinetDocument}/move` | post | **patch** |

### C. Remplacer l'action fantôme smart-attendance
- `/smart-attendance/sessions/{id}/validate` (post) → 2 chemins réels :
  - `/smart-attendance/sessions/{id}/approve` (post, sans body, summary « Approuver une session GPS »)
  - `/smart-attendance/sessions/{id}/reject` (post, body optionnel `rejection_reason`)

### D. Ajouter les routes réelles manquantes
- `/export/payroll-journal`, `/export/payroll-ledger`, `/export/accounting-od` (get, Exports)
- `/growth/partner/dashboard` (get, Growth)
- `/i18n/catalog` (get, Admin)
- Kiosk extensions (après `/kiosks/{deviceCode}/sync`) :
  - `POST /kiosks/{deviceCode}/employee-info`
  - `GET /kiosks/{deviceCode}/announcements`
  - `POST /kiosks/{deviceCode}/leave-balance`
  - `POST /kiosks/{deviceCode}/qr-punch`
  - sécurité `security: []` + `KioskTokenHeader` (comme roster/punch)

### E. info.version
- `version: "4.23.5"` → `"4.24.0"` (aligné sur `config('app.version')` main)

## Fix SPA (D3)
- `front/admin-dashboard/src/views/marketing/MarketingOAuthView.vue` :
  - `api.put('/v1/platform/marketing/oauth-config', payload)` → `api.put('/v1/admin/platform/marketing/oauth-config', payload)`
  - Ajouter un chargement GET de la config existante (onMounted) avec `forms` déjà présents.

## Garde CI (D4)
- `dev-hub/tools/check-openapi-route-coverage.py` : ajouter la passe inverse (chemins OpenAPI absents des routes PHP), avec allowlist inverse `openapi-reverse-allowlist.txt` si nécessaire (préfixes publics volontaires). Après nos correctifs, le drift inverse doit être 0.
- Pruner l'allowlist principale des entrées devenues couvertes (exports, i18n/catalog, kiosk, growth/partner).

## Propreté (D5, P3)
- `SystemView.vue` : supprimer `toggleTask`, `editTask`, `deleteTask`, `updateScalingConfig`, `manualScale`, `toggleLoadBalancerNode`, `drainNode` (inutilisées).
- Nettoyer les imports inutilisés signalés par lint dans les fichiers touchés (warning-only, sans changer le comportement).

## Files touchées
- `api/openapi.yaml` (edits ciblés)
- `dev-hub/tools/check-openapi-route-coverage.py` + allowlist(s)
- `front/admin-dashboard/src/views/marketing/MarketingOAuthView.vue`
- `front/admin-dashboard/src/views/system/SystemView.vue`
- `CHANGELOG.md` (entrée Unreleased)

## Validation
1. `python3 dev-hub/tools/check-openapi-route-coverage.py` → exit 0 (2 sens)
2. Parse YAML OK (`python3 -c "import yaml; yaml.safe_load(open('api/openapi.yaml'))"`)
3. `python3 scripts/route_openapi_compare.py` → 0 OpenAPI-only
4. `cd front/admin-dashboard && npm run lint` → 0 error, warnings réduits
5. `cd front/admin-dashboard && npm run build` → OK
6. CI GitHub Actions sur la PR (Backend Quality, OpenAPI, Web Lint/Build)
