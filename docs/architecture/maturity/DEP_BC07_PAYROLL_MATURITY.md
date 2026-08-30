# DEP-BC07 — Rapport de maturité BC-07 PAYROLL

> **Issue :** [DEP-BC07 #5883](https://github.com/kitokoh/leopardo-hr/issues/5883)
> **Contexte :** BC-07 — Payroll : paie multi-pays (DZ/CM/GA/CG/SN/BF/ML/CI…), déclarations sociales, exports bancaires, documents PDF
> **Date :** 2026-08-30
> **Statut :** **Rapport phase 1 livré** — corrections listées en §4 en PRs courtes de suivi.

## 1. Cartographie de l'existant

| Composant | Emplacement | Volume |
|---|---|---|
| Module Payroll (DDD) | `api/app/Modules/Payroll` | 138 fichiers PHP |
| Règles pays | `CountryRules/` (DZ, CEMAC, CEDEAO) + `CountryRulesResolver` | par pays |
| Déclarations sociales | `CnasDeclarationGenerator`, `CnssDeclarationGenerator`, `CnpsDeclarationGenerator`, `CedeaoCnsDeclarationGenerator`, `CemacCnpsDeclarationGenerator` | 5 générateurs |
| Exports bancaires | `BankExportGenerator` + `GenerateBankExportJob` | job idempotent |
| Documents PDF | `GeneratePaySlipPdfJob`, `GeneratePaymentDocumentJob`, `ArchivePaySlipsToCabinetJob` | jobs dédiés |
| Paiements masse | `ProcessPayrollBatchJob`, `ProcessBulkPaymentJob`, `payment_batches`/`payment_items`/`payment_confirmations` | batch + confirmations |
| Routes | `api/routes/modules/payroll.php` | versionnées `/api/v1` |
| Tests | `api/tests/Feature/Payroll/*` | ~73 cas (116 fichiers avec sous-dossiers) |

## 2. Scorecard des 12 dimensions

| # | Dimension | Statut | Constat |
|---|---|---|---|
| 1 | Domaine | ✅ Présent | Vocabulaire paie/cycle/solde/avance documenté ; `PayrollCycleService`, `PayrollCalculator`, `TaxSlab` |
| 2 | Données | ✅ Présent | Migrations tenant cohérentes ; `tax_slabs`/`social_contributions` par pays ; backfills documentés |
| 3 | Tenant | ✅ Présent | Isolation démontrée ; `company_id` non nullable ; tests cross-tenant |
| 4 | API | ✅ Présent | Routes versionnées ; OpenAPI maintenu ; `mobile-summary` schema-aware (#239) |
| 5 | Autorisation | ✅ Présent | Policies + `manager_role` (principal/rh) ; employé limité à `me/*` |
| 6 | Transactions | ✅ Présent | Cycle paie transactionnel ; soldes jamais silencieusement dégradés (500 explicite `PAYROLL_BALANCE_UNAVAILABLE`, #1663) |
| 7 | Asynchronisme | ✅ Présent | Jobs PDF/export/batch idempotents sur queues `documents,pdf,payroll` ; `queue:health-check` couvre toutes les queues (#4340) |
| 8 | Sécurité | ✅ Présent | Données salaires sensibles ; exports signés ; secret-scan |
| 9 | Frontend | ✅ Présent | Écrans paie mobile (solde, bulletins, documents) + portail web |
| 10 | Performance | 🟡 Partiel | Budgets k6 ; traitement masse à benchmarker (batch paie) |
| 11 | Exploitation | ✅ Présent | Runbooks + jobs CI dédiés (`payroll-ci.yml`, `backend-jobs-ci.yml`) ; SLA bloquants 24 h (#5155) |
| 12 | Produit | ✅ Présent | Freeze scope 60 j ; pilotage par issues |

**Bilan : 10/12 présents, 2 partiels (performance, données).**

## 3. Risques identifiés

1. **Masse annuelle** (dim. 10) : `ProcessPayrollBatchJob` sur de gros effectifs — mesures p95 à publier.
2. **Déclarations pays** (dim. 2) : chaque nouveau pays ajoute un générateur — vérifier la couverture des barèmes (inclusifs `0-5000`, `5001-20000`).

## 4. Recommandations (PRs courtes)

- Benchmark batch paie (k6/scénario) + publication p95.
- Garde de parité barèmes pays vs `CountryRules` (test par tranche).

*Aucun code modifié dans ce livrable — rapport contractuel.*
