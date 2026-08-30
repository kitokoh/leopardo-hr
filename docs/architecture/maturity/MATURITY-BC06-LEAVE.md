# Rapport de maturité — BC-06 LEAVE

> **DEP-BC06 (issue #5882)** — Deep maturity, BC-06 Leave & Absence.
> Audité le 2026-08-30 (main). Agent propriétaire : 06.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-06).

## Périmètre

Congés, absences, justificatifs, soldes, validations et calendrier d'absence.
Contrôleur `api/app/Modules/Absence/Interfaces/Api/V1/Controllers/AbsenceController.php`
+ domaine dans `api/app/Modules/Planning/Domain/Models` (Absence, AbsenceType,
LeavePolicy, LeaveAccrual, LeaveBalance, LeaveBalanceLog) et services
(`AbsenceService`, `LegalLeaveRulesService`, `LegalLeaveEntitlementService`,
`LegalLeaveCalendarService`). Routes `/api/v1/absences*`, `/api/v1/leave-balances*`,
`/api/v1/me/leave-balances` (soldes personnels employé).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Types d'absence par pays (`absence_types`), politiques de congés (`LeavePolicy`), cumuls (`LeaveAccrual`), soldes (`LeaveBalance` + journal `LeaveBalanceLog`), justificatifs (`AbsenceProofTest`). |
| D2 | Données | 🟡 PARTIEL | Tables absence/leave en migrations tenant ; index de croissance à vérifier sur `leave_balance_logs` volumétrique ; unicité `absence_types.code` par tenant (fix #5967 en cours via PR #6346/#6347). |
| D3 | Tenant | 🟢 PRÉSENT | Scopes `BelongsToCompany` sur Absence/LeaveBalance ; isolation cross-tenant testée (suite Absences). |
| D4 | API | 🟢 PRÉSENT | `AbsenceController` (index/store/show/approve/reject/cancel/proof) + `AbsenceIndexRequest` (filtres allowlist), `/me/leave-balances` employé distinct du manager-only `/leave-balances` (AGENTS.md #5594) ; OpenAPI couvert. |
| D5 | Autorisation | 🟢 PRÉSENT | Approbation manager (workflow double validation), rejet motivé, annulation, preuve upload contrôlée ; tests `AbsenceApproveTest`/`AbsenceRejectTest`/`AbsenceCancelTest`. |
| D6 | Transactions | 🟢 PRÉSENT | Mise à jour de solde transactionnelle (débit/crédit du journal `leave_balance_logs`), snapshot soldes (`LeaveBalancesSnapshotTest`), événements `AbsenceRequested/Approved/Rejected` (webhooks). |
| D7 | Asynchronisme | 🟡 PARTIEL | WebhookListener consomme les événements absence ; pas d'outbox dédiée leave (généralisation MAT-008 possible). |
| D8 | Sécurité | 🟢 PRÉSENT | Justificatifs uploads contrôlés (type/taille), PII congés minimisée, audit des validations (AuditLog). |
| D9 | Frontend | 🟢 PRÉSENT | Écrans congés employee/manager (soldes, demandes, historique), formulaire absence alimenté par `/leave-balances` (backfill `DemoCompanyOnceSeeder`). |
| D10 | Performance | 🟡 PARTIEL | Index `company_id,created_at` sur absences ; budgets p95 à verrouiller (MAT-014). |
| D11 | Exploitation | 🟢 PRÉSENT | Logs corrélés sans PII, runbooks globaux, backfill soldes démo. |
| D12 | Produit | 🟡 PARTIEL | Golden journey « cycle de congé » couvert (MAT-013) ; pas de seed pilote leave dédié. |

## Vérification locale (preuve)

```
tests/Feature/Absences/ (8) : AbsenceStoreTest, AbsenceApproveTest,
AbsenceRejectTest, AbsenceCancelTest, AbsenceIndexTest, AbsenceShowTest,
AbsenceProofTest, LeaveBalancesSnapshotTest.
```

## Recommandations (PR futures, non bloquantes)

1. **Invariants de solde** (D2) : test golden soldes (cumul − consommé − en attente
   = disponible) avec calcul manuel, pattern MAT-007.
2. **Unicité absence_types par tenant** : acter le correctif #5967 et ajouter un
   test de non-régression dédié.
3. **Outbox leave** (D7) : publier `AbsenceApproved/Rejected` dans l'outbox
   plateforme pour les notifications multi-canal (BC-13) sans exposition PII.
4. **Calendrier d'absence** : endpoint agrégé par équipe (sans PII individuelle
   hors nécessité métier) + tests de scopage.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
