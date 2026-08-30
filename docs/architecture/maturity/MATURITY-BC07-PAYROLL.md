# Rapport de maturité — BC-07 PAYROLL

> **DEP-BC07 (issue #5883)** — Deep maturity, BC-07 Paie.
> Audité le 2026-08-30. Agent propriétaire : 07.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-07, statut `active`).

## Périmètre

Périodes, règles, calculs, snapshots, bulletins, validations et exports paie.
`api/app/Modules/Payroll` (le plus gros module backend du dépôt), routes
`api/routes/modules/payroll_engine.php` (**97 routes**), multi-pays
(DZ/MA/TN/FR/TR/SN/CM/CI), bordereaux bancaires, packs légaux.
Dépendances : BC-02 (tenant), BC-05 (présence), BC-06 (congés).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Règles par pays via `CountryRulesInterface` (weeklyRestDays, overtimeThreshold…), calculs centralisés `PayrollCalculator`, périodes/runs/snapshots, 13e mois, IRG/CNAS/CNSS par pays. |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant complètes (payroll_runs, pay_slips, bank_exports…), index (pay_slips par run, tenant), parité `CreatesMvpSchema`, garde schéma vert. |
| D3 | Tenant | 🟢 PRÉSENT | Snapshots de paie scopés `company_id`, calculs en `withinTenant`/search_path, tests cross-tenant paie (isolation des runs). |
| D4 | API | 🟢 PRÉSENT | 97 routes versionnées (runs, bulletins, validations, exports, bordereaux), Requests/Resources, OpenAPI couvert. |
| D5 | Autorisation | 🟢 PRÉSENT | PayrollPolicy/PaySlipPolicy (manager/rh/comptable), guards `api.manager` + `tenant.country` (pays légal requis avant opération sensible). |
| D6 | Transactions | 🟢 PRÉSENT | Runs transactionnels (ProcessPayrollBatchJob), snapshots immuables, validations avec garde de re-validation, golden tests montants (18+ Maroc, 40+ DZ). |
| D7 | Asynchronisme | 🟢 PRÉSENT | `ProcessPayrollBatchJob`, `WarmPaySlipPdfPathsForPayrollRunJob`, `GenerateBankExportJob` (pattern pending→generating→generated/failed, retry borné), `GeneratePaySlipPdfJob`, `ArchivePaySlipsToCabinetJob` — socle `TenantScopedJob`/`EnsureTenantContext` respecté. |
| D8 | Sécurité | 🟢 PRÉSENT | PII salaire maximale : accès borné, exports audités, bordereaux sans doublon (idempotence #2198), secrets jamais en fixtures. |
| D9 | Frontend | 🟢 PRÉSENT | Écrans paie admin/manager, bulletins PDF (cabinet), apps mobile (consultation bulletins). |
| D10 | Performance | 🟡 PARTIEL | `GET /payroll-runs/{run}/pay-slips` au registre MAT-014 (p95 ≤ 600 ms, pagination) ; calculs lourds asynchronisés ; budgets p95/p99 non systématiques sur tous les endpoints. |
| D11 | Exploitation | 🟡 PARTIEL | Jobs observables (failed_jobs + QueueObservability), runbook de rollback de run documenté (OPERATIONS) ; pas de runbook paie dédié. |
| D12 | Produit | 🟢 PRÉSENT | Golden tests par pays (Maroc/DZ) + cycle de paie complet couvert (GJ au registre MAT-013 : création run → calcul → validation → bulletins) ; packs légaux audités 2026. |

## Vérification (preuve)

- **Tests** : `api/tests/Feature/Payroll*` — 115 fichiers, ~863 méthodes de test
  (statique) ; golden tests multi-pays (IRG, CNAS, prorata, arrondis, 13e mois).
- **Gardes** : registres MAT-013/014/015 cohérents (vérifiés localement).
- Exécution réelle en CI (checks requis) — aucune assertion dynamique prétendue ici.

## Recommandations (PR futures, non bloquantes)

1. **DLQ paie** (D7) : dead-letter + replay pour les jobs de batch (pattern
   `delivery_dead_letters` BC-26-D07) — zéro run perdu silencieusement.
2. **Budgets p95** (D10) : inscrire les endpoints de runs/validations au
   registre MAT-014 (listes paginées).
3. **Invariants de cycle** (D1/D6) : tests de transition d'état du run
   (draft → processing → validated → archived, aucune réouverture).
4. **Runbook paie dédié** (D11) : symptômes → diagnostic → action → rollback
   (pattern RUNBOOK_DELIVERY BC-26-D12).

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
