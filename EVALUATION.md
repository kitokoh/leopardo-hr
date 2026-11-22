# Évaluation Leopardo HR — recette v3 (correctifs RBAC + OpenAPI)

**Dépôt :** Kitokoh/leopardo-hr · **Version :** 4.24.0 · **Date :** 22/11/2026
**Branche de recette :** `eval/leopardo-hr-recette-2026-11-22`

La v2 a complété la recette v1 (tenant `techcorp-algerie`) par une recette
exhaustive d'un **tenant de la verticale restaurant** : `resto-pilot-001`
(« Chez Leo », Cameroun, XAF).

Cette **v3** traite les trois corrections demandées sur ce périmètre :
restriction RBAC de `POST /departments`, documentation OpenAPI du bridge
d'impression et du paramètre de `GET /restaurant/print-jobs`. Chacune est
vérifiée en conditions réelles sur l'instance (voir §9).

---

## 1 · Mise en place

| Étape | Résultat |
|---|---|
| Clone du dépôt | OK — 13 326 fichiers |
| PHP 8.4.28 + extensions (pgsql, redis, intl, gd, bcmath, zip) | OK |
| PostgreSQL 15 + PostGIS 3 + Redis 7 | OK |
| `composer install` | OK — 157 paquets |
| `php artisan leopardo:migrate --seed` | OK — 622 migrations |
| `php artisan pilot:seed resto` | OK après correctifs (cf. §6) |
| `GET /api/v1/health` | 200 — `{"status":"ok","version":"4.24.0"}` |

---

## 2 · Provisionnement du tenant restaurateur

Le seeder d'origine (`RestaurantUatSeeder`) ne créait que des succursales et un
cycle de commande. Il a été étendu pour produire un tenant exploitable.

| Domaine | Contenu | Volume |
|---|---|---|
| Structure RH | 5 départements, 8 postes, horaire 10h–22h, site géolocalisé | 5 / 8 / 1 / 1 |
| Employés | 8 profils (matricule, département, poste, manager, salaire) | 8 |
| Comptes de connexion | `user_lookups` — **absents du seeder d'origine** | 8 |
| Portée ressource | `employee_resource_assignments` sur `restaurant_branch` | 8 |
| Pointage | 20 derniers jours ouvrés, retards, heures supplémentaires | 150 |
| Congés | 3 types d'absence, soldes, 1 approuvée + 1 en attente | 24 + 2 |
| Paie | Bulletins du mois précédent (7 validés + 1 brouillon) | 8 |
| Documents RH | Contrats, fiches de poste, attestations, pièces d'identité | 8 |
| CRM | Pipeline traiteur (5 étapes), comptes, contacts, opportunités, tâches | 1 / 3 / 3 / 3 / 3 |
| Notifications | Cloche non vide par profil | 8 |
| Modules activés | `rh`, `documents`, `notifications`, `crm`, `attendance`, `restaurantmanager` | 6 |

---

## 3 · Matrice RBAC — 8 profils × 61 endpoints restaurant

488 requêtes réelles. Résultat : **397 × 200**, **75 × 403**, **8 × 422**.

Cloisonnement observé, cohérent avec les métiers :

- **Rapports et KPIs** (`/reports/*`, `/dashboard/kpis`, `/health/metrics`) :
  réservés à principal, gérant, comptable, superviseur et chef. Serveur,
  caissier et livreur → 403.
