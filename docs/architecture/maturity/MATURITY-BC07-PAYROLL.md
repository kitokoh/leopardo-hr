# Rapport de maturité — BC-07 PAYROLL

> **DEP-BC07 (issue #5883)** — Deep maturity, BC-07 Payroll.
> Audité le 2026-08-30 (main). Agent propriétaire : 07.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-07).

## Périmètre

Périodes, règles, calculs, snapshots, bulletins, validations et exports paie.
`api/app/Modules/Payroll` (138 fichiers : Application/Domain/Infrastructure/
Interfaces/Providers) — `PayrollCalculator`, règles pays multi-pays
(`CountryRules/*` : DZ, FR, MA, TN, TR, CI, SN, BF, TG, ML, GA, CG, CM, GB, US,
CA), `PayrollClosingService`, `PayrollAccountingEntryService`, déclarations
(CNAS/CNSS/DSN/CEDEAO/CEMAC), exports bancaires, documents de paiement
asynchrones, golden tests (40+ cas). Routes `/api/v1/payroll-runs*`,
`/api/v1/payroll/mobile-summary`, `/api/v1/me/balance`.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Cycle paie complet (run → bulletins → validation RH → verrou comptable → paiement), régularisations, fins de contrat, prorata, heures sup, avances ; vocabulaire documenté (`docs/payroll/*_COMPLIANCE.md`, CALCULATION_CONTRACT.md). |
| D2 | Données | 🟢 PRÉSENT | `payroll_runs`, `pay_slips`, `pay_slip_lines`, `payroll_calculation_audits` (snapshot immuable #1874), `tax_slabs`, `social_contributions`, `payment_documents` ; migrations tenant conformes. |
| D3 | Tenant | 🟢 PRÉSENT | Scopes `BelongsToCompany` ; isolation cross-tenant testée (`CountryIsolationMatrixTest`, `PayrollTenantIsolation`), calculs scopés par run. |
| D4 | API | 🟢 PRÉSENT | Contrôleurs payroll versionnés (runs, bulletins, simulations, cotisations, exports), Requests + Policies (manager payroll/comptable), OpenAPI couvert. |
| D5 | Autorisation | 🟢 PRÉSENT | `PayrollPolicy` : RH valide, comptable verrouille, employé lit ses bulletins ; tests 403 (RH sans groupe payroll → 403 sur regenerate comptable). |
| D6 | Transactions | 🟢 PRÉSENT | Validation/lock transactionnels (`PayrollClosingService`), écritures salariales équilibrées (débit=crédit, `UnbalancedPayrollEntriesException`), idempotence régénération. |
| D7 | Asynchronisme | 🟢 PRÉSENT | Jobs paie (PDF, exports bancaires, batches), queues `payroll,documents,pdf`, `backend-jobs-ci.yml` (QueueJobsTest), warmup PDF. |
| D8 | Sécurité | 🟢 PRÉSENT | Données bancaires chiffrées (casts `encrypted`), déclarations via modèles (jamais DB::table brut), redaction PII logs, audits de calcul sans données individuelles. |
| D9 | Frontend | 🟢 PRÉSENT | Soldes/bulletins mobile (employee/manager), exports comptables CSV (Journal, Livre de paie, OD), portail web. |
| D10 | Performance | 🟢 PRÉSENT | `Coverage Payroll ≥ 80 %` en CI, benchmarks (`PayrollBenchmarkSeeder`), budgets MAT-014 suivis ; golden report `GOLDEN_PAYROLL_CASES=833`. |
| D11 | Exploitation | 🟢 PRÉSENT | Runbooks deploy/rollback, health-check queues (`documents,pdf,payroll,notifications,webhooks,audit,default` #4340), alertes. |
| D12 | Produit | 🟢 PRÉSENT | 40+ cas golden pays (MAT-007), compliance multi-pays documentée, golden journey cycle de paie (MAT-013), recettes DZ/CEDEAO/CEMAC. |

## Vérification locale (preuve)

```
tests/Feature/Payroll/ (63 fichiers) + Golden/ (35 fichiers, 833 cas comptés) :
GoldenDzPayrollTest, GoldenCiPayrollTest, GoldenCmPayrollTest, GoldenFrPayrollTest,
GoldenMaPayrollTest, GoldenTnPayrollTest, GoldenTrPayrollTest, GoldenUsPayrollTest,
PayrollClosingTest, PayrollAccountingEntriesFlowTest, PayrollAuditTest,
DzLegalExportsTest, DsnExportServiceTest, CnpsDeclarationTest…
```

## Recommandations (PR futures, non bloquantes)

1. **Snapshot de règles** : exposer `rules_version/identifier` dans l'API
   bulletins (traçabilité loi en vigueur au calcul) — déjà stocké
   (`payroll_calculation_audits`), à exposer côté lecture.
2. **Outbox paie** : publier `PayrollValidated` dans l'outbox plateforme
   (MAT-008) pour les intégrations comptables/webhooks sans duplication
   (les écritures salariales restent le canal canonique).
3. **P95 paie batch** : benchmark sur les gros runs (≥ 500 employés) via
   `PayrollBenchmarkSeeder` + budget CI.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
