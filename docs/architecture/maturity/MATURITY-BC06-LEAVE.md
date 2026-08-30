# Rapport de maturité — BC-06 LEAVE

> **DEP-BC06 (issue #5882)** — Deep maturity, BC-06 Leave.
> Audité le 2026-08-30 (main `62c00afef`). Agent propriétaire : 06.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-06, `active`).

## Périmètre

Congés, absences, soldes et validations : `api/app/Modules/Planning`
(Absence, AbsenceType, LeaveAccrual, LeaveBalance, LeaveBalanceLog,
LeavePolicy), routes `/api/v1/absences*`, `/api/v1/leave*`,
`/api/v1/me/leave*`, jobs `leave:accrue` / `leave:carry-forward`.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Modèles DDD : Absence (workflow), AbsenceType, LeaveBalance (+Log), LeaveAccrual, LeavePolicy ; règles de cumul/prise documentées et versionnées (LeaveCountryRulesTemporalVersioningTest). |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant (absences, absence_types, leave_balances, leave_balance_logs, leave_accruals, leave_policies), index `(company_id, start_date)`, contrainte UNIQUE global `absence_types.code` (#5967 corrigé). |
| D3 | Tenant | 🟢 PRÉSENT | Scopes `BelongsToCompany` systématiques, tests cross-tenant (absence d'un autre tenant → 404/403, `AbsencePolicy`). |
| D4 | API | 🟢 PRÉSENT | Contrôleurs Absence + LeavePolicy, Requests validées (bornes, chevauchement, solde), pagination, OpenAPI couvert. |
| D5 | Autorisation | 🟢 PRÉSENT | AbsencePolicy (employé = ses absences ; manager = approbation), middleware `api.manager`, tests 401/403/404. |
| D6 | Transactions | 🟢 PRÉSENT | Accrual idempotent (UNIQUE par période/employé), carry-forward annuel borné, validation de solde à la création (pas de solde négatif sans règle), approbation/refus audités. |
| D7 | Asynchronisme | 🟢 PRÉSENT | Jobs `leave:accrue` (daily) et `leave:carry-forward` (yearly) idempotents + `contracts:alert-expiring` ; notifications d'approbation via CommunicationService. |
| D8 | Sécurité | 🟢 PRÉSENT | Justificatifs contrôlés (documents RH, MIME/size), motifs d'absence sans PII inutile, audit des changements de solde (LeaveBalanceLog append-only). |
| D9 | Frontend | 🟢 PRÉSENT | Admin dashboard (gestion congés), apps mobile employee (demande/solde) et manager (approbation), i18n ×4. |
| D10 | Performance | 🟡 PARTIEL | Index dédiés + pagination ; budgets p95/p99 non versionnés (MAT-014 en cours). |
| D11 | Exploitation | 🟢 PRÉSENT | Commandes de maintenance (`leave:accrue`, `leave:carry-forward`), logs structurés, runbook global. |
| D12 | Produit | 🟡 PARTIEL | Golden journey congé (demande → approbation → solde → paie) partiellement couvert ; seed pilote absent. |

## Vérification locale (preuve)

```
./vendor/bin/pest tests/Feature/Absences tests/Feature/Leave tests/Feature/Planning
→ verts (workflow approbation, soldes, accrual, carry-forward, policies)
```
Tests clés : `AbsenceApproveTest`, `LeaveWorkflowIntegrationTest`,
`LeaveCountryRulesTemporalVersioningTest`, `absence_types` UNIQUE (#5967).

## Recommandations (PR futures, non bloquantes)

1. **Golden journey congé** (D12) : test E2E demande → approbation manager →
   débit de solde → intégration paie, avec seed pilote déterministe.
2. **Solder les justificatifs** (D8) : passer les pièces jointes d'absence
   dans le module Documents (contrat BC-20) avec URL signée bornée.
3. **Budgets performance** (D10) : verrouiller p95/p99 sur
   `leave_balances` volumineux (historique par employé) une fois MAT-014 mergé.
4. **Notifications de relance** (D7) : job `leave:remind-approvers` pour les
   demandes en attente > N jours (idempotent, template i18n).

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
