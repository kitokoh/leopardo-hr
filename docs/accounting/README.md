# Module Comptabilité — Documentation

> Référentiel : `docs/accounting/` — Module 19 (greenfield, DDD).
> Conception : [`docs/architecture/COMPTABILITE_CONCEPTION.md`](../architecture/COMPTABILITE_CONCEPTION.md)

## Périmètre du module

Le module Comptabilité étend Leopardo au cycle financier de l'entreprise :
documents de facturation (facture, proforma, devis, avoir, bon de livraison,
reçu), contacts client/fournisseur, paiements, relances, journal des écritures
et portail client sécurisé. **Zéro impact FOCUS** : module additif, tenant-scoped.

## Cycle de vie d'une facture (vue d'ensemble)

```
Contact ──► Facture (draft) ──► sent ──► [PDF ×4 langues] ──► [email + portail]
                                    │
                                    ▼
                              Paiement ──► partially_paid ──► paid
                                    │
                                    ▼
                            Journal (débit = crédit) + relances J+7/J+15/J+30
```

## Documents de ce dossier

| Document | À qui | Contenu |
|---|---|---|
| [`GUIDE_UTILISATEUR.md`](GUIDE_UTILISATEUR.md) | Comptable, principal, marketing, client portail | Guide par rôle : facturer, encaisser, rapprocher, relancer, consulter le journal |
| [`RUNBOOK.md`](RUNBOOK.md) | Exploitants, support, comptables | Numérotation, TVA, mentions, journal/clôture, relances, PDF, email/portail, migrations, dépannage |
| [`RECETTE_PILOTES_DZ.md`](RECETTE_PILOTES_DZ.md) | Pilotes DZ, formation | Recette de bout en bout : émettre une facture réelle sans assistance |

## Références techniques rapides

- **Module backend** : `api/app/Modules/Accounting/` (Domain / Infrastructure / Interfaces / Application).
- **Tables tenant** : `accounting_contacts`, `accounting_documents`, `accounting_document_lines`,
  `accounting_payments`, `accounting_settings`, `accounting_journal_entries`, `accounting_closed_periods`,
  `accounting_payment_reminders`, `accounting_document_shares`, `accounting_number_counters`.
- **Enums** : `DocumentType` (invoice, proforma, quote, credit_note, delivery_note, receipt),
  `DocumentStatus` (draft, sent, partially_paid, paid, cancelled, overdue),
  `PaymentMethod` (cash, bank_transfer, check, card, other),
  `PaymentStatus` (pending, recorded, matched), `ContactType` (customer, supplier, both).
- **API** : préfixe `/api/v1/accounting/*` — voir `api/openapi.yaml` (tag `Accounting`).
- **i18n** : 4 langues (fr, en, tr, ar — arabe RTL pour les PDF).

## Statut des fonctionnalités

| Fonctionnalité | Issue | Statut |
|---|---|---|
| Modèle de données + isolation tenant | #5221 | ✅ |
| CRUD contacts + RBAC comptable | #5222 | ✅ |
| Workflow documents + numérotation paramétrable | #5223 | ✅ |
| PDF documents multi-langues (6 types × 4 langues) | #5224 | ✅ |
| Email documents + portail client sécurisé | #5225 | ✅ |
| Journal des écritures + clôture de période | #5234 | ✅ |
| Paiements + rapprochement + relances | #5229 | ✅ |
| Consolidation API REST + matrice RBAC | #5226 | ✅ (consolidation #5422) |
| TVA multi-pays, multi-devises | #5270/#5271 | ✅ |
| Plan comptable paramétrable + grand livre + balance | #5422 | ✅ |
| Bilan + compte de résultat + export FEC | #5422 | ✅ |
| Exercices comptables + clôture + lettrage | #5422 | ✅ |
| Paiement en ligne portail client | #5272 | ADR-0017, décision requise |
