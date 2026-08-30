# Matrice RBAC — Permissions `restaurant.*` (BC-25)

> RESTO-306 · issue #6187 · Branche `bc/bc25-restaurant-referential`
> Spec de référence : `docs/specifications/SOLUTION_RESTAURANT_MANAGER.md` — §1.2 (personas) et §1.3 règle 2 (tenant-safe).

---

## 1. Principe

Le RBAC de la plateforme est porté par les **rôles de l'employé**
(`App\Core\Auth\Domain\Models\Employee` : champs `role` + `manager_role`,
méthode `hasManagerRole(...)`) — **il n'existe pas de table de permissions**.

Les permissions `restaurant.*` déclarées par le manifest
(`RestaurantManagerManifest::permissions()`, RESTO-106/#6163) sont donc des
**constantes documentaires** mappées sur les rôles via
`RestaurantPermissions::requiresManagerRoles()` et consommées par les
**Policies du module** (`App\Modules\RestaurantManager\Policies`).

Représentation des colonnes de rôle dans la matrice :

| Colonne | Représentation dans `Employee` |
|---|---|
| `principal`, `rh`, `manager`, `server`, `kitchen`, `rider` | `role = 'manager'` + `manager_role = <colonne>` |
| `employee` | `role = 'employee'` (sans sous-rôle) |

## 2. Matrice permission × rôle

| Permission | principal | rh | manager | server | kitchen | rider | employee |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `restaurant.manage` (configuration) | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| `restaurant.manager` (salle) | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| `restaurant.server` | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |
| `restaurant.kitchen` | ✓ | ✓ | ✓ | ✗ | ✓ | ✗ | ✗ |
| `restaurant.rider` | ✓ | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ |
| `restaurant.reports` (lecture/rapports) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| Lecture du référentiel (`viewAny`) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

Notes :

- **Écriture** (`create` / `update` / `delete`) : suit strictement la matrice
  ci-dessus (rôles requis par permission).
- **Lecture** (`viewAny`) : ouverte à **tout employé authentifié du tenant**
  (y compris `role = 'employee'`) — le référentiel est consultable par
  l'équipe, l'écriture reste réservée aux rôles de la matrice.
- **`view` / `update` / `delete` d'une ressource précise** : exige en plus
  `company_id` de la ressource === `company_id` de l'acteur (cross-tenant →
  refus).
- Le périmètre « menus / horaires de la salle » (création, édition
  quotidienne) est ouvert au manager de salle (`restaurant.manager`) ; la
  configuration des **tarifs et du catalogue produits** relève du gérant
  (`restaurant.manage`) — cf. §1.2 de la spec pour la répartition des
  responsabilités.

## 3. Mapping persona → permission (spec §1.2)

| Persona (spec §1.2) | Permission | Périmètre |
|---|---|---|
| Gérant / propriétaire | `restaurant.manage` | configuration, tarifs, menus, rapports, clôtures |
| Manager de salle | `restaurant.manager` | réservations, affectation des tables, validation |
| Serveur / caissier | `restaurant.server` | prise de commande, service, encaissement |
| Cuisinier | `restaurant.kitchen` | file de commandes en cuisine (écran) |
| Livreur | `restaurant.rider` | tournées de livraison |
| (transversal) | `restaurant.reports` | lecture / rapports pour tous les rôles opérationnels |
| Client (contact CRM) | — | aucun accès back-office (portail public hors périmètre RBAC) |

## 4. Règles d'application

1. **Policies par ressource** dans `App\Modules\RestaurantManager\Policies`
   (une policy par modèle du référentiel), instanciables directement ou via
   le Gate — aucun couplage aux routes.

2. **Mapping policy → permission** (référentiel BC-25) :

   | Policy | Ressource | Permission requise en écriture |
   |---|---|---|
   | `RestaurantBranchPolicy` | `RestaurantBranch` | `restaurant.manage` |
   | `RestaurantCategoryPolicy` | `RestaurantCategory` | `restaurant.manage` |
   | `RestaurantProductPolicy` | `RestaurantProduct` | `restaurant.manage` |
   | `RestaurantSupplierPolicy` | `RestaurantSupplier` | `restaurant.manage` |
   | `RestaurantZonePolicy` | `RestaurantZone` | `restaurant.manager` |
   | `RestaurantMenuPolicy` | `RestaurantMenu` | `restaurant.manager` |
   | (à venir — même mapping) | `RestaurantTable`, `RestaurantHour` | `restaurant.manager` |

3. **Écriture** : `$actor->hasManagerRole(...)` avec les rôles de la matrice
   pour la permission de la policy, **et** `$resource->company_id ===
   $actor->company_id` pour `update` / `delete`.

4. **Cross-tenant — 404 sûr** : `view` / `update` / `delete` d'une ressource
   d'un autre tenant → `false` (fail-closed : 403 côté contrôleur, jamais de
   fuite de données). Les repositories `findForCompany(...)` retournent
   `null` pour une ressource étrangère (404), conformément à la spec §1.3
   règle 2 (cf. `RestaurantIsolationTest`).

5. **Fail-closed** : toute permission inconnue de
   `RestaurantPermissions::requiresManagerRoles()` retourne `[]` → aucun rôle
   n'autorise l'action.

6. Les policies ne modifient ni les routes ni l'openapi : décision purement
   applicative (matrice testée au niveau policy, cf. `RestaurantRbacMatrixTest`).

## 5. Références

- Spec : `docs/specifications/SOLUTION_RESTAURANT_MANAGER.md` §1.2 (personas), §1.3 (principes tenant-safe).
- Manifest : `App\Modules\RestaurantManager\Domain\Manifests\RestaurantManagerManifest` (RESTO-106, #6163).
- Constantes : `App\Modules\RestaurantManager\Domain\Permissions\RestaurantPermissions` (RESTO-306, #6187).
- Test : `api/tests/Feature/Restaurant/RestaurantRbacMatrixTest.php` (instanciation directe des policies).
- Contexte RBAC plateforme : `docs/architecture/RBAC_AUDIT_REPORT.md`.
