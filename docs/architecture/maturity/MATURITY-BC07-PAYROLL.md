# Rapport de maturité — BC-07 PAYROLL

> **DEP-BC07 (issue #5883)** — Deep maturity, BC-07 Payroll.
> Audité le 2026-08-30 (main `62c00afef`). Agent propriétaire : 07.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-07, `active`).

## Périmètre

Paie multi-pays : calcul, fiches de paie, déclarations sociales, banque,
acomptes, prêts, cotisations, journal comptable. `api/app/Modules/Payroll`
(26 modèles, 52 services, 8+ contrôleurs), routes `/api/v1/payroll*`,
`/api/v1/payslips*`, `/api/v1/employee-loans*`, jobs `payroll:precalculate`,
`payroll:bank-export` et gardes CI dédiés (`payroll-ci.yml`).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Modèles DDD riches : PayrollRun, PaySlip, SocialContribution, TaxRateChangeLog, PaymentBatch, Commission, IslamicCalendar, LedgerEntry ; règles par pays (DZ, CEMAC, CEDEAO…) versionnées (presets Leopardo). |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant (payroll_runs, pay_slips, social_contributions, tax_slabs, payment_orders, payroll_accounting_entries…), index `(company_id, period)`, garde schéma verte. |
| D3 | Tenant | 🟢 PRÉSENT | Tous les modèles scopés, `PayrollCalculator` exécuté dans `withinTenant` (search_path), tests cross-tenant (PayrollAccountingExportTest). |
| D4 | API | 🟢 PRÉSENT | Contrôleurs PayrollRun/PaySlip/BankExport/Ledger/Estimation/CotisationSimulation + Requests validées ; OpenAPI couvert ; paie = lecture seule post-validation (état verrouillé). |
| D5 | Autorisation | 🟢 PRÉSENT | PayrollPolicy (manager principal/rh), middleware `api.manager`, accès fiche restreint (rôles dédiés), tests 401/403. |
| D6 | Transactions | 🟢 PRÉSENT | Runs transactionnels avec recalcul idempotent, clôture de période, écritures comptables (PayrollAccountingEntry) dans la même transaction, validation avant publication. |
| D7 | Asynchronisme | 🟢 PRÉSENT | `payroll:precalculate` (nightly, progressif), `payroll:bank-export`, jobs idempotents ; events `PayrollValidated` etc. ; observabilité des jobs. |
| D8 | Sécurité | 🟢 PRÉSENT | Fiches de paie PII (salaire) protégées par policy + logs d'accès sensibles (DataAccessAuditLogger), exports audités, aucun secret dans les fixtures (Golden tests paie DZ). |
| D9 | Frontend | 🟢 PRÉSENT | Admin dashboard (paie, bulletins, déclarations), apps mobile employee (fiche, acomptes) et manager (validation), PWA. |
| D10 | Performance | 🟢 PRÉSENT | Pré-calcul progressif nocturne, index dédiés, pagination ; garde `Coverage Payroll ≥ 80 %` + `Tests module Payroll` en CI. |
| D11 | Exploitation | 🟢 PRÉSENT | Runbooks paie, commandes de recalcul/export, logs structurés, supervision des jobs (Payroll CI dédiée). |
| D12 | Produit | 🟡 PARTIEL | Golden journey paie (clôture → calcul → validation → paiement → journal) largement couverte (tests E2E paie DZ) ; seed pilote multi-pays à consolider. |

## Vérification locale (preuve)

```
./vendor/bin/pest tests/Feature/Payroll
→ 64 fichiers de tests (dont PayrollAccountingExportTest, GoldenTestsDZ, BankExport)
```
Suite complète Payroll verte ; garde CI `Coverage Payroll ≥ 80 %` active sur
`main`.

## Recommandations (PR futures, non bloquantes)

1. **Contrat de données paie** (D1) : formaliser le contrat `payroll.run.validated.v1`
   (événement versionné → Accounting) au-delà des écritures synchrones, pour la
   reprise après crash (runtime outbox BC-14).
2. **Budgets p95** (D10) : verrouiller les budgets de temps de calcul par
   tranche d'effectifs (MAT-014) maintenant que le pré-calcul existe.
3. **Seed pilote multi-pays** (D12) : seed synthétique DZ/MA/CI déterministe
   pour la recette UAT (pattern `CrmPilotSeeder`).
4. **Consolidation services** : poursuivre la réduction des services dupliqués
   Application/Infrastructure engagée par #6266 (DocumentWorkflowService,
   PayrollRegularizationService).

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
