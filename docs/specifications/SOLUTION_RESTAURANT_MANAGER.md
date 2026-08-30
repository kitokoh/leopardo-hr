# SOLUTION RESTAURANT MANAGER — Spécification technique de la verticale « Restauration »

> **Statut :** Proposition technique prête pour implémentation par lots (issues `RESTO-*`)
> **Base :** dernière tête de `main` vérifiée le 29 août 2026
> **Périmètre :** verticale opérationnelle « Restaurant » pour un tenant Leopardo (restaurateur) :
> point de vente & caisse, commandes (salle / à emporter / livraison), réservations de tables,
> stock & achats (COGS), fournisseurs, livraison, fidélité & promotions, rapports. S'appuie sur la
> plateforme (tenant, identité, RBAC, HR, CRM, notifications, documents, comptabilité) sans la dupliquer.
> **Règle d'or AGENTS.md :** ce document doit être **validé par le propriétaire** avant que les issues
> GitHub du module soient créées — il constitue la source de vérité des issues `RESTO-*`.

---

## Table des matières

1. [Contexte & objectifs](#1-contexte--objectifs)
2. [Décisions d'architecture](#2-décisions-darchitecture)
3. [Modèle de données](#3-modèle-de-données)
4. [Flux métier de bout en bout](#4-flux-métier-de-bout-en-bout)
5. [API v1](#5-api-v1)
6. [Paiements & intégrations](#6-paiements--intégrations)
7. [Sécurité & RGPD](#7-sécurité--rgpd)
8. [Activation, provisioning & onboarding](#8-activation-provisioning--onboarding)
9. [Tests, qualité & Definition of Done](#9-tests-qualité--definition-of-done)
10. [Plan de livraison (lots + tâches fines)](#10-plan-de-livraison-lots--tâches-fines)
11. [Ordre de priorité & séquencement](#11-ordre-de-priorité--séquencement)
12. [Éléments non prévus](#12-éléments-non-prévus-volontairement-exclus)
13. [Références](#13-références)

---

## 1. Contexte & objectifs

### 1.1 Pourquoi cette verticale

Leopardo HR est une plateforme modulaire multi-tenant. Les **solutions verticales** ajoutent des
workflows propres à un secteur sans remplacer ni recopier les modules transversaux
(voir `docs/specifications/PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md`).

Le tenant cible est un **restaurateur** (simple ou multi-établissements). Il utilise déjà la
plateforme pour gérer ses employés (HR, présence, paie) et sa relation client (CRM). La verticale
**RestaurantManager** lui ajoute l'outillage opérationnel quotidien : prise de commande, caisse,
réservations, stock, achats, livraison, fidélité et pilotage — dans son espace client, multi-appareils
(web back-office, tablette/caisse, mobile serveur).

### 1.2 Persona

- **Gérant / propriétaire** (`restaurant.manage`) : configuration, tarifs, menus, rapports, clôtures.
- **Manager de salle** (`restaurant.manager`) : réservations, affectation des tables, validation.
- **Serveur / caissier** (`restaurant.server`) : prise de commande, service, encaissement.
- **Cuisinier** (`restaurant.kitchen`) : file de commandes en cuisine (écran).
- **Livreur** (`restaurant.rider`) : tournées de livraison.
- **Client** (contact CRM) : réservation en ligne, commande à emporter/livraison, fidélité.

### 1.3 Principes directeurs

1. **La plateforme reste la plateforme** : la verticale consomme les modules transversaux par
   contrats et événements, jamais par import direct (garde #5584).
2. **Tenant-safe** : `company_id` non nullable partout, `BelongsToCompany`, fail-closed (403).
3. **Invariants métier solides** : stock décrémenté à la vente (jamais négatif), caisse clôturée
   exactement (totaux recalculés serveur), commandes idempotentes, paiements idempotents, workflow
   d'états par enums + transitions validées.
4. **Aucun secret en dur** : passerelles de paiement et intégrations livraison en config/contrat.
5. **Monnaie** : montants en unités mineures entières + devise du tenant ; TVA configurable.
6. **Exhaustivité** : le plan couvre l'opérationnel complet du restaurant (POS, stock, réservations,
   livraison, fidélité, rapports) + extensions (commande en ligne publique, intégrations apps).

---

## 2. Décisions d'architecture

### D1 — Nom, emplacement, identifiants

| Élément | Valeur |
|---|---|
| Bounded context | **BC-25 — RESTAURANT** (contexte `RestaurantManager`) |
| Module | `api/app/Modules/RestaurantManager` (`App\Modules\RestaurantManager\*`) |
| Préfixe routes | `/api/v1/restaurant/*` |
| Préfixe tables | `restaurant_*` |
| Feature flag | `companies.features.restaurantmanager` |
| Manifest | `RestaurantManagerManifest` (code `restaurantmanager`, maturité `pilot`) |
| Label GitHub | `BC-25 RESTAURANT` |

**Justification `Modules/` (et non `Solutions/`)** : aligné sur l'implémentation réelle des verticales
(TravelAgency, EduManager) et sur les conventions routes/providers/tests — cf. décision D1 de
`SOLUTION_TRAVEL_AGENCY.md`.

### D2 — Le tenant EST le restaurant (ou le groupe)

Une `company` Leopardo = un établissement ou un groupe de restauration. Les établissements physiques
deviennent des **branches** tenant-scoped (`restaurant_branches`) ; chaque branche a ses tables, ses
horaires, ses caisses. Le multi-établissement est natif (rapports consolidés optionnels par permission).

### D3 — POS : sessions de caisse et commandes transactionnelles

- **Sessions de caisse** : ouverture (fonds de caisse) → encaissements → clôture (totaux serveur,
  écart calculé, motif si écart). Une seule session ouverte par caisse/établissement.
- **Commandes** : statuts `draft → open → in_preparation → ready → served → paid → closed`
  (+ `cancelled`, `refunded`) ; chaque transition par Action validée + événement.
- **Idempotence** : `idempotency_key` sur la création de commande et de paiement.

### D4 — Stock : décrément à la vente, COGS, inventaire

- Composition des produits : `restaurant_product_ingredients` (quantité, unité).
- À la **confirmation d'une commande**, le stock des ingrédients est décrémenté en transaction
  (`SELECT FOR UPDATE` sur les lignes de stock) ; stock insuffisant → avertissement ou blocage
  configurable par produit.
- **COGS** : coût théorique consommé calculé serveur (quantités × coût moyen pondéré).
- **Inventaire physique** : comptages avec écarts justifiés ; seuils d'alerte → événement.

### D5 — Paiements : contrat de passerelle (pattern Travel)

```php
interface PaymentGatewayInterface
{
    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResult;
    public function verify(VerifyPaymentRequest $request): PaymentStatus;
    public function refund(RefundRequest $request): RefundResult;
}
```
- Adapters v1 : `CashPaymentGateway` (espèces), `CardPaymentGateway` (terminal — paiement confirmé
  localement), `MobileMoneyPaymentGateway` (sandbox, ex. PVIT/Orange Money via config).
- Callbacks signés + idempotents ; montants en minor units ; payloads redacted.
- Futur : contrat partagé multi-verticales (chantier BC paiements transversal) — les deux verticales
  (Travel, Restaurant) implémentent le même contrat dans leur module en attendant.

### D6 — Réservations : plan de salle et créneaux

- Tables (`restaurant_tables`) rattachées à une zone (`restaurant_zones`) ; capacité et
  composition possible (tables fusionnables).
- Réservation : date/heure, nombre de couverts, table(s) affectée(s), statut
  `pending → confirmed → seated → completed | no_show | cancelled` ; conflit de créneau refusé (409).
- Arrhes/dépôt optionnels (via paiement) ; politique d'annulation configurable.

### D7 — Livraison & à emporter

- Commandes `takeaway` / `delivery` : zones de livraison (`restaurant_delivery_zones` avec tarifs),
  livreurs (`restaurant_delivery_riders` — référence employé HR par valeur), statuts
  `pending → preparing → ready → out_for_delivery → delivered | cancelled`.
- Intégration apps de livraison (Uber Eats/Glovo…) : **Phase 2** via contrat d'adaptateur (mêmes
  principes que les webhooks transporteurs de Travel).

### D8 — Fidélité & promotions

- Programme tenant-scoped : points par tranche de montant, récompenses (réductions), opt-in RGPD.
- Promotions : happy hour, offres par produit/catégorie, code promo — validées serveur, bornées,
  cumul contrôlé.

### D9 — Intégrations inter-modules : événements & contrats uniquement

- Outbox `restaurant_outbox_events` (pattern `crm_outbox_events` #5741).
- Événements : `restaurant.order.created.v1`, `restaurant.order.paid.v1`,
  `restaurant.table.closed.v1`, `restaurant.payment.confirmed.v1`, `restaurant.reservation.confirmed.v1`,
  `restaurant.stock.alert.v1`, `restaurant.pos.closed.v1`, `restaurant.sales.settled.v1` (Accounting).
- Notifications (BC-13), CRM (contacts clients), Documents (tickets PDF, bons), Accounting (synthèse).

### D10 — Activation & sécurité d'accès

- Feature flag `restaurantmanager` + middleware `module.restaurantmanager` (pattern module.cameras).
- Permissions : `restaurant.manage`, `restaurant.manager`, `restaurant.server`, `restaurant.kitchen`,
  `restaurant.rider`, `restaurant.reports`. Policies Laravel sur chaque action.

---

## 3. Modèle de données

> Toutes les tables portent `company_id` (UUID) non nullable + timestamps ; index tenant-first.
> Migrations **tenant** (`api/database/migrations/tenant/`), nommage `YYYY_MM_DD_0000NN_<issue>_<slug>.php`,
> réentrantes (helpers `schemaTableExists`/`resolveTableSchema`), exécutées par `php artisan leopardo:migrate`,
> parité `CreatesMvpSchema` (#5443). Montants **minor units** + devise. PII clients chiffrées/redactées.

### 3.1 Référentiel & configuration

```text
restaurant_branches
- id, company_id
- code, name, address, city, phone, timezone, currency, status
- UNIQUE(company_id, code)

restaurant_zones             # salles / zones (terrasse, salle, bar…)
- id, company_id, branch_id
- name, color NULL, sort_order, status

restaurant_tables
- id, company_id, branch_id, zone_id
- label, capacity, min_covers NULL, is_mergeable bool, status
- UNIQUE(company_id, branch_id, label)

restaurant_categories        # entrées / plats / desserts / boissons
- id, company_id, branch_id NULL (0 = toutes)
- name, color NULL, sort_order, status

restaurant_products          # plats et boissons vendables (recettes)
- id, company_id, branch_id NULL, category_id
- code, name, description_redacted NULL, price_minor int, currency
- cost_minor int NULL (coût matière théorique), tax_rate_id NULL
- is_available bool, image_asset_id NULL, status
- UNIQUE(company_id, code)

restaurant_product_ingredients
- id, company_id, product_id, ingredient_id, quantity numeric, unit_code
- UNIQUE(company_id, product_id, ingredient_id)

restaurant_ingredients       # matières premières
- id, company_id, branch_id
- code, name, unit_code (kg/l/unite), avg_cost_minor int NULL, status
- UNIQUE(company_id, branch_id, code)

restaurant_units             # référentiel d'unités (kg, l, u, pce…)
- id, company_id, code, label, status
- UNIQUE(company_id, code)

restaurant_menus             # formules / menus (ex. menu du jour)
- id, company_id, branch_id NULL, code, name, price_minor, currency
- starts_at/ends_at NULL, status
- UNIQUE(company_id, code)

restaurant_menu_items
- id, company_id, menu_id, product_id, position, is_optional bool
- UNIQUE(company_id, menu_id, product_id)

restaurant_hours             # horaires d'ouverture par branche
- id, company_id, branch_id
- day_of_week smallint, opens_at time, closes_at time, is_closed bool

restaurant_suppliers
- id, company_id, name, contact_phone, email NULL, address NULL, status

restaurant_tax_rates
- id, company_id, code, label, rate_bps int (taux en points de base), is_default bool
- UNIQUE(company_id, code)
```

### 3.2 Point de vente & caisse

```text
restaurant_pos_sessions
- id, company_id, branch_id
- opened_at, closed_at NULL, opened_by_user_id, closed_by_user_id NULL
- opening_cash_minor int, expected_cash_minor NULL, counted_cash_minor NULL
- variance_minor NULL, variance_reason NULL
- status (open|closed|cancelled), version
- UNIQUE(company_id, branch_id) PARTIAL (une seule session ouverte par branche)

restaurant_orders
- id, company_id, branch_id, pos_session_id NULL
- reference (unique), order_type (dine_in|takeaway|delivery)
- table_id NULL, zone_id NULL, covers int NULL
- customer_contact_id NULL, rider_id NULL
- status (draft|open|in_preparation|ready|served|paid|closed|cancelled|refunded)
- subtotal_minor, tax_minor, discount_minor, total_minor, currency
- source (pos|web|phone|delivery_app), note_redacted NULL
- idempotency_key, version
- UNIQUE(company_id, reference), UNIQUE(company_id, idempotency_key)

restaurant_order_items
- id, company_id, order_id
- product_id, menu_id NULL, quantity numeric, unit_price_minor, line_total_minor
- tax_rate_id NULL, tax_minor NULL, status (active|cancelled)
- UNIQUE(company_id, order_id, product_id, line_index)

restaurant_order_payments
- id, company_id, order_id, pos_session_id NULL
- provider_code (cash|card|mobile_money), amount_minor, currency
- status (pending|confirmed|failed|refunded), paid_at NULL, provider_reference NULL
- tip_minor NULL, callback_payload_redacted JSONB NULL, idempotency_key
- UNIQUE(company_id, idempotency_key)

restaurant_refunds
- id, company_id, order_id, payment_id NULL
- amount_minor, reason_code, reason_text_redacted, refunded_by_user_id, status
- UNIQUE(company_id, idempotency_key)

restaurant_table_sessions   # occupation d'une table (ouverture → clôture)
- id, company_id, branch_id, table_id, order_id NULL
- opened_at, closed_at NULL, covers int, status (open|closed|cancelled)
```

### 3.3 Réservations

```text
restaurant_reservations
- id, company_id, branch_id
- reference (unique), customer_contact_id NULL, contact_name, contact_phone
- reserved_at (datetime), covers int, table_id NULL, zone_id NULL
- status (pending|confirmed|seated|completed|no_show|cancelled)
- deposit_minor NULL, notes_redacted NULL, idempotency_key
- UNIQUE(company_id, reference), UNIQUE(company_id, idempotency_key)
- INDEX(company_id, branch_id, reserved_at)
```

### 3.4 Stock & achats

```text
restaurant_stock_levels
- id, company_id, branch_id, ingredient_id
- quantity numeric, avg_cost_minor int NULL
- UNIQUE(company_id, branch_id, ingredient_id)

restaurant_inventory_movements
- id, company_id, branch_id, ingredient_id, stock_level_id NULL
- quantity_delta numeric, reason_code (sale|receiving|count|adjustment|waste|transfer)
- reference_type NULL, reference_id NULL, note_redacted NULL, user_id NULL

restaurant_purchase_orders
- id, company_id, branch_id, supplier_id
- reference (unique), status (draft|sent|received|cancelled), expected_at NULL, received_at NULL
- total_minor int NULL, currency
- UNIQUE(company_id, reference)

restaurant_purchase_order_items
- id, company_id, purchase_order_id, ingredient_id
- quantity numeric, unit_price_minor int, line_total_minor int

restaurant_receivings          # réceptions (entrées stock)
- id, company_id, branch_id, purchase_order_id NULL, supplier_id NULL
- reference, received_at, note_redacted NULL

restaurant_inventory_counts    # inventaires physiques
- id, company_id, branch_id
- counted_at, status (draft|submitted|approved), counted_by_user_id, approved_by NULL

restaurant_inventory_count_items
- id, company_id, count_id, ingredient_id, expected_qty, counted_qty, variance_qty, reason_code NULL
```

### 3.5 Livraison & fidélité

```text
restaurant_delivery_zones
- id, company_id, branch_id
- name, fee_minor int, min_order_minor int NULL, status
- UNIQUE(company_id, branch_id, name)

restaurant_delivery_riders
- id, company_id, branch_id
- employee_id NULL (référence HR par valeur), name, phone, vehicle_code NULL, is_active bool

restaurant_deliveries
- id, company_id, order_id
- zone_id NULL, rider_id NULL, status (pending|assigned|out_for_delivery|delivered|cancelled)
- fee_minor int, delivered_at NULL, delivered_to_contact NULL

restaurant_loyalty_programs
- id, company_id
- points_per_amount_minor int, redeem_rate_minor int, is_active bool

restaurant_loyalty_customers
- id, company_id, customer_contact_id, points int, tier_code NULL
- UNIQUE(company_id, customer_contact_id)

restaurant_loyalty_points_movements
- id, company_id, customer_id, delta int, reason_code, order_id NULL, reference_id NULL

restaurant_promotions
- id, company_id, branch_id NULL
- code, title, discount_type (percent|amount), value_minor, min_order_minor NULL
- starts_at, ends_at, max_uses NULL, used_count int, is_active bool
- UNIQUE(company_id, code)
```

### 3.6 Intégration & audit

```text
restaurant_outbox_events      # pattern crm_outbox_events (#5741)
- id, company_id, event_type (restaurant.*.v1), payload_redacted JSONB
- status (pending|published|failed), available_at, attempts, last_error, idempotency_key
- UNIQUE(company_id, idempotency_key)
```

---

## 4. Flux métier de bout en bout

### 4.1 Service en salle (flux roi)

```text
Ouverture de caisse (POST /restaurant/pos-sessions) — fonds de caisse
  → Ouverture de table (POST /restaurant/tables/{table}/open)
  → Création de commande (POST /restaurant/orders, idempotency_key, table_id)
  → Ajout d'articles (POST /restaurant/orders/{order}/items) — stock décrémenté à la confirmation
  → Écran cuisine (GET /restaurant/kitchen/orders) — statuts in_preparation → ready
  → Service (POST /restaurant/orders/{order}/serve)
  → Addition (GET /restaurant/orders/{order}/bill) — totaux serveur, promo, TVA
  → Encaissement (POST /restaurant/orders/{order}/pay) — espèces/carte/mobile money + pourboire
  → Clôture de table (POST /restaurant/tables/{table}/close)
  → Clôture de caisse (POST /restaurant/pos-sessions/{session}/close) — écart calculé + motif si besoin
```

### 4.2 À emporter / livraison

```text
Commande takeaway/delivery (source pos|phone|web)
  → Zone de livraison + frais (si delivery)
  → Préparation → prête → (rider affecté) → en route → livrée
  → Paiement à la livraison ou en ligne
```

### 4.3 Réservation → service

```text
Réservation (web/téléphone/CRM) → confirmation (événement + notification)
  → Arrivée : check-in → table affectée (conflit 409)
  → Seated → commande en salle → … (flux 4.1)
  → no_show après délai configuré (job)
```

### 4.4 Stock & achats

```text
Seuil d'alerte franchi → événement restaurant.stock.alert.v1
  → Bon de commande fournisseur (draft → sent)
  → Réception → mouvements de stock (+ coût moyen recalculé)
  → Inventaire physique périodique → écarts justifiés → ajustements
  → COGS calculé à la clôture (ventes × composition × coût moyen)
```

### 4.5 Workflows d'états (enums + transitions validées)

```text
Order    : draft → open → in_preparation → ready → served → paid → closed
           draft/open → cancelled ; paid → refunded (motif + audit)
POS      : open → closed | cancelled
Table    : free → occupied → cleaning → free  (via table_sessions)
Reservation : pending → confirmed → seated → completed | no_show | cancelled
Delivery : pending → assigned → out_for_delivery → delivered | cancelled
PO       : draft → sent → received | cancelled
```

---

## 5. API v1

> Conventions #4930 (POST action, PATCH modification, GET lecture) ; groupe
> `['throttle:api','auth:sanctum','token.refresh','tenant','throttle:api-plan','module.restaurantmanager']`,
> préfixe `restaurant`, chargé dans `api/routes/api.php`. Chaque endpoint documenté dans
> `api/openapi.yaml` (coverage CI). Contexte tenant + Policies (mismatch → 404).

### 5.1 Référentiel (back-office)

| Méthode | Route | Action |
|---|---|---|
| CRUD | `/restaurant/branches` · `/restaurant/branches/{branch}/zones` · `/tables` | Établissements, zones, tables |
| CRUD | `/restaurant/categories` · `/restaurant/products` (+ `/products/{product}/ingredients`) | Catalogue & recettes |
| CRUD | `/restaurant/ingredients` · `/restaurant/units` | Matières & unités |
| CRUD | `/restaurant/menus` (+ items) · `/restaurant/tax-rates` | Menus & fiscalité |
| CRUD | `/restaurant/suppliers` · `/restaurant/hours` | Fournisseurs & horaires |

### 5.2 POS & caisse

| Méthode | Route | Action |
|---|---|---|
| POST | `/restaurant/pos-sessions` · `/restaurant/pos-sessions/{session}/close` | Ouvrir/clôturer la caisse |
| GET | `/restaurant/pos-sessions/current` | Session en cours |
| POST | `/restaurant/orders` (idempotent) · GET liste/détail | Commandes |
| POST | `/restaurant/orders/{order}/items` · `/items/{item}/cancel` | Articles |
| POST | `/restaurant/orders/{order}/submit` · `/confirm` · `/serve` | Transitions |
| GET | `/restaurant/orders/{order}/bill` | Addition (totaux serveur) |
| POST | `/restaurant/orders/{order}/pay` · `/refund` | Paiement / remboursement |
| POST | `/restaurant/tables/{table}/open` · `/close` | Occupation des tables |
| GET | `/restaurant/kitchen/orders` | File cuisine (par branche) |
| POST | `/restaurant/kitchen/orders/{order}/start` · `/ready` | Transitions cuisine |

### 5.3 Réservations

| Méthode | Route | Action |
|---|---|---|
| CRUD | `/restaurant/reservations` (+ `/{reservation}/confirm` · `/check-in` · `/no-show` · `/cancel`) | Réservations |
| GET | `/restaurant/reservations/availability` | Créneaux disponibles (table, date, couverts) |

### 5.4 Stock & achats

| Méthode | Route | Action |
|---|---|---|
| CRUD | `/restaurant/stock-levels` · `/restaurant/inventory-movements` | Niveaux & mouvements |
| CRUD | `/restaurant/purchase-orders` (+ `/send` · `/receive`) | Bons de commande |
| CRUD | `/restaurant/receivings` | Réceptions |
| CRUD | `/restaurant/inventory-counts` (+ `/submit` · `/approve`) | Inventaires |
| GET | `/restaurant/stock/alerts` | Alertes de seuil |

### 5.5 Livraison & fidélité

| Méthode | Route | Action |
|---|---|---|
| CRUD | `/restaurant/delivery-zones` · `/restaurant/delivery-riders` | Zones & livreurs |
| POST | `/restaurant/deliveries` (+ `/assign` · `/out-for-delivery` · `/deliver` · `/cancel`) | Cycle de livraison |
| CRUD | `/restaurant/loyalty-programs` · `/restaurant/loyalty-customers` (+ `/points` movements) | Fidélité |
| CRUD | `/restaurant/promotions` | Promotions & codes |

### 5.6 Rapports

| Méthode | Route | Action |
|---|---|---|
| GET | `/restaurant/reports/sales` · `/occupancy` · `/products` · `/cogs` · `/pos` | Ventes, occupation, top produits, coût matière, caisses |
| GET | `/restaurant/reports/export` | Export CSV idempotent |

---

## 6. Paiements & intégrations

### 6.1 Adapters

| Provider | Implémentation | v1 |
|---|---|---|
| Espèces | `CashPaymentGateway` — confirmation manuelle par un serveur autorisé | ✅ |
| Carte (terminal) | `CardPaymentGateway` — paiement confirmé localement par le terminal | ✅ |
| Mobile money | `MobileMoneyPaymentGateway` — **sandbox** (identifiants en config), callback signé | ✅ |
| Apps de livraison | Contrat `DeliveryAppAdapter` (webhooks entrants/sortants) | P2 |

### 6.2 Callback sécurisé

- Signature HMAC (secret par tenant), idempotence par `UNIQUE(company_id, idempotency_key)`,
  payload redacted, montant vérifié, aucune stack trace.

### 6.3 Événements (outbox)

| Événement | Consommateurs autorisés |
|---|---|
| `restaurant.order.created.v1` | Kitchen UI, Notifications, Reporting |
| `restaurant.order.paid.v1` | Accounting, Fidélité, Reporting |
| `restaurant.table.closed.v1` | Reporting, Accounting |
| `restaurant.payment.confirmed.v1` | Accounting, Notifications |
| `restaurant.reservation.confirmed.v1` | Notifications, CRM (activité) |
| `restaurant.stock.alert.v1` | Notifications (gérant), Reporting |
| `restaurant.pos.closed.v1` | Accounting (synthèse), Reporting |
| `restaurant.sales.settled.v1` | Accounting (synthèse périodique validée) |

### 6.4 Intégrations transversales

| Besoin | Mécanisme |
|---|---|
| Client → contact CRM | Contrat `RestaurantCustomerContactResolver` + événements |
| Synthèse ventes → Accounting | Événement `restaurant.sales.settled.v1` |
| Notifications | BC-13 (canal configuré + consentement) |
| Tickets/bons PDF → Documents | Contrat documents (BC-20), fallback disque + URL signée |
| Personnel serveurs/livreurs | HR (BC-04) propriétaire ; références par valeur (`employee_id`) |

---

## 7. Sécurité & RGPD

| Sujet | Mesure |
|---|---|
| Tenant isolation | `company_id` + `BelongsToCompany` (fail-closed), Policies (mismatch → 404), tests cross-tenant |
| PII clients | Contacts via CRM ; données de réservation (nom, téléphone) chiffrées/redactées ; rétention documentée ; droit d'effacement |
| Paiements | Secrets en config, callback signé, payloads redacted, minor units, audit |
| Caisse | Totaux recalculés serveur, écart justifié, clôture immuable (version) |
| API | Rate limiting, Requests strictes, erreurs sûres, OpenAPI à jour |
| Audit | `Auditable` + événements + outbox ; traçabilité des transitions |

---

## 8. Activation, provisioning & onboarding

- Feature flag `restaurantmanager` + middleware `module.restaurantmanager` ; kill switch 403.
- Manifest `RestaurantManagerManifest` : `requiredModules ['rh','documents','notifications','crm']`,
  `optionalModules ['accounting','marketing']`, permissions `restaurant.*`, sensitiveData
  `customer_pii`, `payments`.
- Catalogue onboarding : secteur `restaurant` (solutions recommandées : RestaurantManager + RH,
  Documents, Notifications, CRM ; optionnels : Accounting, Marketing).
- Commandes `leopardo:restaurant:activate {company}` et `leopardo:restaurant:seed-demo {company}`
  (idempotentes) — pattern Travel (TRAVEL-105/107).

---

## 9. Tests, qualité & Definition of Done

- 1 test Feature minimum par endpoint (PHPUnit, `Tests\RefreshTenantDatabase`), factories plates
  `api/database/factories/*Factory.php`, parité `CreatesMvpSchema` (#5443).
- Tests cross-tenant ; concurrence (2 commandes simultanées sur le dernier stock) ; idempotence
  paiement (rejeu callback) ; clôture de caisse (totaux exacts) ; réservation (conflit de créneau).
- Golden journey `GJ-RESTO-01` (ouverture caisse → commande → service → paiement → clôture) (MAT-013).
- Gates CI : tests, PHPStan strict (delta), isolation #5584, registre BC (MAT-001), conventions
  migrations (MAT-005), parité MVP, OpenAPI coverage, Pint, coverage 65 %.
- DoD : code dans le module, migrations `leopardo:migrate` + rollback, Requests strictes, Policies
  tenant-safe, événements versionnés outbox, tests négatifs, OpenAPI à jour, CHANGELOG.

---

## 10. Plan de livraison (lots + tâches fines)

> Les issues `RESTO-001..030` sont le **roadmap par lot** ; les issues `RESTO-1xx..9xx` sont les
> **tâches fines Agent-Ready** (template Contexte/Périmètre/Exigences/Critères d'acceptation/
> Dépendances/DoD), chacune référençant son lot parent.

### Épic 1xx — Fondations & gouvernance (parents RESTO-001..004)

| ID | Tâche fine | Parent |
|---|---|---|
| RESTO-101 | Squelette module DDD `RestaurantManager` (stub, provider, routes file, enregistrement) | RESTO-002 |
| RESTO-102 | Middleware `module.restaurantmanager` + feature flag + smoke `GET /restaurant/ping` + tests | RESTO-002 |
| RESTO-103 | Registre BC-25 RESTAURANT (active) + CODEOWNERS + gardes CI | RESTO-003 |
| RESTO-104 | Rapport de maturité `DEP_BC25_RESTAURANT_MATURITY.md` (Planifié) | RESTO-003 |
| RESTO-105 | Activation tenant : `ActivateRestaurantManagerAction` + `leopardo:restaurant:activate` | RESTO-004 |
| RESTO-106 | `RestaurantManagerManifest` + interface `SolutionManifest` (prête pour PLAT-001) | RESTO-004 |
| RESTO-107 | Commande `leopardo:restaurant:seed-demo` idempotente | RESTO-004 |
| RESTO-108 | Harness de test : factories de base, `CreatesMvpSchema`, tests cross-tenant génériques | RESTO-003 |

### Épic 2xx — Schéma & domaine (parents RESTO-010..015)

| ID | Tâche fine | Parent |
|---|---|---|
| RESTO-201 | Migrations branches + zones + tables | RESTO-010 |
| RESTO-202 | Migrations catégories + produits + recettes (ingredients) | RESTO-010 |
| RESTO-203 | Migrations ingrédients + unités + tax_rates | RESTO-010 |
| RESTO-204 | Migrations menus + menu_items + horaires | RESTO-010 |
| RESTO-205 | Migrations fournisseurs + stock_levels + inventory_movements | RESTO-011 |
| RESTO-206 | Migrations purchase_orders + items + receivings | RESTO-011 |
| RESTO-207 | Migrations inventory_counts + items | RESTO-011 |
| RESTO-208 | Migrations pos_sessions + orders + order_items | RESTO-012 |
| RESTO-209 | Migrations order_payments + refunds + table_sessions | RESTO-012 |
| RESTO-210 | Migrations réservations | RESTO-013 |
| RESTO-211 | Migrations delivery_zones + riders + deliveries | RESTO-013 |
| RESTO-212 | Migrations fidélité (programs/customers/points_movements) + promotions | RESTO-014 |
| RESTO-213 | Migration restaurant_outbox_events | RESTO-015 |
| RESTO-214 | Enums & Value Objects (`OrderStatus`, `OrderType`, `PaymentProvider`, `Money`, `OrderReference`, `IdempotencyKey`) | RESTO-010..015 |
| RESTO-215 | Contracts & repositories + bindings provider | RESTO-010..015 |
| RESTO-216 | Factories + parité MVP de toutes les tables | RESTO-010..015 |

### Épic 3xx — API référentiel (parent RESTO-020)

| ID | Tâche fine | Parent |
|---|---|---|
| RESTO-301 | CRUD branches + zones + tables (+ Policy) + tests | RESTO-020 |
| RESTO-302 | CRUD catégories + produits + recettes + tests | RESTO-020 |
| RESTO-303 | CRUD ingrédients + unités + tax_rates + tests | RESTO-020 |
| RESTO-304 | CRUD menus + items + horaires + tests | RESTO-020 |
| RESTO-305 | CRUD fournisseurs + tests | RESTO-020 |
| RESTO-306 | Matrice permissions restaurant.* + tests RBAC globaux | RESTO-020 |

### Épic 4xx — POS, commandes & paiements (parents RESTO-021..022)

| ID | Tâche fine | Parent |
|---|---|---|
| RESTO-401 | Ouvrir/clôturer une session de caisse (totaux serveur, écart + motif) + tests | RESTO-021 |
| RESTO-402 | Création de commande (idempotente, types salle/emporter/livraison) + tests | RESTO-021 |
| RESTO-403 | Articles de commande (ajout, annulation, quantités) + tests | RESTO-021 |
| RESTO-404 | Transitions commande (submit/confirm/prepare/ready/serve) + événements + tests | RESTO-021 |
| RESTO-405 | Addition & remises (calcul serveur : sous-total, TVA, promo, total) + tests | RESTO-021 |
| RESTO-406 | Paiements : `PaymentGatewayInterface` + adapters cash/carte/mobile money + tests | RESTO-022 |
| RESTO-407 | `POST /restaurant/orders/{order}/pay` + callback signé idempotent + tests | RESTO-022 |
| RESTO-408 | Remboursements (motif, idempotence, événement) + tests | RESTO-022 |
| RESTO-409 | Occupation des tables (open/close, sessions) + tests | RESTO-021 |
| RESTO-410 | File cuisine (écran : liste, start/ready) + tests | RESTO-021 |
| RESTO-411 | Décrément de stock à la confirmation de commande (verrou transactionnel) + tests de concurrence | RESTO-021 |
| RESTO-412 | Clôture de caisse → événement `restaurant.pos.closed.v1` + tests | RESTO-021 |

### Épic 5xx — Stock, achats & inventaire (parent RESTO-023)

| ID | Tâche fine | Parent |
|---|---|---|
| RESTO-501 | Niveaux de stock & mouvements (raisons, références) + tests | RESTO-023 |
| RESTO-502 | Bons de commande fournisseurs (draft/sent/receive) + tests | RESTO-023 |
| RESTO-503 | Réceptions (entrées stock, coût moyen pondéré) + tests | RESTO-023 |
| RESTO-504 | Inventaires physiques (comptage, écarts justifiés, approbation) + tests | RESTO-023 |
| RESTO-505 | Alertes de seuil + événement `restaurant.stock.alert.v1` + tests | RESTO-023 |
| RESTO-506 | COGS : calcul serveur à la clôture (quantités × coût moyen) + tests | RESTO-023 |

### Épic 6xx — Réservations, livraison & fidélité (parents RESTO-024..025)

| ID | Tâche fine | Parent |
|---|---|---|
| RESTO-601 | Réservations CRUD + check-in/no-show + conflit de créneau (409) + tests | RESTO-024 |
| RESTO-602 | Disponibilité de créneaux (tables, couverts, dates) + tests | RESTO-024 |
| RESTO-603 | Arrhes/dépôt de réservation (paiement) + politique d'annulation + tests | RESTO-024 |
| RESTO-604 | Zones de livraison + frais + tests | RESTO-025 |
| RESTO-605 | Livreurs + cycle de livraison (assign/out/deliver/cancel) + tests | RESTO-025 |
| RESTO-606 | Programme fidélité (points, récompenses, opt-in) + tests | RESTO-025 |
| RESTO-607 | Promotions (types, bornes, cumul, codes) + tests | RESTO-025 |
| RESTO-608 | Job no-show (expiration réservations) + job rappels (notification) + tests | RESTO-024 |

### Épic 7xx — Rapports & UI (parents RESTO-026..027)

| ID | Tâche fine | Parent |
|---|---|---|
| RESTO-701 | Rapports ventes/occupation/produits/COGS/caisses + tests | RESTO-026 |
| RESTO-702 | Export CSV idempotent + URL signée + tests | RESTO-026 |
| RESTO-703 | Dashboard KPIs (chiffre du jour, panier moyen, rotation tables) + tests | RESTO-026 |
| RESTO-704 | UI admin web : navigation + gate flag + écrans référentiel | RESTO-027 |
| RESTO-705 | UI admin web : écrans POS & commandes (prise de commande, encaissement) | RESTO-027 |
| RESTO-706 | UI admin web : écrans réservations, stock/achats, livraison, fidélité, rapports | RESTO-027 |
| RESTO-707 | Écran cuisine (file de commandes temps réel) | RESTO-027 |
| RESTO-708 | i18n fr/en des écrans + OpenAPI complet + Postman | RESTO-027 |

### Épic 8xx — Mobile & extensions (parents RESTO-028..029)

| ID | Tâche fine | Parent |
|---|---|---|
| RESTO-801 | App mobile serveur (Flutter, leopardo_core) : prise de commande, service, encaissement cash | RESTO-028 |
| RESTO-802 | App mobile livreur (tournées, statuts, navigation) | RESTO-028 |
| RESTO-803 | App mobile gérant (KPIs, alertes stock, clôtures) | RESTO-028 |
| RESTO-804 | Synchronisation offline mobile (file idempotente, conflits → revue) | RESTO-028 |
| RESTO-805 | Commande en ligne publique (menu public par tenant, token signé, paiement) | RESTO-029 |
| RESTO-806 | Intégrations apps de livraison (adaptateur Uber Eats/Glovo, webhooks) | RESTO-029 |
| RESTO-807 | Kiosque libre-service (commande + paiement) — étude puis implémentation | RESTO-029 |
| RESTO-808 | Notifications push cuisine/service (nouvelle commande, prête) | RESTO-028 |

### Épic 9xx — Qualité, docs & pilote (parent RESTO-030)

| ID | Tâche fine | Parent |
|---|---|---|
| RESTO-901 | Golden journey GJ-RESTO-01 (caisse → commande → paiement → clôture) | RESTO-030 |
| RESTO-902 | Tests E2E Playwright (POS + réservation) | RESTO-030 |
| RESTO-903 | Runbook pilote + recette UAT + pilot gates (MAT-018) | RESTO-030 |
| RESTO-904 | Audit sécurité & RGPD avant pilote (PII clients, paiements, caisse) | RESTO-030 |
| RESTO-905 | Pilote : tenant synthétique, seeds démo, kill switch, rapport signé | RESTO-030 |
| RESTO-906 | i18n complet (fr/en/ar/tr) + RTL | RESTO-030 |

---

## 11. Ordre de priorité & séquencement

| Vague | Contenu | Sortie |
|---|---|---|
| **V1** | Fondations (1xx) + schéma (2xx) + API référentiel (3xx) + POS/paiements (4xx) | POS opérationnel (salle + caisse) |
| **V2** | Stock & achats (5xx) + réservations/livraison/fidélité (6xx) | Opérationnel complet |
| **V3** | Rapports & UI web (7xx) + mobile (8xx) | Multi-canal (web + mobile serveur/livreur/gérant) |
| **V4** | Commande en ligne publique + apps livraison + kiosque (8xx P2) + qualité/pilote (9xx) | Mise en production |

---

## 12. Éléments non prévus (volontairement exclus)

| Sujet | Justification |
|---|---|
| CRM commercial plateforme | Reste dans Platform/Marketing (ADR dual contexts) |
| Comptabilité détaillée dans la verticale | Accounting reste propriétaire des écritures (synthèses seulement) |
| Planification des équipes / roulement | Couvert par Planning/HR (modules transversaux) |
| Moteur de yield management des prix | Tarifs gérés manuellement (extensible) |
| Recettes de cuisine détaillées (étapes, photos) | Périmètre opérationnel réduit aux ingrédients/COGS |
| GDS / réservation via OTAs | Hors périmètre v1 |

---

## 13. Références

- `docs/specifications/PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md` — cadre des solutions verticales.
- `docs/specifications/SOLUTION_TRAVEL_AGENCY.md` — verticale sœur (BC-24) : patterns identiques
  (feature flag, manifest, outbox, paiements, seeds).
- `docs/architecture/BOUNDED-CONTEXT-REGISTRY.md` + `dev-hub/governance/bounded-context-registry.json` (MAT-001).
- `docs/architecture/module-creation-guide.md` + `api/stubs/module-template/`.
- `CONVENTIONS.md` + `api/ARCHITECTURE.md` — conventions code, routes, migrations, tests, OpenAPI.
- Modules de référence : `api/app/Modules/TravelAgency` (squelette implémenté), `api/app/Modules/CRM`
  (outbox, policies), `api/app/Modules/Cameras` (middleware de flag).
- Règle d'or AGENTS.md : spec dans `docs/specifications/` **validée par le propriétaire** avant création des issues.