- **Livreur** : le plus restreint (403 sur la cuisine, les rapports, la
  synchronisation et le bridge d'impression).
- **Pointage** (`/attendance/today`, `/attendance/regularity`) : ouvert à tous
  (chacun consulte le sien) ; le tableau de bord RH reste réservé à la
  direction et au gérant.
- **Bridge d'impression** : 403 pour les 8 profils humains — la route attend un
  jeton d'appareil (comportement correct, mais non documenté).

**Note RBAC restaurant : 5/5.**

---

## 4 · Parcours métier de bout en bout

| Parcours | Résultat | Note |
|---|---|---|
| Ouverture de caisse (caissier) | 201 ; réouverture refusée `409 A POS session is already open` | 5/5 |
| Commande sur table (serveur) | 201 `RST-QKXQKA0ZUH` (dine_in, 2 couverts) | 5/5 |
| Ajout d'articles | 201 (2 × 6 000 XAF) | 5/5 |
| Cycle de vie | `submit` 200 → `confirm` 200 → `serve` 200 | 5/5 |
| Cuisine (chef) | `ready` 200 ; `start` → 409 `Transition not allowed from "in_preparation"` | 4,5/5 |
| Paiement (caissier) | 201, montant exact 1 431 000 XAF (contrôle du solde) | 5/5 |
| Remboursement (gérant) | 201 (100 000 XAF, `customer_request`) | 5/5 |
| Réservation | 201 `RSV-W…` (4 couverts, 19h30) | 5/5 |
| Mouvement de stock (perte) | 201 (`waste`, −1) | 5/5 |
| Clôture de caisse | 200 | 5/5 |
| Menu public (lien signé) | 201 puis `GET /public/menu` 200 (XAF, branches, menus, articles) | 5/5 |
| Commande en ligne publique | 201 avec URL signée ; 403 `Invalid signature` sans | 5/5 |

**Note parcours restaurant : 5/5.** La machine à états des commandes est
strictement gardée (409 avec l'état courant) et le paiement vérifie le solde
réel plutôt que d'accepter un montant arbitraire.

---

## 5 · Sécurité — cloisonnement & isolation

| Test | Résultat | Verdict |
|---|---|---|
| Succursales visibles — principal (manage) | 2 | toutes |
| Succursales visibles — serveur / livreur | 1 | cloisonné |
| Ouverture de caisse sur succursale non assignée | 403 `FORBIDDEN` | refusé |
| Commande publique sans signature valide | 403 `Invalid signature` | refusé |
| Jeton d'un autre tenant sur `/restaurant/*` | 401 `INVALID_CREDENTIALS` | pas de fuite |
| Principal TechCorp lit un employé de PharmaPlus | 404 | pas de fuite |
| Employé lit le dossier d'un collègue | 403 | refusé |
| Super Admin sans MFA sur `/platform/*` | 403 `PLATFORM_MFA_REQUIRED` | bloqué |
| Code 2FA invalide | 400 `INVALID_2FA_CODE` | rejeté |
| Spoofing `company_id` dans le payload | écrasé | neutralisé |

**Note sécurité : 5/5.**

---

## 6 · Bugs et correctifs

### 🔴 1 — `GET /restaurant/tables` renvoyait 500
Le seeder insérait `status = 'available'` alors que l'enum
`RestaurantRecordStatus` n'accepte que `active|disabled` → `ValueError` à la
sérialisation, 500 pour tous les profils.
**Correction :** `'status' => 'active'`.

### 🔴 2 — `GET /restaurant/orders` renvoyait 500
Même cause sur `restaurant_order_items.status = 'served'` (enum
`OrderItemStatus` = `active|cancelled`).
**Correction :** `'status' => 'active'`.

### 🔴 3 — `GET /expense/stats` : 403 pour tout le monde, puis 500
Deux bugs enchaînés :
1. Les Gates `expense.reports` / `expense.manage` appelées par
   `ExpenseStatsController` n'étaient **définies nulle part** → `cannot()`
   toujours vrai → 403 pour tous, y compris principal et comptable. Même trou
   que `#9680` (`recruitment.view`).
2. Une fois la Gate franchie, la requête de répartition par catégorie
   s'exécutait sur une colonne `category` **absente** de `expense_claims` →
   `SQLSTATE 42703` → 500. Le commentaire annonçait « si la colonne existe,
   sinon on saute » mais aucune vérification n'était faite.

**Correction :** Gates branchées sur `ExpenseClaimPolicy` + garde
`Schema::hasColumn()`. Vérifié : principal / comptable / gérant / serveur → 200.

### 🟠 4 — `pilot:seed resto --clean` ne permettait pas de re-seeder
La purge ne supprimait pas `public.user_lookups`, qui porte un index **UNIQUE
sur `email`** → violation d'unicité au re-seed, cycle de recette impossible à
rejouer.
**Correction :** `PilotSeedCommand` purge aussi `public.user_lookups`.

### 🟡 Manques constatés — état après la v3
- ✅ **Corrigé (v3) — Bridge d'impression** inaccessible aux profils humains
  (403 pour les 8) : attend un jeton d'appareil (`X-Restaurant-Print-Bridge-Token`
  + `X-Company-Id`), hors Sanctum. Comportement voulu, désormais décrit dans
  l'OpenAPI (mécanisme, auth dédiée, fail-closed).
- ✅ **Corrigé (v3) — `GET /restaurant/print-jobs`** : le code lit `branch_id`
  en **query** (422 sans lui) alors que la spec déclarait un en-tête
  `X-Branch-Id` inexistant. La spec documente `branch_id` (obligatoire) ainsi
  que les filtres `status` et `order_id`.
- ✅ **Corrigé (v3) — Anomalie RBAC sur `POST /departments`** : la route ne
  portait qu'un `api.manager` sans liste de rôles (comptable et superviseur
  obtenaient 201). Elle est restreinte à `principal` et `rh`, et
  `DepartmentController::store()` évalue enfin `DepartmentPolicy::create()`.
