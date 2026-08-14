# Tasks: Intégrité des contrats API

**Input**: spec.md + plan.md (`.specify/features/api-contract-integrity-2026-08-14/`)

**Prerequisites**: plan.md (required), spec.md (required)

**Tests**: gardes scripts + lint + CI PR (voir plan.md « Validation »)

## Phase 1 — Correction spec OpenAPI (US1, P1) 🎯 MVP

- [ ] T001 [P] US1 Renommer `/exports/*` → `/export/*` (7 chemins : employees, attendance, absences, training, contracts, vehicles, history) dans `api/openapi.yaml` — éditions chirurgicales des clés de chemin
- [ ] T002 [P] US1 Supprimer le bloc `/exports/pay-slips` (doublon de `/export/pay-slips` ligne 7423)
- [ ] T003 [P] US1 Renommer `/partner/*` → `/growth/partner/*` (apply, stats, companies, payout)
- [ ] T004 [P] US1 Supprimer le bloc `/bank-exports` (get+post) — routes inexistantes (garder `/bank-exports/{bankExport}/download`)
- [ ] T005 [P] US1 Corriger `/i18n/{locale}` → `/i18n/catalog/{locale}` + ajouter `/i18n/catalog`
- [ ] T006 [P] US1 Verbes : `/loans/{employeeLoan}/approve` post→put, `/disburse` post→put, `/expense-claims/{expenseClaim}/approve` post→put, `/cabinet/documents/{cabinetDocument}/move` post→patch
- [ ] T007 [P] US1 Remplacer `/smart-attendance/sessions/{id}/validate` par `/sessions/{id}/approve` + `/sessions/{id}/reject`
- [ ] T008 [P] US1 Ajouter `/export/payroll-journal`, `/export/payroll-ledger`, `/export/accounting-od` (get)
- [ ] T009 [P] US1 Ajouter `/growth/partner/dashboard` (get)
- [ ] T010 [P] US1 Bump `info.version` → 4.24.0

## Phase 2 — Endpoints kiosk (US2, P1)

- [ ] T011 [P] US2 Documenter `POST /kiosks/{deviceCode}/employee-info`, `GET /kiosks/{deviceCode}/announcements`, `POST /kiosks/{deviceCode}/leave-balance`, `POST /kiosks/{deviceCode}/qr-punch` (security `[]` + `KioskTokenHeader`)

## Phase 3 — Fix SPA admin (US3, P1)

- [ ] T012 US3 `MarketingOAuthView.vue` : PUT → `/v1/admin/platform/marketing/oauth-config` + GET config au montage

## Phase 4 — Garde CI (US1 SC-001, P2)

- [ ] T013 US1 Étendre `check-openapi-route-coverage.py` avec la passe inverse (OpenAPI→routes PHP) + allowlist inverse si besoin
- [ ] T014 US1 Pruner `openapi-coverage-allowlist.txt` des entrées devenues documentées (exports, i18n/catalog, kiosk, growth/partner)

## Phase 5 — Propreté (US4, P3)

- [ ] T015 US4 `SystemView.vue` : supprimer les 7 fonctions mortes + imports inutilisés (warnings lint)

## Phase 6 — Documentation & validation

- [ ] T016 CHANGELOG.md — entrée Unreleased décrivant la correction contrat API
- [ ] T017 Exécuter la validation complète (plan.md) : check couverture 2 sens, parse YAML, route_openapi_compare, lint+build admin
- [ ] T018 PR avec `Closes #<issue>` + checks CI verts (Backend Quality, OpenAPI CI, Web Lint/Build)

## Dependencies & Execution Order

- T001-T011 indépendants (blocs YAML distincts) — parallélisables
- T012 dépend de rien (fichier Vue)
- T013/T014 après T001-T011 (les couvertures changent)
- T015 indépendant (fichier Vue distinct)
- T016-T018 à la fin (validation globale)
