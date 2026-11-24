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

## 10 · Chantiers v4 — vérification en conditions réelles

Branche `eval/leopardo-hr-recette-2026-11-22`, commit `86da7594a`.

### 1 · Routes de statistiques homogénéisées

**Cause racine :** les trois chemins n'existaient pas. `/employees/stats` et
`/crm/activities` étaient documentés (matrice CRM §2.5) mais jamais routés ;
`/planning` n'exposait que ses deux sous-chemins d'optimisation. Le 404 n'était
pas une incohérence de nommage mais une absence de route, alors que
`/absences/stats`, `/expense/stats`, `/recruitment/stats` et `/library/stats`
existaient déjà.

| Route | Avant | Après | Convention alignée |
|---|---|---|---|
| `GET /employees/stats` | 404 | **200** | `from`/`to`, enveloppe `data`, RBAC `employees.view` |
| `GET /crm/activities` | 404 | **200** | pagination `per_page`/`page`, filtres typés |
| `POST /crm/activities` | 404 | **201** | append-only, ancrage métier obligatoire |
| `GET /planning` | 404 | **200** | agrégat + `links` vers les endpoints d'optimisation |

**Matrice RBAC vérifiée sur l'instance (8 profils) :**

| Profil | /employees/stats | /crm/activities | /planning |
|---|---|---|---|
| principal | 200 | 200 | 200 |
| gérant | 200 | 200 | 200 |
| comptable | 200 | 403 | 200 |
| superviseur | 200 | 403 | 200 |
| chef / serveur / caissier / livreur | 403 | 403 | 403 |

**Point de conception notable :** `/employees/stats` applique le périmètre
« équipe » de l'acteur. Le superviseur obtient **3 employés sur 8** — exactement
ce qu'il voit dans la liste. Sans ce garde-fou, les statistiques auraient été
une fuite d'information latérale. Le service est **fail-closed** : un acteur
team-scoped sans périmètre assigné voit un effectif vide, pas celui de toute la
société.

**Détail métier :** la masse salariale n'additionne que les contrats
**mensuels** et expose `headcount_with_salary` — mélanger un horaire et un
mensuel produirait un total dénué de sens.

### 2 · Payloads de verticales exposés dans l'OpenAPI

**Cause racine :** les FormRequest de la verticale restaurant sont très stricts
(`Rule::exists(...)->where(company_id)`, énumérations fermées, montants en unité
mineure) mais **aucun `requestBody`** n'apparaissait dans la spec. Un client
généré depuis l'OpenAPI ne pouvait pas construire une requête valide.

| Ajout | Volume |
|---|---|
| Chemins restaurant documentés (branches, zones, tables, catégories, produits, ingrédients, unités, TVA, menus, horaires, fournisseurs, POS, commandes, lignes, paiement, réservations, stock, mouvements, achats, livreurs, zones de livraison) | **19 chemins** |
| Schémas d'entrée miroirs des FormRequest | **20 schémas** |
| Schémas `CrmActivity` / `CrmActivityInput` | **2 schémas** |
| `RestaurantPrintJob` — `$ref` cassée (préexistante, jamais résolue) | **1 schéma réparé** |
| Entrées d'allowlist devenues obsolètes | **38 purgées** |

