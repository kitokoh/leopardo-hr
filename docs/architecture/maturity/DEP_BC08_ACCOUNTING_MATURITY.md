# DEP-BC08 — Rapport de maturité BC-08 ACCOUNTING

> **Issue :** [DEP-BC08 #5884](https://github.com/kitokoh/leopardo-hr/issues/5884)
> **Contexte :** BC-08 — Accounting : journal, plan comptable, rapprochement bancaire, documents, paiements (Chargily), synthèses
> **Date :** 2026-08-30
> **Statut :** **Rapport phase 1 livré** — corrections listées en §4 en PRs courtes de suivi.

## 1. Cartographie de l'existant

| Composant | Emplacement | Volume |
|---|---|---|
| Module Accounting (DDD) | `api/app/Modules/Accounting` | 129 fichiers PHP |
| Services | `AccountingLedgerService`, `ChartOfAccountsService`, `BankReconciliationService`, `BankStatementImportService`, `DocumentNumberingService`, `DocumentWorkflowService`, `DocumentPdfRenderer`, `DocumentShareService`, `AccountingRetentionService` | 9 services |
| Paiements | `ChargilyPaymentGateway` + webhooks signés (PaymentWebhookController) | gateway + callback idempotent |
| Exports | `JournalCsvExporter` (`/accounting/journal/export.csv`) | export CSV |
| Routes | `api/routes/modules/accounting.php` | versionnées `/api/v1` |
| Tests | `api/tests/Feature/Accounting/*` | ~37 cas |

## 2. Scorecard des 12 dimensions

| # | Dimension | Statut | Constat |
|---|---|---|---|
| 1 | Domaine | ✅ Présent | Vocabulaire journal/écriture/plan comptable/rapprochement documenté ; owner @kitokoh |
| 2 | Données | ✅ Présent | Migrations tenant ; `bank_reconciliation`/`bank_statement_*` ; index rapprochement (#5523) |
| 3 | Tenant | ✅ Présent | Isolation démontrée ; tests cross-tenant |
| 4 | API | ✅ Présent | Routes versionnées ; OpenAPI maintenu ; export CSV |
| 5 | Autorisation | ✅ Présent | Policies + `manager_role` ; RBAC matrice |
| 6 | Transactions | ✅ Présent | Écritures transactionnelles ; clôtures documentées |
| 7 | Asynchronisme | 🟡 Partiel | Webhooks paiements idempotents ; pas d'outbox dédiée pour les synthèses vers Accounting |
| 8 | Sécurité | ✅ Présent | PII financière protégée ; callback signé ; secret-scan |
| 9 | Frontend | ✅ Présent | Écrans comptabilité portail web + admin |
| 10 | Performance | 🟡 Partiel | Rapprochement bancaire volumineux — index et pagination à vérifier |
| 11 | Exploitation | ✅ Présent | Runbooks (MAT-015) ; tests CI dédiés (`Tests module Accounting`) |
| 12 | Produit | ✅ Présent | Freeze scope 60 j ; pilotage par issues |

**Bilan : 10/12 présents, 2 partiels (asynchronisme, performance).**

## 3. Risques identifiés

1. **Synthèses inter-modules** (dim. 7) : les verticales (Travel, Restaurant, Fuel) publient des événements pour Accounting — garantir un consommateur idempotent côté Accounting (dédup par clé d'événement).
2. **Rapprochement volumineux** (dim. 10) : index `bank_statement_lines` + matching (#5523) à auditer sur gros exports.

## 4. Recommandations (PRs courtes)

- Contrat de consommation outbox (restaurant/fuel → accounting) avec dédup idempotente.
- Audit index rapprochement bancaire.

*Aucun code modifié dans ce livrable — rapport contractuel.*