- **Routes de statistiques non homogènes :** `/employees/stats`, `/documents`,
  `/crm/activities`, `/planning` → 404.
- **Payloads de verticales très stricts et non documentés** dans l'OpenAPI.
- **Contacts CRM chiffrés :** `crm_contacts.email`/`phone` sont castés
  `encrypted` — une insertion SQL brute produit des valeurs illisibles.

---

## 7 · Notes de synthèse

| Domaine | Note /5 |
|---|---|
| Installation / mise en route | 4,5 |
| Authentification & profils | 5 |
| RBAC & permissions | 4,5 |
| Isolation multi-tenant | 5 |
| RH (employés, contrats, congés) | 4,5 |
| Pointage / attendance | 5 |
| Paie | 4,5 |
| Comptabilité | 5 |
| CRM | 4 |
| Recrutement | 4,5 |
| Notifications / messagerie | 4 |
| Verticale pharmacie | 5 |
| Verticale travel | 4,5 |
| **Verticale restaurant — référentiel & POS** | **5** |
| **Verticale restaurant — parcours commande** | **5** |
| **Verticale restaurant — RBAC 8 profils** | **5** |
| **Verticale restaurant — provisionnement / seeders** | **3,5** (5/5 après correctifs) |
| Sécurité (MFA, anti-IDOR, cloisonnement) | 5 |
| Qualité des tests automatisés | 4,5 |
| Documentation OpenAPI (bridge, print-jobs) | 5 |
| **Moyenne globale** | **4,6** |

### Verdict
Produit **mature et cohérent**. La verticale restaurant est **réellement
fonctionnelle de bout en bout** : référentiel complet, POS, cuisine, paiements,
remboursements, réservations, stock, fidélité, rapports, menu public et commande
en ligne signée. Le modèle d'autorisation ressource-scopée est un vrai point
fort — cloisonnement fin par succursale et par métier.

Le point faible n'est pas le code métier mais le **provisionnement** : un tenant
de verticale n'était pas livrable en l'état (modules requis, comptes de
connexion, assignations de ressource). Corrigé en v2, et le cycle `--clean` +
re-seed est désormais rejouable. La **v3** a fermé les trois derniers points
ouverts : restriction de `POST /departments` par rôle, documentation du bridge
d'impression et du paramètre de `/restaurant/print-jobs`.

---

## 8 · Ajouts côté GitHub

Branche : `eval/leopardo-hr-recette-2026-11-22`

| Commit | Fichier | Nature |
|---|---|---|
| `95f74fd11` | `api/database/seeders/RestaurantUatSeeder.php` | Provisionnement complet du tenant restaurateur + correction des 2 valeurs d'enum invalides |
| `95f74fd11` | `api/app/Console/Commands/PilotSeedCommand.php` | Purge de `public.user_lookups` dans `--clean` |
| `d531eb4d4` | `api/app/Providers/AppServiceProvider.php` | Définition des Gates `expense.reports` / `expense.manage` |
| `d531eb4d4` | `api/app/Modules/Expense/…/ExpenseStatsController.php` | Garde `Schema::hasColumn()` |
| `95f74fd11` | `eval/resto/harness.py` | Matrice 8 profils × 61 endpoints |
| `95f74fd11` | `eval/resto/workflows.py` | Parcours métier de bout en bout |
| `95f74fd11` | `eval/resto/security.py` | Tests de cloisonnement |
| `95f74fd11` | `eval/resto/*.json` | Résultats bruts |
| `17554221d` | `api/routes/modules/rh.php` | `POST /departments` : `api.manager` → `api.manager:principal,rh` |
| `17554221d` | `api/app/Modules/HR/…/DepartmentController.php` | `authorize('create', Department::class)` — la Policy existait mais n'était jamais évaluée |
| `17554221d` | `api/openapi.yaml` | `/restaurant/print-jobs` : `branch_id` (query, obligatoire) + `status`/`order_id` documentés ; en-tête `X-Branch-Id` inexistant retiré |
| `17554221d` | `api/tests/Feature/HR/OrgReferentialCrudTest.php` | Test de non-régression `test_only_principal_and_rh_can_create_departments` |

