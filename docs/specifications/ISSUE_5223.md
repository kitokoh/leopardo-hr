# ISSUE #5223 — Workflow documents + numérotation paramétrable (Comptabilité Phase A)

**Statut** : implémenté (branche `mod/accounting/5223-document-workflow`).

## Périmètre

- **Numérotation** : `DocumentNumberingService` (implements `DocumentNumberingInterface`), séries par type configurables via `accounting_settings.number_series` (défauts FAC/PRO/DEV/AVO/BL/RC, format `FAC-2026-0001`, année optionnelle, pad paramétrable). Attribution à la création, **concurrent-safe** : contrainte unique `(company_id, number)` + retry borné sur SQLSTATE 23505 (pattern upsert #4978) — test de course dédié.
- **Workflow** : `DocumentWorkflowService` — cycle `draft → sent → partially_paid → paid | cancelled` (+ `overdue` calculé) ; règles : send exige des lignes (+ contact facture/avoir), `paid` jamais atteint sans paiement, cumul des paiements borné au total TTC, pas d'annulation d'un document payé, avoir ≤ reste à payer de la facture source (`metadata.source_document_id`), irsaliye avec `delivery_date`.
- **API** : `GET/POST /accounting/documents`, `GET /accounting/documents/next-number`, `GET /accounting/documents/{id}`, `POST .../send`, `POST .../payments`, `POST .../cancel`, `POST .../credit-note` — RBAC `api.manager:principal,comptable`, isolation tenant 404, audit `accounting.document_*`.

## Tests

`AccountingDocumentWorkflowTest` (cycle complet + règles de transition) et `AccountingDocumentNumberingTest` (formats, séries custom, **course 23505**, isolation tenant des compteurs, RBAC, endpoint next-number) — 22 tests.

## Hors périmètre (phasé)

Rapprochement bancaire (#5229), relances (#5230), PDF multi-langues (#5224), envoi email (#5225), i18n ×4 UI (#5227).
