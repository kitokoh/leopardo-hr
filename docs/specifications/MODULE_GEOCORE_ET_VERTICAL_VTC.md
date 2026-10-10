# Conception — Core Géospatial (BC-33) & Verticale VTC/Taxi (BC-34)

**Statut** : Validée par le fondateur (demande directe du 2026-10-10)
**Auteur** : Zentor (agent) pour @kitokoh
**Couche(s)** : TRANSVERSE (`geo`) + VERTICALE (`vtc`)
**Exception freeze** : #8348 (`[FREEZE-EXCEPTION]` approuvée par le fondateur le 2026-10-10)
**Épic** : #8349 · **Tâches** : #8350 → #8364 · **Branche** : `feat/geo-core-vtc` (canonique après consolidation des deux sessions du 2026-10-10 ; ex-`feat/8349-geo-core-vtc` supprimée)
**Références** : `docs/architecture/business-os/03_TARGET_ARCHITECTURE.md`, ADR-0026, `docs/architecture/MIGRATIONS_CONVENTIONS.md`, ADR-0016 (geofence single-usage), module Delivery (blueprint)

---

## 1. Vision

Deux modules complémentaires :

1. **`geo` — le core géospatial transverse.** Aujourd'hui, chaque module qui fait de la géo se débrouille : Haversine PHP dans Attendance (geofencing), Haversine SQL brut dans le annuaire public Restaurant, colonnes décimales éparses (Fleet, Delivery, Travel, Hospitality). Aucune extension spatiale, aucun contrat partagé. Le module `geo` devient **l'unique source** de calculs de positionnement : distance, « dans un rayon », « les plus proches ». Il est pensé comme un moteur réutilisable par toutes les verticales — VTC, pharmacie la plus proche, livraison de repas, pointage, flotte.
2. **`vtc` — la verticale VTC/taxi.** Réservation de course par un passager, estimation de prix, dispatch vers le chauffeur disponible **le plus proche** (via `geo`), cycle de vie de course complet, suivi de position. C'est la première verticale consommatrice du core `geo` — elle le justifie et le durcit.

Principe fondateur : **`vtc` ne calcule jamais une distance lui-même.** Tout positionnement passe par `geo`. Les autres verticales migreront vers `geo` progressivement (pilote : annuaire Restaurant).

## 2. Classification architecturale

Alignée sur `03_TARGET_ARCHITECTURE.md` :

| Module | Couche | BC | Flag | Scope flag | Défaut | Killable | Manifest |
|---|---|---|---|---|---|---|---|
| `geo` | TRANSVERSE (comme Notification/Delivery) | **BC-33 GEO** | `geo` | module | `false` | oui | non (moteur, pas une solution) |
| `vtc` | VERTICALE (1 verticale = 1 manifest = 1 flag) | **BC-34 VTC** | `vtc` | solution | `false` | oui | `VtcManifest`, industrie `mobility` |

Dépendances déclarées dans le manifest VTC : `geo` en `required_modules` (dépendance dure — distances et dispatch), `notifications`, `fleet`, `billing` en `optional_modules` (activation possible sur tenant frais, dégradation gracieuse — le flag `notifications` n'étant pas au registre de flags, le mettre en requis rendrait l'activation impossible). L'activateur de solutions refuse l'activation de `vtc` si `geo` est inactif (mécanisme existant `SolutionActivator`, fail-closed).

Règles d'isolation respectées :
- Aucun import direct entre verticales : `vtc` consomme `geo` via `App\Shared\Contracts\Geo\*` (contrats partagés), pas via `App\Modules\Geo` directement côté Domain.
- Intégrations cross-module par **événements** uniquement (pattern Delivery) : `VtcRideCompleted`, etc.
- Chaque module derrière son middleware `module.geo` / `module.vtc` (fail-closed, alias dans `bootstrap/app.php`).

## 3. Décisions techniques clés

### D1 — PostGIS sur Neon (première introduction dans le repo)

