# Plan: Workflow documents + numérotation paramétrable (Closes #5223)

**Spec**: `.specify/features/5223-accounting-document-workflow/spec.md`
**Issue**: #5223

## Architecture

Aucun changement de schéma — le socle #5221 fournit tout. Ajouts dans le module Accounting (patterns DDD des modules existants) :

```
api/app/Modules/Accounting/
├── Application/Services/
│   └── DocumentWorkflowService.php        # NOUVEAU — cycle de vie + règles de transition
├── Domain/
│   ├── Contracts/DocumentNumberingInterface.php   # (existant — implémentation attendue ici)
│   └── Models/AccountingDocument.php      # + helper overdue (lecture seule, sans migration)
├── Infrastructure/Services/
│   └── DocumentNumberingService.php       # NOUVEAU — numérotation paramétrable + retry 23505
├── Interfaces/Api/V1/
│   └── AccountingDocumentController.php   # NOUVEAU — index/store/show/send/payments/cancel/credit-note/next-number
└── Providers/AccountingServiceProvider.php # + binding DocumentNumberingInterface → DocumentNumberingService

api/routes/modules/accounting.php          # NOUVEAU — groupe api.manager:principal,comptable, prefix accounting
api/routes/api.php                         # + require modules/accounting.php
api/openapi.yaml                           # + chemins /accounting/documents*
api/tests/Feature/Accounting/
│   ├── AccountingDocumentWorkflowTest.php     # NOUVEAU — cycle de vie + règles
│   └── AccountingDocumentNumberingTest.php    # NOUVEAU — numérotation + course + RBAC/tenant
docs/validation/FRONTEND_API_CONTRACT_MATRIX.md  # + lignes
docs/security/RBAC_ROUTE_MATRIX.md                # + ligne
docs/specifications/ISSUE_5223.md                 # NOUVEAU — spec issue (règle repo)
CHANGELOG.md                                      # + entrée [Unreleased]
```

## Décisions

1. **Numérotation au brouillon** : le numéro est attribué à la création (stable ensuite) — la série ne change pas à l'envoi. Le numéro est unique par `(company_id, number)`.
2. **Concurrence** : calcul du candidat (max existant + 1) + contrainte unique comme filet + **retry borné (5) sur 23505** dans la transaction de création — pattern upsert du repo (#4978), test de course par pré-insertion (pattern #3726).
3. **Totaux calculés** : `subtotal_ht` = Σ(quantité × prix unitaire − remise), `tax_amount` = subtotal × `tva_rate`, `total_ttc` = subtotal + taxe. Pas de champ montant en base sur les lignes (schéma #5221).
4. **Avoir** : création via action dédiée `POST .../credit-note` (guard source facture + montant ≤ reste à payer) ; lien via `metadata.source_document_id`.
5. **Overdue** : statut calculé/rafraîchi à la lecture (`show`/`index`) + méthode `refreshOverdue(companyId)` — pas de job planifié dans ce lot (Phase B trésorerie).
6. **Paiements** : action `recordPayment` (statut `recorded`) — le rapprochement bancaire (#5229) reste Phase B.
