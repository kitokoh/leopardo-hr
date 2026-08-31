# SOLUTION_DELIVERY — Spécification BC-26 DELIVERY (module de livraison générique)

> **BC-26 — DELIVERY** — statut `planned`.
> **Owner :** Agent 26 — BC-DELIVERY.
> **Registre :** `dev-hub/governance/bounded-context-registry.json` (BC-26).
> **Cadre :** `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> **Version :** v0.2 (conception élargie multi-tenant) — 2026-08-30.

## 1. Positionnement : un module de livraison, pas une verticale « agence »

BC-26 DELIVERY est le **module de livraison dernier-kilomètre de Leopardo** :
il exécute la livraison (colis/commandes, tournées, livreurs, preuves, suivi,
règlement) pour **tout tenant qui livre**, quel que soit son métier :

| Type de tenant | Source des livraisons | Contrat |
|---|---|---|
| **Agence de livraison** (coursiers, dernière-mille) | Saisie dispatcher manuelle ou API | BC-26 seul (mode `manual`) |
| **Restaurant** (livraison de repas) | Commandes RestaurantManager | **BC-25 RESTAURANT → BC-26** (contrat à ratifier, cf. §6) |
| **Commerce / Retail / POS** | Tickets/commandes Retail | **BC-17 RETAIL → BC-26** |
| **E-commerce / marketplace** | Commandes poussées par API/webhooks | **BC-14 INTEGRATION → BC-26** |
| **Client CRM** (livraison de marchandise) | Commandes/opportunités CRM | **BC-11 CRM → BC-26** |
| **Pharmacie / service de terrain** | Saisie ou intégration | BC-26 + BC-18 (véhicules) |

Le tenant est **l'opérateur de livraison** : le module est scopé `company_id`
(fail-closed #3727) et activable par feature flag (`companies.features.delivery`),
comme les autres modules. **Un restaurant, un e-commerçant ou une agence
utilisent le même moteur** — seules les origines des livraisons diffèrent.

## 2. Périmètre du contexte

| Possède (owned) | Consomme par contrat (ne possède pas) |
|---|---|
| Livraisons / colis / commandes à livrer (référence, type, poids, valeur, COD, adresses) | Employés → livreurs (BC-04 HR, contrat `EmployeeHired/Changed/Departed`) |
| Tournées + affectation livreur/véhicule/jour | Pointage & disponibilité (BC-05 WORKFORCE) |
| Arrêts : ordre, ETA/ETD, statut, POD | Véhicules & flotte (BC-18 FLEET) |
| Preuves de livraison (photo/signature) | Contacts destinataires (BC-11 CRM) |
| Événements de suivi temps réel (tracking) | Notifications destinataire (BC-13 COMMS) |
| Règlement COD & commissions livreurs | Encaissements & écritures (BC-08 ACCOUNTING) |
| Rapports & KPIs livraison | Documents & preuves (BC-20 DOCUMENTS) |
| **Origines** : restaurant/retail/e-commerce/CRM (référence source) | **Commandes sources** : BC-25 RESTAURANT, BC-17 RETAIL, BC-11 CRM, BC-14 INTEGRATION |
| | Read models & KPIs (BC-22 ANALYTICS) |

## 3. Modèle de domaine

```text
Tenant (tout type : agence, restaurant, retail, e-commerce…) ── 1..n ── Delivery (livraison)
   │                                                                       │ 1..n
   │                                                                       DeliveryStop (arrêt)
   │                                                                       │ 1..1
   │                                                                       DeliveryEvent (tracking)
   │                                                                       DeliveryProof (POD → BC-20)
   │
   ├── DeliveryRoute (tournée) ── 1..n ── DeliveryStop
   ├── DeliveryDriver (profil livreur ↔ Employee BC-04)
   ├── DeliveryVehicle (↔ véhicule BC-18)
   └── DeliveryCodSettlement (règlement COD ↔ BC-08)
