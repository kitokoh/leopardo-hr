# GEO × VTC — Marketplace publique VTC/Taxi + corrections BC-34/35 (lot 2)

> Date : 2026-10-11 · Refs #8423
> Specs de référence : `docs/specifications/MODULE_GEOCORE_ET_VERTICAL_VTC.md`, `docs/specifications/SOLUTION_TRAVEL_AGENCY.md`
> Branche : `feat/8423-vtc-marketplace-public`
> Convention de statut : ✅ fait · 🚧 en cours · ⬜ à faire
> Découpage : chaque tâche = un commit poussé sur la branche.
> Contexte : core geo **BC-34** livré via #8377 (mergée), backend VTC **BC-35** livré via #8422.

## État de l'audit (2026-10-11)

- **Geo (BC-34) ~90 %** : `GeoServiceContract` + VOs partagés, `PostgisDistanceCalculator`/`HaversineDistanceCalculator`, `EloquentNearestSearch` (ST_DWithin + KNN), `SearchableRegistry` fail-closed, 4 endpoints authentifiés. Manques : surface publique, autorisation par type sur `GET /v1/geo/nearest` (positions chauffeurs lisibles par tout employé), géocodage, colonnes `geography` natives + index GIST (déviation D2 `decimal(10,7)`).
- **VTC (BC-35) backend complet** : 6 tables tenant, réservation idempotente (passager invité déjà nullable → prêt pour le public), dispatch cascade avec `lockForUpdate`, tarification en minor units, state machine, RBAC, 13 tests. Manques : file `vtc` **non consommée** par les workers déployés (`render.yaml`, `render.prod.yaml`, `docker-compose.yml`) = dispatch mort en prod ; aucun endpoint public ; listeners notifications absents (`VtcRideAccepted`/`Expired`/`Completed` orphelins) ; annulation dispatcher/admin/driver non exposée ; 2 chaînes EN en dur dans `VtcDriverBaseController` (`error`/`message` du profil non lié) ; scan PHP complet dans `VtcDriverOfferController::index` ; comparaison stricte `passenger_user_id === getAuthIdentifier()` (`VtcRideController.php:93`) ; spec §5.1 désynchronisée (`geography` vs `decimal`).
- **Marketplace VTC publique : 0 %** — tout `/v1/vtc/*` exige Sanctum + tenant.

## 1. Cible

