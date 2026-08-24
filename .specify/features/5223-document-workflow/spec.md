# Feature Specification: Workflow documents + numérotation paramétrable (issue #5223)

**Feature Branch**: `mod/accounting/5223-doc-workflow`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Input**: Issue #5223 [P1][backend][api] — cycle de vie `AccountingDocument` (draft → sent → partially_paid → paid | cancelled + overdue), numérotation par type/série paramétrable et concurrent-safe. Le socle data (#5221) pose `DocumentNumberingInterface::nextNumber(companyId, type)` et le modèle `AccountingDocument` (status, due_date, delivery_date, totaux, payments).

## Décision

1. **Numérotation** : `SequentialDocumentNumbering` implémente `DocumentNumberingInterface` — séries paramétrables par type (`AccountingSettings.number_series`, défauts FAC/PRF/DEV/AVR/BL/RCP), format `{SERIE}-{ANNEE}-{NUMERO}`. Incrément **atomique** via `INSERT ... ON CONFLICT (company_id, type, series, year) DO UPDATE ... RETURNING last_number` (pattern upsert exigé par la DoD — jamais de doublon sous concurrence).
2. **Workflow** : `DocumentWorkflowService` — machine à états + règles métier (pas de `paid` sans paiement couvrant le TTC, `partially_paid` = paiement partiel strict, avoir lié à sa facture source, bordereau avec date de livraison, `overdue` calculé). Exceptions de domaine dédiées.
3. **Migration tenant additive** : `accounting_number_counters` + `accounting_documents.source_document_id` (FK auto-référencée, lien avoir → facture).
4. **Aucun endpoint API** — l'exposition REST/RBAC/OpenAPI est le périmètre de #5226.

## User Scenarios & Testing

### User Story 1 — Numérotation (Priority: P1)
1. **Given** une entreprise sans settings, **When** 3 numéros facture, **Then** FAC-{année}-0001/0002/0003 ; types distincts → séries distinctes.
2. **Given** `number_series: {invoice: FA}`, **Then** FA-{année}-0001.
3. **Given** un compteur pré-existant (simulation de course, convention repo #4978), **Then** l'upsert incrémente (0042) sans doublon.
4. **Given** 100 appels, **Then** 100 numéros uniques.

### User Story 2 — Workflow (Priority: P1)
1. **Given** un brouillon, **When** transition vers `sent`, **Then** OK.
2. **Given** draft → `paid`, **Then** `InvalidDocumentTransitionException`.
3. **Given** `sent` + paiement partiel, **When** `paid`, **Then** `DocumentNotFullyPaidException` ; paiement complet → OK.
4. **Given** `sent` sans paiement, **When** `partially_paid`, **Then** exception ; paiement partiel → OK.
5. **Given** un avoir sans facture source, **When** `sent`, **Then** `CreditNoteRequiresSourceInvoiceException` ; après `linkCreditNote` → OK.
6. **Given** un bordereau sans date de livraison, **When** `sent`, **Then** `DeliveryNoteRequiresDeliveryDateException` ; avec date → OK.
7. **Given** des documents émis dont l'échéance est dépassée, **When** `refreshOverdue`, **Then** statut `overdue`.

## Hors périmètre
- API REST /accounting/* + RBAC + OpenAPI : #5226. Email/portail : #5225. PDF : #5224 (PR #5341).