```

### Agrégats & invariants

- **Delivery** (agrégat racine — générique) : référence unique tenant
  (`DLV-2026-000123`), **source** (`DeliverySource` : `manual / restaurant /
  retail / ecommerce / crm / field`), **`source_reference`** (id de la commande
  source, ex. `RST-…` BC-25, `POS-…` BC-17, `ORD-…` BC-14), **type** de livraison
  (`parcel / order / food / grocery / medication / document`), poids & volume,
  valeur déclarée, montant COD (nullable), adresses ramassage/destination,
  fenêtre de livraison, statut.
  - **Invariant** : une livraison ne peut atteindre un état terminal
    (`delivered` / `returned` / `cancelled`) qu'une seule fois — transitions
    versionnées, aucune réouverture après clôture.
  - **Invariant** : `cod_amount > 0` ⇒ encaissement attendu à la remise ;
    `delivered` sans POD = incohérence bloquée par le workflow.
  - **Invariant** : `source != manual` ⇒ `source_reference` obligatoire et
    **unique par (tenant, source)** — une commande restaurant ne crée jamais
    deux livraisons (idempotence du contrat source).
- **DeliveryRoute** (tournée) : date de tournée, livreur, véhicule, zone,
  liste ordonnée de stops, état (`draft → assigned → in_progress → completed`),
  clôture (totaux livraisons, COD, échecs).
  - **Invariant** : une tournée a **un seul livreur + un seul véhicule par
    date** (pas de chevauchement d'affectation).
  - **Invariant** : la clôture est idempotente — deux clôtures produisent le
    même résultat (exigence BC-22 « deux recalculs produisent le même résultat »).
- **DeliveryStop** (arrêt) : ordre de passage, adresse, fenêtre, ETA/ETD,
  statut (`pending / en_route / arrived / delivered / failed / skipped`), POD.
- **DeliveryEvent** (tracking) : `picked_up / out_for_delivery / arrived /
  delivered / failed / returned`, horodaté, géolocalisé, émis par l'app livreur
  (offline inclus) — **idempotent** (clé `(company_id, delivery_id, type, event_at)`
  ou `idempotency_key` client).
- **DeliveryProof** (POD) : photo (upload BC-20, URLs temporaires) et/ou
  signature, horodatée, rattachée au stop — requis pour `delivered`.
- **DeliveryCodSettlement** : montant COD collecté par tournée/livreur, remise
  caisse, commissions, contrat de posting idempotent vers BC-08.

### Cycle de vie de la livraison

```text
created → assigned (tournée) → picked_up → out_for_delivery → arrived
   → delivered (POD obligatoire) | failed → retour (returned) | cancelled