- Migration `public` : `CREATE EXTENSION IF NOT EXISTS postgis` (ré-exécutable, via `DB::statement`, conforme aux conventions — DDL encadré, pas de SQL sauvage hors migration).
- Neon supporte PostGIS nativement ; activation documentée dans la spec + runbook.
- **Vérification de capacité au runtime** : `GeoCapabilities::postgisAvailable()` (cache 5 min, requête `pg_extension`). Si l'extension est absente (ex. environnement de test non provisionné), les services basculent sur le **fallback Haversine** plutôt que de casser — comportement testé.

### D2 — Colonnes spatiales natives

- Nouvelles colonnes : `geography(Point, 4326)` via le schema builder Laravel 12 (`$table->geography('pickup_location', 'point', 4326)`) + `spatialIndex` (GIST).
- Les colonnes historiques `latitude`/`longitude` décimales **ne sont pas migrées en v1** (pas de migration de structure sans issue dédiée ; le pilote Restaurant lit via vue d'adaptation, cf. tâche GEO-06).

### D3 — Zéro nouvelle dépendance composer en v1

- Requêtes spatiales en SQL brut encadré (`ST_Distance`, `ST_DWithin`, `ST_MakePoint`, `ST_SetSRID`) via `selectRaw`/`whereRaw` avec **bindings paramétrés** (jamais d'interpolation).
- L'adoption de `matanyadaev/laravel-eloquent-spatial` est une évolution possible (issue d'amélioration ultérieure) — non retenue en v1 pour ne pas toucher `composer.lock` sans CI locale.

### D4 — `geo` = primitives + contrat, pas annuaire

- Le core expose des **primitives** (distance, plus-proches) et un contrat `GeoLocatable` que les modèles implémentent. Il ne crée **pas** de table annuaire générique en v1 (YAGNI — chaque verticale possède déjà ses tables : pharmacies, restaurants…).
- Une **registry de types recherchables** (config `geo.searchables`) mappe `type => modèle + scope` pour l'endpoint public « le plus proche » — extensible sans modifier le core.

### D5 — Précédents copiés

- **Garde « single calculator »** : comme ADR-0016 (geofence Attendance), une garde CI interdira tout `Haversine|ST_Distance` hors module `geo` à terme (tâche GEO-07, en warning d'abord).
- **Idempotence** : clés d'idempotence sur la création de course et l'ingestion de positions (pattern `Delivery\Domain\ValueObjects\IdempotencyKey`).
- **State machine** : transitions de course centralisées, exceptions dédiées (pattern `DeliveryStateMachine`).

## 4. Module `geo` (BC-33)

### 4.1 Arborescence (squelette `api/stubs/module-template/`)

```
api/app/Modules/Geo/
├── Domain/
│   ├── Contracts/          # DistanceCalculatorInterface, NearestSearchInterface, GeoLocatable
│   ├── ValueObjects/       # GeoPoint (lat/lng validés), BoundingBox, Distance (mètres)
│   ├── Exceptions/         # InvalidGeoPointException, UnknownSearchableTypeException
│   └── Support/            # GeoCapabilities (détection PostGIS + cache)
├── Application/
│   ├── Actions/            # ComputeDistanceAction, SearchNearestAction
│   └── DTOs/               # GeoPointData, NearestResultData
├── Infrastructure/
│   └── Services/           # PostgisDistanceCalculator, HaversineDistanceCalculator (fallback),
│                           # EloquentNearestSearch (registry-driven)
├── Interfaces/Api/V1/
│   ├── Controllers/        # GeoDistanceController, GeoNearestController
│   ├── Requests/           # DistanceRequest, NearestRequest (FormRequest + validation)
│   └── Resources/          # DistanceResource, NearestResultResource
├── Console/Commands/       # geo:check-postgis (diagnostic)
├── Policies/               # (usage tenant authentifié — pas de donnée propre v1)
└── Providers/GeoServiceProvider.php
```

`App\Shared\Contracts\Geo\GeoServiceContract` : façade transverse pour les autres modules (`distanceMeters(GeoPoint, GeoPoint)`, `nearest(GeoLocatable…)`), bindée dans le provider.

### 4.2 API v1 (`api/routes/modules/geo.php`, middleware `module.geo`)

| Endpoint | Rôle |
|---|---|
| `POST /v1/geo/distance` | `{from:{lat,lng}, to:{lat,lng}}` → `{distance_m}` (auth tenant) |
| `GET /v1/geo/nearest` | `?type=pharmacy&lat=&lng=&radius_km=` → liste triée par distance (registry `geo.searchables`) |
| `GET /v1/geo/capabilities` | diagnostic : postgis disponible, version (rôle admin tenant) |

### 4.3 Requêtes types (SQL encadré)

```sql
-- Plus proches dans un rayon (bindings paramétrés)
SELECT *, ST_Distance(location, ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)) AS distance_m
FROM <table>
WHERE company_id = :company
  AND ST_DWithin(location, ST_SetSRID(ST_MakePoint(:lng, :lat), 4326), :radius_m)
ORDER BY location <-> ST_SetSRID(ST_MakePoint(:lng, :lat), 4326)  -- index GIST (KNN)
LIMIT :limit;
```

`<->` (KNN) exploite l'index GIST ; `ST_DWithin` filtre avant tri. `company_id` toujours en tête de filtre (isolation tenant).

## 5. Verticale `vtc` (BC-34)

### 5.1 Modèle de données (migrations tenant, `company_id` UUID non nul + index tenant-first partout)

| Table | Colonnes clés |
|---|---|
| `vtc_drivers` | `user_id` (nullable), nom, téléphone, `status` (offline/available/busy/suspended), `vehicle_id` nullable, `current_location geography`, `location_updated_at` |
| `vtc_vehicles` | plaque (unique par tenant), marque, modèle, couleur, places, catégorie (berline/van/moto), statut |
| `vtc_fare_profiles` | nom, devise, `base_minor`, `per_km_minor`, `per_minute_minor`, `minimum_minor`, `is_default` |
| `vtc_rides` | `reference` (VO lisible), passager (`user_id` nullable + nom/téléphone invité), `pickup_location geography`, `pickup_address`, `dropoff_location geography`, `dropoff_address`, `status`, `fare_profile_id`, `estimated_distance_m`, `estimated_duration_s`, `estimated_price_minor`, `final_price_minor` nullable, devise, `driver_id` nullable, horodatages de cycle (`requested_at…completed_at/cancelled_at`), `cancel_reason`, **`idempotency_key` unique**, `metadata jsonb` |
| `vtc_ride_events` | `ride_id`, `type`, `payload jsonb` — journal append-only |
| `vtc_driver_positions` | `driver_id`, `location geography`, `recorded_at`, `source` (app/traccar) — unique `(driver_id, recorded_at)` (idempotence) |

### 5.2 Cycle de vie d'une course (state machine)

```
requested → dispatching → accepted → arrived → in_progress → completed
    │           │            │          │           │
    └──expired──┴──cancelled─┴──────────┴───────────┘ (motifs tracés)
```

- `expired` : aucun chauffeur n'a accepté dans la fenêtre (config `vtc.offer_timeout_s`, défaut 30 s, max cascade `vtc.max_offers`, défaut 5).
- Transitions invalides → `InvalidRideTransitionException` ; chaque transition écrit un `vtc_ride_events` + émet un événement de domaine.

### 5.3 Dispatch / matching (le cœur, via `geo`)

1. Course créée (idempotente) → statut `dispatching`, job `OfferRideToNearestDriver` en file.
2. Chauffeurs `available` du tenant, `ST_DWithin(current_location, pickup, radius)` (défaut 5 km, config), tri KNN.
3. Offre au plus proche → timeout 30 s → suivant (cascade). Déclinaison = suivant immédiat.
4. Acceptation → `driver_id` assigné, statut `accepted`, événement `VtcRideAccepted`.
5. Aucun acceptant → `expired` + notification passager.

### 5.4 Tarification v1

`prix = max(minimum, base + per_km × distance_km + per_minute × durée_min)` — distance PostGIS réelle (v1 : distance à vol d'oiseau × coefficient config `vtc.road_factor`, défaut 1.3 ; intégration d'un vrai moteur d'itinéraire = hors scope). Estimation stockée à la création, `final_price_minor` recalculé sur trajet réel à la clôture (positions ingérées).

### 5.5 API v1 (`api/routes/modules/vtc.php`, middleware `module.vtc` + RBAC)

| Surface | Endpoints |
|---|---|
| Passager | `POST /v1/vtc/rides/estimate` · `POST /v1/vtc/rides` (Idempotency-Key) · `GET /v1/vtc/rides/{id}` · `POST /v1/vtc/rides/{id}/cancel` |
| Chauffeur | `GET /v1/vtc/driver/offers` · `POST /v1/vtc/driver/rides/{id}/accept|decline|arrive|start|complete` · `POST /v1/vtc/driver/position` (throttle strict) · `POST /v1/vtc/driver/availability` |
| Dispatcher | `GET /v1/vtc/dispatch/rides` · `GET /v1/vtc/dispatch/drivers` (carte temps réel, polling v1) |
| Admin tenant | CRUD `vtc/fare-profiles`, `vtc/vehicles`, `vtc/drivers` |

RBAC deny-by-default : rôles `vtc.dispatcher`, `vtc.driver`, `vtc.admin` (matrice dans `api/docs/architecture/VTC_RBAC.md`, pattern DELIVERY_RBAC).

### 5.6 Manifest & activation

`VtcManifest implements SolutionManifest` : code `vtc`, industrie `mobility` (nouvelle case du registre fermé `SolutionIndustry`), `requiredModules: [geo]`, `optionalModules: [notifications, fleet, billing]`, permissions déclarées (installées par `SolutionPermissionInstaller`), données de démo via `DemoDataRegistry`.

## 6. Points d'enregistrement (checklist d'implémentation)

Pour chaque module (leçon HC-001..008) :

1. `api/config/feature-flags.php` (flags `geo` / `vtc`)
2. **`App\Core\Feature\Domain\ModuleRegistry`** (parité CI obligatoire — ADR-0026)
3. `Company::KNOWN_MODULES`
4. Miroir `HORIZONTAL_TOOL_FEATURES` si outil web (vtc oui, geo non)
5. Middleware `Ensure*ModuleMiddleware` + alias `module.geo` / `module.vtc` dans `api/bootstrap/app.php`
6. `require routes/modules/{geo,vtc}.php` dans `api/routes/api.php` (groupe `/v1` existant)
7. Manifest dans `SolutionCatalogue` via le provider (vtc uniquement)
8. `CODEOWNERS` (`/api/app/Modules/Geo/`, `/api/app/Modules/Vtc/`) + `dev-hub/governance/bounded-context-registry.json` (BC-33, BC-34) + labels GitHub `BC-33 GEO`, `BC-34 VTC`

## 7. Sécurité & conformité

- Isolation tenant : `company_id` partout, `BelongsToCompany` fail-closed, tests d'isolation croisée obligatoires.
- Positions chauffeurs = données personnelles (RGPD) : rétention configurable `vtc.positions_retention_days` (défaut 30) + commande de purge planifiée ; accès dispatcher audité.
- Bindings SQL paramétrés partout (aucune interpolation dans le SQL spatial).
- Throttle renforcé sur `driver/position` et création de course ; idempotence anti-double-course.
- Endpoint public « le plus proche » : pas de donnée tenant exposée sans publication explicite (registry opt-in).

## 8. Plan de tests

- **Unit** : `GeoPoint` (validation bornes), calculateurs (PostGIS vs Haversine, tolérance < 1 %), state machine course (toutes transitions + invalides), calcul de tarif.
- **Feature** : ≥ 1 par endpoint ; matrice RBAC ; isolation tenant (un tenant ne voit jamais les courses/chauffeurs d'un autre) ; flag désactivé → 403 `MODULE_DISABLED`.
- **Golden journey** : demande → dispatch → acceptation → démarrage → clôture → tarif final.
- Fallback PostGIS absent : test dédié (skip conditionnel si extension indisponible sur l'environnement CI).

## 9. Découpage en tâches (issues GitHub)

| # | Titre | Portée | Taille |
|---|---|---|---|
| 1 | `[FREEZE-EXCEPTION]` Core géospatial + verticale VTC | gouvernance | XS |
| 2 | `[EPIC] Core géospatial (geo, BC-33) + verticale VTC (vtc, BC-34)` | suivi | — |
| 3 | GEO-01 PostGIS : migration extension + `GeoCapabilities` + runbook Neon | backend, database | S |
| 4 | GEO-02 Squelette module `geo` : provider, flag, middleware, routes, enregistrements | backend | S |
| 5 | GEO-03 Domaine `geo` : `GeoPoint`, calculateurs (PostGIS + fallback) + tests unitaires | backend | S |
| 6 | GEO-04 `GeoLocatable` + `NearestSearch` générique + registry `geo.searchables` | backend | M |
| 7 | GEO-05 API v1 `geo` : distance, nearest, capabilities + tests Feature | backend | M |
| 8 | GEO-06 Pilote : annuaire public Restaurant migré sur `geo` (vue d'adaptation) | backend | S |
| 9 | GEO-07 Garde CI « calculs de distance uniquement dans geo » (mode warning) | devops | XS |
| 10 | VTC-01 Squelette verticale `vtc` : manifest, flag, middleware, routes, enregistrements | backend | S |
| 11 | VTC-02 Migrations + modèles + enums (drivers, vehicles, fares, rides, events, positions) | backend, database | M |
| 12 | VTC-03 Estimation tarif + création de course idempotente (API passager) | backend | M |
| 13 | VTC-04 Dispatch : matching plus-proche + cascade d'offres + state machine + événements | backend | L |
| 14 | VTC-05 API chauffeur : offres, transitions, positions, disponibilité | backend | M |
| 15 | VTC-06 API dispatcher/admin : suivi, CRUD profils/véhicules/chauffeurs, purge RGPD | backend | M |
| 16 | VTC-07 Front web : tableau de bord dispatch (squelette `(dashboard)/vtc/`) | frontend | M |
| 17 | GEO+VTC : openapi.yaml, docs, CHANGELOG, tests d'isolation finaux | testing, documentation | S |

Dépendances : GEO-01→GEO-02→GEO-03→(GEO-04, GEO-05)→GEO-06 ; GEO-07 indépendant ; VTC-01→VTC-02→VTC-03→VTC-04→(VTC-05, VTC-06)→VTC-07 ; tous dépendent de GEO-03 pour les distances.

## 10. Risques & mitigations

| Risque | Mitigation |
|---|---|
| Freeze scope (modules gelés jusqu'au 17/10) | Issue `[FREEZE-EXCEPTION]` + décision fondateur tracée |
| PostGIS indisponible sur un environnement | Fallback Haversine + commande de diagnostic `geo:check-postgis` + runbook |
| Quotas Neon (connexions) | Pas de nouveau pool ; jobs dispatch sur la file existante ; throttle ingestion positions |
| CI rouge (pas de PHP local côté agent) | Patterns copiés des modules existants, gardes locales (`check-migration-basename-collisions.sh`) lancées avant push, corrections itératives sur retour CI |
| Régression sur l'annuaire Restaurant (pilote GEO-06) | Vue d'adaptation, tests Feature existants conservés, rollout derrière le flag `geo` |

## 11. Hors scope v1

- App mobile chauffeur Flutter (`leopardo_driver`) — v2 (API chauffeur prête pour).
- Moteur d'itinéraire routier (OSRM/Valhalla), trafic temps réel, surge pricing.
- Paiement course intégré (Billing) — prévu en évolution, événement `VtcRideCompleted` déjà émis.
- Migration des colonnes lat/lng historiques (Attendance, Fleet, Delivery) vers `geography`.
- Annuaire générique de lieux (`geo_places`) — remplacé par la registry `geo.searchables`.
- Partage de trajet temps réel public (pattern `PublicDeliveryTrackingController`) — v2.
