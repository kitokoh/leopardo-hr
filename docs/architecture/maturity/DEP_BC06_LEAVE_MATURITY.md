# DEP-BC06 — Rapport de maturité BC-06 LEAVE

> **Issue :** [DEP-BC06 #5882](https://github.com/kitokoh/leopardo-hr/issues/5882)
> **Contexte :** BC-06 — LEAVE (congés, absences, justificatifs, soldes, validations, calendrier d'absence)
> **Date :** 2026-08-30
> **Statut :** **Actif** — audit 12 dimensions du code sur `main`.

## 1. Cartographie (état `main`)

| Élément | État |
|---|---|
| `api/app/Modules/Absence` | Module DDD (Domain + Infrastructure + Interfaces Api/V1) |
| `api/app/Modules/Planning` | Contrats congés (LegalLeaveCountryRuleInterface), ApproveLeave, soldes (InsufficientLeaveBalanceException, LeaveBalancesSnapshot) |
| Routes | `/api/v1/absence/*` (CRUD, approve/reject/cancel, proof, balances snapshot) + planning |
| Registre BC | `BC-06` = LEAVE, dépendances BC-03 (IDENTITY) / BC-04 (HR) |

Preuves de code : `AbsenceApproveTest`, `AbsenceCancelTest`, `AbsenceIndexTest`, `AbsenceProofTest`, `AbsenceRejectTest`, `AbsenceShowTest`, `AbsenceStoreTest`, `LeaveBalancesSnapshotTest`, `AbsenceDateConflictException`, `AbsenceNotPendingException`, `InsufficientLeaveBalanceException`, justificatifs (`proof`), seed des types d'absence (`absence_types`, index UNIQUE global #5967 en cours par un autre agent).

## 2. Scorecard des 12 dimensions

| Dim | Domaine | Verdict | Constat / preuve |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Module Absence DDD + contrats congés dans Planning, règles par pays (LegalLeaveCountryRuleInterface), vocabulaire documenté |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant absences + soldes + justificatifs, index tenant-first, garde migrations #1962 vert |
| D3 | Tenant | 🟢 PRÉSENT | Modèles scopés `BelongsToCompany`, tests cross-tenant, conflit de dates intra-employé (AbsenceDateConflict) |
| D4 | API | 🟢 PRÉSENT | Routes `/api/v1/absence/*` versionnées, Requests, OpenAPI couvert, erreurs 422 métier (date conflict, solde insuffisant) |
| D5 | Autorisation | 🟢 PRÉSENT | Workflow approbation manager (approve/reject), annulation contrôlée, `api.manager` + policies employé |
| D6 | Transactions | 🟢 PRÉSENT | Validation d'absence transactionnelle, snapshots de soldes (LeaveBalancesSnapshot) cohérents |
| D7 | Asynchronisme | 🟡 PARTIEL | Rappels/notifications d'absence via Notification module ; pas de file de recalcul de soldes (snapshot synchrone borné) |
| D8 | Sécurité | 🟢 PRÉSENT | Justificatifs via Documents (BC-20), PII absences (motifs médicaux) non exposées hors tenant, secrets jamais loggés |
| D9 | Frontend | 🟢 PRÉSENT | Écrans congés mobile hr/manager + portail web, calendrier d'absence |
| D10 | Performance | 🟡 PARTIEL | Pagination sur les listes ; budgets p95/p99 non verrouillés (MAT-014) |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés + corrélation (MAT-009), audit des approbations, runbooks ops globaux |
| D12 | Produit | 🟢 PRÉSENT | Cycle de congé complet couvert (demande → validation → solde → justificatif), golden journey congé dans `golden-journeys.json` |

## 3. Vérification (preuve)

Suites sur `main` : `Absence*Test` (7 scénarios API) + `LeaveBalancesSnapshotTest` + tests Planning (approbation, conflits, soldes). Gardes locales : registre ✅, migrations ✅, OpenAPI ✅.

## 4. Recommandations (PR futures, non bloquantes)

1. **Recalcul de soldes asynchrone** (D7) : job `TenantScopedJob` de recalcul périodique des soldes.
2. **Rappel automatique** (D7) : job no-show/retour de congé (pattern RESTAURANT RESTO-608 déjà livré).
3. **Budgets performance** (D10) : verrouiller les listes d'absences paginées (MAT-014).

## 5. Non-régression

Aucun changement de code de production dans ce rapport — audit + documentation uniquement. CRM commercial plateforme intact.