```

## 4. API (v1, versionnée)

| Méthode | Route | Rôle | Description |
|---|---|---|---|
| GET/POST | `/api/v1/deliveries` | dispatcher | CRUD livraisons (filtres source/statut/date/zone, pagination) |
| GET/POST/PATCH | `/api/v1/deliveries/routes` | dispatcher | Tournées : création, affectation livreur/véhicule, ordre des stops |
| POST | `/api/v1/deliveries/routes/{route}/assign` | dispatcher | Affectation (idempotente, garde chevauchement) |
| POST | `/api/v1/deliveries/routes/{route}/close` | dispatcher | Clôture (idempotente, totaux) |
| GET | `/api/v1/deliveries/routes/today` | livreur (mobile) | Tournée du jour du livreur authentifié |
| POST | `/api/v1/deliveries/{delivery}/status` | livreur (mobile) | Changement de statut + POD (photo/signature) |
| POST | `/api/v1/deliveries/events` | livreur/edge | Événement de tracking (idempotent, offline replay) |
| GET | `/api/v1/deliveries/{delivery}/tracking` | destinataire/public (lien borné) | Suivi sans authentification (token court, BC-20) |
| GET | `/api/v1/deliveries/reports/summary` | manager | KPIs (livrées/jour, taux de succès, délais, COD) par source |

RBAC : `delivery.dispatcher` (gestion tournées/livraisons), `delivery.rider`
(mobile livreur), `delivery.manager` (rapports), `delivery.admin`
(paramétrage). Middleware `module.delivery` (kill switch, pattern
cameras/travel/restaurant).

**Contrat sources** : les modules consommateurs créent les livraisons via
`POST /api/v1/deliveries` avec `source` + `source_reference` et une
`idempotency_key` — jamais de création directe en base inter-module.

## 5. Les douze dimensions (audit de conception)

| # | Dimension | Statut conception | Exigence |
|---|---|---|---|
| D1 | Domaine | 🔵 CONÇU | Glossaire générique, agrégats, invariants (ci-dessus) — issue BC-26-D01 |
| D2 | Données | 🔵 CONÇU | Migrations tenant `delivery_*` : livraisons (avec `source`, `source_reference`), tournées, stops, événements, POD, règlements — index `(company_id, statut, date)`, unique `(company_id, source, source_reference)`, réentrantes |
| D3 | Tenant | 🔵 CONÇU | Tout scopé `company_id` (fail-closed #3727), tests cross-tenant multi-types (agence vs restaurant vs e-commerce) |
| D4 | API | 🔵 CONÇU | Routes v1, Requests strictes, Resources allowlistées, OpenAPI |
| D5 | Autorisation | 🔵 CONÇU | Matrice RBAC livreur/dispatcher/manager/admin + tests 401/403 |
| D6 | Transactions | 🔵 CONÇU | Idempotence événements + clôture + création par source, verrouillage statut (`SELECT FOR UPDATE`) |
| D7 | Asynchronisme | 🔵 CONÇU | Jobs de clôture/export/notifications, retry borné, DLQ, replay |
| D8 | Sécurité | 🔵 CONÇU | POD = données personnelles (RGPD), URLs temporaires, redaction logs, rate limits |
| D9 | Frontends | 🔵 CONÇU | App mobile livreur (offline + replay, pattern EdgeSync), dashboard dispatcher multi-source |
| D10 | Performance | 🔵 CONÇU | Budgets p95 (registre MAT-014), index, pagination, pas de N+1 |
| D11 | Exploitation | 🔵 CONÇU | Runbook livraison, logs corrélés, alertes (échecs, retards, COD manquants) |
| D12 | Produit | 🔵 CONÇU | Golden journeys par source (agence manuelle, restaurant, e-commerce) + seed pilote synthétique |

## 6. Contrats inter-contextes (sources consommatrices)

- **BC-25 RESTAURANT** (à ratifier dès merge #6236) : les commandes restaurant
  deviennent des `deliveries` de source `restaurant` (référence `RST-…`).
  **Alignement requis** : les modèles `RestaurantDelivery*` conçus dans BC-25
  ne doivent PAS dupliquer le moteur BC-26 — BC-25 conserve zones/livreurs
  restaurant et **consomme BC-26 pour l'exécution** (tournées, tracking, POD).
- **BC-17 RETAIL** : les ventes/tickets avec livraison créent des `deliveries`
  de source `retail` (référence `POS-…`).
- **BC-11 CRM** : livraison de marchandise à un client (adresse du contact).
- **BC-14 INTEGRATION** : les plateformes e-commerce poussent les commandes via
  API/webhooks (inbox/outbox) → création `deliveries` de source `ecommerce`
  avec `idempotency_key` (zéro doublon).
- **BC-18 FLEET** : véhicules de tournée (contrat, pas de duplication).
- **BC-08 ACCOUNTING** : posting idempotent des encaissements COD + commissions.
- **BC-20 DOCUMENTS** : upload des preuves (POD) via le contrat documents.
- **BC-13 COMMS** : notifications destinataire (SMS/WhatsApp) avec opt-out.
- **BC-22 ANALYTICS** : read models KPIs livraison (déterminisme, budgets p95).

## 7. Golden journeys (sortie exigée)

1. **Agence (mode manual)** : le dispatcher crée 3 livraisons (dont 1 COD) →
   tournée du jour affectée à un livreur + véhicule → mobile : picked_up →
   out_for_delivery → arrived → photo POD → delivered ; 1 échec → returned ;
   clôture (totaux) ; rapport manager ; lien destinataire ; réconciliation COD.
2. **Restaurant (contrat BC-25)** : une commande `RST-…` crée une `delivery`
   de source `restaurant` (idempotente) → même moteur de tournée/POD → le
   restaurant suit le statut dans son dashboard.
3. **E-commerce (contrat BC-14)** : commande poussée par webhook → `delivery`
   de source `ecommerce` (rejeu webhook → zéro doublon) → suivi destinataire.

## 8. Hors périmètre (à ne PAS faire ici)

- Pas de duplication des commandes sources : BC-25/17/11/14 restent propriétaires
  de leurs commandes ; BC-26 ne stocke que `source` + `source_reference`.
- Les véhicules restent dans BC-18 ; les employés/pointage dans BC-04/05.
- Le paiement en ligne n'est pas un prérequis : le COD est le flux initial ;
  les paiements digitaux viendront par contrat BC-21/BC-08.
