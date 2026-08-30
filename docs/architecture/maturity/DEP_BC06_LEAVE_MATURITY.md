# DEP-BC06 — Rapport de maturité BC-06 LEAVE

> **Issue :** [DEP-BC06 #5882](https://github.com/kitokoh/leopardo-hr/issues/5882)
> **Contexte :** BC-06 — Leave : absences, congés, soldes, calendriers légaux, approbations
> **Date :** 2026-08-30
> **Statut :** **Rapport phase 1 livré** — corrections listées en §4 en PRs courtes de suivi.

## 1. Cartographie de l'existant

| Composant | Emplacement | Volume |
|---|---|---|
| Module Absence | `api/app/Modules/Absence` | 5 fichiers (contrôleur, requests, provider) |
| Logique congés (HR transverse) | `api/app/Modules/HR` + `Attendance` (soldes, EstimationService) | services dédiés |
| Calendriers légaux | `LegalLeaveCalendarService`, `LegalLeaveEntitlementService`, `LegalLeaveRulesService` | 3 services |
| Approbations | `AbsenceApproveTest`/`AbsenceRejectTest` ; workflows manager | tests dédiés |
| Routes | `api/routes/modules/absence.php` | versionnées `/api/v1` |
| Tests | `api/tests/Feature/Absences/*` (8 fichiers) + `LeaveBalancesSnapshotTest` | ~10 cas |

## 2. Scorecard des 12 dimensions

| # | Dimension | Statut | Constat |
|---|---|---|---|
| 1 | Domaine | ✅ Présent | Vocabulaire absence/congé/solde documenté ; owner @kitokoh |
| 2 | Données | 🟡 Partiel | Migrations tenant (`absence_types`, `absences`, `leave_balances`) ; **fix #5967** (unicité `absence_types.code` par tenant) en cours ; index soldes à vérifier |
| 3 | Tenant | ✅ Présent | `company_id` + `BelongsToCompany` fail-closed ; tests cross-tenant |
| 4 | API | ✅ Présent | CRUD versionné + transitions (approve/reject) ; OpenAPI maintenu |
| 5 | Autorisation | ✅ Présent | Policies + `manager_role` ; employé limité à ses absences (`/me/leave-balances`) |
| 6 | Transactions | ✅ Présent | Approbation transactionnelle ; snapshot soldes (`LeaveBalancesSnapshotTest`) |
| 7 | Asynchronisme | 🟡 Partiel | Événements d'absence (AbsenceRequested/Approved) dispatchés via webhooks ; pas d'outbox dédiée |
| 8 | Sécurité | ✅ Présent | Preuves (`requires_proof`), PII minimisée |
| 9 | Frontend | ✅ Présent | Écrans absences mobile + portail web |
| 10 | Performance | 🟡 Partiel | Index `leave_balances(company_id, employee_id)` à confirmer |
| 11 | Exploitation | ✅ Présent | Runbooks plateforme (MAT-015) |
| 12 | Produit | ✅ Présent | Freeze scope 60 j (#5147) |

**Bilan : 9/12 présents, 3 partiels (données, asynchronisme, performance).**

## 3. Risques identifiés

1. **Coexistence des schémas** (dim. 2) : les tenants historiques peuvent manquer les colonnes modernes `leave_balances` (backfill `DemoCompanyOnceSeeder` requis — déjà en place).
2. **Événements webhook** (dim. 7) : garantir l'idempotence de rejeu côté consommateurs.

## 4. Recommandations (PRs courtes)

- Vérifier index `leave_balances` sur les tenants volumineux.
- Documenter l'idempotence des webhooks d'absence (clé de rejeu).

*Aucun code modifié dans ce livrable — rapport contractuel.*
