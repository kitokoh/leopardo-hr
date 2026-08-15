# Feature Specification: Export bancaire SEPA — IBAN/BIC débiteur réels

**Feature Branch**: `fix/2198-sepa-company-iban`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2198

## Contexte

`BankExportGenerator::generateSepaXml()` émettait des placeholders
(`PLACEHOLDER_COMPANY_IBAN`, `PLACEHOLDER_BIC`, `NOTPROVIDED`, `UNKNOWN`) :
le fichier SEPA servi par `POST /payroll-runs/{id}/bank-export` n'était
utilisable par aucune banque.

## User Stories & Testing

### User Story 1 — IBAN/BIC débiteur réels (P1)
**Acceptance Scenarios**:
1. Given `companies.metadata.company_iban` renseigné, When génération SEPA,
   Then `<DbtrAcct><IBAN>` porte l'IBAN réel de l'entreprise.
2. Given `companies.metadata.company_bic` renseigné, When génération SEPA,
   Then `<DbtrAgt><BIC>` porte le BIC réel (débiteur et fallback créancier).
3. Given aucun BIC entreprise, When génération SEPA,
   Then l'élément `<CdtrAgt>` est omis (valide pain.001.001.03) — jamais `NOTPROVIDED`.

### User Story 2 — 422 MISSING_COMPANY_IBAN (P1)
**Acceptance Scenarios**:
1. Given `metadata.company_iban` absent, When POST bank-export sepa_xml,
   Then 422 `MISSING_COMPANY_IBAN`, aucune ligne BankExport créée, job non dispatché.
2. Given le job tourne sans IBAN entreprise (course), When génération,
   Then le job échoue avec `MISSING_COMPANY_IBAN` (statut `failed`, message lisible).

### User Story 3 — Aucun placeholder, formats legacy intacts (P1)
**Acceptance Scenarios**:
1. Given un employé sans IBAN, When génération SEPA,
   Then échec explicite `MISSING_EMPLOYEE_IBAN` — jamais `UNKNOWN` dans le fichier.
2. Given formats `ccp_dz`/`cpa_dz`/`bna_dz`/`csv_generic`/`virement_ma`,
   When génération, Then contenu inchangé (aucune régression).

## Contraintes

- Lecture des données débiteur depuis `public.companies` (jamais le search_path
  tenant) via `App\Support\CompanyBankDetails` (clés plates metadata :
  `company_iban`, `company_bic`).
- Clé `error` = `MISSING_COMPANY_IBAN` pour le contrat frontend.
