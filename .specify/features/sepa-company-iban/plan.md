# Plan technique — SEPA IBAN/BIC débiteur réels

## Analyse

Le générateur SEPA (`BankExportGenerator`) n'a accès qu'au `PayrollRun`. Les
identifiants bancaires de l'entreprise vivent dans `companies.metadata`
(convention clés plates existante : `tax_id`, `nis`, `siret`…).

## Architecture

- **Nouveau** `App\Support\CompanyBankDetails::forCompany(string $companyId)` :
  lecture de `public.companies` (qualifié, aucun effet sur le search_path
  tenant) → `{name, iban, bic}` depuis `metadata.company_iban/company_bic`.
- **`BankExportGenerator::generate(PayrollRun, string $format, array $companyBank)`** :
  paramètre optionnel `companyBank` (défaut `[]` → formats legacy inchangés).
  `generateSepaXml()` : IBAN débiteur obligatoire (`RuntimeException
  MISSING_COMPANY_IBAN`), BIC débiteur optionnel, BIC créancier = fallback BIC
  entreprise sinon élément omis, IBAN employé obligatoire
  (`MISSING_EMPLOYEE_IBAN` — plus jamais `UNKNOWN`).
- **`BankExportController::generate()`** : pour `sepa_xml`, contrôle synchrone
  → 422 `MISSING_COMPANY_IBAN` avant création de la ligne BankExport.
- **`GenerateBankExportJob::handle()`** : résout `CompanyBankDetails` pour le
  run et le passe au générateur (échec → statut `failed` + message).

## Décisions

1. `public.companies` qualifié plutôt que `PlatformCompanyLookup` : pas de
   bascule de `search_path` dans un contexte tenant (les écritures BankExport
   suivent dans la même requête).
2. Le 422 est synchrone (contrat HTTP) ; le job garde un garde défensif.

## Tests

- `BankExportControllerTest` : 202 avec IBAN, 422 MISSING_COMPANY_IBAN sans
  IBAN (aucune ligne créée, job non dispatché), isolation cross-tenant.
- `GenerateBankExportJobTest` : SEPA valeurs réelles (IBAN/BIC entreprise +
  employé, aucun placeholder), échec MISSING_COMPANY_IBAN au job, omission du
  `CdtrAgt` sans BIC entreprise.
