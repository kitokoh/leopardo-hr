# DEP-BC08 — Rapport de maturité BC-08 ACCOUNTING

> **Issue :** [DEP-BC08 #5884](https://github.com/kitokoh/leopardo-hr/issues/5884)
> **Contexte :** BC-08 — ACCOUNTING (plan comptable, journaux, écritures, exercices, lettrage, FEC, états financiers)
> **Date :** 2026-08-30
> **Statut :** **Actif** — audit 12 dimensions du code sur `main`.

## 1. Cartographie (état `main`)

| Élément | État |
|---|---|
| `api/app/Modules/Accounting` | 133 fichiers — DDD complet (Domain/Models, Infrastructure/Services/Exports, Interfaces Api/V1) |
| Routes | `/api/v1/accounting/*` (payments, documents, journal, ledger, bank-statements, chart, fiscal years, reminders, payment-webhooks) |
| Registre BC | `BC-08` = ACCOUNTING, dépendances BC-04 (HR) / BC-07 (PAYROLL) / BC-13 (COMMS) |

Preuves de code : `FecExporter` (FEC), `LetteringService` + exceptions de lettrage (InvalidLettering, UnbalancedLettering, LetteringAlreadyUsed), multi-devises (`AccountingMultiCurrencyTest`), paiements en ligne (webhooks gateway, `AccountingOnlinePaymentTest`), écritures de paie (`payroll_accounting_entries`), banque (`bank_statement_tables`, `AccountingBankStatements`), exercices comptables et plan comptable (`accounting_chart_and_fiscal_years`), états financiers, audits et rétention (`AccountingAuditRetentionTest`).

## 2. Scorecard des 12 dimensions

| Dim | Domaine | Verdict | Constat / preuve |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Module DDD complet (133 fichiers), plan comptable, journaux, lettrage, FEC, multi-devises — vocabulaire documenté |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant (chart, fiscal years, écritures, bank statements, lettering), index tenant-first, garde #1962 vert |
| D3 | Tenant | 🟢 PRÉSENT | Modèles scopés `BelongsToCompany`, tests cross-tenant, exercices par compagnie, multi-devises par compagnie |
| D4 | API | 🟢 PRÉSENT | Routes `/api/v1/accounting/*` versionnées, Requests, OpenAPI couvert, webhooks de paiement signés |
| D5 | Autorisation | 🟢 PRÉSENT | Rôle comptable (`manager_role=comptable`), policies Accounting, guards manager, accès FIN |
| D6 | Transactions | 🟢 PRÉSENT | Écritures équilibrées (UnbalancedLetteringException), lettrage transactionnel, réconciliation paiements, écritures paie cohérentes |
| D7 | Asynchronisme | 🟢 PRÉSENT | Webhooks paiement idempotents, rappels (reminders/run), outbox CRM partagée (Intégration BC-14) ; exports FEC synchrones bornés |
| D8 | Sécurité | 🟢 PRÉSENT | PII financières tenant-scopées, paiements via gateway (secrets serveur), webhooks signés/vérifiés, audit & rétention (AccountingAuditRetentionTest) |
| D9 | Frontend | 🟢 PRÉSENT | Écrans comptabilité (admin dashboard), paiements, rapports, banque |
| D10 | Performance | 🟢 PRÉSENT | Ledger/journal indexés, pagination, `AccountingPerformanceTest` dédié ; budgets p95/p99 versionnés partiellement (MAT-014) |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés + corrélation (MAT-009), audit des écritures et lettrages, runbooks backup/restore dédiés |
| D12 | Produit | 🟢 PRÉSENT | Clôture comptable couverte (golden journey dans `golden-journeys.json`), demo E2E (`AccountingDemoE2ETest`), i18n ×4 |

## 3. Vérification (preuve)

Suites sur `main` : `Accounting*Test` (activation, chart, journal, ledger, multi-devises, paiements en ligne, documents, i18n, performance, audit/rétention, demo E2E, modèles), `PayrollAccountEntriesTest`, tests `AccountingBankStatements`. Gardes locales : registre ✅, migrations ✅, OpenAPI ✅.

## 4. Recommandations (PR futures, non bloquantes)

1. **Exports FEC asynchrones** (D7) : passer le FEC en job `TenantScopedJob` avec URL signée (pattern RESTO-702).
2. **Budgets performance** (D10) : verrouiller p95/p99 sur ledger/journal/bank statements (MAT-014).
3. **Contrat d'événements** (D7) : outbox `accounting.entry.posted.v1` pour les intégrateurs.

## 5. Non-régression

Aucun changement de code de production dans ce rapport — audit + documentation uniquement. CRM commercial plateforme intact.
