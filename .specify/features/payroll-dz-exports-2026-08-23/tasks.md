# Tasks: Paie DZ exports — CNEP/EDX, bordereau, DAS (Closes #5243)

**Spec**: `.specify/features/payroll-dz-exports-2026-08-23/spec.md`
**Plan**: `.specify/features/payroll-dz-exports-2026-08-23/plan.md`

- [x] T1. Claim : issue #5243 self-assignée + branche `mod/payroll/5243-exports-dz` poussée (protocole #2400) + commentaire annoncé
- [x] T2. Audit des exports existants vs besoins DZ (spec.md — tableau)
- [ ] T3. `BankExportGenerator` : formats `cnep_dz` + `edx_dz` (match arm, fileExtension, mimeType)
- [ ] T4. `BankExportController` : `cnep_dz`/`edx_dz` dans les règles `in:` (store + generate) + OpenAPI enums
- [ ] T5. `DasDeclarationGenerator` (CSV annuel : NIS, nom, brut, CNAS 9/26 %, IRG, net, mois + TOTAUX)
- [ ] T6. `PayrollBordereauGenerator` (totaux par cotisation + récap run)
- [ ] T7. `SocialDeclarationController::generateDasDz` (POST /social-declarations/das-dz, manager, audit) + `PayrollRunController::bordereau` (GET /payroll-runs/{run}/bordereau, garde DZ, audit) + routes
- [ ] T8. `PayrollAccountingExportService` : colonnes cotisations DZ (runs DZ uniquement)
- [ ] T9. Tests : `DzLegalExportsTest` (round-trip DAS + bordereau, RBAC/tenant/country), `BankExportContractApiTest` (+cnep/edx), `PayrollAccountingExportTest` (+colonnes DZ)
- [ ] T10. Docs : FRONTEND_API_CONTRACT_MATRIX, RBAC_ROUTE_MATRIX, CHANGELOG [Unreleased]
- [ ] T11. Vérifs locales : tests Payroll + PHPStan strict + Pint + OpenAPI cohérente
- [ ] T12. PR `feat(payroll/DZ): exports CNEP/EDX, bordereau, DAS + export comptable enrichi (Closes #5243)` → CI verte → merge → suppression branche
