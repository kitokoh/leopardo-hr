# Rapport de maturité — BC-08 ACCOUNTING

> **DEP-BC08 (issue #5884)** — Deep maturity, BC-08 Accounting & Finance.
> Audité le 2026-08-30 (main). Agent propriétaire : 08.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-08).

## Périmètre

Plan comptable, journaux, écritures, exercices, lettrage, FEC et états
financiers. `api/app/Modules/Accounting` (129 fichiers) — `JournalPostingService`
(posting document/paiement équilibré), `AccountingLedgerService` (grand-livre,
soldes), `FiscalYearClosingService` (clôture exercice), `ChartOfAccountsService`
(plan PCF/SYSCOHADA), `AccountingRetentionService` (120 mois), `DocumentNumberingService`,
`BankReconciliationService`, `OnlinePaymentService` (Chargily/Stripe),
`DocumentWorkflowService`. Routes `/api/v1/accounting/*` (journal, grand-livre,
clôtures, exports CSV, partages documentaires).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Plan comptable PCF/SYSCOHADA simplifié, documents (facture/avoir/reçu), paiements (cash/virement/chèque/carte/online), lettrage, clôtures de période et d'exercice ; vocabulaire dans les specs + conception (`docs/architecture/COMPTABILITE_CONCEPTION.md`). |
| D2 | Données | 🟢 PRÉSENT | `accounting_journal_entries` (débit OU crédit exclusifs, CHECK DB), `accounting_closed_periods`, `accounting_documents`, `accounting_payments` (reference chiffrée), `accounting_document_shares` (expiration) ; migrations tenant conformes. |
| D3 | Tenant | 🟢 PRÉSENT | Scopes `BelongsToCompany` + WHERE `company_id` explicite dans le ledger (le scope global ne suffit pas — documenté), isolation testée (`AccountingTenantIsolationTest`). |
| D4 | API | 🟢 PRÉSENT | Journal, grand-livre (running balance), clôtures, exports CSV, partages publics signés, paiement en ligne ; OpenAPI couvert. |
| D5 | Autorisation | 🟢 PRÉSENT | Rôle comptable (managerComptable) pour régénération/écritures, RBAC matrice documentée, partages à durée de vie (`ShareAccessAuditTest`). |
| D6 | Transactions | 🟢 PRÉSENT | Posting équilibré (Σ débit = Σ crédit, tolérance 0,005), idempotent (updateOrCreate source+compte), `UnbalancedJournalEntryException`, période close = figée (`PeriodClosedException`), clôture exercice transactionnelle. |
| D7 | Asynchronisme | 🟡 PARTIEL | Purges planifiées (partages expirés, rétention), paiements online synchrones ; pas d'outbox dédiée (généralisation MAT-008 possible). |
| D8 | Sécurité | 🟢 PRÉSENT | Partage documentaire à durée de vie + token, `reference` bancaire chiffrée, rétention légale 120 mois, audit des accès (`AccountingAuditRetentionTest`). |
| D9 | Frontend | 🟢 PRÉSENT | Écrans comptables admin (journal, exports, partages), portail documents publics contrôlés. |
| D10 | Performance | 🟡 PARTIEL | Index perf dédiés (`add_perf_audit_indexes`), tests volume (`AccountingPerformanceTest`) ; budgets p95 à verrouiller (MAT-014). |
| D11 | Exploitation | 🟢 PRÉSENT | Runbook Accounting dédié, purge rétention, partages expirés nettoyés (`AccountingPurgeExpiredCommand`). |
| D12 | Produit | 🟢 PRÉSENT | Invariants golden comptabilité (MAT-007 #5865 — suite golden dédiée), golden journey clôture comptable (MAT-013), E2E démo (`AccountingDemoE2ETest`). |

## Vérification locale (preuve)

```
tests/Feature/Accounting/ (34 fichiers) + GoldenAccountingInvariantsTest (MAT-007) :
AccountingJournalTest, AccountingLedgerTest, AccountingChartOfAccountsTest,
AccountingFiscalYearClosing, AccountingTenantIsolationTest,
AccountingMultiCurrencyTest, AccountingRetentionTest, BankReconciliationManualTest…
```

## Recommandations (PR futures, non bloquantes)

1. **Invariants de clôture d'exercice** (D12) : golden test de clôture
   (résultat reporté, périodes fermées, écritures de clôture équilibrées) dans
   la suite MAT-007.
2. **Outbox comptable** (D7) : publier `JournalEntryPosted` / `FiscalYearClosed`
   dans l'outbox plateforme pour les exports/notifications sans duplication.
3. **FEC** : couvrir l'export FEC (fichier des écritures comptables) par un test
   golden de format (fichier + agrégats) si non couvert.
4. **Budgets performance** (D10) : p95 sur grand-livre multi-périodes une fois
   MAT-014 mergé.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
