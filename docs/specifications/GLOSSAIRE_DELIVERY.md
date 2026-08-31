# Glossaire unifié — BC-26 DELIVERY (v0.2 générique multi-tenant)

> BC-26-D01 (#6292). Lexique commun du dernier-kilomètre, utilisé tel quel
> dans le code (`api/app/Modules/Delivery`), les APIs, les tests et la doc.
> Toute nouvelle notion doit entrer ici AVANT d'entrer dans le code.

## Objets métier

| Terme | Définition | Notes techniques |
|---|---|---|
| **Livraison / colis** | Unité de transport d'un point A vers un point B (parcel, order, food, grocery, medication, document) | table `delivery_deliveries`, référence `DLV-YYYY-NNNNNN` |
| **Source** | Module consommateur qui a créé la livraison : `manual` \| `restaurant` \| `retail` \| `ecommerce` \| `crm` \| `field` | unique `(company_id, source, source_reference)` — zéro doublon |
| **source_reference** | Référence de la commande côté source (`RST-…`, `POS-…`, id webhook) | obligatoire hors `manual` (DELIVERY-208) |
| **Tournée** | Planification d'un livreur + véhicule pour une date, avec un ordre de passage | table `delivery_routes` ; 1 livreur = 1 tournée/date ; `draft → assigned → in_progress → completed` |
| **Stop / arrêt** | Point de passage d'une tournée (adresse, contact, ETA/ETD, POD) | table `delivery_stops` ; `pending → en_route → arrived → delivered/failed/skipped` |
| **POD** | Preuve de livraison (photo/signature), document BC-20 par valeur | exigée pour `delivered` (`proof_document_id`) |
| **COD** | Contre-remboursement : montant collecté à la livraison | `cod_amount_minor` (minor units) ; règlement `delivery_cod_settlements` |
| **Règlement** | Cycle de l'encaissement COD : `pending → collected → settled → reconciled` | posting BC-08 idempotent, commission livreur |
| **Événement de tracking** | Fait observé en terrain (`picked_up`, `out_for_delivery`, `arrived`, `delivered`, `failed`, `returned`) | idempotent `(company, delivery, type, event_at)` |
| **Lien de suivi** | URL publique bornée (token 64 chars expirant) pour le destinataire | pattern AccountingDocumentShare #5225 |

## Personas (RBAC BC-26-D05)

| Rôle | Qui | Droits |
|---|---|---|
| `delivery.admin` | manager principal | tout, dont settle/reconcile |
| `delivery.dispatcher` | manager principal/operations | planification des tournées, création livraisons |
| `delivery.manager` | tout manager | supervision, lecture, rapports |
| `delivery.reports` | tout manager | KPIs (alias manager) |
| `delivery.rider` | employé non-manager | sa tournée (propriété), statuts, POD |

## États & invariants

- Cycle de vie livraison : `created → assigned → picked_up → out_for_delivery → arrived → delivered` ; `failed`/`returned`/`cancelled` sous conditions (machine à états `DeliveryStateMachine`, DELIVERY-103).
- États terminaux irréversibles : `delivered`, `returned`, `cancelled`.
- `delivered` exige la POD ; une tournée close est immuable ; une clôture/affectation rejouée est sans effet (idempotence).
- Toute opération est scopée `company_id` (fail-closed #3727) ; le token de lien public EST la credential (pas d'auth).

## Conventions

- Montants en **minor units** (`*_minor`) ; timestamps ISO-8601 ; pagination ≤ 100.
- Migration tenant canonique (`php artisan leopardo:migrate`), parité `CreatesMvpSchema`.
- 1 issue = 1 PR ; gardes registre/OpenAPI/budgets (MAT-014).
