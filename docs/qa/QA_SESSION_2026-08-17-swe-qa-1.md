# QA Session SWEQA-3 — Audit 360° (2026-08-17)

**Agent**: agent-swe-qa-1 · **Session**: Phase 1 du plan de bataille (audit 360°)
**Périmètre**: landing, web app, admin back-office, mobile, APIs, workflows, onboarding, UX/i18n/SEO

---

## 1. Synthèse

- **API production**: `/api/v1/health`, `/supported-countries`, `/i18n/catalog/fr`, `/docs/openapi.yaml` → **200 OK** (les régressions #2812 sont résolues).
- **Vitrine**: `leopardo-rh.com` toujours **NXDOMAIN** (#3452/#3766) ; fallback Vercel `leopardo-rh.vercel.app` → 404 ; le domaine de marque n'est pas en ligne.
- **Staging**: `api-staging.leopardo-rh.com` NXDOMAIN ; `gestionemployerbackend-staging.onrender.com/health` → 404.
- **Contrôleurs orphelins**: 144 contrôlés → 0 orphelin.
- **i18n**: rapport `I18N_DEBT_REPORT_2026_08_17.md` → 12 465 signaux (2 948 P1) — dette connue, non traitée à ce jour.

## 2. Constats VERIFIÉS (sur main, 2026-08-17)

### 2.1 Clôtures d'issues sans correctif (« ghost close ») — CRITIQUE
84 issues clôturées entre 04:40–05:06 UTC. Vérification code : **#4690, #4687, #4688, #4305, #4410 non résolues** (→ tickets #4812/#4813/#4814/#4815). #4723, #4714, #4716 clôturées avant le merge de leurs PRs. → ticket gouvernance **#4816**.

### 2.2 Messages API non localisés (~15 sites)
`Company.php:137`, `AttendanceController:336`, `IslamicCalendarController:119`, `PlatformCompanyRequestController:170/178`, `EnsureManagerRoleMiddleware:33`, `SalaryAdvanceController:177/361/398`, `BulkPaymentController:40`, `EmployeeLoanController:144`, `BankExportController:203`, `PaymentDocumentController:63`, `VehicleController:115`, `ScheduleController:189`, `AttendanceMonthlyReportService:96`, `KioskController:80`, `AIGatewayController:100/135`, `AITenantInjector:17` → **#4812**.

### 2.3 Sécurité — credentials Edge exposés
`EdgeNode`/`EdgeLicense` : `license_key` + `signed_payload` sans `$hidden` → **#4813**.

### 2.4 Contrat API — paginator brut
`ApprovalController::history()` → `response()->json($decisions)` sans enveloppe `{data}` → **#4814**.

### 2.5 Admin i18n résiduel
`SettingsView.vue` (~14 littéraux FR), `SystemView.vue` (+ ~39 fichiers Vue selon #4410) → **#4815**.

## 3. Surfaces contrôlées — RAS (dette déjà traquée)

| Surface | État | Suivi |
|---|---|---|
| Vitrine metadata racine (keywords/alt/JSON-LD) | Localisée (#4707/#4708, PR #4766) | — |
| Vitrine i18n composants | 452 tests verts, 0 littéral FR accentué | — |
| Admin temps relatif/dates/WS | Traité (#4714-4716, PR #4760) | — |
| API FR accentués (hors les 15 sites §2.2) | 0 | — |
| Enveloppe refreshToken / AIFeatureCheck | FIXÉ (#4697/#4698 vérifiés) | — |
| Mobile i18n (employee/manager/hr/core) | 12 465 signaux mesurés | #2755/#4194/#4303/#4409 |
| Deep links Android | Manifests sans résolution runtime | #4304 |
| OpenAPI drift | 192 routes non documentées (rapport stale) | #3885 |
| CI famine / jobs | Mitigations #3545/#4359/#4745 | #2413 |
| Super-admin demo prod | RUNBOOK + warn entrypoint livrés (branch neo mergée) | #2646 |
| Trial signup prod 500 | Corrigé côté code (provisioning), à re-smoker | #3259 (fermée) |

## 4. Recommandations Phase 3 (ordre de priorité)

1. **#4812** (i18n API, 15 sites — petite PR mécanique)
2. **#4813** (sécurité — 2 modèles, risque réel)
3. **#4814** (contrat — 1 contrôleur)
4. **#4816** (gouvernance — règle + garde)
5. **#4815** (admin i18n — chantier plus gros, par lots)