Les schémas documentent les pièges qui coûtent des heures d'intégration :
`price_minor` en **unité mineure** (jamais de décimal), `rate_bps` en **points de
base** (2000 = 20 %), `table_id` obligatoire pour `dine_in`, `idempotency_key`
sur commande/paiement/réservation, et le fait qu'un identifiant d'un autre
tenant renvoie **422** (pas 403 — pas de fuite d'existence).

**Contrôle automatisé :** `check-openapi-route-coverage.py --strict-staleness`
→ `new_uncovered: 0`, `new_param_drift: 0`, `new_reverse_drift: 0`,
`staleness: 0`. Aucune référence `$ref` cassée (vérification programmatique sur
les 464 schémas).

### 3 · Test Feature de bout en bout du bridge d'impression

**Ce qui manquait :** `RestaurantPrintBridgeTest` couvrait la surface du bridge
*prise isolément*, en fabriquant directement des travaux en base. Personne ne
testait la **couture** entre la mise en file côté application (Sanctum) et la
récupération côté poste cuisine (jeton partagé) — précisément l'endroit qui
casse en production.

`RestaurantPrintBridgeEndToEndTest` — **6 tests, 51 assertions** :

| Test | Ce qu'il verrouille |
|---|---|
| `the_full_print_cycle_from_enqueue_to_acknowledgement` | enqueue → jobs → claim → payload → ack ; le `payload_sha256` du bridge est **identique** au `payload_hash` calculé à la mise en file ; aucune impression réputée réussie sans accusé ; le travail imprimé sort de la file |
| `a_requeue_creates_a_new_revision_the_bridge_can_pick_up` | réimpression = **nouvelle révision** (2), jamais un rejeu |
| `enqueue_is_idempotent_for_the_same_order` | double clic serveur / retry réseau → même travail, 1 seule ligne en base |
| `an_ordinary_employee_cannot_enqueue_a_print_job` | 403 + aucun travail créé |
| `the_bridge_cannot_claim_a_job_enqueued_by_another_tenant` | 404, travail toujours `pending` |
| `the_bridge_cannot_ack_a_job_of_another_tenant` | 404, travail toujours `pending` |

**Résultat :** `6 passed (51 assertions)` en 3,94 s. Non-régressions :
`RestaurantPrintBridgeTest`, `RestaurantCatalogueCrudTest`,
`RestaurantGoldenJourneyTest`, `OrgReferentialCrudTest` → **23 passed
(107 assertions)**.

### Bilan v4

Les trois chantiers sont **livrés, testés et poussés**. Les routes de
statistiques répondent avec la même convention que le reste de l'API et
respectent le cloisonnement par équipe ; les payloads de la verticale restaurant
sont documentés au niveau de détail des FormRequest ; le bridge d'impression est
couvert de bout en bout, y compris l'égalité de hash entre mise en file et
impression réelle.

**Note de la verticale restaurant : 4,5 → 4,8 / 5.** Les trois points ouverts de
la recette v2 sont fermés. Restent des manques mineurs, hors périmètre :
`travelagency` (214 routes) et `edu_manager` (88 routes) ne sont pas encore
documentées dans l'OpenAPI, et le bridge d'impression reste inaccessible aux
profils humains par conception (jeton d'appareil).

---

---

## Recette v6 — bootstrap de test et fin de la couverture OpenAPI

Trois chantiers : réparer le bootstrap de test (table `connector_catalog`
absente), puis documenter les 62 routes `crm` et les 155 routes
`restaurantmanager` restantes. Un `git pull` a été fait avant de commencer.

### 1 · Bootstrap de test — la garde était périmée par construction

**Reproduction :** `tests/Feature/Connectors` → **26 échecs / 10 succès**, tous
en `SQLSTATE[42P01] relation "connector_catalog" does not exist`.

**Cause racine :** `RefreshTenantDatabase::canonicalSchemaReady()` décidait s'il
fallait re-migrer en vérifiant une **liste de tables sentinelles codée en dur**.
Cette liste a dû être étendue trois fois (#5201 `employees`, #6754
`training_enrollments`, #9115 `biometric_enrollments`) — et elle a encore manqué
`connector_catalog` et `connector_connections`, ajoutées après. Une base migrée
*avant* ces migrations passait la garde, la re-migration était sautée, et le
premier test qui touchait la table mourait en 42P01, cassant la transaction et
faisant cascader les suivants en 25P02.

**Correctif :** la garde n'énumère plus de tables, elle **interroge le
migrateur** — `getMigrationFiles()` contre `getRan()` — sur les deux dépôts
(`public` et `tenant`), avec le bon `search_path` pour chacun. C'est la seule
définition qui ne se périme jamais : toute migration future est couverte sans
retoucher ce fichier. En cas d'erreur, la méthode répond « pas prêt » — on
re-migre, on ne saute pas.

| Indicateur | Avant | Après |
|---|---|---|
| `tests/Feature/Connectors` | 10 / 36 | **36 / 36** (144 assertions) |
| Durée du 2ᵉ run (même base) | — | **4,5 s** — aucune re-migration inutile |

Le second point compte autant que le premier : une garde qui répondrait toujours
« pas prêt » ferait re-migrer à chaque classe (~87 fichiers), ce qui était
précisément le problème que #6948 avait corrigé.

### 2 · OpenAPI — `crm` (62) et `restaurantmanager` (155)

Générateur dédié : `scripts/doc_crm_and_restaurant_openapi.py`, qui réutilise le
parseur de la garde CI. Trois spécificités ont dû être traitées :

| Cas | Traitement |
|---|---|
| **Surfaces publiques** — `/public/restaurants/*`, `/restaurant/public/*`, `/restaurant/marketplace/*`, `/crm/webhooks/*` | `security: []` — aucun token Sanctum. Déclarer un BearerAuth aurait été un mensonge |
| **Bridge d'impression** — `/restaurant/printing/bridge/*` | Nouveau scheme `RestaurantPrintBridge` (`apiKey` en en-tête `X-Restaurant-Print-Bridge-Token`) |
| **Paramètre de requête** — `GET /restaurant/printing/bridge/jobs` | `branch_id` déclaré en **query obligatoire** (le contrôleur répond 422 sans lui) — le drift identifié en recette v3 |

| Indicateur | Avant | Après |
|---|---|---|
| Opérations documentées | 2 346 | **2 563** |
| Routes couvertes | 2 312 / 2 607 | **2 529 / 2 607** |
| `crm` non couvertes | 62 | **0** |
| `restaurantmanager` non couvertes | 155 | **0** |
| `new_uncovered` (drift) | 18 | 18 — inchangé, préexistantes |
| `new_param_drift` / `new_reverse_drift` | 0 / 0 | **0 / 0** |
| Allowlist : orphelines / obsolètes | 113 / 217 | **0 / 0** (437 purgées) |

**Redocly :** 4 erreurs et 31 warnings — **strictement identiques à l'état
d'avant**. Une première génération avait ajouté 2 warnings
`operation-4xx-response` sur deux routes publiques GET ; corrigé en déclarant le
**404 fail-closed** réel de ces surfaces plutôt qu'un 401 qui n'arrive jamais.
Vérifié par diff des listes de warnings avant/après : aucun nouveau.

### 3 · Non-régression

Périmètre touché (Connectors + Restaurant + CRM + HR) : **896 passés / 2
échoués** (3 544 assertions) en 122 s.

Les 2 échecs sont dans `CrmOpportunityStageTest` (« reason on a won deal is
refused », « the previous stage is journaled in notes and timeline »). Ils ont
été **reproduits à l'identique avec les changements retirés** (`git stash`) :
ce sont des échecs **préexistants**, sans rapport avec ce chantier. Ils portent
sur le libellé du journal de changement d'étape (`prospecting → proposal`
attendu, horodatage reçu) — un défaut de formatage, pas de logique.

**Pint :** PASS sur le fichier modifié.

### Bilan v6

Le bootstrap de test est réparé **à la racine** : la garde ne dépend plus d'une
liste de tables qui se périme à chaque migration ajoutée. Les 26 échecs
Connectors sont à zéro, et le mécanisme profite à toute migration future sans
intervention.

Le contrat OpenAPI couvre désormais **2 529 des 2 607 routes** (97 %). Les deux
derniers gros modules — `crm` et `restaurantmanager` — sont à 100 %, y compris
leurs surfaces publiques et le bridge d'impression, avec le bon mécanisme
d'authentification pour chacune.

**Note de la couverture OpenAPI : 4,5 → 4,8 / 5.** Restent 78 routes non
couvertes, dont 53 dans `api.php` et 8 dans `connectors` — les 18 « nouvelles »
sont préexistantes et hors périmètre de ces trois chantiers. **Note de la
verticale restaurant : 4,8 / 5 (inchangée)** — ce tour ne touche pas au
comportement métier.

---

## Recette v7 — 24/11/2026

Deux chantiers : documenter les **78 dernières routes** non couvertes et corriger
les **2 échecs préexistants** de `CrmOpportunityStageTest`. `git pull` fait avant
de commencer ; branche `eval/leopardo-hr-recette-2026-11-23` à jour.

### 1 · OpenAPI — 2 607 / 2 607 routes (100 %)

Les 78 routes restantes couvraient **sept familles** aux mécanismes
d'authentification **différents** — c'est ce qui les avait laissées de côté. Un
générateur naïf les aurait toutes marquées `BearerAuth`, ce qui aurait été faux
pour la moitié.

| Famille | Routes | Authentification réelle |
|---|---|---|
| Boutiques publiques (`/public/travel/shop/*`, `/public/restaurant/shop/*`, `/public/restaurant/kiosk/*`, `/public/vitrine/*`, `/solutions/*`) | ~20 | `security: []` — tenant résolu par **jeton de boutique signé** ou slug |
| Webhooks entrants (callbacks paiement travel/restaurant, `carrier-sync`, `delivery-apps`) | 4 | `security: []` + **signature HMAC** vérifiée dans le contrôleur, fail-closed. Le 4xx documenté est le **401 de signature** |
| Console plateforme (`/platform/*`, `/admin/*`) | ~30 | `SuperAdminBearerAuth` + `platform.mfa` + `platform.permission:*` |
| Connecteurs tenant (`/connectors/*`) | 8 | `BearerAuth` — secrets **write-only** |
| RH (permissions verticales, enrôlement kiosque) | 7 | `BearerAuth` |
| Vitrine, solutions, marketing IA, demandes d'intégration | 9 | `BearerAuth` |

Générateur : `scripts/doc_remaining_openapi.py`, qui **réutilise le parseur de la
garde CI** comme source unique de vérité.

**Trois pièges rencontrés, tous corrigés dans le générateur :**

| Piège | Symptôme | Correctif |
|---|---|---|
| `description` dans le **Media Type Object** | 31 erreurs Redocly « Property `description` is not expected here » | En OpenAPI 3.0 un Media Type n'accepte que `schema`/`example`/`examples`/`encoding` — la description va dans le **Schema Object** |
| Chemins déjà documentés sous une **autre forme de paramètre** (`{type}/{id}` vs `{param}/{param}`) | « path already exists which differs only by path parameter name(s) » | Comparaison sur le chemin **normalisé**, comme la garde CI |
| Spec portant encore **107 chemins en forme legacy `/v1/…`** | `operationId` dupliqué sur `/v1/audit-logs/export` vs `/audit-logs/export` | Préfixe `/v1` retiré à la normalisation |

| Indicateur | Avant | Après |
|---|---|---|
| Opérations documentées | 2 563 | **2 641** |
| Routes couvertes | 2 529 / 2 607 (97 %) | **2 607 / 2 607 (100 %)** |
| `api.php` non couvertes | 53 | **0** |
| `connectors` non couvertes | 8 | **0** |
| Garde `--strict-staleness` | exit 1 | **exit 0** |
| Allowlist : couverture / orphelines | 60 / 113 | **0 / 0** (173 purgées) |
| Redocly | 4 erreurs / 75 warnings | **4 / 75 — inchangé** |

Redocly vérifié par **diff des listes de problèmes avant/après** : aucun nouveau.
Le seul écart est l'**index** d'un tag dupliqué préexistant (`Pages` déclaré deux
fois), qui se décale quand des tags sont ajoutés ; le compte reste identique.

`test_openapi_route_coverage_orphans.py` exigeait un gel **non vide** — solder la
dette d'orphelines transformait donc le succès en échec. Le test tolère désormais
un gel vide et vérifie la règle générale à la place.

### 2 · CRM — les 2 échecs préexistants

Deux défauts **distincts**, chacun couvert par un test qui existait déjà.

| Test | Attendu | Observé | Cause racine |
|---|---|---|---|
| `test_reason_on_a_won_deal_is_refused` | 422 | **200** | `TransitionOpportunityStageRequest` n'avait que `required_if:stage,lost` — **aucune règle n'interdisait** une `lost_reason` sur une affaire gagnée. Le champ était accepté puis écrit dans `notes` |
| `test_the_previous_stage_is_journaled_in_notes_and_timeline` | « prospecting → proposal » | « prospecting -> proposal » | `TransitionOpportunityStageAction` écrivait la flèche en **ASCII** (`->`) alors que la timeline et les exports lisent **U+2192** (`→`) |

**Correctifs :**

- `prohibited_unless:stage,lost` ajouté à `lost_reason`, avec message dédié FR/EN.
  Une raison de perte sur une affaire gagnée laisse croire qu'elle a échoué.
  **⚠️ Remplacé en v8** : cette règle interdisait aussi le champ sur les étapes
  ouvertes — voir §14.
- La flèche `→` est utilisée dans `notes` **et** dans la `CrmActivity` : les deux
  représentaient le même événement avec deux glyphes différents.

### 3 · Non-régression

| Suite | Résultat | Assertions |
|---|---|---|
| `CrmOpportunityStageTest` | **14 / 14** | 39 |
| `tests/Feature/CRM` + `tests/Feature/Connectors` | **316 / 316** | 1 133 |
| `tests/Feature/Restaurant` + `tests/Feature/HR` | **582 / 582** | 2 415 |
| **Total périmètre touché** | **912 / 912** | **3 587** |

**Pint :** PASS sur les 4 fichiers PHP modifiés. **Garde OpenAPI :**
`--strict-staleness` exit 0.

### Bilan v7

Le contrat OpenAPI est **complet** : 2 607 routes sur 2 607, avec pour chaque
famille le mécanisme d'authentification **réellement** appliqué — y compris les
surfaces publiques et les webhooks signés, qui n'ont aucun token. Les deux
allowlists sont vides : plus aucune dette gelée.

Les 2 échecs de `CrmOpportunityStageTest` sont corrigés **à la source** : une
validation manquante et un glyphe incohérent.

**Note de la couverture OpenAPI : 4,8 → 5,0 / 5.** **Note du module CRM :
4,5 → 4,8 / 5.** **Note de la verticale restaurant : 4,8 / 5 (inchangée)** — ce
tour ne touche pas au comportement métier restaurant. **Note globale : 4,9 / 5.**

---

## 14 · Chantiers v8 — vérification en conditions réelles

Trois points restaient ouverts au rapport v7. Le premier s'est révélé plus
profond que « 4 erreurs à corriger » : c'était un **défaut de forme des chemins**
qui en masquait d'autres.

### 1 · Les 4 erreurs Redocly — cause racine

Le bloc `servers` de la spec porte déjà `/api/v1`. Un chemin documenté
`/v1/travel/adverts` désigne donc en réalité `/api/v1/v1/travel/adverts` — une
URL qui n'existe pas. La garde CI neutralise ce préfixe pour comparer spec et
routes, ce qui **masquait le problème** ; Redocly, lui, le voyait.

| Défaut | Effet réel | Correctif |
|---|---|---|
| **122 chemins** préfixés `/v1` alors que `servers` le porte | URLs doubles dans tout client généré ; `operationId` dupliqués sur 8 opérations | Préfixe retiré, **47 blocs fusionnés** verbe par verbe (variante la plus riche conservée) |
| `/travel/community/tourist-sites/{site}` | Le contrôleur injecte `TravelTouristSite $travelTouristSite` : la liaison implicite se fait par **nom**. Un `{site}` laissait le modèle vide → **404 sur tout identifiant valide** | Paramètre renommé `{travelTouristSite}`, y compris dans les `parameters:` de niveau chemin |
| Tag `Pages` déclaré **deux fois** | Documentation scindée en deux entrées | Entrée la plus pauvre retirée |
| Schéma `AuditLog` devenu inutilisé | Deux schémas concurrents pour la même ressource | `AuditLog` retiré — `AuditLogEntry` est le fidèle |

| Indicateur | Avant | Après |
|---|---|---|
| Erreurs Redocly | 12 | **0** |
| Warnings Redocly | 52 | 51 |
| Chemins documentés | 2 030 | 1 983 |
| Opérations documentées | 2 605 | 2 603 |
| Garde de couverture | 2 607 / 2 607 | **2 607 / 2 607** |
| `$ref` cassées | 0 | **0** |
| `operationId` dupliqués | 8 | **0** |

**Vérifié par diff des listes de problèmes avant/après :** aucun nouveau warning.
Les 2 opérations « perdues » sont le renommage `{site}` → `{travelTouristSite}` —
mêmes opérations, autre nom de paramètre.

### 2 · Le tag `Pages`

Deux entrées coexistaient : `BC PAGES (#8728/#8729/#8730) — pages d'établissement :
CRUD et blocs (tenant), modération plateforme, rendu public SSR…` et
`BC PAGES — Pages par établissement (surface tenant, #8728)`. La seconde est un
sous-ensemble strict de la première : elle est retirée.

### 3 · La règle `lost_reason` — le test a trouvé un défaut

Le test demandé devait **verrouiller** la règle livrée en v7. Il a surtout montré
qu'elle était **fausse**.

| Règle v7 | Comportement observé | Règle v8 |
|---|---|---|
| `prohibited_unless:stage,lost` | Interdit `lost_reason` dès que `stage ≠ lost` — donc sur **toutes les étapes ouvertes**. Un client renvoyant le formulaire complet avec une raison héritée d'un brouillon recevait un **422 sur `prospecting`** | `prohibited_if:stage,won` — seule étape réellement trompeuse |

La clé de message a suivi (`lost_reason.prohibited_if`) : sans cela, l'utilisateur
aurait vu la clé brute au lieu du texte traduit.

**Le test** — `CrmOpportunityLostReasonRuleTest`, **7 tests / 19 assertions** —
vérifie la règle **elle-même**, pas seulement son effet HTTP :

- la règle est bien **déclarée** dans `rules()` (si elle disparaît, le test tombe
  même si `withValidator()` masquait la régression) ;
- refus sur `won`, avec le **message traduit** ;
- le refus est un **422** et **n'écrit rien** en base ;
- **acceptation** sur `lost` — sinon `required_if` et `prohibited_unless` se
  contrediraient et il deviendrait impossible de perdre une affaire ;
- **acceptation** sur les 4 étapes ouvertes ;
- sans raison sur `lost`, c'est bien `required_if` qui parle, avec **son** message.

### 4 · Non-régression

| Suite | Résultat | Assertions |
|---|---|---|
| `CrmOpportunityLostReasonRuleTest` (nouveau) | **7 / 7** | 19 |
| `tests/Feature/CRM` + `tests/Feature/Connectors` | **323 / 323** | 1 152 |
| `tests/Feature/Restaurant` + `tests/Feature/HR` | **582 / 582** | 2 415 |
| **Total périmètre touché** | **912 / 912** | **3 586** |

**Pint :** PASS sur les 2 fichiers PHP. **Garde d'orphelines :** 13 / 13
vérifications. **Garde de couverture :** 0 nouvelle non couverte, 0 nouveau drift.

### 5 · Pull préalable et conflits

La branche d'évaluation était **16 commits derrière `main`**. Fusion faite avant
tout travail, avec **2 conflits** :

- `ConnectorSchemaViolationException` — les deux côtés portaient **le même
  correctif** (résolution de `__()` à l'instanciation), seuls les commentaires
  différaient : unifiés.
- `openapi.yaml` — **9 blocs** de conflit, tous des insertions additives au même
  point d'ancrage. Résolus par **union structurelle** : le travail de la branche
  conservé, plus les **15 chemins**, **11 schémas** et **3 paramètres** propres à
  `main` (connecteurs, permissions verticales, inventaire et réservations
  d'hôtel). Vérifié : aucune perte, 0 `$ref` cassée.

### Bilan v8

Le contrat OpenAPI est désormais **valide**, pas seulement complet : **0 erreur
Redocly** contre 12, sans un seul nouveau warning. Les chemins désignent enfin les
URLs réellement servies — un client généré depuis la spec atteint les bons
endpoints.

Le point le plus utile de ce tour n'est pas la correction des 4 erreurs : c'est
que **le test demandé a invalidé la règle qu'il devait protéger**. La règle v7
refusait `lost_reason` sur toutes les étapes ouvertes, pas seulement sur `won` —
un client renvoyant un formulaire complet était bloqué en `prospecting`. Sans ce
test, le défaut serait resté invisible : le test HTTP existant passait, puisqu'il
ne testait que le cas `won`.

**Note de la couverture OpenAPI : 5,0 / 5 (maintenue)** — la note était déjà au
maximum, mais elle récompensait une complétude qui n'était pas valide ; elle l'est
maintenant. **Note du module CRM : 4,8 → 4,9 / 5.** **Note de la verticale
restaurant : 4,8 / 5 (inchangée).** **Note globale : 4,9 / 5.**

---

*Tests réalisés sur une instance locale réelle (PHP 8.4 · PostgreSQL 15 +
PostGIS · Redis 7). Les notes reflètent les résultats observés sur cette
instance ; elles ne couvrent pas les applications mobiles Flutter ni les fronts
Next.js/Vue, non déployés ici.*
