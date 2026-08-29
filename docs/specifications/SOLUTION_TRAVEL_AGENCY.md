# SOLUTION TRAVEL AGENCY — Spécification technique de la verticale « Agence de Voyage »

> **Statut :** Proposition technique prête pour implémentation par lots (issues `TRAVEL-*`)
> **Base :** dernière tête de `main` vérifiée le 29 août 2026 (`a66aae3b3`)
> **Origine :** portage de l'ancien projet `gv-back` (fork `kitokoh/gv-back` de `lesphinx/gv-back-unified`,
> Laravel 5.6 / PHP 7.1) vers l'architecture DDD multi-tenant Leopardo HR.
> **Périmètre :** verticale opérationnelle « Agence de Voyage » = vente de billets en ligne (voyages
> interurbains), gestion du réseau (routes, trajets, gares, compagnies), réservations & passagers,
> paiements mobile money, billets PDF, location de véhicules, hôtellerie, rapports. S'appuie sur la
> plateforme (tenant, identité, RBAC, HR, CRM, notifications, documents, comptabilité) sans la dupliquer.
> **Règle d'or AGENTS.md :** ce document doit être **validé par le propriétaire** avant que les issues
> GitHub du module soient créées — il constitue la source de vérité des issues `TRAVEL-*`.

---

## Table des matières

