# Rapport de maturité — BC-06 LEAVE

> **DEP-BC06 (issue #5882)** — Deep maturity, BC-06 Congés & absences.
> Audité le 2026-08-30. Agent propriétaire : 06.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-06, statut `active`).

## Périmètre

Congés, absences, justificatifs, soldes, validations et calendrier d'absence.
`api/app/Modules/Absence` (routes `api/routes/modules/absence.php`, 12 routes)
+ `LeavePolicy`/planning de congés dans `api/app/Modules/Planning`, migrations
`leave_management_tables`, permissions `absences.view/create/approve`.
Dépendances : BC-02 (tenant), BC-05 (WORKFORCE — présence).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Module Absence DDD, modèle `AbsenceType` (codes standards CA/MAL/MAT/PAT/CSS/INT/CHOM), cycle demande → approbation → solde, `LeavePolicy` (politique de congés par pays). |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant (absence_types, absences, leave_management_tables), **fix R1 livré** : index unique `(company_id, code)` sur absence_types (#5967) — l'unicité globale cassait le seed multi-tenant. |
| D3 | Tenant | 🟢 PRÉSENT | Modèles scopés `company_id` ; tests cross-tenant (dont `AbsenceTypesTenantUniqueTest` : mêmes codes standards chez 2 tenants, doublon intra-tenant refusé). |
| D4 | API | 🟢 PRÉSENT | 12 routes versionnées (`/api/v1/absences*`, balances `me/leave-balances`), Requests strictes (`AbsenceIndexRequest`, `RejectAbsenceRequest`), OpenAPI couvert. |
| D5 | Autorisation | 🟡 PARTIEL | Approbation bornée aux rôles manager (permissions `absences.approve`) ; pas de matrice fine versionnée ; `AbsencePolicy` à consolider. |
| D6 | Transactions | 🟡 PARTIEL | Décrément de solde transactionnel à l'approbation ; pas de verrouillage `SELECT FOR UPDATE` documenté sur les races d'approbation concurrente. |
| D7 | Asynchronisme | 🟡 PARTIEL | Pas de jobs dédiés congés (rappels, validation auto) — socle queue disponible ; relances possibles en `TenantScopedJob`. |
| D8 | Sécurité | 🟢 PRÉSENT | Justificatifs = PII santé (maladie/maternité) : accès borné, exports audités ; aucune PII dans les logs. |
| D9 | Frontend | 🟢 PRÉSENT | Demandes/validations dans les apps manager/employee, calendrier d'absence web. |
| D10 | Performance | 🟡 PARTIEL | Index tenant+statut présents ; budgets p95 non inscrits au registre MAT-014 pour les balances (liste bornée par employé). |
| D11 | Exploitation | 🟡 PARTIEL | Couvert par runbooks plateforme (backup/rollback) ; pas de runbook métier congés (non critique). |
| D12 | Produit | 🟢 PRÉSENT | Golden journey « cycle de congé complet » (GJ-02) au registre MAT-013 : demande → approbation → solde mis à jour, sans fuite cross-tenant. |

## Vérification (preuve)

- **Tests** : `api/tests/Feature/Absences/*` (AbsenceShowTest…), `EmployeesRbacTest`,
  `AbsenceTypesTenantUniqueTest` (4 tests R1), Planning (~8 méthodes).
- **Gardes** : registres MAT-013/015 cohérents (vérifiés localement).
- Exécution réelle en CI (checks requis) — aucune assertion dynamique prétendue ici.

## Recommandations (PR futures, non bloquantes)

1. **Invariants de solde** (D1/D6) : tests de transition (approbation double
   refusée, solde négatif bloqué, report d'année) + verrouillage transactionnel.
2. **Rappels & validations auto** (D7) : job `TenantScopedJob` de rappel
   (pattern SendPaymentRemindersCommand) + relance hiérarchique bornée.
3. **Matrice d'autorisation** (D5) : pattern `delivery.role` (BC-26-D05) pour
   manager/RH/employé sur les demandes et approbations.
4. **Budgets p95** (D10) : inscrire `GET /api/v1/me/leave-balances` et
   `GET /api/v1/absences` au registre MAT-014.

## Non-régression

Aucun code de production modifié (le fix #5967 est une PR séparée). Rapport uniquement.
