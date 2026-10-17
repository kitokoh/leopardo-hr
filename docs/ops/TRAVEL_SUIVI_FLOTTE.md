# Suivi géographique de la flotte TravelAgency — architecture et limites

> BC-24 TRAVEL OS — issue **#8548**. Ce document distingue **quatre niveaux de
> localisation** et dit, pour chacun, ce qui est **réellement alimenté** dans le
> dépôt. Aucune position n'est simulée : sans dispositif, il n'y a pas de
> donnée.

## 1. Les quatre niveaux, sans ambiguïté

| Niveau | Définition | Alimenté aujourd'hui ? | Où |
|---|---|---|---|
| **(a) Temps réel** | Position poussée par un équipement GPS ou un appareil autorisé, à intervalle court | **Oui, si un dispositif transmet** — aucun fournisseur n'est intégré | `POST /v1/travel/vehicles/{id}/position` |
| **(b) Dernière position connue + date** | Instantané : dernière coordonnée reçue et son horodatage | **Oui** | `travel_vehicles.latitude/longitude/position_source/position_recorded_at` (#8540) |
| **(c) Localisation occasionnelle** | Position transmise ponctuellement par une application autorisée (conducteur, agent) | **Oui** — même endpoint, `source: app` | idem (a) |
| **(d) Position estimée** | Position **déduite** du trajet, jamais mesurée | **Non calculée** — l'enum `estimated` existe et est acceptée en entrée, mais **rien ne la produit** | `TravelVehiclePositionSource::ESTIMATED` |

**Règle opposable** : une position `estimated` est exposée avec
`is_measured: false` et ne doit **jamais** être présentée comme une position
GPS. Le tableau de bord s'appuie sur ce drapeau.

## 2. Choix explicite : aucun fournisseur de télémétrie intégré

**Décision documentée : ne pas intégrer de fournisseur externe dans cette
mission.** Aucun adaptateur, aucun client, aucun stub silencieux n'est ajouté
côté TravelAgency.

Justification : un abonnement de télémétrie par véhicule est un **coût
récurrent** et un **engagement contractuel** qui ne relèvent pas d'une décision
technique unilatérale. L'issue #8548 §2 autorise explicitement cette décision
(« ou décision documentée de ne pas en intégrer ») et §6 interdit tout `TODO`
masquant une fonctionnalité annoncée.

**Ce qui existe ailleurs dans le dépôt** (à réutiliser si un jour un
fournisseur est retenu, plutôt que d'écrire un second client) :

- `App\Shared\Contracts\Tracking\VehicleTrackingProvider` — contrat transverse ;
- `App\Modules\Attendance\Infrastructure\Services\TraccarService` — client Traccar ;
- `App\Modules\Fleet\Infrastructure\Services\FleetTrackingSyncService` — synchro flotte ;
- `vehicle_positions` (module Fleet, #7401) — historique Traccar.

Ces briques appartiennent au module **Fleet**, pas à TravelAgency : les
fusionner serait un chantier à part entière, hors périmètre de cette mission.

## 3. Ce qui est livré et vérifié

| Élément | État | Preuve |
|---|---|---|
| Ingestion par dispositif autorisé | **Livré** (#8540) | `RecordVehiclePositionAction`, `RecordTravelVehiclePositionRequest` |
| Source obligatoire et whitelistée | **Livré** | `source ∈ {gps, app, estimated}` |
| Horodatage de la mesure transmis par le dispositif | **Livré** (#8548) | `recorded_at` borné (`before_or_equal:now`, `after:-30 days`) |
| **Historique** des positions réellement transmises | **Livré** (#8548) | table `travel_vehicle_position_logs`, `GET /v1/travel/vehicles/{id}/positions` |
| Fraîcheur opposable (récente / ancienne / indisponible) | **Livré** (#8548) | `VehiclePositionFreshness` |
| Tableau de bord flotte (dernière position, départs à venir) | **Livré** (#8548) | `GET /v1/travel/fleet/tracking` |
| Isolation par entreprise | **Livré** | scope `BelongsToCompany` + 404 cross-tenant |
| Détection de retard | **NON livrée** | voir §4 |

### Historique : bornage et rétention

- Lecture **bornée** : tenant (scope), véhicule, fenêtre `from`/`to`, `limit`
  plafonné à 500 points.
- **Aucune position fabriquée** : un véhicule sans dispositif retourne
  `count: 0` et une liste vide.
- **Rétention** : aucune purge automatique n'est planifiée dans cette mission.
  La table est en append-only ; une politique de rétention (RGPD) reste à
  définir avant une mise en production à fort volume.

## 4. Détection de retard : volontairement absente

La détection de retard exige une **source fiable et datée** (position récente
+ horaire théorique + vitesse). Aucun fournisseur n'alimentant le système, une
telle fonction produirait des alertes fondées sur des données absentes ou
anciennes — c'est-à-dire des alertes fausses.

**Règle appliquée** : l'absence de donnée est affichée « **indisponible** »,
jamais « à l'heure ». Une position plus vieille que le seuil explicite
(`travel.fleet_position.stale_after_minutes`, défaut 30 min) est signalée
`stale` et **ne peut pas** produire d'alerte de retard.

## 5. Confidentialité et permissions

- Lecture du tableau de bord : tout employé du tenant (`TravelVehiclePolicy::viewAny`).
- Ingestion : `travel.manage` (principal/rh) — un dispositif autorisé agit via
  un compte habilité.
- Isolation stricte : un véhicule d'un autre tenant répond **404**, jamais 403
  (ne rien divulguer sur son existence).
- Aucune donnée personnelle n'est stockée dans l'historique : uniquement des
  coordonnées, une source et un horodatage.

## 6. Ce qui reste à faire (dispositif ou fournisseur requis)

- **Niveau (a) en production** : choisir et contractualiser un fournisseur de
  télémétrie (coût récurrent par véhicule), ou déployer des boîtiers GPS
  transmettant à l'endpoint existant.
- **Détection de retard** : à construire **après** qu'une source fiable
  alimente le système.
- **Calcul de position estimée** (niveau d) : à implémenter si le produit le
  souhaite, en respectant `is_measured: false`.
- **Politique de rétention** de l'historique.

Tant qu'aucun dispositif ne transmet, le suivi **temps réel** n'est pas
déclaré opérationnel.