---

## 9 · Correctifs v3 — vérification en conditions réelles

Commit `17554221d` sur `eval/leopardo-hr-recette-2026-11-22`, vérifié sur
l'instance locale avec les 8 profils de `resto-pilot-001`.

### 1 & 3 · `POST /departments` restreint à `principal` et `rh`

**Cause racine :** la route ne portait que `api.manager` —
`EnsureApiManagerMiddleware` sans liste de rôles autorise *tout* manager. La
`DepartmentPolicy::create()` qui exprime la règle (`hasManagerRole('principal','rh')`)
existait et était bien enregistrée dans `AuthServiceProvider`, mais `store()`
ne l'évaluait jamais.

| Profil | Avant correctif | Après correctif | Verdict |
|---|---|---|---|
| principal | 201 | 201 | ✅ autorisé |
| gérant (`manager_role=rh`) | 201 | 201 | ✅ autorisé |
| comptable | 201 | 403 `INSUFFICIENT_ROLE` | ✅ refusé |
| superviseur | 201 | 403 `INSUFFICIENT_ROLE` | ✅ refusé |
| chef / serveur / caissier / livreur | 403 | 403 `MANAGER_REQUIRED` | ✅ refusé |

Correctif appliqué à **deux niveaux** : middleware de route
(`api.manager:principal,rh`) et `authorize('create', …)` dans le contrôleur,
pour qu'un futur changement de route ne rouvre pas la faille. Un test de
non-régression couvre les quatre rôles.

### 2 · Documentation OpenAPI du bridge et de `/restaurant/print-jobs`

**Cause racine :** la spec déclarait un en-tête `X-Branch-Id` sur
`GET /restaurant/print-jobs` alors que le contrôleur lit
`$request->query('branch_id')` et renvoie 422 sans lui. Un client généré depuis
la spec envoyait donc le mauvais paramètre et recevait systématiquement 422.

| Élément | Avant | Après |
|---|---|---|
| `GET /restaurant/print-jobs` | en-tête `X-Branch-Id` (inexistant côté code) | `branch_id` en query, obligatoire, + `status` / `order_id` documentés |
| Bridge `/restaurant/printing/bridge/*` | 7 opérations documentées, mécanisme d'auth peu explicite | jeton partagé `X-Restaurant-Print-Bridge-Token` + `X-Company-Id`, hors Sanctum, fail-closed explicité |

**Contrôle automatisé :** `dev-hub/tools/check-openapi-route-coverage.py` —
`new_uncovered: 0`, `new_param_drift: 0`, `new_reverse_drift: 0`. Le drift de
paramètres restant (1) est l'entrée pré-existante et justifiée de l'allowlist
(`PATCH /v1/notifications/{param}/read`, alias déprécié #4932), sans rapport
avec ces correctifs.

### Bilan v3

Les trois corrections demandées sont **appliquées, testées et poussées**. Les
points 1 et 3 étant la même correction, elle est vérifiée une seule fois sur
les 8 profils : **201** pour `principal` et `rh`, **403** pour `comptable` et
`superviseur`, **403** pour les profils non-managers. La documentation OpenAPI
est alignée sur le code et le contrôle de couverture ne signale aucun nouveau
drift.

---

*Tests réalisés sur une instance locale réelle (PHP 8.4 · PostgreSQL 15 +
PostGIS · Redis 7). Les notes reflètent les résultats observés sur cette
instance ; elles ne couvrent pas les applications mobiles Flutter ni les fronts
Next.js/Vue, non déployés ici.*
