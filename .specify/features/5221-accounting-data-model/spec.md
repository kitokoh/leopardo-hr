# Feature Specification: Module Comptabilité — Migrations + modèles DDD (Closes #5221)

**Feature Branch**: `mod/accounting/5221-data-model`
**Issue**: #5221 (P1, backend, data-model — Phase A)
**Created**: 2026-08-22
**Status**: Implementation

## Contexte

Le module Comptabilité est le 19ᵉ module DDD de la plateforme (greenfield, zéro code).
Cette spec couvre le socle data : les 5 tables tenant (`shared_tenants`) et leurs modèles
Eloquent associés, conformément à `docs/architecture/COMPTABILITE_CONCEPTION.md` §4-5.

**Règles** : migration additive ; `company_id` non nullable ; données sensibles chiffrées
(NIF, références de paiement, metadata) — mêmes politiques que Payroll/Cabinet ;
zéro modification des modules existants ; scope tenant automatique (`BelongsToCompany`).

## User Stories

### User Story 1 — Les tables tenant du module Comptabilité existent (P1)

**Why this priority**: Sans tables, aucun endpoint ni workflow Accounting ne peut exister — c'est le socle qui débloque #5222→#5239.

**Independent Test**: `php artisan migrate --path=database/migrations/tenant` crée les 5 tables `accounting_*` dans `shared_tenants`.

**Acceptance Scenarios**:

1. **Given** le schéma tenant, **When** les migrations sont exécutées, **Then** les tables `accounting_contacts`, `accounting_documents`, `accounting_document_lines`, `accounting_payments`, `accounting_settings` existent avec `company_id` non nullable.
2. **Given** la migration, **When** elle est rejouée, **Then** elle est idempotente (garde `schemaTableExists`), aucune erreur.
3. **Given** `accounting_settings`, **When** insérée, **Then** une seule ligne par entreprise (unique `company_id`).

### User Story 2 — Les modèles DDD respectent la structure du module (P1)

**Why this priority**: La structure DDD (Domain/Models, Domain/Enums, Domain/Contracts, Providers) est la convention des 18 modules existants et la condition du code review.

**Independent Test**: `php -l` + PHPStan Strict level 8 passent sur tous les nouveaux fichiers.

**Acceptance Scenarios**:

1. **Given** les 5 modèles, **When** instanciés, **Then** `$fillable`, `$casts` et les relations (`contact`, `lines`, `payments`, `document`) sont typées et documentées.
2. **Given** les 6 enums (`ContactType`, `ContactSource`, `DocumentType`, `DocumentStatus`, `PaymentMethod`, `PaymentStatus`), **When** appelées `values()`, **Then** les valeurs canoniques du design doc §4 sont retournées.
3. **Given** les contracts (`DocumentNumberingInterface`, `PdfRendererInterface`), **When** référencés, **Then** ils existent dans `Domain/Contracts` pour les issues #5223/#5224.

### User Story 3 — Isolation tenant et chiffrement (P1)

**Why this priority**: RGPD/loi 18-07 + isolation cross-tenant sont des non-négociables du programme (DoD #5221).

**Independent Test**: test Feature `AccountingTenantIsolationTest` — les données du tenant A sont invisibles depuis B, et les valeurs sensibles sont chiffrées en base.

**Acceptance Scenarios**:

1. **Given** deux tenants A et B, **When** A crée un contact/document, **Then** B ne le voit pas (scope `BelongsToCompany`).
2. **Given** un `AccountingContact` avec `tax_id`, **When** persisté, **Then** la valeur en base est chiffrée (cast `encrypted`), non lisible en clair.
3. **Given** `metadata`, **When** persisté, **Then** le cast `encrypted:array` restitue le tableau intact.

## Requirements

- **FR-001**: migration tenant `2026_08_22_000001_create_accounting_tables.php` (5 tables, `company_id` uuid non nullable, index/unique, FKs `accounting_documents.contact_id` → `accounting_contacts`, `accounting_document_lines.document_id` / `accounting_payments.document_id` → `accounting_documents`).
- **FR-002**: modèles `AccountingContact`, `AccountingDocument`, `AccountingDocumentLine`, `AccountingPayment`, `AccountingSettings` dans `api/app/Modules/Accounting/Domain/Models/` avec `use App\Shared\Traits\BelongsToCompany`.
- **FR-003**: 6 enums string-backed dans `Domain/Enums/` avec `values(): array`.
- **FR-004**: 2 contracts dans `Domain/Contracts/` (numérotation, rendu PDF) — signatures minimales pour #5223/#5224.
- **FR-005**: `AccountingServiceProvider` dans `Providers/` (pattern Cabinet).
- **FR-006**: tests Feature (création des tables + isolation tenant + chiffrement) + CHANGELOG.

## Hors périmètre

- Aucun endpoint/controller (issue #5226), aucun service (numérotation #5223, PDF #5224, email #5225), aucun changement des modules existants.

## Success Criteria

- Migrations additives idempotentes ; `company_id` non nullable sur les 5 tables.
- Tests d'isolation tenant verts ; valeurs sensibles chiffrées en base.
- PHPStan Strict (level 8) + Pint verts sur les fichiers du module ; CI verte.
- `Closes #5221` dans la PR ; CHANGELOG `### Added` en tête d'`[Unreleased]`.