a. **Core geo réutilisable exposé au public de façon sûre** : la recherche de proximité devient consommable par la marketplace publique, bornée par type de ressource et par société opt-in (pas de fuite de positions chauffeurs, registry fail-closed conservé).
b. **Verticale VTC bouclée côté public** : devis, réservation invitée et suivi de course sans compte, en réutilisant les actions existantes (`EstimateRideFareAction`, `RequestRideAction`) et le dispatch existant — aucune logique métier dupliquée.
c. **Offres VTC/taxi réservables sur `front/travel-web`** : la marketplace publique (où l'on réserve déjà les billets de bus via `/api/v1/public/travel/marketplace/*`) propose un parcours « VTC & Taxi » complet : opérateurs → devis → réservation → suivi.

## 2. Architecture

### Flux public cible

```
┌────────────────────┐         ┌──────────────────────────────────────────────┐
│  front/travel-web  │  HTTPS  │  api — /api/v1/public/vtc/marketplace/*      │
│  parcours          │────────▶│  throttle:shop-public · DTO publics stricts  │
│  « VTC & Taxi »    │         └───────┬──────────────────────────────────────┘
└────────────────────┘                 │ agrégation cross-tenant bornée
                                       │ aux sociétés opt-in (annuaire public)
                                       ▼
                        ┌──────────────────────────────┐
                        │ TenantManager::withinTenant()│  tenant résolu par ressource
                        └───────┬──────────────────────┘
                                ▼
              ┌─────────────────────────────────────┐
              │ Actions existantes (BC-35)          │
              │ EstimateRideFareAction (devis)      │
              │ RequestRideAction (réservation      │
              │   invitée, idempotente)             │
              └───────┬─────────────────────────────┘
                      ▼ événements existants
              ┌─────────────────────────────────────┐
              │ Dispatch cascade existant           │
              │ (lockForUpdate, file `vtc`)         │
              └─────────────────────────────────────┘
```

### Principes (alignés sur le pattern `TravelMarketplaceController`)

- **DTO publics stricts** : Resources dédiées, aucune fuite de champs internes (tarifs en minor units + devise, pas d'IDs internes sensibles).
- **Throttle `shop-public`** sur tout le préfixe public, comme la marketplace bus.
- **Agrégation cross-tenant bornée** : seules les sociétés opt-in (annuaire marketplace, pattern travel) sont exposées ; le tenant est résolu **par ressource** via `TenantManager::withinTenant()`, jamais globalement.
- **Idempotence** : la réservation invitée réutilise l'idempotence BC-35 (clé côté client, passager invité nullable déjà en place).
- **Fail-closed** : toute ressource hors scope (société non opt-in, type non déclaré, course d'un autre tenant) répond 404/403, jamais de dégradation silencieuse.
- **Pas de re-préfixage de routes** : nouveau groupe `routes/modules/vtc.php` sous le préfixe public existant, conforme aux gardes CI.

## 3. Tâches

### Phase 0 — Cadrage

| ID | Tâche | Fichiers | Statut |
|---|---|---|---|
| M-00 | Ce document + issue #8423 + branche `feat/8423-vtc-marketplace-public` | `docs/specifications/GEO_VTC_MARKETPLACE_TASKS.md` | ✅ |

### Phase 1 — Corrections BC-34/35

| ID | Tâche | Fichiers | Statut |
|---|---|---|---|
| M-01 | Workers : consommer la file `vtc` (+ `delivery`, `communication`, `reconciliation`) sur toutes les topologies déployées | `render.yaml`, `render.prod.yaml`, `docker-compose.yml` | ⬜ |
| M-02 | Garde d'autorisation par type sur `GET /v1/geo/nearest` (positions chauffeurs non lisibles par tout employé) | `GeoNearestController`, `NearestRequest`, config `geo.searchables` (policy par type) | ⬜ |
| M-03 | Externaliser les 2 chaînes EN en dur de `VtcDriverBaseController` vers `vtc.*` | `VtcDriverBaseController.php`, 4 locales (fr/en/ar/tr) + `versions.json` | ⬜ |
| M-04 | `VtcDriverOfferController::index` : remplacer le scan PHP complet par un filtre JSONB | `VtcDriverOfferController.php` (`where metadata->pending_offer`) | ⬜ |
| M-05 | Comparaison stricte `passenger_user_id` : caster `(int) $user->getAuthIdentifier()` | `VtcRideController.php:93` | ⬜ |

### Phase 2 — API publique marketplace VTC

| ID | Tâche | Fichiers | Statut |
|---|---|---|---|
| M-10 | Opt-in société marketplace VTC : migration **public** (flag/annuaire `companies`, pattern travel) | migration public + modèle/config société | ⬜ |
| M-11 | `GET /public/vtc/marketplace/operators` — annuaire des sociétés VTC opt-in (DTO strict) | controller + Resource publics, `routes/modules/vtc.php` | ⬜ |
| M-12 | `POST /public/vtc/marketplace/estimate` — devis public, wrapper d'`EstimateRideFareAction` | controller + Request + Resource publics | ⬜ |
| M-13 | `POST /public/vtc/marketplace/rides` — réservation invitée (idempotence, throttle `shop-public`, wrapper de `RequestRideAction`) | controller + Request + Resource publics | ⬜ |
| M-14 | `GET /public/vtc/marketplace/rides/{reference}` — suivi invité par référence + `tracking_code` | controller + Resource publics, migration **tenant** (`tracking_code`) | ⬜ |
| M-15 | Tests Feature des endpoints publics (agrégation opt-in, isolation cross-tenant 404, idempotence, throttle) | `api/tests/Feature/Vtc/` | ⬜ |

### Phase 3 — Notifications & ops

| ID | Tâche | Fichiers | Statut |
|---|---|---|---|
| M-20 | Listeners `VtcRideAccepted`/`VtcRideExpired`/`VtcRideCompleted` → notifications passager/chauffeur, dégradation gracieuse (mail/FCM indispo ≠ échec course) | `app/Modules/Vtc/Application/Listeners`, providers | ⬜ |
| M-21 | Annulation dispatcher/admin/driver exposée (`CancelRideAction`, champ `cancelled_by` + acteur) | `CancelRideAction`, controller, migration tenant si colonne absente | ⬜ |

### Phase 4 — Front travel-web

| ID | Tâche | Fichiers | Statut |
|---|---|---|---|
| M-30 | Parcours « VTC & Taxi » : opérateurs → géoloc/adresses → devis → réservation invitée → suivi | `front/travel-web` (pages, composants, client API) + i18n fr/en/ar/tr + `versions.json` | ⬜ |

### Phase 5 — Documentation

| ID | Tâche | Fichiers | Statut |
|---|---|---|---|
| M-40 | Resync spec BC-34 §5.1 (`decimal` vs `geography`) + note de migration 000102 | `docs/specifications/MODULE_GEOCORE_ET_VERTICAL_VTC.md` | ⬜ |

## 4. Hors scope (issues dédiées futures)

- Colonnes `geography` natives + index GIST (déviation D2, migration lourde à part).
- UI admin VTC (gestion véhicules, grilles tarifaires, carte dispatch temps réel).
- Application chauffeur Flutter.
- Géocodage d'adresses (provider + cache).
- Section vitrine `vtc_booking` du site marketing.
- Moteur d'itinéraire routier + paiement de course en ligne.
- Files `delivery` / `communication` / `reconciliation` au-delà du fix worker M-01.

## 5. Conventions CI rappelées

- **Gardes migrations** : migrations idempotentes, `down()` propre, séparation stricte public/tenant (M-10 public, M-14 tenant).
- **i18n** : aucune chaîne en dur — catalogues fr/en/ar/tr synchronisés + `versions.json` régénéré (garde i18n-diff, M-03 et M-30).
- **Architecture-check** : respect de `GEO_SINGLE_USAGE_STRICT` — le core geo reste consommé via `GeoServiceContract`, aucune requête PostGIS ad hoc dans le module VTC.
- **Routes** : pas de re-préfixage — le groupe public vit dans `routes/modules/vtc.php` sous le préfixe `/api/v1` existant.
- **Commits** : Conventional Commits en FR avec scope + référence d'issue (`Refs #8423` / `Closes #8423`).
