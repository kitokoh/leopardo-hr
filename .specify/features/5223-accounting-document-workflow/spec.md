# Feature Specification: Workflow documents + numérotation paramétrable (Closes #5223)

**Feature Branch**: `mod/accounting/5223-document-workflow`
**Created**: 2026-08-23 | **Status**: Draft → In progress
**Issue**: #5223 (P1, backend, api, Comptabilité Phase A)
**Spec**: `.specify/features/5223-accounting-document-workflow/spec.md`
**Anti-collision**: module `accounting` — sous-domaine **documents**. La branche `mod/accounting/5222-contacts-crud` (contacts) est le seul autre chantier du module ; fichiers distincts (`AccountingDocument*`, `DocumentNumberingService`, `DocumentWorkflowService`, contrôleur documents, routes accounting).

## Contexte

Le socle data #5221 (mergé) fournit `accounting_documents`, `accounting_document_lines`, `accounting_payments`, `accounting_settings` (unique `(company_id, number)`) et le contrat `DocumentNumberingInterface` **dont l'implémentation est explicitement attendue par #5223** (commentaire dans le provider : bindings par #5223/#5224).

Cycle de vie `AccountingDocument` : `draft → sent → partially_paid → paid | cancelled` (+ `overdue` calculé). Numérotation par type et série paramétrable (ex. `FAC-2026-0001`) via `AccountingSettings.number_series`, idempotente et concurrent-safe (contrainte unique + retry 23505, pattern upsert du repo). Avoir lié à la facture source (`metadata.source_document_id`) ; irsaliye avec date de livraison (`delivery_date`).

## User Stories & Testing

### US-1 — Numérotation paramétrable et concurrent-safe (P1)

En tant que comptable, je veux que chaque document reçoive un numéro unique par type et série (défaut `FAC-2026-0001`, configurable via `AccountingSettings.number_series`), même sous créations concurrentes.

**Acceptance Scenarios**:
1. Given un settings avec `number_series.invoice = {prefix: FAC, year: true, pad: 4}`, When création de 3 factures, Then numéros `FAC-2026-0001..0003` (sans doublon).
2. Given `year: false`, When création, Then numéros `FAC-0001..` (sans année).
3. Given deux créations concurrentes du même candidat (contrainte unique engagée), When la 2ᵉ arrive, Then retry → numéro suivant, jamais de 23505 visible, jamais de doublon.
4. Given deux entreprises avec la même série, When création, Then compteurs indépendants par `company_id` (isolation tenant).

### US-2 — Cycle de vie du document (P1)

En tant que comptable, je veux faire passer un document par les statuts légaux avec des règles de transition strictes.

**Acceptance Scenarios**:
1. Given un document `draft` avec lignes + contact, When `send`, Then `sent` + `sent_at` posé.
2. Given un document sans lignes, When `send`, Then 422 (`DOCUMENT_NO_LINES`) — jamais de transition.
3. Given un paiement partiel, When `recordPayment`, Then `paid_amount` incrémenté et statut `partially_paid`.
4. Given paiements cumulés ≥ total, When dernier `recordPayment`, Then statut `paid` — et `paid` n'est jamais atteint sans paiement enregistré.
5. Given un document `paid`, When `cancel` ou nouveau paiement, Then refusé (422).
6. Given `due_date` dépassée et statut `sent`/`partially_paid`, When `refreshOverdue`, Then statut `overdue`.
7. Given un avoir lié à une facture, When création, Then `metadata.source_document_id` posé et montant ≤ reste à payer de la source (422 sinon).
8. Given un irsaliye, Then `delivery_date` acceptée.

### US-3 — API RBAC + isolation tenant

1. Given un employé non-manager, Then 403 sur toutes les routes documents.
2. Given un document d'une autre entreprise, Then 404 (masquage).
3. Given le listing, Then filtres `type`/`status`/`contact_id` + pagination.

## Requirements

### Functional Requirements

- **FR-001**: `DocumentNumberingService` implémente `DocumentNumberingInterface` — lecture de `AccountingSettings.number_series` (défauts : `FAC/PRO/DEV/AVO/BL/RC` selon type, année active, pad 4), format `{prefix}-{YEAR}-{NNNN}`.
- **FR-002**: la création de document attribue le numéro dans une transaction ; sur `23505` (unique `(company_id, number)`), retry borné (5) avec le numéro suivant — jamais de doublon ni de 500 (pattern upsert #4978).
- **FR-003**: `DocumentWorkflowService` expose `createDraft`, `send`, `recordPayment`, `cancel`, `createCreditNote`, `refreshOverdue` avec les règles de transition (US-2) ; les totaux (`subtotal_ht`, `tax_amount`, `total_ttc`) sont calculés depuis les lignes (TVA paramétrable).
- **FR-004**: contrôleur `AccountingDocumentController` — `index` (filtres + pagination), `store`, `show`, `send`, `payments`, `cancel`, `credit-note`, `next-number` — RBAC `api.manager:principal,comptable`, isolation tenant 404.
- **FR-005**: tests — workflow complet (US-2), numérotation idempotente + **course dédiée** (US-1.3, simulation pré-insertion pattern #3726/#4978), isolation tenant (US-1.4/US-3), RBAC.
- **FR-006**: OpenAPI (nouveaux chemins documentés, coverage 0 drift), matrices frontend/RBAC, CHANGELOG, spec `.specify`, `docs/specifications/ISSUE_5223.md`.

## Success Criteria

- **SC-001**: workflow complet testé (cycle draft→sent→partially_paid→paid, cancel, overdue, avoir, irsaliye).
- **SC-002**: numérotation sans doublon sous concurrence (test dédié).
- **SC-003**: règles de transition (pas de payé sans paiement, pas d'annulation d'un payé, montant avoir borné).
- **SC-004**: PHPStan strict 0, Pint PASS, tests Accounting verts, coverage OpenAPI 0 drift.

## Assumptions

- Le modèle #5221 est la source de vérité (colonnes/casts inchangés) — aucune migration nouvelle (la table `accounting_settings.number_series` existe déjà).
- Les paiements « workflow » (statut `recorded`) relèvent de #5223 ; le rapprochement bancaire complet est #5229 (Phase B).
- RBAC v1 : `principal` + `comptable` (conception §2) ; le rôle `marketing` en lecture arrive avec l'intégration leads (#5231).
