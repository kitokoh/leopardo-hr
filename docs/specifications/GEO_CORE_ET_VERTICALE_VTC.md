# Socle géospatial (Core/Geo) & verticale VTC/Taxi (VtcManager)

> Spécification conceptuelle et plan de tâches — GEO-001.
> Statut : **en cours d'implémentation** (branche `feat/geo-core-vtc`).
> Base de données cible : PostgreSQL (Neon en production) avec **PostGIS** quand
> l'extension est disponible, repli Haversine SQL sinon (dégradation gracieuse).

---

## 1. Vision

Deux briques distinctes, dans l'esprit du monorepo DDD :

1. **Un socle transversal `App\Core\Geo`** — comme `Core\Auth`, `Core\Tenant`,
   `Core\AI` — qui fournit à TOUT le projet les primitives de positionnement
   géographique : point géodésique validé, calcul de distance, recherche de
   proximité (« les N entités les plus proches »), détection des capacités
   PostGIS de la base. Ce socle **ne connaît aucun module métier** (garde
   d'isolation #5584 : `Core/` n'importe jamais `App\Modules\*`).

2. **Une verticale métier `App\Modules\Vtc`** (solution `vtc`, nom d'affichage
   **VtcManager**) — réservation de courses VTC/taxi — qui **consomme le socle
   Geo** pour le dispatch (chauffeur disponible le plus proche), l'estimation
   tarifaire basée sur la distance et le suivi de position des chauffeurs.

Le socle Geo est conçu pour être réutilisé ensuite, sans modification, par
d'autres besoins déjà identifiés dans la suite :

- **RestaurantManager / Delivery** : « restaurants proches de moi », livreurs ;
- **Pharmacy (PharmaManager)** : « pharmacies les plus proches » (annuaire) ;
- **Retail / Showcase** : recherche de points de vente à proximité ;
- **Attendance** : à terme, réunification possible avec la géofence
  (ADR-0016) — **hors scope** de ce chantier : la règle « un seul chemin
  d'usage » de `AttendanceGeofenceService` reste intacte, Core/Geo ne s'y
  substitue pas dans cette PR.

## 2. Décisions d'architecture

### 2.1 Stratégie de calcul : PostGIS d'abord, Haversine sinon

| Aspect | PostGIS (si extension active) | Repli Haversine (toujours disponible) |
|---|---|---|
| Filtre « dans le rayon » | `ST_DWithin(geography, geography, r)` | formule haversine en SQL (`whereRaw`) |
| Distance | `ST_Distance(...)` en mètres | haversine SQL en mètres |
| Index exploitable | GiST sur colonne `geography` (option future) | index composite `(company_id, lat, lng)` + pré-filtre bounding-box |
| Coordonnées stockées | `decimal(10,7)` lat/lng (colonnes simples, portables) | idem |

