# Plan: Paie DZ exports — CNEP/EDX, bordereau, DAS (Closes #5243)

**Spec**: `.specify/features/payroll-dz-exports-2026-08-23/spec.md`
**Issue**: #5243

## Architecture

Aucun changement structurel : ajouts ciblés dans le module Payroll, en miroir des patterns existants (`CnasDeclarationGenerator`, `PayrollJournalGenerator`, `SocialDeclarationController`).

```
api/app/Modules/Payroll/
├── Infrastructure/Services/
│   ├── BankExportGenerator.php            # + formats cnep_dz, edx_dz (+ fileExtension/mimeType)
│   ├── DasDeclarationGenerator.php        # NOUVEAU — CSV DAS annuel
│   └── PayrollBordereauGenerator.php      # NOUVEAU — CSV bordereau par run
├── Infrastructure/Exports/
│   └── PayrollAccountingExportService.php # + colonnes cotisations DZ (runs DZ uniquement)
└── Interfaces/Api/V1/
    ├── BankExportController.php           # + cnep_dz/edx_dz dans les règles in:
    ├── PayrollRunController.php           # + bordereau() (miroir journal)
    └── SocialDeclarationController.php    # + generateDasDz() (miroir generateCnasDz)

routes/modules/payroll_engine.php          # + POST /social-declarations/das-dz
                                           # + GET  /payroll-runs/{run}/bordereau
api/openapi.yaml                           # enums + 2 chemins
api/tests/Feature/Payroll/DzLegalExportsTest.php   # NOUVEAU — round-trip + endpoints
api/tests/Feature/Payroll/BankExportContractApiTest.php # + formats cnep/edx
api/tests/Feature/PayrollAccountingExportTest.php    # + colonnes DZ
docs/validation/FRONTEND_API_CONTRACT_MATRIX.md      # + lignes routes
docs/security/RBAC_ROUTE_MATRIX.md                   # + ligne routes
CHANGELOG.md                                         # + entrée [Unreleased]
```

## Décisions

1. **DAS = annuelle, agrégée des bulletins validés de l'année** (pas par run) — cohérent avec la nature légale du document ; pattern `generateCnasDz` (POST + JSON `data.content`) pour la cohérence produit.
2. **Bordereau = par run** (`GET /payroll-runs/{run}/bordereau`, stream CSV comme `/journal`) — le bordereau récapitule les totaux du run, garde pays DZ (422 sinon) comme les autres déclarations par run.
3. **Formats CNEP/EDX = conventions internes documentées** (pipe-delimited / largeur fixe H-D-F), mêmes limites que ccp_dz/cpa_dz/bna_dz existants : disclaimer « à valider avec la banque » dans le code + docs.
4. **Bridge comptable** : extension additive de `PayrollAccountingExportService` (colonnes CNAS salariale/patronale + IRG) **réservée aux runs DZ** pour ne pas casser le contrat multi-pays ; les écritures elles-mêmes restent #5239.
