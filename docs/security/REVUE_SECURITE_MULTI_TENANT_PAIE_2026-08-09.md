# 🔐 Revue de sécurité ciblée — multi-tenant + paie (F-19, #1549)

> Session 2026-08-09. Périmètre : surfaces paie (listes, détails, exports,
> jobs, webhooks), isolation tenant, RBAC par rôle, rate limits.
> Méthode : revue statique des contrôleurs/routes + inventaire des tests
> adversarial + ajout de tests croisés manquants.

## 1. Surfaces paie et protections

| Surface | Routes | Isolation tenant | RBAC | Tests adversarial |
|---|---|---|---|---|
| Runs de paie (CRUD, calcul, validation, clôture) | `/payroll-runs*` | `company_id` strict + 404 cross-tenant | `api.manager:principal,comptable` | `PayrollTenantIsolationTest` (7), `PayrollRunClosingApiTest` (#1632), `PayrollCrossTenantAdversarialTest` (#1549) |
| Bulletins / PDF | `/pay-slips*`, `/me/pay-slips*` | `company_id` + propriétaire | manager / self | `PaySlipControllerTest`, `PayrollTenantIsolationTest` |
| Journal de paie / exports | `/payroll-runs/{run}/journal`, `/export` | 404 cross-tenant | principal/comptable | `PayrollJournalApiTest` (#1633), `PayrollCrossTenantAdversarialTest` |
| Déclarations sociales (CNAS/CNSS/DSN) | `/social-declarations*` | testé cross-tenant | principal/comptable | `SocialDeclarationControllerTest::test_manager_can_generate_cnas_dz_without_leaking_other_tenant_payroll` |
| Virement bancaire | `/bank-exports*` | 404 cross-tenant | principal/comptable | `PayrollTenantIsolationTest::test_cross_tenant_bank_export_is_inaccessible` |
| Avances sur salaire (double validation) | `/salary-advances*` | 404/422 cross-tenant | workflow manager/employee | `SalaryAdvanceSecurityTest` (689 l.), `SalaryAdvanceWorkflowTest` (#1635) |
| Documents de paiement | `/payment-documents*` | run/employé scopé | manager/self | `PaymentDocumentControllerTest`, `PayrollCrossTenantAdversarialTest` |
| Anomalies pré-clôture | `/payroll-runs/{run}/anomalies` | 404 cross-tenant | principal/comptable | `PayrollAttendanceAnomalyApiTest` (#1638) |
| Cycles de paie / réglages | `/payroll/cycles*` | scopé à l'acteur (pas de paramètre ressource) | manager | `PayrollCycleSettingsTest`, `PayrollCycleIntegrationTest` |
| Jobs / webhooks paie | `GeneratePaymentDocumentJob`, `WarmPaySlipPdfPathsForPayrollRunJob` | dispatch par `company_id` du run | — | `GeneratePaymentDocumentJobTest`, `ProcessPayrollBatchJobTest` |

## 2. Protections transverses

- **Rate limits** : `throttle:payroll-sensitive` sur tout le groupe paie
  (config `RateLimiter`), + `throttle:api-plan` par plan tarifaire.
- **Isolation schéma** : search_path PostgreSQL + validation stricte du nom de
  schéma (audit API 07-19 ✅) ; convention #1613 : pas de `Schema::hasTable`
  au nom nu dans les migrations tenant.
- **RBAC** : middleware `api.manager:principal,comptable` + gardes
  `isManager()`/`isComptable()` dans les contrôleurs ; l'employé n'accède
  qu'à ses propres bulletins/documents (`/me/*`).
- **Écriture automatique interdite** : `PayrollAnomalyService` en lecture
  seule (WriteToolPolicy) — aucun correctif automatique.

## 3. Ajouts de cette revue

1. `PayrollCrossTenantAdversarialTest` (5 tests) : manager tenant B sur run
   tenant A → 404 pour `payment-documents`, `export`, `pay-slips`,
   `send-slips`, `bulk-pay`.
2. Les nouveaux endpoints F-10/F-11/F-20 (journal, lock/unlock, anomalies)
   sont couverts par des tests cross-tenant 404 + RBAC (PR #1632/#1633/#1638).

## 4. Constats / recommandations

| Sévérité | Constat | Recommandation |
|---|---|---|
| 🟡 Moyen | `PayrollRunController` répète les gardes `company_id`/`isManager` dans chaque méthode (pas de Policy) | Extraire une `PayrollRunPolicy` (refactor, pas de changement de comportement) |
| 🟡 Moyen | `updateCycleSettings` modifie des réglages partagés sans audit trail | Tracer via `AuditLog` (comme F-11) |
| 🟢 OK | Aucune fuite cross-tenant détectée sur les surfaces testées | — |

## 5. Conclusion

Aucune fuite cross-tenant détectée sur le périmètre paie : chaque surface
testée renvoie 404 pour une tentative croisée et 403 pour un rôle non autorisé.
La paie reste la surface la plus sensible — les tests adversarial ci-dessus
sont le filet de sécurité permanent (CI).