Décision clé : **on stocke latitude/longitude en colonnes décimales** (pattern
déjà acté pour `restaurant_branches`, migration #7746) et non en colonnes
`geography`. Ainsi les schémas restent identiques avec ou sans PostGIS, les
fixtures de test restent valides, et PostGIS est un **accélérateur** détecté
à l'exécution (`PostgisCapabilities`), jamais un prérequis bloquant.

- Le moteur est configurable : `GEO_ENGINE=auto|postgis|haversine`
  (`auto` = PostGIS si l'extension est installée, sinon Haversine).
- La détection est mise en cache (par connexion) pour ne pas requêter
  `pg_extension` à chaque appel.
- Une migration tenant **tolérante** tente `CREATE EXTENSION IF NOT EXISTS
  postgis` : si le rôle n'a pas le privilège (hébergement mutualisé), elle
  journalise un avertissement et **n'échoue pas** — le repli Haversine prend
  le relais. Sur Neon, PostGIS est une extension supportée et activable par
  le rôle propriétaire.

### 2.2 API du socle `Core/Geo`

```
api/app/Core/Geo/
├── Domain/
│   ├── ValueObjects/GeoPoint.php               # VO immuable lat/lng validé (-90..90 / -180..180)
│   ├── Contracts/DistanceCalculatorContract.php # distanceMeters(GeoPoint, GeoPoint): float
│   ├── Contracts/ProximitySearchContract.php    # withinRadius()/withDistance() sur Builder Eloquent
│   └── Exceptions/InvalidCoordinateException.php
├── Application/DTOs/
│   ├── NearbySearchQuery.php                   # centre + rayon + limites bornées
│   └── GeoEstimate.php                         # distance_meters + duration_seconds + source du moteur
├── Infrastructure/Services/
│   ├── PostgisCapabilities.php                 # détection extension + config engine (cache)
│   ├── HaversineDistanceCalculator.php         # implémentation pure PHP (rayon Terre configurable)
│   └── SqlProximitySearchService.php           # implémente ProximitySearchContract (SQL PostGIS ou haversine)
├── Support/HasGeoLocation.php                  # trait Eloquent : scopeNearby()/scopeWithDistanceTo()
├── Providers/GeoServiceProvider.php            # bindings des contrats (singleton)
└── (config) api/config/geo.php                 # engine, rayon Terre, facteur de détour routier, rayon par défaut
```

Points de contrat :

- `GeoPoint` est la SEULE façon de passer des coordonnées au socle (validation
  centralisée, `InvalidCoordinateException` fail-closed).
- `ProximitySearchContract::withinRadius(Builder $q, GeoPoint $c, float $r,
  ?string $latCol = null, ?string $lngCol = null)` ajoute le filtre SQL ;
  `withDistance(...)` ajoute un `selectRaw('... AS distance_meters')`.
  Colonnes par défaut `latitude`/`longitude`, surchargeables.
- Le trait `HasGeoLocation` expose `scopeNearby($q, GeoPoint $c, float $r)` et
  `scopeWithDistanceTo($q, GeoPoint $c)` qui délèguent au contrat — un module
  consommateur (Vtc aujourd'hui, Pharmacy/Delivery demain) n'écrit jamais de
  SQL géospatial lui-même.
- Estimation routière : aucun moteur de routing n'est intégré à ce stade.
  `EstimatedRoute = distance_à_vol_d'oiseau × GEO_ROAD_DETOUR_FACTOR`
  (défaut 1.3) — documenté comme approximation, remplaçable plus tard par un
  provider (OSRM, Valhalla…) derrière un contrat dédié.

### 2.3 Ce que le socle ne fait PAS (bornes explicites)

- Aucune route HTTP : le socle est une bibliothèque ; chaque module expose
  ses propres endpoints (isolation et RBAC par module).
- Aucune géofence de pointage (ADR-0016 inchangé).
- Aucun géocodage d'adresses : les adresses restent du texte libre ; un
  contrat `GeocodingContract` pourra être ajouté ultérieurement sans cassure.

## 3. Verticale VtcManager (`App\Modules\Vtc`)

### 3.1 Modèle de domaine

| Entité | Table tenant | Rôle |
|---|---|---|
| `VtcVehicle` | `vtc_vehicles` | Véhicule (immatriculation unique par tenant, catégorie, places, statut) |
| `VtcDriver` | `vtc_drivers` | Chauffeur (lien `employee_id` optionnel, permis unique par tenant, statut, **dernière position connue** lat/lng + horodatage) |
| `VtcRide` | `vtc_rides` | Course (points de prise en charge / destination, statut, tarif, distances estimée/réelle, horodatages de cycle) |
| `VtcFareSetting` | `vtc_fare_settings` | Tarification du tenant (base, par km, par minute, minimum, devise) — **un enregistrement par tenant** (unique `company_id`) |

Toutes portent `company_id` + trait `BelongsToCompany` (scope fail-closed
#3727) ; pas de FK SQL (conventions migrations tenant §2.6), index dédiés.

### 3.2 Cycle de vie d'une course (state machine)

```
requested → accepted → driver_arrived → in_progress → completed
    │           │            │               │
    └───────────┴────────────┴───────────────┴──→ cancelled
```

- Transitions autorisées uniquement (table fermée, fail-closed) :
  `VtcRideInvalidTransitionException` sinon (code `VTC_INVALID_TRANSITION`).
- `cancelled` possible depuis tout état non terminal, avec `cancelled_by`
  (`rider|driver|manager`) et `cancel_reason`.
- `accepted` exige un chauffeur `available` → passage automatique du chauffeur
  en `on_ride` ; `completed`/`cancelled` le rendent à nouveau `available`.
- Le tarif final est recalculé à la complétion : base + km réels × par-km +
  minutes réelles × par-minute, plancher `minimum_fare` (devise du tenant).

### 3.3 Dispatch & estimation (consommation du socle Geo)

- `VtcDispatchService::nearestAvailableDrivers(GeoPoint $pickup, int $limit,
  float $radiusMeters)` : `VtcDriver::nearby($pickup, $radius)->where(
  'status', 'available')->orderBy('distance_meters')` via Core/Geo — c'est
  **ici** que la verticale branche le socle (jamais de SQL géo local).
- `VtcFareCalculator::estimate(GeoPoint $pickup, GeoPoint $dropoff)` :
  distance via `DistanceCalculatorContract` × facteur de détour →
  `GeoEstimate`, puis application de la grille tarifaire du tenant.
- Positions chauffeurs : `PATCH /vtc/drivers/{id}/location` (fréquence
  mobile) — écriture légère, validation stricte des coordonnées, refus des
  positions incohérentes (> 1 000 km/h implicite non contrôlé en V1 :
  documenté comme évolution).

### 3.4 API HTTP (toutes tenant-scoped + flag `vtc`, pattern PharmaManager)

Middleware : `throttle:api`, `auth:sanctum`, `token.refresh`, `tenant`,
`throttle:api-plan`. Solution inactive → 403 `VTC_SOLUTION_INACTIVE`.

| Endpoint | Rôle | Permission |
|---|---|---|
| `GET/POST /vtc/vehicles`, `GET/PUT /vtc/vehicles/{id}` | Parc véhicules | `vtc.vehicles` |
| `GET/POST /vtc/drivers`, `GET/PUT /vtc/drivers/{id}` | Chauffeurs | `vtc.drivers` |
| `PATCH /vtc/drivers/{id}/status` | available/offline/suspended | `vtc.drivers` |
| `PATCH /vtc/drivers/{id}/location` | remontée GPS | `vtc.drivers` |
| `GET /vtc/drivers/nearby?lat&lng&radius` | chauffeurs disponibles proches | `vtc.drivers` |
| `GET/POST /vtc/rides`, `GET /vtc/rides/{id}` | courses | `vtc.rides` |
| `POST /vtc/rides/estimate` | estimation tarif/distance | `vtc.rides` |
| `POST /vtc/rides/{id}/accept` | attribution chauffeur | `vtc.rides` |
| `POST /vtc/rides/{id}/arrived` `/start` `/complete` `/cancel` | cycle de vie | `vtc.rides` |
| `GET/PUT /vtc/fare-settings` | grille tarifaire tenant | `vtc.fares` |

RBAC : deny-by-default via Policies enregistrées dans `AuthServiceProvider`
(point unique PA2-ARCH-008) ; écriture réservée aux managers (pattern
Pharmacy), lecture aux employés du tenant. Isolation cross-tenant : 404
(`assertSameTenant`), jamais 403 fuyard.

### 3.5 Enregistrements transverses

- `SolutionIndustry::Transport = 'transport'` — extension du registre fermé
  (nouvelle case uniquement, conforme BOS-013).
- Manifest `vtc` (VtcManifest, maturité `pilot`) enregistré au
  `SolutionCatalogue` par `VtcServiceProvider` ; activation par
  `SolutionActivator` existant (pose le flag + grants de permissions).
- Flag `vtc` déclaré dans `config/feature-flags.php` (scope solution, défaut
  `false`, killable), `Company::KNOWN_MODULES` et `ModuleRegistry`
  (`known_order` suivant).
- `SolutionManifestConformanceTest::EXPECTED_CODES` étendu (10 → 11 codes).
- Gouvernance : BC-33 `GEO` (Core/Geo) + BC-34 `VTC` (Modules/Vtc) dans le
  registre MAT-001, lignes CODEOWNERS, arêtes MAT-002 (VTC → BC-01/02/03/33).

## 4. Plan de tâches (issues GitHub)

| Code | Titre | Livrables |
|---|---|---|
| GEO-001 | Spec & gouvernance du socle Geo + verticale VTC | ce document, registre BC, CODEOWNERS, matrice MAT-002, parité docs (4 docs), CHANGELOG |
| GEO-002 | Core/Geo — fondations | `config/geo.php`, `GeoPoint`, contrats, `InvalidCoordinateException`, `PostgisCapabilities`, `GeoServiceProvider`, binding bootstrap |
| GEO-003 | Core/Geo — calculs & proximité SQL | `HaversineDistanceCalculator`, `SqlProximitySearchService`, migration `CREATE EXTENSION` tolérante |
| GEO-004 | Core/Geo — trait HasGeoLocation + tests unitaires | trait + `Tests\Unit\Geo\*` |
| VTC-001 | VTC — schéma tenant | 4 migrations + miroir `CreatesMvpSchema` (garde #5443) |
| VTC-002 | VTC — domaine | 4 modèles, enums, state machine, exceptions, policies |
| VTC-003 | VTC — application | actions (CRUD, cycle course), `VtcDispatchService`, `VtcFareCalculator` |
| VTC-004 | VTC — API & activation | contrôleurs, requests, `routes/modules/vtc.php`, provider, manifest, flags, policies AuthServiceProvider |
| VTC-005 | VTC — tests feature | parcours complet : config tarifs → chauffeurs → estimation → course de bout en bout → annulation → isolation tenant |
| VTC-006 | VTC — docs & conformité | `EXPECTED_CODES`, docs architecture, i18n exceptions (fr/en/ar/tr) |

## 5. Risques & limites assumées (V1)

1. **Estimation routière approximative** (facteur de détour) — pas de moteur
   de routing ; contrat prévu pour brancher OSRM/Valhalla plus tard.
2. **Dispatch manuel** : l'acceptation d'une course se fait par choix explicite
   du chauffeur parmi les proches — pas d'auto-assignation ni de file
   d'attente en V1.
3. **Temps réel** : pas de websocket/push de position en V1 ; les clients
   polluent `GET /vtc/rides/{id}` + `GET /vtc/drivers/nearby`.
4. **PostGIS optionnel** : les perfs « nearest » à grande échelle dépendent de
   son activation ; en repli Haversine, le pré-filtre bounding-box borne le
   scan à la bbox + index `(company_id, status)`.
5. Tests locaux impossibles sans PostgreSQL dans l'environnement de
   développement de l'agent → validation par CI (backend PHP 8.4 + PG 16).
