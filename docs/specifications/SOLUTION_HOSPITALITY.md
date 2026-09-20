# SOLUTION_HOSPITALITY — Verticale HospitalityManager (BC-32)

> **Statut : validée par le propriétaire (fondateur, mandat direct de session, 2026-09-20).**
> Exception freeze 60 jours : `[FREEZE-EXCEPTION]` accordée par le fondateur (précédent #7764).
> Base : `main` au moment de la rédaction. Modèles copiés : HealthManager (BC-31, checklist
> d'enregistrement ×8), RestaurantManager (BC-25, multi-établissements + staff + RBAC scopé),
> TravelAgency (BC-24, réservation publique idempotente).

## 1. Vision

Un fondateur de chaîne d'hôtels (ou un gestionnaire de résidences / d'appartements en location)
crée son compte depuis le site vitrine et active la solution **Hospitality**. Depuis son espace,
il dispose de tous les outils :

- **Multi-établissements** : hôtels, résidences hôtelières, immeubles d'appartements, maisons
  d'hôtes — chaque établissement a son code, sa devise, son fuseau, son statut.
- **Inventaire** : types de chambres (capacité, prix de base) et unités physiques
  (chambres / appartements) avec statut opérationnel.
- **Équipe par établissement** : affectation d'employés (rôle métier descriptif) et nomination
  de **responsables par site** via le RBAC ressource-scopé de la plateforme.
- **Réservations** : guichet (staff) et **en ligne sans compte** via une vitrine publique dédiée
  par établissement (slug public), avec suivi par référence + code, expiration des pending.
- **Gestion locative** : baux (appartements / longue durée) et suivi des loyers (échéances,
  encaissements, retards).

Non-objectif V1 : channel managers (Booking/Expedia), yield management, housekeeping planning,
paiement en ligne intégré (les réservations en ligne sont confirmées par l'établissement ;
l'intégration `PaymentGatewayRegistry` est un lot V2), POS restauration de l'hôtel (utiliser la
verticale Restaurant), comptabilité (module Accounting existant).

Distinction avec `TravelHotel` (BC-24) : les hôtels de la verticale Travel sont des
**partenaires d'une agence de voyage** (référentiel de vente). Ici l'hôtel est **le tenant
lui-même**. Aucun couplage.

## 2. Architecture

- **Module** : `api/app/Modules/HospitalityManager/` (DDD 4 couches, garde #5584 : aucune
  dépendance directe inter-modules).
- **Code solution / feature flag** : `hospitality` (scope `solution`, `default => false`,
  fail-closed, killable).
- **BC** : `BC-32 HOSPITALITY` — registre `dev-hub/governance/bounded-context-registry.json`,
  arêtes MAT-002 : `BC-32→BC-01` (SolutionCatalogue/FeatureFlag), `BC-32→BC-02` (tenant),
  `BC-32→BC-03` (acteur Employee).
- **Routes** : `api/routes/modules/hospitality_manager.php`, préfixe `/hospitality`,
  middleware `['throttle:api','auth:sanctum','token.refresh','tenant','throttle:api-plan']`
  + trait `ChecksHospitalitySolution` (solution inactive → 403 `HOSPITALITY_SOLUTION_INACTIVE`).
- **Routes publiques** : `api/routes/api.php`, préfixe `/public/hospitality/*`, tenant résolu
  **par la ressource** (slug d'établissement / référence de réservation) — pattern marketplace
  #7736/#7737, throttle dédié, aucune donnée cross-tenant.
- **Enregistrement ×8** (leçon HC-001..008) : catalogue via provider, `config/feature-flags.php`,
  `Company::KNOWN_MODULES`, registre BC + CODEOWNERS + arêtes, parité docs ×4 (compteur 31→32),
  fixture `CreatesMvpSchema`, couverture OpenAPI, préfixes protégés front
  (`protected-prefixes.ts` + `proxy.ts` + `sw.js` : `/hospitality`).

## 3. Modèle de données (tenant, préfixe `hospitality_`)

Toutes les tables : `company_id` uuid non-null indexé, trait `BelongsToCompany`, pattern
« Travel moderne » (pas de FK physique, index nommés, migrations idempotentes
`schemaTableExists`), nommage `YYYY_MM_DD_0000NN_<issue>_<slug>.php`.

| Table | Colonnes clés | Invariants |
|---|---|---|
| `hospitality_properties` | code(40), name, type (`hotel`\|`residence`\|`apartment_building`\|`guesthouse`), address, city, country(2), timezone, currency(3), phone, email, star_rating, amenities json, latitude/longitude, status (`active`\|`inactive`), is_public bool, public_slug | unique `(company_id, code)` ; `public_slug` unique global, généré au passage `is_public=true` |
| `hospitality_room_types` | property_id, code(40), name, description, capacity_adults, capacity_children, base_price_minor, currency(3), amenities json, status | unique `(company_id, property_id, code)` ; prix en minor units |
| `hospitality_units` | property_id, room_type_id nullable (null = appartement locatif hors typologie), code(40), floor, status (`available`\|`occupied`\|`maintenance`\|`out_of_service`), notes | unique `(company_id, property_id, code)` |
| `hospitality_property_staff` | property_id, employee_id (référence **par valeur**, RH propriétaire), role string(80) nullable (réceptionniste, gouvernante, gérant…), assigned_at, softDeletes | unique `(company_id, property_id, employee_id)` soft-deleted inclus ; ré-affectation = restore ; cross-tenant → 422 `EMPLOYEE_OUTSIDE_TENANT` ; doublon actif → 409 `EMPLOYEE_ALREADY_ASSIGNED` (pattern #7909) |
| `hospitality_reservations` | reference (unique/company), property_id, room_type_id, unit_id nullable, guest_name, contact_email, contact_phone, check_in date, check_out date, adults, children, status (`pending`\|`confirmed`\|`checked_in`\|`checked_out`\|`cancelled`\|`no_show`), total_amount_minor, currency, source (`desk`\|`online`), expires_at nullable, idempotency_key nullable, notes, version | unique `(company_id, idempotency_key)` partiel ; overbooking contrôlé par comptage transactionnel `lockForUpdate` sur le type de chambre et l'intervalle `[check_in, check_out)` ; `pending` en ligne expire à +30 min ; `check_out > check_in` |
| `hospitality_leases` | property_id, unit_id, tenant_name, contact_email, contact_phone, start_date, end_date nullable, rent_amount_minor, currency, deposit_minor, billing_day (1..28), status (`draft`\|`active`\|`terminated`) | une seule lease `active` par unité (unique partiel `(company_id, unit_id)` where status=active) |
| `hospitality_rent_payments` | lease_id, period char(7) `YYYY-MM`, amount_due_minor, amount_paid_minor, currency, status (`due`\|`partial`\|`paid`\|`late`), due_date, paid_at nullable, method nullable, reference nullable | unique `(company_id, lease_id, period)` ; `late` = due_date dépassée et non soldée |

## 4. RBAC (deny-by-default)

- Gate d'entrée : feature flag `hospitality` actif pour le tenant (fail-closed).
- **Type de ressource `hospitality_property`** déclaré dans `api/config/resource_types.php` ;
  autorisation par `EmployeeResourceAssignment` (niveaux `view` < `operate` < `manage`).
- **Trait `ChecksHospitalityPropertyAccess`** (copie du pattern restaurant #7598/#7599,
  progressif) : tant qu'aucune assignation `hospitality_property` n'existe dans le tenant →
  fallback `hasManagerRole('principal','rh')` ; dès la première assignation → fail-closed via
  `Employee::hasResourceAccess()`.
- Une policy par modèle (7), enregistrées dans `AuthServiceProvider` ; lecture cross-tenant → 404.
- Le `role` du pivot staff est **descriptif** (métier), jamais une source d'autorisation.
- Nommer un **responsable de site** = poser une assignation `hospitality_property` niveau
  `manage` sur l'établissement (endpoints RH existants `/employees/{id}/resource-assignments`).

## 5. API v1 (interne, préfixe `/hospitality`)

CRUD + actions, Requests validées, Resources JSON, pagination standard :

- `GET|POST /hospitality/properties`, `GET|PATCH|DELETE /hospitality/properties/{property}`
  + `POST /hospitality/properties/{property}/publish` (active `is_public`, génère le slug),
  `POST .../unpublish`
- `GET|POST /hospitality/properties/{property}/room-types`, `PATCH|DELETE /hospitality/room-types/{roomType}`
- `GET|POST /hospitality/properties/{property}/units`, `PATCH|DELETE /hospitality/units/{unit}`
- `GET|POST /hospitality/properties/{property}/staff`, `PATCH|DELETE /hospitality/properties/{property}/staff/{assignment}`
- `GET|POST /hospitality/reservations` (filtres property/status/date), `GET|PATCH /hospitality/reservations/{reservation}`,
  `POST .../{reservation}/confirm|check-in|check-out|cancel|no-show` (transitions gardées,
  409 `INVALID_RESERVATION_TRANSITION`)
- `GET /hospitality/properties/{property}/availability?from=&to=` (disponibilités par type)
- `GET|POST /hospitality/leases`, `GET|PATCH /hospitality/leases/{lease}`,
  `POST /hospitality/leases/{lease}/terminate`,
  `POST /hospitality/leases/{lease}/generate-periods` (génère les échéances `due` jusqu'à une période donnée)
- `GET /hospitality/rent-payments` (filtres lease/status/period), `POST /hospitality/rent-payments/{payment}/record`
  (encaissement partiel/total)
- `GET /hospitality/dashboard/kpis` (occupation du jour, arrivées/départs, pending en ligne,
  loyers en retard)

## 6. API publique (`/public/hospitality`, sans auth, throttle dédié)

- `GET /public/hospitality/properties/{slug}` — fiche établissement publiée (fail-closed si
  `is_public=false`), types de chambres et prix.
- `GET /public/hospitality/properties/{slug}/availability?from=&to=&adults=` — disponibilités.
- `POST /public/hospitality/properties/{slug}/reservations` — réservation en ligne :
  idempotency_key, lock inventaire, statut `pending` + `expires_at=+30min`, retourne
  `reference` + `tracking_code` (hash stocké). Pattern `CreateBookingAction` Travel.
- `GET /public/hospitality/reservations/{reference}?code=` — suivi sans compte.
- `POST /public/hospitality/reservations/{reference}/cancel` — annulation par code.

Expiration : commande `hospitality:expire-pending-reservations` schedulée (pattern
`travel:expire-pending-bookings`).

## 7. Web (front/web)

- Entrée catalogue `client-features.ts` : module `hospitality`, `vertical: 'hospitality'`,
  `featureKeys: ['hospitality']`, href `/hospitality`.
- Pages `(dashboard)/hospitality/` : `page.tsx` (hub à tuiles + KPIs), `properties/`,
  `inventory/` (types + unités, CRUD config-driven pattern `RestaurantCrudTable`),
  `reservations/` (liste, filtres, transitions), `team/` (pattern `restaurant/team` #7909),
  `rentals/` (baux + loyers).
- Vitrine publique : `front/web/src/app/stay/[slug]/page.tsx` — SSR indexable (pattern
  `/vitrine/[slug]`), fiche + formulaire de réservation + page de confirmation avec référence
  et code de suivi. `/stay` reste HORS `PROTECTED_PREFIXES` ; `/hospitality` y entre (×3
  fichiers).
- i18n : clés ×4 locales (`shared/i18n/locales/`, sync catalogues).

## 8. Tests & Definition of Done

- Feature tests par contrôleur : isolation cross-tenant (404), flag inactif (403), transitions
  invalides (409), idempotence publique, overbooking refusé, unicité lease active, staff
  422/409/restore.
- Fixture `CreatesMvpSchema` : parité stricte avec les migrations (`check-mvp-schema-parity.sh`).
- Gardes locales avant push : registre BC, isolation module, parité docs ×4, OpenAPI,
  collisions migrations, duplicate schema create, routes, policies verticales.
- CHANGELOG `[Unreleased]` ; PHPStan strict L8 = 0 erreur ; PR verte avant merge.

## 9. Plan de livraison (lot unique `bc/bc32-hospitality`, protocole lot BC)

| # | Issue | Contenu |
|---|---|---|
| HOSP-001 | Fondations | Manifest, flag, catalogue, KNOWN_MODULES, provider, routes squelette, registre BC-32, CODEOWNERS, arêtes, parité docs ×4, préfixes front, spec |
| HOSP-002 | Référentiel | Migrations + modèles + policies + CRUD properties / room types / units, publish/unpublish |
| HOSP-003 | Équipe | Pivot staff + service 422/409/restore + endpoints + resource_type `hospitality_property` |
| HOSP-004 | Réservations | Modèle + service disponibilité/transitions + endpoints internes + KPIs + commande d'expiration |
| HOSP-005 | Locatif | Baux + loyers + génération d'échéances + encaissement |
| HOSP-006 | Vitrine publique API | Endpoints publics slug + réservation en ligne idempotente + suivi/annulation par code |
| HOSP-007 | Web gestion | Hub + pages properties/inventory/reservations/team/rentals + client-features + i18n |
| HOSP-008 | Vitrine web + docs | `/stay/[slug]` + OpenAPI + fixture MVP + CHANGELOG + docs annexes |
