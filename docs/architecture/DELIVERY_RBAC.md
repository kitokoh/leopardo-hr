# DELIVERY_RBAC — Matrice d'autorisation BC-26 DELIVERY

> **BC-26-D05 (issue #6294)** — RBAC du module de livraison dernier-kilomètre.
> Spec : `docs/specifications/SOLUTION_DELIVERY.md` §4. Manifest :
> `DeliveryManifest` (permissions `delivery.admin/dispatcher/rider/manager/reports`).
> Garde de routes : middleware `delivery.role` (alias
> `EnsureDeliveryRoleMiddleware`) ; décisions par ressource : Policies
> `DeliveryPolicy` (livraisons + événements) / `DeliveryRoutePolicy`
> (tournées — ownership livreur).

## Principes

- **Deny-by-default** : un employé sans rôle couvert reçoit `403
  DELIVERY_ROLE_REQUIRED`. Il n'existe pas d'accès « par défaut ».
- **Scope tenant** : toute décision est bornée à `company_id` de l'acteur
  (fail-closed #3727) — jamais de ressource d'un autre tenant (404 / 403).
- **Livreur borné** : le livreur n'accède qu'à SA tournée du jour
  (`route.driver_id = employé connecté`), pas d'ID d'URL pour l'identifier.
- **Versionné** : cette matrice évolue par PR ; toute nouvelle route du module
  doit déclarer son rôle ici avant merge (garde code review).

## Correspondance rôles → employé

| Rôle delivery | Profil employé | Rationale |
|---|---|---|
| `delivery.admin` | manager, `manager_role = principal` | Propriétaire du tenant (paramétrage, purge, supervision totale) |
| `delivery.dispatcher` | manager, `manager_role ∈ {principal, manager}` | Ops livraison : crée/affecte/clôture tournées & livraisons |
| `delivery.manager` | manager, `manager_role ∈ {principal, manager, rh}` | Lecture, rapports & KPIs, export — inclut le dispatcher (chef ops) et la RH |
| `delivery.reports` | alias de `delivery.manager` | Parité manifest (permission dédiée rapports) |
| `delivery.rider` | employé actif (`role = employee`) | Livreur terrain — accès borné à sa tournée |

## Matrice actions × rôles

| Action | Route (préfixe `/api/v1/delivery`) | admin | dispatcher | manager | rider |
|---|---|---|---|---|---|
| Smoke test module | `GET /ping` | ✅ | ✅ | ✅ | ✅ |
| Lister livraisons | `GET /deliveries` | ✅ | ✅ | ❌ | ❌ |
| Créer une livraison | `POST /deliveries` | ✅ | ✅ | ❌ | ❌ |
| Consulter une livraison | `GET /deliveries/{id}` | ✅ | ✅ | ❌ | ❌ |
| Générer lien tracking | `POST /deliveries/{id}/tracking-link` | ✅ | ✅ | ❌ | ❌ |
| Créer une tournée | `POST /deliveries/routes` | ✅ | ✅ | ❌ | ❌ |
| Affecter livreur/véhicule | `POST /deliveries/routes/{id}/assign` | ✅ | ✅ | ❌ | ❌ |
| Clôturer une tournée | `POST /deliveries/routes/{id}/close` | ✅ | ✅ | ❌ | ❌ |
| Détail tournée | `GET /deliveries/routes/{id}` | ✅ | ✅ | ❌ | ✅* |
| Événement de tracking | `POST /deliveries/events` | ✅ | ✅ | ❌ | ✅† |
| Timeline interne | `GET /deliveries/{id}/tracking` | ✅ | ✅ | ✅ | ✅† |
| Rapport summary | `GET /deliveries/reports/summary` | ✅ | ✅* | ✅ | ❌ |
| Export CSV | `GET /deliveries/reports/export` | ✅ | ✅* | ✅ | ❌ |
| Suivi public destinataire | `GET /api/v1/deliveries/tracking/{token}` | public (token = credential, DELIVERY-204) | | | |

*✅ dispatcher : le rôle `manager_role = manager` (chef ops) couvre à la fois
la dispatch et la consultation des rapports (`delivery.manager` ⊇
`delivery.dispatcher` pour `manager_role = manager`).

*✅ rider : uniquement SA tournée (`driver_id = id`), sinon 403/404.
†✅ rider : uniquement les livraisons d'une de SES tournées (Policy
`DeliveryPolicy::store`), sinon 403.

## Tests négatifs par rôle (BC-26-D05)

`api/tests/Feature/Delivery/DeliveryRbacTest.php` couvre la matrice :

- `401` sans authentification (toutes les routes du module).
- `403` employé sans rôle delivery (rider sur CRUD, manager sur écriture…).
- `403` rider sur la tournée d'un collègue (cross-employé, ownership).
- `403/404` cross-tenant : ressource du tenant B introuvable depuis A
  (fail-closed, IDs connus).
- Enforcement des Policies via `Gate::forUser()` (décisions par ressource).

## Évolution

- DELIVERY-203 (app mobile livreur) ajoutera `routes/today` et
  `stops/{stop}/status` : ces routes s'appuieront sur `delivery.role:rider`
  + `DeliveryRoutePolicy::view` (tournée du jour du livreur connecté) — la
  présente matrice est leur contrat d'autorisation.
- Le gestionnaire d'erreurs 403 du module répond `DELIVERY_ROLE_REQUIRED`
  (message i18n `fr`).
