# Feature Specification: Modèle de données Accounting — entités, relations, enums (issue #5220)

**Feature Branch**: `mod/accounting/5220-data-model-spec`

**Created**: 2026-08-23

**Status**: Draft → En revue (aligned sur l'implémentation réelle #5221)

**Input**: Issue #5220 (Phase A) — spec du modèle de données du module Comptabilité.

**Références** : conception `docs/architecture/COMPTABILITE_CONCEPTION.md` §4-5 ; implémentation de référence branche `mod/accounting/5221-data-model` (migration `2026_08_22_000001_create_accounting_tables.php`, modèles `api/app/Modules/Accounting/Domain/**` — source de vérité) ; spec de chantier `.specify/features/5221-accounting-data-model/spec.md`.

## Contexte

Le module Comptabilité est le **19ᵉ module DDD** de la plateforme (greenfield). Cette spec est la **référence canonique du socle data** livré par #5221 : 5 tables tenant, 5 modèles Eloquent, 6 enums, 2 contracts — alignée sur la conception v1 (§4-5) et sur le code réellement mergé (pas une vue idéale). Elle est le contrat que les issues #5222→#5239 et #5270→#5276 devront respecter.

**Règles non négociables** (conception §3 + ADR-0001) :
- migration additive et idempotente (garde `schemaTableExists`) ; tables dans le schéma tenant (`shared_tenants`) ;
- `company_id` **uuid non nullable** sur les 5 tables → isolation tenant portée par le trait `BelongsToCompany` (garde fail-closed #3727) ;
- données sensibles **chiffrées en base** (`tax_id`/`reference`/`metadata` — casts `encrypted`/`encrypted:array`, politiques Payroll/Cabinet, RGPD loi 18-07) ;
- **zéro impact FOCUS** : aucun module existant n'est modifié.

## Vue d'ensemble (ERD)

```
AccountingContact 1───n AccountingDocument 1───n AccountingDocumentLine
                          │
                          └───n AccountingPayment
AccountingSettings  (1 ligne par entreprise — unique company_id)
MarketingLead (existant, non modifié) ──source── AccountingContact (marketing_lead_id)
```

## Tableaux des tables (types, contraintes, index)

### `accounting_contacts` — tiers de facturation (client/fournisseur)

| Colonne | Type | Contraintes | Notes |
|---|---|---|---|
| `id` | bigint unsigned | PK auto | |
| `company_id` | uuid | **NOT NULL**, index | isolation tenant |
| `type` | string(20) | défaut `'customer'` | enum `ContactType` |
| `name` | string(255) | NOT NULL | |
| `legal_name` | string(255) | nullable | raison sociale |
| `tax_id` | text | nullable | **NIF — chiffré (cast `encrypted`)** |
| `email` | string(255) | nullable | |
| `phone` | string(50) | nullable | |
| `address` | string(500) | nullable | |
| `currency` | string(10) | nullable | devise par défaut |
| `payment_terms` | string(60) | nullable | ex. « 30 J » |
| `language` | string(10) | nullable | langue de correspondance |
| `source` | string(30) | défaut `'manual'` | enum `ContactSource` |
| `marketing_lead_id` | bigint unsigned | nullable, index | lien lead Marketing qualifié (pas de FK — module tiers) |
| `metadata` | text | nullable | **chiffré (cast `encrypted:array`)** — périmètre TVA, notes |
| `created_at`/`updated_at` | timestamp | | |

Index : `(company_id)`, `(company_id, type)`.

### `accounting_documents` — document unique à type discriminé

| Colonne | Type | Contraintes | Notes |
|---|---|---|---|
| `id` | bigint unsigned | PK auto | |
| `company_id` | uuid | **NOT NULL**, index | isolation tenant |
| `type` | string(30) | NOT NULL | enum `DocumentType` : invoice/proforma/quote/credit_note/delivery_note/receipt |
| `number` | string(60) | NOT NULL | numéro de série (règles §Numérotation) |
| `status` | string(30) | défaut `'draft'` | enum `DocumentStatus` |
| `contact_id` | bigint unsigned | nullable, index, **FK → `accounting_contacts.id` `nullOnDelete`** | |
| `project_ref` | string(120) | nullable | référence projet optionnelle |
| `issue_date` | date | NOT NULL | date d'émission |
| `due_date` | date | nullable | échéance |
| `delivery_date` | date | nullable | date livraison (irsaliye) |
| `currency` | string(10) | nullable | devise du document |
| `exchange_rate` | decimal(15,6) | nullable | taux de change (multi-devises #5270) |
| `subtotal_ht` | decimal(15,2) | défaut 0 | |
| `tax_amount` | decimal(15,2) | défaut 0 | |
| `total_ttc` | decimal(15,2) | défaut 0 | |
| `tva_rate` | decimal(8,4) | nullable | taux appliqué |
| `notes` | text | nullable | |
| `footer_mentions` | text | nullable | mentions légales paramétrables |
| `pdf_path` | string(500) | nullable | PDF généré (#5224) |
| `sent_at` | timestamp | nullable | envoi (#5225) |
| `paid_amount` | decimal(15,2) | défaut 0 | cumul encaissé |
| `metadata` | text | nullable | **chiffré (cast `encrypted:array`)** — lien avoir parent, relance |

Contraintes : FK `contact_id` → `accounting_contacts` (`nullOnDelete`) ; **UNIQUE `(company_id, number)`** ; index `(company_id, type, status)`.

### `accounting_document_lines` — lignes de document

| Colonne | Type | Contraintes | Notes |
|---|---|---|---|
| `id` | bigint unsigned | PK auto | |
| `company_id` | uuid | **NOT NULL**, index | isolation tenant |
| `document_id` | bigint unsigned | NOT NULL, index, **FK → `accounting_documents.id` `cascadeOnDelete`** | |
| `description` | string(500) | NOT NULL | |
| `quantity` | decimal(15,4) | défaut 1 | |
| `unit_price` | decimal(15,4) | défaut 0 | HT |
| `discount` | decimal(15,4) | défaut 0 | remise |
| `tax_id` | string(60) | nullable | clé de taxe (référentiel pays) |
| `sort_order` | integer | défaut 0 | ordre d'affichage |
| `created_at`/`updated_at` | timestamp | | |

### `accounting_payments` — encaissements/règlements + rapprochement

| Colonne | Type | Contraintes | Notes |
|---|---|---|---|
| `id` | bigint unsigned | PK auto | |
| `company_id` | uuid | **NOT NULL**, index | isolation tenant |
| `document_id` | bigint unsigned | NOT NULL, index, **FK → `accounting_documents.id` `cascadeOnDelete`** | |
| `amount` | decimal(15,2) | NOT NULL | |
| `method` | string(30) | NOT NULL | enum `PaymentMethod` |
| `reference` | text | nullable | **n° chèque/RIB — chiffré (cast `encrypted`)** |
| `received_at` | date | nullable | date d'encaissement |
| `reconciled_at` | date | nullable | rapprochement (#5229) |
| `status` | string(20) | défaut `'pending'` | enum `PaymentStatus` |
| `metadata` | text | nullable | **chiffré (cast `encrypted:array`)** |
| `created_at`/`updated_at` | timestamp | | |

Index : `(company_id)`, `(company_id, status)`.

### `accounting_settings` — paramétrage par entreprise

| Colonne | Type | Contraintes | Notes |
|---|---|---|---|
| `id` | bigint unsigned | PK auto | |
| `company_id` | uuid | **NOT NULL, UNIQUE** | une ligne par entreprise |
| `number_series` | json | nullable | par type : préfixe + compteur + format année |
| `tva_rates` | json | nullable | taux par défaut (pays) |
| `legal_mentions` | text | nullable | mentions légales |
| `template_style` | string(60) | nullable | style PDF |
| `currency` | string(10) | nullable | devise par défaut |
| `payment_terms` | string(60) | nullable | conditions par défaut |
| `document_language` | string(10) | défaut `'fr'` | langue des documents |
| `created_at`/`updated_at` | timestamp | | |

## Enums (string-backed, `values(): array`)

| Enum | Valeurs | Usage |
|---|---|---|
| `ContactType` | `customer`, `supplier`, `both` | `accounting_contacts.type` |
| `ContactSource` | `manual`, `marketing_lead` | `accounting_contacts.source` |
| `DocumentType` | `invoice`, `proforma`, `quote`, `credit_note`, `delivery_note`, `receipt` | `accounting_documents.type` (type discriminé) |
| `DocumentStatus` | `draft`, `sent`, `partially_paid`, `paid`, `cancelled`, `overdue` | `accounting_documents.status` |
| `PaymentMethod` | `cash`, `bank_transfer`, `check`, `card`, `other` | `accounting_payments.method` |
| `PaymentStatus` | `pending`, `recorded`, `matched` | `accounting_payments.status` |

## Relations

| Modèle | Relation | Cible | Cardinalité | Clé |
|---|---|---|---|---|
| `AccountingContact` | `documents()` | `AccountingDocument` | 1→n | `contact_id` |
| `AccountingDocument` | `contact()` | `AccountingContact` | n→1 | `contact_id` |
| `AccountingDocument` | `lines()` | `AccountingDocumentLine` | 1→n | `document_id` |
| `AccountingDocument` | `payments()` | `AccountingPayment` | 1→n | `document_id` |
| `AccountingDocumentLine` | `document()` | `AccountingDocument` | n→1 | `document_id` |
| `AccountingPayment` | `document()` | `AccountingDocument` | n→1 | `document_id` |
| `AccountingSettings` | — (pas de relation) | — | 1 par `company_id` | `company_id` UNIQUE |

Intégration externe (hors module, sans FK) : `AccountingContact.marketing_lead_id` → `MarketingLead` existant (workflow lead qualifié §6.1 de la conception).

## Règles de numérotation

- Numéro unique **par entreprise** : UNIQUE `(company_id, number)`.
- Séries paramétrables par type dans `accounting_settings.number_series` (JSON : préfixe + compteur + format année) — ex. `FAC-2026-0001`.
- Le contrat `DocumentNumberingInterface` (`Domain/Contracts/`) définit l'API de réservation/incrément ; implémentation `NumberingService` dans le périmètre de l'issue #5223 (workflow documents + numérotation).
- Changement de série en cours de période interdit sans migration dédiée (intégrité fiscale).

## User Scenarios & Testing

### User Story 1 — Le socle data est créé et idempotent (P1)

**Independent Test**: `php artisan test --filter=AccountingDataModelTest` — 5 tables créées dans `shared_tenants`, rejouables sans erreur.

**Acceptance Scenarios**:

1. **Given** le schéma tenant, **When** la migration `2026_08_22_000001_create_accounting_tables.php` s'exécute, **Then** les 5 tables `accounting_*` existent avec `company_id` non nullable.
2. **Given** la migration déjà exécutée, **When** elle est rejouée, **Then** aucune erreur (garde `schemaTableExists`).
3. **Given** `accounting_settings`, **When** une seconde ligne est insérée pour la même entreprise, **Then** violation UNIQUE `company_id`.

### User Story 2 — Isolation tenant et chiffrement des données sensibles (P1)

**Independent Test**: `php artisan test --filter=AccountingTenantIsolationTest` — tenant A invisible depuis B ; valeurs sensibles illisibles en base.

**Acceptance Scenarios**:

1. **Given** deux tenants A et B, **When** A crée un contact et un document, **Then** B ne voit ni l'un ni l'autre (scope `BelongsToCompany`).
2. **Given** un contact avec `tax_id` (NIF), **When** persisté, **Then** la valeur en base est chiffrée, non lisible en clair (cast `encrypted`).
3. **Given** un `metadata` JSON, **When** persisté, **Then** le cast `encrypted:array` restitue le tableau intact.

### User Story 3 — Le modèle est exploitable par les issues suivantes (P2)

**Independent Test**: PHPStan Strict (level 8) + Pint verts sur `api/app/Modules/Accounting/**` ; les contracts `DocumentNumberingInterface`/`PdfRendererInterface` sont résolubles.

**Acceptance Scenarios**:

1. **Given** un document avec lignes, **When** `lines()` est appelé, **Then** les lignes ordonnées par `sort_order` sont retournées.
2. **Given** `AccountingDocument::where('company_id', …)` (type discriminé), **When** `type=invoice`, **Then** le modèle ne distingue pas les types par table (table unique, pas de table par type).

## Requirements

### Functional Requirements

- **FR-001** : 5 tables tenant `accounting_contacts`, `accounting_documents`, `accounting_document_lines`, `accounting_payments`, `accounting_settings` — `company_id` uuid **non nullable** et indexé (ou unique pour settings).
- **FR-002** : `accounting_documents` est une **table unique à type discriminé** (`DocumentType`) — pas de table par type de document.
- **FR-003** : UNIQUE `(company_id, number)` sur `accounting_documents` — numérotation par entreprise.
- **FR-004** : FKs — `documents.contact_id` → contacts (`nullOnDelete`) ; `lines.document_id` et `payments.document_id` → documents (`cascadeOnDelete`).
- **FR-005** : données sensibles chiffrées — `contacts.tax_id`, `payments.reference` (cast `encrypted`) ; `contacts.metadata`, `documents.metadata`, `payments.metadata` (cast `encrypted:array`).
- **FR-006** : 6 enums string-backed avec `values(): array` (valeurs du tableau §Enums).
- **FR-007** : modèles dans `Domain/Models/` avec trait `BelongsToCompany`, relations typées (`contact`, `lines`, `payments`, `documents`, `document`) et docblocks `@property`.
- **FR-008** : `AccountingSettings` singleton par entreprise (UNIQUE `company_id`) — pas de settings multiples.
- **FR-009** : contracts `DocumentNumberingInterface` (réservation/incrément de numéro) et `PdfRendererInterface` dans `Domain/Contracts/` pour #5223/#5224.
- **FR-010** : migration idempotente et additive ; zéro modification des modules existants.

### Key Entities

- **AccountingContact** — tiers (client/fournisseur/both), NIF chiffré, source lead marketing.
- **AccountingDocument** — facture/proforma/devis/avoir/bordereau/reçu, numéro unique par entreprise, totaux (ht/tva/ttc), cumul `paid_amount`.
- **AccountingDocumentLine** — lignes (description, quantité, prix, remise, taxe, ordre).
- **AccountingPayment** — encaissement (montant, méthode, référence chiffrée, rapprochement).
- **AccountingSettings** — paramétrage entreprise (séries, TVA, mentions, langue).

## Success Criteria

### Measurable Outcomes

- **SC-001** : les 5 tables + 6 enums + 5 modèles documentés correspondent **exactement** à l'implémentation mergée de #5221 (diff table par table).
- **SC-002** : les tests d'isolation tenant et de chiffrement (#5221) sont verts — la spec reflète le comportement testé.
- **SC-003** : toute issue Phase B/C (documents, paiements, exports, TVA multi-pays, multi-devises) référence cette spec sans contradiction (garde de revue).
- **SC-004** : zéro impact FOCUS — aucun module existant modifié par le socle.

## Assumptions

- La conception v1 (`COMPTABILITE_CONCEPTION.md` §4-5) reste la référence produit ; cette spec en est le **contrat data** aligné sur le code.
- Les issues #5222→#5239 (API, workflows, PDF, email, paiements, exports) construiront sur ce socle sans nouvelle migration destructive.
- `MarketingLead` n'est pas modifié (pas de FK — lien logique via `marketing_lead_id`).
- Le taux de change (`exchange_rate`) est posé dès le socle pour #5270 (multi-devises) mais son usage métier est hors périmètre Phase A.
- `tva_rate` unique sur le document couvre la v1 (TVA simple) ; la TVA multi-pays détaillée (#5271) pourra introduire des lignes de taxe sans casser ce socle.