1. [Contexte & objectifs](#1-contexte--objectifs)
2. [Analyse de l'existant (gv-back)](#2-analyse-de-lexistant-gv-back)
3. [Cartographie ancien → nouveau](#3-cartographie-ancien--nouveau)
4. [Décisions d'architecture](#4-décisions-darchitecture)
5. [Modèle de données](#5-modèle-de-données)
6. [Flux métier de bout en bout](#6-flux-métier-de-bout-en-bout)
7. [API v1](#7-api-v1)
8. [Paiements & intégrations](#8-paiements--intégrations)
9. [Sécurité & RGPD](#9-sécurité--rgpd)
10. [Activation, provisioning & onboarding](#10-activation-provisioning--onboarding)
11. [Tests, qualité & Definition of Done](#11-tests-qualité--definition-of-done)
12. [Plan de livraison par lots (TRAVEL-*)](#12-plan-de-livraison-par-lots-travel-)
13. [Hors périmètre v1 (Phase 2)](#13-hors-périmètre-v1-phase-2)
14. [Références](#14-références)

---

## 1. Contexte & objectifs

### 1.1 Pourquoi cette verticale

Leopardo HR est une plateforme modulaire multi-tenant (identité, tenant, RBAC, HR, présence, paie, CRM
client, marketing, comptabilité, documents, notifications, audit). Les **solutions verticales** ajoutent
des workflows propres à un secteur sans remplacer ni recopier les modules transversaux
(voir `docs/specifications/PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md`).

L'utilisateur possède un ancien projet (gv-back) : une **plateforme d'agence de voyage** qui permettait
de vendre des billets en ligne (recherche de voyage, réservation par passager, paiement mobile money
PVIT, génération de billet PDF nominatif), de gérer des itinéraires avec étapes, des compagnies
partenaires et agences, des tarifs par classe (adulte/enfant), des locations de véhicules, de
l'hôtellerie, des annonces payantes et du contenu communautaire.

**Objectif :** porter l'ensemble des fonctionnalités utiles de gv-back dans la nouvelle architecture,
sous forme d'une **solution verticale « TravelAgency »** activable par tenant, conçue selon les
conventions DDD, multi-tenant, sécurisée (RGPD, paiements), testée et prête pour un agent IA
d'implémentation (issues détaillées ci-dessous).

### 1.2 Persona cible

Le responsable d'une agence de voyage est un **tenant Leopardo** (une `company`). Il utilise déjà la
plateforme pour gérer ses employés (HR, présence, paie) et sa relation client (CRM). La verticale
TravelAgency lui ajoute la capacité de **vendre ses billets en ligne**, de gérer son réseau de trajets,
ses partenaires transporteurs, ses passagers, ses paiements et ses rapports — tout cela dans son
espace client, multi-appareils.

### 1.3 Principes directeurs

1. **La plateforme reste la plateforme** : la verticale consomme les modules transversaux par contrats
   et événements, jamais par import direct de classes (garde d'isolation #5584).
2. **Tenant-safe** : chaque table métier porte `company_id` non nullable ; `BelongsToCompany` scope +
   `TenantMiddleware` ; fail-closed (403) si contexte tenant absent.
3. **Invariants métier solides** : stock de places transactionnel (fini les décréments non protégés),
   paiements idempotents, billets nominatifs, workflow d'états par enums + transitions validées.
4. **Aucun secret en dur** : clés PVIT/ConvertAPI → configuration + contrat de passerelle ; la
   génération PDF est locale (suppression de la dépendance à l'API externe).
5. **Tout ce qui existait est planifié** : chaque fonctionnalité de gv-back est cartographiée
   (section 3) ; celles qui ne sont pas en v1 sont explicitement planifiées en Phase 2 (section 13).

---

## 2. Analyse de l'existant (gv-back)

Résumé de l'inventaire complet (voir rapport d'analyse joint au dossier de conception) :

### 2.1 Stack

- Laravel **5.6.10**, PHP ^7.1.3, MySQL. Auth web **Sentinel** + API **JWT** (tymon/jwt-auth).
- Aucun SDK paiement ni lib PDF : le code appelle `PhpOffice\PhpWord` et `ConvertApi` (dépendances
  **absentes** de composer.json → génération PDF cassée en l'état), clé API ConvertAPI en dur,
  identifiants PVIT en dur (`tel_marchand`, token JWT).
- Front admin : Blade + composants Vue ; SPA front séparée consommant l'API JWT.

### 2.2 Fonctionnalités (par domaine)

| Domaine | Contenu |
|---|---|
| **Auth & identités** | users/roles/permissions (Sentinel), clients, admins, personnels, partenaires (compagnies), demandes de partenariat, activation/blocage. |
| **Géographie** | pays (dump mondial ISO/indicatifs), provinces, villes, découpages admin à 3 niveaux. |
| **Réseau de voyages** | agences, voyages (date/heure départ, durée, places, moyen de transport, état, image), itinéraires ville→ville, étapes (rang, escale), classes (Ordinaire…), tarifs par classe (prix adulte/enfant). |
| **Vente** | recherche multi-critères, réservation par passager (nom, prénom, âge, pièce d'identité, classe, prix), décrément du stock, statuts (1 en attente → 2 confirmé → 3 annulé). |
| **Paiement** | mobile money **PVIT** (initiation `startpayeReservation`, callback XML `pvitcallback`), transactions, facturations. |
| **Billetterie** | génération PDF nominatif (#RCGV…, villes, passager, pièce, prix FCFA), code barre, validité, table billets. |
| **Locations** | véhicules (disponibilité, prix/jour, ville, images), réservations (dates, prix, statut, note). |
| **Hôtellerie** | hôtels (classement, ville, partenaire), chambres (disponibilité, prix, type), types de chambres. |
| **Annonces payantes** | types, positions, tarifs (prix/image, prix/caractère, devise), validation, transactions liées. |
| **Contenu communautaire** | articles, catégories, commentaires, likes, partages, notes, quiz/jeu-concours. |
| **Divers** | notifications (file manuelle mail/SMS), formulaire de contact, logs d'audit CRUD, pièces jointes. |

### 2.3 Flux de bout en bout (à préserver)

1. **Recherche** : filtre ville/pays de départ & d'arrivée, prix min/max, dates, moyen de transport.
2. **Réservation** : 1 enregistrement par passager, stock décrémenté.
3. **Paiement mobile money** : initiation → callback provider → confirmation/annulation.
4. **Billet PDF** : généré à la confirmation, nominatif.
5. **Validation/annulation** : changement d'état par l'admin ; recettes = somme des billets confirmés.
6. **Publication** : workflow d'états (créé → publié → …) ; seuls les voyages publiés avec places
   disponibles sont visibles en ligne.

### 2.4 Points d'attention pour le portage

| # | Problème constaté | Traitement dans la nouvelle conception |
|---|---|---|
| A1 | Schéma réel ≠ migrations (colonnes manquantes, **aucune FK**). | Conception ex nihilo du schéma (section 5), avec contraintes FK tenant-scoped et index. |
| A2 | Stock des places décrémenté sans verrou ni contrôle de disponibilité. | Modélisation sièges + verrouillage transactionnel + expiration (D4, section 4). |
| A3 | Callback PVIT cassé (recherche par `id` au lieu de la référence). | Contrat de passerelle avec callbacks signés, idempotents, testés (D5). |
| A4 | Secrets en dur (PVIT, ConvertAPI) et dépendances PDF absentes. | Config + contrat ; génération PDF locale (D6). |
| A5 | Aucun multi-tenant. | `company_id` partout, `BelongsToCompany`, tests cross-tenant. |
| A6 | API non versionnée, routes web-api/API dupliquées. | API v1 canonique sous `/api/v1/travel/*`, un seul chemin par concept (#4932). |
| A7 | États en int (1/2/3), prix en float. | Enums PHP + montants en unités mineures entières + devise. |
| A8 | Multilingue/multi-devise limités (FR + FCFA implicite). | i18n plateforme (fr/en v1), devise du tenant (minor units). |

---

## 3. Cartographie ancien → nouveau

> Légende : **v1** = livré dans cette trajectoire ; **P2** = planifié Phase 2 ; **N/A** = remplacé par la plateforme.

| Ancien (gv-back) | Nouveau (leopardo-hr + TravelAgency) | Statut |
|---|---|---|
| users / roles / permissions (Sentinel) | `Core/Auth` + RBAC plateforme (rôles standard) + permissions de la verticale (manifest) | N/A |
| clients (voyageurs) | Contacts CRM client (BC-11) via contrat ; passagers de réservation dans la verticale ; portail client | v1 (intégration) |
| personnels | Employés HR (BC-04) | N/A |
| partenaires (compagnies) | `travel_carriers` (annuaire tenant-scoped des transporteurs) | v1 |
| agences (bureaux de vente) | `travel_offices` (points de vente de l'agence) | v1 |
| demandepartenaires (onboarding) | Provisioning tenant + formulaire de contact → lead CRM | v1 (simplifié) |
| pays / provinces / villes / découpages | `travel_countries` + `travel_cities` (référentiel tenant-scoped seedé) | v1 (découpages : P2) |
| voyages | `travel_trips` (instance datée d'un trajet) + `travel_routes` (ligne) | v1 |
| itineraires + villeitineraires (étapes) | `travel_routes` + `travel_route_stops` (rang, escale) | v1 |
| classes / classe_voyages (tarifs) | `travel_classes` + `travel_trip_prices` (adulte/enfant, devise, minor units) | v1 |
| nombre_place | `travel_trip_seats` (inventaire par siège) + verrouillage transactionnel | v1 |
| reservationvoyages | `travel_bookings` + `travel_passengers` (référence unique, source, idempotency) | v1 |
| billets (code barre, url_pdf, validité) | `travel_tickets` (n° unique, code de validation/QR, pdf asset, validité, check-in) | v1 |
| transactions / facturations / modefacturations | `travel_payments` (provider, statut, référence, callback redacted) ; taux de facturation : P2 | v1 |
| startpayeReservation / pvitcallback | `POST /travel/payments/initiate` + `POST /travel/payments/callback` (signé, idempotent) | v1 |
| generatePDF (PHPWord/ConvertAPI) | `TravelTicketPdfGenerator` local + template versionné + asset documents | v1 |
| locations / location_images / reservationlocations | `travel_rental_vehicles` (+ images) + `travel_rental_bookings` | v1 |
| hotels / chambres / typechambres | `travel_hotels` + `travel_hotel_rooms` (catalogue v1 ; réservation P2) | v1 catalogue / P2 résa |
| sites touristiques | `travel_tourist_sites` | P2 |
| annonces / typeannonces / positionannonces / tarifannonces | `travel_adverts` (annonces payantes avec transaction) | P2 |
| articles / categories / commentaires / likes / shares / notes / quiz | Contenu communautaire de la verticale (ou extension Marketing avec consentement) | P2 |
| notifications (file mail/SMS) | BC-13 COMMS via événements (booking.confirmed → notification) | v1 |
| messagecontacts | Formulaire contact → création lead CRM (événement) | v1 |
| logs (audit CRUD) | Trait `Auditable` + événements de domaine + outbox | v1 |
| change_state (workflow int) | Enums + transitions validées (POST d'action) | v1 |
| deeper_search | `GET /travel/shop/trips` (recherche multi-critères) | v1 |
| DashboardStats (recettes) | `GET /travel/reports/*` (read models) | v1 |
| multilingue (users.langue) | i18n plateforme (fr/en v1) | v1 |
| devise FCFA implicite | Devise du tenant + montants minor units | v1 |
| SPA front consommant l'API | UI web admin (admin-dashboard) v1 ; boutique publique dédiée P2 | v1 UI / P2 shop public |

---

## 4. Décisions d'architecture

### D1 — Nom, emplacement, identifiants

| Élément | Valeur |
|---|---|
| Bounded context | **BC-24 — TRAVEL** (contexte `TravelAgency`) |
| Module | `api/app/Modules/TravelAgency` (`App\Modules\TravelAgency\*`) |
| Préfixe routes | `/api/v1/travel/*` |
| Préfixe tables | `travel_*` |
| Feature flag | `companies.features.travelagency` |
| Manifest | `TravelAgencyManifest` (code `travelagency`, maturité `pilot`) |
| Label GitHub | `BC-24 TRAVEL` |

**Justification emplacement `Modules/` (et non `Solutions/`) :** le registre BC (MAT-001) déclare
FUEL/EDU sous `api/app/Solutions/` avec statut `planned`, mais l'implémentation réelle (branche EDU)
utilise `api/app/Modules/`. La garde CI exige que tout répertoire créé soit déclaré actif ; choisir
`Modules/TravelAgency` aligne le registre sur la réalité d'implémentation, réutilise le template de
module existant (`stubs/module-template/`), les conventions routes/providers/tests et la garde
d'isolation #5584. Le registre BC-24 déclarera donc le chemin `api/app/Modules/TravelAgency`.

### D2 — Le tenant EST l'agence

Une `company` Leopardo = une agence de voyage. Les « partenaires » de gv-back (compagnies de
transport dont on revend les voyages) deviennent un **annuaire tenant-scoped** `travel_carriers`
(géré par l'agence). Une compagnie extérieure n'est pas un tenant : elle n'accède pas à l'espace
client. Les trajets de la flotte propre de l'agence ont `carrier_id` NULL (véhicules `travel_vehicles`).

### D3 — Référentiel géographique propriétaire

Pas de module géographie partagé dans la plateforme → la verticale possède ses tables de référence
(`travel_countries`, `travel_cities`), **tenant-scoped et seedées** par le provisioning (idempotent).
Cela évite tout import inter-module (garde #5584) et permet des personnalisations par tenant.
Découpages administratifs à 3 niveaux : Phase 2 (non requis pour v1).

### D4 — Stock des places : inventaire transactionnel par siège

L'invariant « ne jamais vendre plus de places que disponibles » est modélisé par :

- `travel_trip_seats` : une ligne par siège (`UNIQUE(company_id, trip_id, seat_number)`), statuts
  `free|reserved|sold`.
- À la réservation : transaction `DB::transaction` + `SELECT ... FOR UPDATE` sur les lignes siège
  choisies (et sur le voyage) ; échec si indisponible → `409 SEATS_UNAVAILABLE`.
- Réservations `pending` avec expiration (`expires_at`) : un job tenant-scoped libère les sièges
  réservés non payés (retry + idempotence).
- Le compteur « places restantes » exposé à la recherche est dérivé (read model recalculé par job
  idempotent), jamais un simple décrement non protégé.

### D5 — Paiements : contrat de passerelle, callbacks signés, idempotence

```php
interface PaymentGatewayInterface
{
    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResult; // reference provider + url
    public function verify(VerifyPaymentRequest $request): PaymentStatus;             // rappel / vérif active
    public function refund(RefundRequest $request): RefundResult;
}
```

- Adapters v1 : `CashPaymentGateway` (comptant au guichet — validation manuelle) et
  `PvitPaymentGateway` (mobile money, **mode sandbox**, identifiants en config/env).
- `travel_payments` : `reference` unique, `provider_code`, `status`, `idempotency_key`
  `UNIQUE(company_id, idempotency_key)`, `callback_payload_redacted` (JSONB borné, jamais de token).
- Le callback est **signé** (secret partagé par tenant) et **idempotent** : rejeu → retour du résultat
  existant, aucun double paiement ni double confirmation. Le bug historique (recherche par `id` au
  lieu de la référence) est couvert par des tests dédiés.
- Futur : adapter `CauriPay` (agrégateur dev-first) quand il sera disponible — le contrat rend ce
  changement local à `Infrastructure/Services/Payment/`.

### D6 — Billet PDF : génération locale

- `TravelTicketPdfGenerator` dans `Infrastructure/Services/` : template versionné (blade→PDF ou
  lib dédiée, ex. barryvdh/laravel-dompdf), QR de validation (hash du `ticket_number`), données
  nominatives (passager, pièce, trajet, classe, prix, référence).
- Le PDF est stocké en asset via le contrat documents (BC-20) si disponible, sinon disque tenant
  (`storage/app/...`) avec URL signée temporaire.
- **Suppression** de la dépendance à ConvertAPI/PHPWord (dépendances absentes + API externe).

### D7 — Intégrations inter-modules : événements & contrats uniquement

La garde d'isolation #5584 interdit `use App\Modules\<autre>`. La verticale communique via :

- **Outbox** : table `travel_outbox_events` (pattern `crm_outbox_events`, #5741) + publisher ;
  événements versionnés `travel.*.v1` (section 8.4).
- **Contrats** : interface `TravelCustomerContactResolver` (résout un contact CRM à partir d'un
  identifiant fourni par la verticale — implémentation dans la verticale via événements, ou contrat
  partagé si un BC d'intégration l'héberge) ; synthèse comptable émise par événement (Accounting
  consomme un contrat de synthèse validé, jamais les tables internes).

### D8 — Périmètre v1 vs Phase 2

- **v1** : tout ce qui est marqué v1 dans la cartographie (section 3) — cœur voyage + vente en ligne
  + paiements + billets + locations + hôtels (catalogue) + rapports.
- **Phase 2 (P2)** : contenu communautaire (articles/commentaires/likes/quiz), annonces payantes,
  sites touristiques, boutique publique multi-tenant (site dédié billets), réservation hôtelière,
  découpages admin, import des données legacy, extensions mobile. Chaque sujet a une issue dédiée
  (section 13) — rien n'est oublié, tout est planifié.

### D9 — Activation & sécurité d'accès

- Feature flag `travelagency` (`Company::setFeature('travelagency', true)`), middleware
  `EnsureTravelAgencyModuleMiddleware` (pattern `EnsureCameraModuleMiddleware` : 403 si flag absent).
- Permissions de la verticale (déclarées dans le manifest) : `travel.manage` (admin agence),
  `travel.agent` (vendeur/caissier), `travel.checkin` (contrôleur embarquement), `travel.reports`.
  Toute action passe par une Policy Laravel (company_id match, sinon 404).

### D10 — Vente en ligne

- **v1** : l'API shop est sous authentification tenant (agents de l'agence + contacts CRM ayant un
  compte portail). Une réservation en ligne peut être créée par un contact CRM authentifié.
- **Phase 2** : boutique publique (site dédié billets) via token public signé par tenant — prévu
  dans `TRAVEL-063` ; l'API shop est conçue dès v1 pour être exposable sans modification de contrat.

---

## 5. Modèle de données

> Toutes les tables portent `company_id` (UUID) non nullable, `id`, timestamps ; index tenant-first.
> Migrations **tenant** (`api/database/migrations/tenant/`, schéma `shared_tenants`), nommage
> `YYYY_MM_DD_0000NN_<issue>_<slug>.php`, réentrantes (helpers `schemaTableExists` /
> `resolveTableSchema`), exécutées par `php artisan leopardo:migrate`. Chaque nouvelle table est
> ajoutée à `api/tests/Support/CreatesMvpSchema.php` (parité MVP #5443).
> Montants : **unités mineures entières** + colonne `currency` (devise du tenant). Flottants interdits.
> PII (pièces d'identité, dates de naissance) : chiffrées au repos / colonnes dédiées.

### 5.1 Référentiel

```text
travel_countries
- id, company_id
- iso2 (2), iso3 (3), name, phone_code, currency_code
- status (active|disabled)
- UNIQUE(company_id, iso2)

travel_cities
- id, company_id
- country_iso2 (ref travel_countries), name, region NULL
- latitude NULL, longitude NULL
- status
- INDEX(company_id, country_iso2), INDEX(company_id, name)

travel_classes
- id, company_id
- code, label, color NULL, priority, status
- UNIQUE(company_id, code)          # ex: standard, vip

travel_stations          # gares / terminaux (départ & arrivée)
- id, company_id
- code, name, city_id (ref travel_cities), address NULL, contact_phone NULL
- timezone, is_terminal bool, status
- UNIQUE(company_id, code)

travel_offices           # bureaux de vente de l'agence
- id, company_id
- name, city_id, address NULL, contact_phone NULL, status

travel_carriers          # compagnies de transport (annuaire)
- id, company_id
- code, name, type (bus|train|plane|boat), contact_phone NULL, logo_asset_id NULL, status
- UNIQUE(company_id, code)
```

### 5.2 Réseau & trajets

```text
travel_vehicles          # flotte propre de l'agence
- id, company_id
- code, registration_number NULL, seat_capacity int, carrier_id NULL (ref travel_carriers)
- status, notes NULL
- UNIQUE(company_id, code)

travel_routes            # ligne ville A → ville B (abstraite)
- id, company_id
- code, origin_city_id, destination_city_id
- distance_km NULL, duration_min NULL, status
- UNIQUE(company_id, origin_city_id, destination_city_id)

travel_route_stops       # étapes d'une route (rang, escale)
- id, company_id, route_id
- city_id, rank int, is_stopover bool, min_duration_min NULL
- UNIQUE(company_id, route_id, rank)

travel_trips             # instance datée d'un trajet (= ancien « voyage »)
- id, company_id
- code, route_id, carrier_id NULL, vehicle_id NULL
- departure_date, departure_time (heure), arrival_date NULL, arrival_time NULL
- means_of_transport (bus|car|train|plane|boat)
- total_seats int, status (draft|scheduled|published|cancelled)
- published_at NULL, created_by_user_id NULL
- UNIQUE(company_id, code)
- INDEX(company_id, route_id, departure_date), INDEX(company_id, departure_date, status)

travel_trip_prices       # tarifs par classe (ancien classe_voyages)
- id, company_id, trip_id, class_id (ref travel_classes)
- adult_price_minor int, child_price_minor int, currency (3)
- UNIQUE(company_id, trip_id, class_id)

travel_trip_seats        # inventaire par siège (invariant stock)
- id, company_id, trip_id
- seat_number, status (free|reserved|sold)
- booking_id NULL, passenger_id NULL, reserved_until NULL
- UNIQUE(company_id, trip_id, seat_number)
- INDEX(company_id, trip_id, status)
```

### 5.3 Ventes

```text
travel_bookings
- id, company_id
- reference (10, unique), trip_id
- status (pending|confirmed|cancelled|refunded|completed)
- passenger_count int, total_amount_minor int, currency
- booking_source (online|office|phone|partner)
- customer_contact_id NULL            # référence contact CRM (via contrat, jamais FK inter-module)
- booked_by_user_id NULL, payment_status (unpaid|partial|paid|refunded)
- expires_at NULL                     # expiration des pending (libération des sièges)
- idempotency_key, booked_at, version
- UNIQUE(company_id, reference), UNIQUE(company_id, idempotency_key)

travel_passengers
- id, company_id, booking_id
- full_name, birth_date NULL
- document_type (national_id|passport|driver_license|other), document_number_encrypted
- age_category (adult|child|infant), class_id
- seat_number NULL, ticket_id NULL, unit_price_minor int
- UNIQUE(company_id, booking_id, document_number_hash)

travel_tickets
- id, company_id
- ticket_number (unique, format #GV-…), booking_id, passenger_id
- validation_code (hash/QR), pdf_asset_id NULL
- issued_at, valid_from, valid_until, status (issued|checked_in|void)
- checked_in_at NULL, checked_in_by NULL
- UNIQUE(company_id, ticket_number)

travel_payments
- id, company_id, booking_id
- reference (unique), provider_code (cash|pvit|momo|card)
- amount_minor int, currency, status (pending|confirmed|failed|refunded)
- paid_at NULL, provider_reference NULL
- callback_payload_redacted JSONB NULL, idempotency_key
- UNIQUE(company_id, reference), UNIQUE(company_id, idempotency_key)
```

### 5.4 Location & hôtellerie

```text
travel_rental_vehicles
- id, company_id
- code, title, city_id, price_per_day_minor int, currency
- available_from NULL, available_until NULL, owner_carrier_id NULL, status, notes NULL
- UNIQUE(company_id, code)

travel_rental_vehicle_images
- id, company_id, vehicle_id, asset_id, position int

travel_rental_bookings
- id, company_id
- reference (unique), vehicle_id, customer_contact_id NULL
- start_date, end_date, total_amount_minor int, currency
- deposit_amount_minor int NULL, payment_status, status (pending|confirmed|cancelled|completed)
- notes NULL, idempotency_key
- UNIQUE(company_id, reference), UNIQUE(company_id, idempotency_key)
- INDEX(company_id, vehicle_id, start_date)

travel_hotels
- id, company_id
- name, city_id, classification int NULL, address NULL, contact_phone NULL
- description_redacted NULL, status
- INDEX(company_id, city_id)

travel_hotel_rooms
- id, company_id, hotel_id
- type_code, room_number, capacity int, price_per_night_minor int, currency, status
- UNIQUE(company_id, hotel_id, room_number)
```

### 5.5 Intégration & audit

```text
travel_outbox_events     # pattern crm_outbox_events (#5741)
- id, company_id
- event_type (travel.*.v1), payload_redacted JSONB
- status (pending|published|failed), available_at, attempts int, last_error NULL
- idempotency_key
- UNIQUE(company_id, idempotency_key)
- INDEX(company_id, status, available_at)
```

> Nota : les tables de **Phase 2** (annonces, contenu, sites touristiques, quiz) sont spécifiées dans
> leurs issues dédiées (section 13) et suivront exactement les mêmes conventions.

---

## 6. Flux métier de bout en bout

### 6.1 Vente d'un billet en ligne (flux roi)

```text
Recherche (GET /travel/shop/trips?origin=&destination=&date=&class=&passengers=)
  → Détail trajet (GET /travel/shop/trips/{trip} : prix, places restantes, horaires, étapes)
  → Réservation (POST /travel/shop/bookings, idempotency_key) → pending, sièges réservés, expires_at
  → Paiement (POST /travel/payments/initiate) → redirect/url provider ou comptant
  → Callback provider (POST /travel/payments/callback, signé) → confirmed
  → Billet (POST /travel/bookings/{booking}/issue-ticket) → PDF généré + QR + statut issued
  → Suivi (GET /travel/shop/bookings/{reference} avec code de validation)
  → Embarquement (POST /travel/tickets/{ticket}/check-in) → checked_in
```

### 6.2 Gestion back-office

```text
Créer réseau (pays/villes → stations/gares → routes + étapes → classes → compagnies)
  → Programmer un trajet (travel_trips + tarifs + sièges)
  → Publier (draft → scheduled → published) — visible en recherche
  → Vente au guichet (booking_source=office, paiement cash → confirmation immédiate + billet)
  → Check-in des passagers (liste + manifeste par trajet)
  → Annulation / remboursement (workflow validé, POST d'action, motif obligatoire, audit)
  → Rapports (ventes par période/trajet/route, occupation, recettes, annulations, exports CSV)
```

### 6.3 Workflow d'états (enums + transitions)

```text
Trip      : draft → scheduled → published → cancelled   (publié/annulé : transitions validées)
Booking   : pending → confirmed → completed
            pending → cancelled        (expiration ou annulation avant paiement)
            confirmed → cancelled → refunded   (remboursement : motif + audit obligatoires)
Payment   : pending → confirmed | failed → refunded
Ticket    : issued → checked_in | void
Seat      : free → reserved → sold → free (libération à l'expiration/annulation)
```

Toute transition est réalisée par une **Action** (`Application/Actions/`) dans une transaction,
avec événement de domaine + outbox ; jamais par assignation directe dans un contrôleur.

---

## 7. API v1

> Conventions : POST = créer **et** déclencher une action ; PATCH = mise à jour ; GET = lecture pure
> (#4930). Toutes les routes dans `api/routes/modules/travelagency.php`, groupe
> `['throttle:api','auth:sanctum','token.refresh','tenant','throttle:api-plan']`, préfixe `travel`,
> charge dans `api/routes/api.php`. **Chaque endpoint documenté dans `api/openapi.yaml`**
> (coverage CI, allowlist vide). Contexte tenant : `BelongsToCompany` + Policies (mismatch → 404).

### 7.1 Référentiel & réseau (back-office)

| Méthode | Route | Action |
|---|---|---|
| GET/POST | `/travel/countries` · `/travel/cities` | Lister/créer le référentiel (seed automatique, lecture publique tenant) |
| GET | `/travel/cities/{city}` | Détail ville |
| CRUD | `/travel/stations` | Gares/terminaux (`travel.station.manage`) |
| CRUD | `/travel/offices` | Bureaux de vente |
| CRUD | `/travel/carriers` | Compagnies de transport |
| CRUD | `/travel/classes` | Classes de service |
| CRUD | `/travel/vehicles` | Flotte propre |
| CRUD | `/travel/routes` (+ `/travel/routes/{route}/stops`) | Lignes + étapes (rang/escale) |
| CRUD | `/travel/trips` (+ `/travel/trips/{trip}/prices`) | Trajets + tarifs par classe |
| POST | `/travel/trips/{trip}/publish` · `/travel/trips/{trip}/cancel` | Workflow de publication |
| GET | `/travel/trips/search` | Recherche interne (back-office) |

### 7.2 Ventes & billetterie (back-office)

| Méthode | Route | Action |
|---|---|---|
| GET/POST | `/travel/bookings` | Lister/créer (guichet, source office/phone) |
| GET | `/travel/bookings/{booking}` | Détail (passagers, paiements, billet) |
| POST | `/travel/bookings/{booking}/confirm` | Confirmation manuelle (espèces) |
| POST | `/travel/bookings/{booking}/cancel` | Annulation (+ motif) |
| POST | `/travel/bookings/{booking}/refund` | Remboursement (+ motif, audit) |
| POST | `/travel/bookings/{booking}/issue-ticket` | Émettre le(s) billet(s) PDF |
| GET | `/travel/tickets/{ticket}/pdf` | Télécharger le billet (URL signée) |
| POST | `/travel/tickets/{ticket}/check-in` | Valider l'embarquement |
| GET | `/travel/trips/{trip}/manifest` | Manifeste des passagers |

### 7.3 Boutique en ligne (v1 : auth tenant ; P2 : publique)

| Méthode | Route | Action |
|---|---|---|
| GET | `/travel/shop/trips` | Recherche (origine, destination, date, classe, passagers, prix min/max) |
| GET | `/travel/shop/trips/{trip}` | Détail + disponibilité + tarifs |
| POST | `/travel/shop/bookings` | Réservation en ligne (idempotent, expiration) |
| GET | `/travel/shop/bookings/{reference}` | Statut par référence + code de validation |
| POST | `/travel/shop/bookings/{reference}/pay` | Initier le paiement (provider) |

### 7.4 Paiements

| Méthode | Route | Action |
|---|---|---|
| POST | `/travel/payments/initiate` | Initier (mobile money / comptant) |
| POST | `/travel/payments/callback` | **Webhook provider** (signé, idempotent — hors auth utilisateur, vérification par signature) |
| GET | `/travel/payments/{payment}` | Statut d'un paiement |

### 7.5 Locations & hôtels

| Méthode | Route | Action |
|---|---|---|
| CRUD | `/travel/rental-vehicles` (+ images) | Véhicules en location |
| GET/POST | `/travel/rental-bookings` (+ confirm/cancel) | Réservations de location |
| CRUD | `/travel/hotels` (+ `/travel/hotels/{hotel}/rooms`) | Catalogue hôtelier (v1) |

### 7.6 Rapports

| Méthode | Route | Action |
|---|---|---|
| GET | `/travel/reports/sales` | Ventes par période/trajet/route/source (`travel.reports`) |
| GET | `/travel/reports/occupancy` | Taux d'occupation par trajet |
| GET | `/travel/reports/revenue` | Recettes encaissées (confirmées + remboursements) |
| GET | `/travel/reports/cancellations` | Annulations & motifs |
| GET | `/travel/reports/export` | Export CSV idempotent (job + URL signée) |

---

## 8. Paiements & intégrations

### 8.1 Adapters

| Provider | Implémentation | v1 |
|---|---|---|
| Cash (guichet) | `CashPaymentGateway` — confirmation manuelle par un agent autorisé | ✅ |
| PVIT mobile money | `PvitPaymentGateway` — **sandbox** (identifiants en config/env), initiation + callback signé | ✅ |
| CauriPay (futur) | `CauriPayPaymentGateway` — quand le service sera disponible | P2 |
| Carte / autres | via le contrat | P2 |

### 8.2 Contrat de passerelle (rappels D5)

- `InitiatePaymentRequest` : booking, montant, devise, canal, idempotency_key.
- `InitiatePaymentResult` : `provider_reference`, `redirect_url|null`, `status`.
- `verify()` : re-conciliation active (idempotente, avec retry/backoff borné).
- `refund()` : remboursement (réservé `travel.manage`), résultat journalisé.

### 8.3 Callback sécurisé

- Signature HMAC (secret par tenant, jamais dans les logs), horodatage, rejeu détecté par
  `UNIQUE(company_id, idempotency_key)` → retour du résultat existant (200) sans effet de bord.
- Payload stocké **redacted** (`callback_payload_redacted`), taille bornée, jamais de token.
- Le **bug historique** (callback cherchant la réservation par `id` au lieu de `reference`) est couvert
  par un test de non-régression explicite.

### 8.4 Événements (outbox)

| Événement | Producteur | Consommateurs autorisés |
|---|---|---|
| `travel.trip.published.v1` | Travel | Notifications, reporting |
| `travel.booking.pending.v1` | Travel | Notifications (si configuré) |
| `travel.booking.confirmed.v1` | Travel | Notifications, Accounting (synthèse), CRM (activité) |
| `travel.booking.cancelled.v1` | Travel | Notifications, Accounting, CRM |
| `travel.payment.confirmed.v1` | Travel | Accounting, Notifications |
| `travel.payment.refunded.v1` | Travel | Accounting, Notifications |
| `travel.ticket.issued.v1` | Travel | Notifications (email/WhatsApp avec consentement), Documents |

Chaque consommateur vérifie version, tenant, correlation ID, idempotency key et permissions.
Publication par outbox **après commit** (pattern `CrmOutboxPublisher`, #5741) ; effets externes
rejouables.

### 8.5 Intégrations transversales (contrats, pas d'imports)

| Besoin | Mécanisme |
|---|---|
| Voyageur → contact CRM | Résolution par identifiant externe via contrat `TravelCustomerContactResolver` ; création de lead CRM par événement (formulaire de contact) |
| Synthèse ventes → Accounting | Événement `travel.payment.confirmed.v1` ; Accounting construit ses écritures depuis un contrat de synthèse validé |
| Notifications (mail/SMS/WhatsApp) | Événements → BC-13 COMMS (canal configuré + consentement) |
| Billet PDF → Documents | Asset via contrat documents (BC-20) ; fallback disque tenant + URL signée |
| Emploi du temps / personnel | HR (BC-04) reste propriétaire des employés ; la verticale référence `employee_id` par valeur (via contrat) |
| Marketing (promos par consentement) | Événements + opt-in explicite (P2) |

---

## 9. Sécurité & RGPD

| Sujet | Mesure |
|---|---|
| **Tenant isolation** | `company_id` non nullable + `BelongsToCompany` (fail-closed 403), Policies (mismatch → 404), tests cross-tenant, jamais de `company_id` client comme autorité (résolution serveur). |
| **PII passagers** | Noms, n° de pièce (chiffré), date de naissance : colonnes dédiées, `document_number_encrypted` + hash pour unicité ; redaction des logs (`description_redacted`, `payload_redacted`) ; rétention documentée (politique du tenant) ; droit d'effacement RGPD. |
| **Paiements** | Secrets en config/env, jamais en dur ; callback signé ; payloads redacted ; montants minor units ; audit complet des transitions. |
| **Billets** | URL signée temporaire pour le PDF ; code de validation (QR) ; révocation (`void`). |
| **API** | Rate limiting (`throttle:api-plan`), validation stricte des Requests (allowlists, bornes), erreurs sûres (codes `ApiError`, pas de stack trace), OpenAPI à jour. |
| **Audit** | Trait `Auditable` + événements de domaine + outbox ; traçabilité des transitions (qui, quand, pourquoi). |
| **Fail-closed** | Feature flag absent → 403 ; contexte tenant absent sur surface tenant → 403 ; route non déclarée au BC → CI rouge. |

---

## 10. Activation, provisioning & onboarding

### 10.1 Feature flag

- `Company::setFeature('travelagency', true)` ; middleware `EnsureTravelAgencyModuleMiddleware`
  (pattern `EnsureCameraModuleMiddleware`).
- Kill switch opérationnel : désactiver le flag → API 403 immédiate (aucune donnée détruite).

### 10.2 Manifest de la solution

```php
final class TravelAgencyManifest // enregistré dans TravelAgencyServiceProvider::register()
{
    public function code(): string        { return 'travelagency'; }
    public function maturity(): string    { return 'pilot'; }
    public function requiredModules(): array { return ['rh', 'documents', 'notifications', 'crm']; }
    public function optionalModules(): array { return ['accounting', 'marketing']; }
    public function sensitiveData(): array { return ['passenger_pii', 'payments']; }
    public function permissions(): array   { return ['travel.manage','travel.agent','travel.checkin','travel.reports']; }
}
```

> Nota : le système de manifest/catalogue de solutions (PLAT-001) n'est **pas encore sur main**.
> `TRAVEL-004` active la verticale par feature flag dès v1 et prépare l'interface manifest pour
> brancher le catalogue quand PLAT-001 sera livré (dépendance optionnelle, non bloquante).

### 10.3 Onboarding / provisioning

- Secteur « travel » ajouté au catalogue d'onboarding (solutions recommandées : TravelAgency +
  modules RH, Documents, Notifications, CRM ; optionnels : Accounting, Marketing).
- Étape `install_solution` : activation du flag + seeds du référentiel (pays, villes principales,
  classes par défaut) + création d'un jeu de données démo non sensible (v1 : commande
  `leopardo:travel:seed-demo` idempotente).
- Le CRM commercial plateforme reste hors périmètre (jamais fusionné — ADR CRM dual).

---

## 11. Tests, qualité & Definition of Done

### 11.1 Tests exigés (par issue)

- **1 test Feature minimum par endpoint** (PHPUnit, trait `Tests\RefreshTenantDatabase`), factories
  plates `api/database/factories/*Factory.php`.
- **Tests cross-tenant** : accès tenant B → 404/403 ; contexte manquant → 403 (fail-closed).
- **Tests d'invariants** : concurrence sur les sièges (2 réservations simultanées → 1 seule réussit),
  expiration des pending, libération des sièges.
- **Tests paiements** : idempotence du callback (rejeu), signature invalide rejetée, callback sur
  référence inexistante → 404 propre, remboursement avec motif.
- **Tests billet** : génération PDF (contenu minimal, QR), révocation, URL signée.
- **Parité MVP** : toute nouvelle table ajoutée à `CreatesMvpSchema` (#5443).
- **Golden journey** : `GJ-TRAVEL-01` (recherche → réservation → paiement → billet → check-in)
  enregistré dans `dev-hub/tools/golden-journeys.json` (MAT-013).

### 11.2 Gates CI (non négociables)

`tests.yml`, `architecture-check.yml` (phpstan-modules + strict, **delta-only**), module isolation
#5584, registre BC (MAT-001), conventions migrations (MAT-005 + #5437 + #1613 + #5431 + #1962),
parité MVP #5443, OpenAPI coverage #1473, Pint, coverage gate (65 % global, ratchet), sécurité
(CodeQL, secret-scan, ZAP informational), `leopardo:migrate` (LeopardoMigrateRunnerTest).

### 11.3 Definition of Done commune

Code dans le module propriétaire · migrations Laravel exécutables par `leopardo:migrate` + rollback ·
index et contraintes testés · Requests strictes · Policies tenant-safe · erreurs sûres · événements
versionnés par outbox · tests négatifs présents · OpenAPI à jour · jobs tenant-scoped & idempotents ·
logs sans PII inutile · PR courte fusionnable sur `main` · entrée `CHANGELOG.md` · docs à jour.

---

## 12. Plan de livraison par lots (TRAVEL-*)

> Chaque ligne devient une **issue GitHub dédiée** (template Agent-Ready : Contexte / Périmètre /
> Exigences / Critères d'acceptation / Dépendances / DoD). Les dépendances sont exprimées en
> `TRAVEL-xxx` et en issues existantes.

### Lot 0 — Fondations & gouvernance

| ID | Tâche | Dépendances |
|---|---|---|
| TRAVEL-001 | **Spécification & décisions** : valider ce document (source de vérité), créer le label `BC-24 TRAVEL` | — |
| TRAVEL-002 | **Squelette module DDD** `TravelAgency` (stub, provider, routes file, enregistrement `bootstrap/providers.php`, feature flag + middleware `EnsureTravelAgencyModuleMiddleware`, contrat `PaymentGatewayInterface` vide) | TRAVEL-001 |
| TRAVEL-003 | **Registre BC-24 TRAVEL** : entrée `planned` (path `api/app/Modules/TravelAgency`) + ligne CODEOWNERS + garde CI verte + maturité `DEP-BC24` | TRAVEL-002 |
| TRAVEL-004 | **Activation & onboarding** : secteur « travel » au catalogue, étape provisioning `install_solution`, commande de seed démo idempotente, interface manifest (préparée pour PLAT-001) | TRAVEL-003 |

### Lot 1 — Schéma & domaine (migrations + models + enums + contracts + factories)

| ID | Tâche | Dépendances |
|---|---|---|
| TRAVEL-010 | **Référentiel géographique** : migrations `travel_countries`/`travel_cities` + seeds idempotents + models + tests | TRAVEL-003 |
| TRAVEL-011 | **Réseau** : `travel_stations`, `travel_offices`, `travel_carriers`, `travel_classes`, `travel_vehicles` + models + enums + tests | TRAVEL-010 |
| TRAVEL-012 | **Routes & trajets** : `travel_routes`, `travel_route_stops`, `travel_trips`, `travel_trip_prices`, `travel_trip_seats` + enums de statut + tests (contraintes, index) | TRAVEL-011 |
| TRAVEL-013 | **Ventes** : `travel_bookings`, `travel_passengers`, `travel_tickets`, `travel_payments`, `travel_outbox_events` + contracts + tests | TRAVEL-012 |
| TRAVEL-014 | **Location & hôtels** : `travel_rental_vehicles` (+ images), `travel_rental_bookings`, `travel_hotels`, `travel_hotel_rooms` + tests | TRAVEL-011 |

### Lot 2 — API back-office

| ID | Tâche | Dépendances |
|---|---|---|
| TRAVEL-020 | **API référentiel & réseau** : countries/cities/stations/offices/carriers/classes/vehicles + Policies + tests | TRAVEL-011 |
| TRAVEL-021 | **API routes & trips** : CRUD + tarifs + publish/cancel + recherche interne + tests | TRAVEL-012 |
| TRAVEL-022 | **API réservations back-office** : création guichet, confirmation, annulation, remboursement, check-in, manifeste, gestion du stock (verrous + expiration) + tests de concurrence | TRAVEL-013 |
| TRAVEL-023 | **API locations** : véhicules + images + réservations + tests | TRAVEL-014 |
| TRAVEL-024 | **API hôtels** : catalogue + chambres + tests | TRAVEL-014 |

### Lot 3 — Vente en ligne, paiements & billetterie

| ID | Tâche | Dépendances |
|---|---|---|
| TRAVEL-030 | **API shop** : recherche/détail/réservation en ligne/statut par référence + expiration des pending + tests | TRAVEL-012, TRAVEL-013 |
| TRAVEL-031 | **Paiements** : adapters Cash + PVIT sandbox, initiate, callback signé idempotent, verify/refund, retries + tests (rejeu, signature, référence inconnue) | TRAVEL-013, TRAVEL-022 |
| TRAVEL-032 | **Billet PDF** : générateur local, QR, template versionné, URL signée, révocation, contrat documents (fallback disque) + tests | TRAVEL-013, TRAVEL-022 |
| TRAVEL-033 | **Intégrations événementielles** : outbox publisher, événements `travel.*.v1`, notifications (confirm/annuler), formulaire contact → lead CRM, synthèse Accounting + tests | TRAVEL-013 |

### Lot 4 — Rapports, UI, documentation

| ID | Tâche | Dépendances |
|---|---|---|
| TRAVEL-040 | **Rapports & exports** : sales/occupancy/revenue/cancellations + export CSV job idempotent + tests | TRAVEL-022 |
| TRAVEL-041 | **UI admin web** (admin-dashboard) : navigation verticale TravelAgency, écrans réseau/trajets/réservations/billets/rapports, i18n fr/en | TRAVEL-021, TRAVEL-022, TRAVEL-040 |
| TRAVEL-042 | **OpenAPI & docs** : endpoints `travel.*` documentés (coverage CI), collection Postman, guide intégration | TRAVEL-020 → TRAVEL-033 |
| TRAVEL-043 | **Golden journey & E2E** : `GJ-TRAVEL-01` (recherche → billet → check-in) + tests E2E Playwright admin | TRAVEL-030 → TRAVEL-032, TRAVEL-041 |

### Lot 5 — Pilote & mise en production

| ID | Tâche | Dépendances |
|---|---|---|
| TRAVEL-050 | **Maturité & runbook** : `DEP-BC24_TRAVEL_MATURITY.md` (scorecard 12 dimensions), runbook pilote, recette UAT, pilot gates (MAT-018) | TRAVEL-040 → TRAVEL-043 |
| TRAVEL-051 | **Pilote** : tenant pilote synthétique, seeds démo, kill switch vérifié, rapport de recette signé | TRAVEL-050 |

### Phase 2 (planifiée, non bloquante pour v1)

| ID | Tâche | Dépendances |
|---|---|---|
| TRAVEL-060 | Contenu communautaire (articles, catégories, commentaires, likes, partages, notes) | v1 |
| TRAVEL-061 | Annonces payantes (types, positions, tarifs, transactions, modération) | v1 |
| TRAVEL-062 | Sites touristiques & quiz | v1 |
| TRAVEL-063 | Boutique publique multi-tenant (site dédié billets, token public signé) | v1 |
| TRAVEL-064 | Import des données legacy gv-back (dump → outil de migration contrôlé) | v1 |

---

## 13. Hors périmètre v1 (Phase 2)

Justification et périmètre de chaque sujet repoussé (tous planifiés, rien n'est oublié) :

| Sujet | Pourquoi P2 | Issue |
|---|---|---|
| Contenu communautaire | Le cœur v1 est la vente de billets ; le contenu était secondaire dans gv-back (faible usage). À re-confirmer avec le produit. | TRAVEL-060 |
| Annonces payantes | Fonction de monétisation à part (paiement à la publication) ; nécessite modération et fraude-conscience. | TRAVEL-061 |
| Sites touristiques & quiz | Valeur marginale, contenu éditorial. | TRAVEL-062 |
| Boutique publique dédiée | Le produit a explicitement indiqué qu'un site dédié billets viendrait plus tard ; l'API shop v1 est conçue pour être exposable sans rupture. | TRAVEL-063 |
| Import données legacy | Nécessite un dump de production (le schéma réel diffère des migrations, zéro FK) ; outil contrôlé + mapping documenté. | TRAVEL-064 |
| Réservation hôtelière (au-delà du catalogue) | Dépend de disponibilités multi-nuits et de paiements ; catalogue v1 d'abord. | (dans TRAVEL-024/041 P2) |
| Mobile pompiste-like (app agent) | Après validation du flux web ; le mobile réutilisera `leopardo_core`. | P2 |
| Découpages administratifs 3 niveaux | Non requis pour la v1 (pays + villes suffisent). | P2 |

---

## 14. Références

- `docs/specifications/PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md` — cadre des solutions verticales (PLAT-001..012, FUEL-*, EDU-*).
- `docs/architecture/BOUNDED-CONTEXT-REGISTRY.md` + `dev-hub/governance/bounded-context-registry.json` — registre BC (MAT-001).
- `docs/architecture/module-creation-guide.md` + `api/stubs/module-template/` — création de module.
- `CONVENTIONS.md` + `api/ARCHITECTURE.md` — conventions code, routes, migrations, tests, OpenAPI.
- `docs/architecture/MIGRATIONS_CONVENTIONS.md` (MAT-005) — migrations tenant/search_path.
- `docs/architecture/maturity/DEP_BC15_FUEL_MATURITY.md` — format du rapport de maturité.
- Modules de référence : `api/app/Modules/CRM` (squelette DDD + outbox + policies), `api/app/Modules/Cameras` (middleware de flag).
- Ancien projet : `kitokoh/gv-back` (fork de `lesphinx/gv-back-unified`) — inventaire complet dans le dossier de conception.
- Règle d'or AGENTS.md (nouveaux modules) : spec dans `docs/specifications/` **validée par le propriétaire** avant création des issues.
