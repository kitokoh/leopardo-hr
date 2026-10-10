# VTC_RBAC — Matrice d'autorisation BC-34 VTC

> **VTC-06 (issue #8362)** — RBAC de la verticale VTC/taxi. Spec :
> `docs/specifications/MODULE_GEOCORE_ET_VERTICAL_VTC.md` §5.5. Manifest :
> `VtcManifest` (rôles `vtc.admin/dispatcher/driver`). Garde de routes :
> middleware `vtc.role` (alias `EnsureVtcRoleMiddleware`) ; module gate :
> `module.vtc` (feature flag `companies.features.vtc`, posé par
> `SolutionActivator`). Source unique des correspondances :
> `VtcRoleResolver` (leçon #8185 — jamais de duplication).

## Principes

- **Deny-by-default** : un employé sans rôle couvert reçoit
  `403 VTC_ROLE_REQUIRED`. Il n'existe pas d'accès « par défaut ».
- **Scope tenant** : toute décision est bornée à `company_id` de l'acteur
  (trait `BelongsToCompany`, fail-closed) — jamais de ressource d'un autre
  tenant (404).
- **Chauffeur borné** : le chauffeur n'accède qu'à SES offres et SES
  courses (`user_id` ↔ fiche `vtc_drivers`, 404 uniforme — jamais un 403
  qui révélerait l'existence d'une ressource d'un autre chauffeur).
- **Passager borné** : la surface passager (VTC-03) exige seulement un
  compte authentifié du tenant + flag `vtc` ; la visibilité d'une course
  est = son passager (ou tout manager du tenant, ops) — 404 sinon.
- **Versionné** : cette matrice évolue par PR ; toute nouvelle route du
  module doit déclarer son rôle ici avant merge (garde code review).

## Correspondance rôles → employé

| Rôle vtc | Profil employé | Rationale |
|---|---|---|
| `vtc.admin` | manager, `manager_role = principal` | Propriétaire du tenant (grilles tarifaires, flotte, chauffeurs, supervision totale) |
| `vtc.dispatcher` | manager, `manager_role ∈ {principal, manager}` | Ops répartition : console temps réel (courses actives, carte chauffeurs) |
| `vtc.driver` | employé actif (`role = employee`, `status = active`) + fiche `vtc_drivers` rattachée (`user_id`) | Chauffeur terrain — périmètre borné à SES offres/courses |

## Matrice actions × rôles

| Action | Route (préfixe `/api/v1/vtc`) | admin | dispatcher | driver |
|---|---|---|---|---|
| Smoke test module | `GET /ping` | ✅ | ✅ | ✅ |
| Devis course | `POST /rides/estimate` | ✅¹ | ✅¹ | ✅¹ |
| Demander une course | `POST /rides` | ✅¹ | ✅¹ | ✅¹ |
| Consulter SA course | `GET /rides/{id}` | ✅ (toutes) | ✅ (toutes) | ✅ (siennes) |
| Annuler SA course | `POST /rides/{id}/cancel` | ✅ (avant accepted) | ✅ (avant accepted) | ✅ (siennes, avant accepted) |
| Offres en cours | `GET /driver/offers` | ❌ | ❌ | ✅ |
| Accepter une offre | `POST /driver/rides/{id}/accept` | ❌ | ❌ | ✅ |
| Décliner une offre | `POST /driver/rides/{id}/decline` | ❌ | ❌ | ✅ |
| Transitions de course | `POST /driver/rides/{id}/arrive\|start\|complete` | ❌ | ❌ | ✅ |
| Ingestion position | `POST /driver/position` | ❌ | ❌ | ✅ (throttle 30/min) |
| Disponibilité | `POST /driver/availability` | ❌ | ❌ | ✅ |
| Courses actives (polling) | `GET /dispatch/rides` | ✅ | ✅ | ❌ |
| Carte chauffeurs (polling) | `GET /dispatch/drivers` | ✅ | ✅ | ❌ |
| CRUD grilles tarifaires | `GET\|POST /fare-profiles`, `PUT\|DELETE /fare-profiles/{id}` | ✅ | ❌ | ❌ |
| CRUD véhicules | `GET\|POST /vehicles`, `PUT\|DELETE /vehicles/{id}` | ✅ | ❌ | ❌ |
| CRUD chauffeurs | `GET\|POST /drivers`, `PUT\|DELETE /drivers/{id}` | ✅ | ❌ | ❌ |

¹ Surface passager : ouverte à tout compte authentifié du tenant (module
`vtc` actif) — les rôles fins ne s'y appliquent pas (VTC-03).

## Règles complémentaires

- **Création de course** : en-tête `Idempotency-Key` (UUID v4) obligatoire,
  throttle 10/min — anti-double-course (spec §7).
- **Double acceptation impossible** : verrou `lockForUpdate` +
  `VTC_OFFER_NOT_CURRENT` (409) côté dispatch (VTC-04).
- **Suppressions admin bornées** : grille référencée → `409
  VTC_FARE_PROFILE_IN_USE` ; véhicule affecté → `409 VTC_VEHICLE_ASSIGNED` ;
  chauffeur avec courses → `409 VTC_DRIVER_HAS_RIDES` (préférer la
  suspension).
- **RGPD** : positions chauffeurs = données personnelles — rétention
  `vtc.positions_retention_days` (défaut 30 j) + purge planifiée
  `vtc:purge-positions` (daily 03:15, idempotente, auditée par log).
- **Diagnostic plateforme** : `GET /api/v1/geo/capabilities` (core geo
  BC-33) reste réservé au rôle admin tenant (garde `geo.admin`, GEO-05).
