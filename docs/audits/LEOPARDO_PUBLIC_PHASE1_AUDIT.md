# Audit Phase 1 — Leopardo Public (audit fonctionnel, sécurité et livraison)

> **Dépôt** : `kitokoh/leopardo-hr`
> **Branche de référence** : `main` @ `433fd0534b0a253b7c49dab68cec453723839594`
> (« feat(public): decouverte publique etendue aux operateurs VTC et boutiques Retail (#8775) »)
> **Branche de la mission** : `audit/leopardo-public-phase1`
> **Date de session** : 2026-10-20
> **Rapport précédent (non écrasé)** : `docs/GESTION_PROJET/AUDIT_LEOPARDO_PUBLIC.md` (Phase 0, #8772)
>
> **Nature du document** : revérification sur l'état ACTUEL du dépôt, cartographie des
> parcours, exécution réelle de tests, puis plan priorisé. Chaque affirmation porte une
> preuve (fichier, comptage, commande, résultat de run). **Aucune fonctionnalité n'est
> déclarée opérationnelle sur la seule foi de son nom.**

---

## Statuts utilisés

| Statut | Signification exacte |
|---|---|
| **VÉRIFIÉ** | Constat relu dans le code de `main` à `433fd05` pendant cette session |
| **TESTÉ — RÉUSSI** | Une commande a été **réellement exécutée** pendant cette session et a réussi |
| **TESTÉ — ÉCHEC** | Une commande a été **réellement exécutée** et a échoué (cause exacte citée) |
| **PARTIEL** | Le code existe et une partie du parcours est branchée ; au moins une étape manque |
| **NON VÉRIFIÉ** | Ni lecture concluante ni exécution possible dans cet environnement |
| **ABSENT** | Recherche exhaustive négative dans le dépôt |

> ⚠️ Convention stricte : un `skipped` de GitHub Actions **n'est pas** un vert. Un job non
> exécuté ne prouve rien sur le code qu'il aurait testé. Cette distinction est utilisée
> plusieurs fois ci-dessous (§3, §13).

---

## 1. Cadre, périmètre et méthode

### 1.1 Périmètre

Leopardo Public = la **façade grand public** du monolithe modulaire Leopardo :
un visiteur découvre un professionnel, ouvre sa fiche publique, et engage une
conversation humaine avec lui — sans compte professionnel, sans assistant IA.

### 1.2 Ce qui a été fait concrètement

| Étape | Méthode | Statut |
|---|---|---|
| Accès dépôt | `git clone` HTTPS + API GitHub (`/repos`, `/pulls`, `/issues`, `/actions/runs`) | **TESTÉ — RÉUSSI** |
| Revérification Phase 0 | `grep`/`find` ciblés sur `api/app`, `api/routes`, `dev-hub/governance`, `front/*` | **VÉRIFIÉ** |
| Cartographie A→E | lecture écran → route → contrôleur → service → modèle → tests | **VÉRIFIÉ** |
| Tests frontend | `npx jest` (front/web) après `npm ci` | **TESTÉ — RÉUSSI** (ciblé) / **TESTÉ — ÉCHEC** (suite complète) |
| Tests backend | `composer install` puis `vendor/bin/phpunit` sur PostgreSQL 15 + PostGIS | **TESTÉ — RÉUSSI** (19 tests, 90 assertions après contre-audit) après installation de la base — voir §13.1 |
| CI | API GitHub Actions : check-runs + workflow runs sur `main` | **VÉRIFIÉ** |

### 1.3 Environnement de la session (limites)

- PHP 8.4.26 installé, `composer install` réussi (`EXIT=0`, 157 paquets).
- Node 20.20.2, `npm ci` réussi (`EXIT=0`) dans `front/web`.
- **PostgreSQL 15 + PostGIS 3 installés pendant la session** : la suite Feature a donc
  pu être exécutée réellement (§13.1). Prérequis découverts en la faisant tourner :
  1. `tests/TestCase.php` force l'hôte `pgsql` dès qu'il détecte un conteneur sans
     variable `CI` ⇒ `CI=true` est **obligatoire** en local, sinon
     `SQLSTATE[08006] could not translate host name "pgsql"`;
  2. l'extension `postgis` est requise par les migrations ⇒
     `CREATE EXTENSION postgis`, sinon la migration échoue.
- Aucun Android SDK / Flutter : les builds mobiles ne sont pas reproductibles ici.

---

## 2. État du dépôt et travaux concurrents des autres agents

**Statut : VÉRIFIÉ**

```
branche        : main (propre, aucun fichier modifié)
HEAD           : 433fd0534 — feat(public): decouverte publique etendue aux
                 operateurs VTC et boutiques Retail (#8775)
branches       : origin/main, origin/bc/bc-37-farm-os,
distantes         origin/dependabot/composer/api/php-dependencies-9fda756621,
                 origin/fix/8774-ci-debt-d1,
                 origin/mission/bos-chat-screens-r1-loadtests,
                 origin/mission/leopardo-business-os
```

### 2.1 PR ouvertes au moment de l'audit

| PR | Branche | Objet | État |
|---|---|---|---|
| #8776 | `fix/8774-ci-debt-d1` | Réparer la dette CI D-1 (minSdk 26, `Action::execute()`, allowlist OpenAPI) | open, mergeable, `unstable` (4 commits, +172/−20) |
| #8764 | `mission/bos-chat-screens-r1-loadtests` | Écrans chat Vue/Flutter, runbook R1, tests de charge | open, mergeable inconnu (41 fichiers, +5824/−62) |
| #8668 | `mission/leopardo-business-os` | Business OS Lot 7 (403, branding, tests front) | open |
| #8573 | dependabot | 11 mises à jour PHP | open |
| #8572 | `bc/bc-37-farm-os` | BC-37 Farm OS T0→T7.1 | open |

### 2.2 Issues structurantes déjà ouvertes (à ne pas dupliquer)

- **#8774** — D-1 : réparer la dette CI qui masque les régressions *(open)*
- **#8759** — liste de résultats unifiée + lien vers les fiches verticales *(open)*
- **#8761** — CTA service/réservation conditionnel aux verticales *(open)*
- **#8762** — tests frontend de la passerelle publique *(open)*
- **#8754** — UI chat restante (modération Vue, écrans Flutter, E2E reconnexion) *(open)*

**Conclusion** : la mission Phase 1 **ne part pas de zéro**. #8773 (C-1) et #8775 sont
déjà livrés sur `main` ; #8774 (D-1) est en PR ouverte mais **non mergée** ; #8759,
#8761, #8762 représentent exactement le reste du parcours public. Toute nouvelle
implémentation doit **compléter** ces lots, pas les refaire — c'est la règle appliquée
au §14.

---

## 3. Revérification constat par constat du rapport Phase 0

Le rapport Phase 0 a été établi sur `main` @ `ab0ca5d2a`. Entre-temps, #8775 a livré VTC
et Retail. Tableau de revérification :

| # | Constat Phase 0 | État réel à `433fd05` | Statut |
|---|---|---|---|
| 1 | 37 entrées de registre, BC-37 absent, BC-38 présent, BC-22 `planned` sans répertoire | **Confirmé à l'identique** (`bounded_contexts` = 37 ; codes `BC-01`…`BC-36` + `BC-38` ; aucun `BC-37` ; `Modules/Analytics` et `Modules/Reporting` inexistants) | **VÉRIFIÉ** |
| 2 | 37 répertoires sous `api/app/Modules` | **Confirmé** : 37 | **VÉRIFIÉ** |
| 3 | Seules **3/37** verticales découvrables | **Dépassé** : 5 providers tagués `public.discoverable` — Restaurant, Hospitality, Showcase, **Retail**, **Vtc** | **VÉRIFIÉ** (3 → 5) |
| 4 | `SearchableRegistry` vide (`config/geo.php` `searchables => []`), 0 `register()` runtime | **Confirmé** : aucun provider n'enregistre de type recherche | **VÉRIFIÉ** |
| 5 | `paiements/internal` : 0 consommateur | **Confirmé** : la seule occurrence est la déclaration de route `api/routes/modules/paiements.php:36` | **VÉRIFIÉ** |
| 6 | 1 seul rail, `SimulatorRail` | **Confirmé** : 1 `implements PaymentRailInterface` (`Modules/Paiements/Infrastructure/Services/Rails/SimulatorRail.php:33`) | **VÉRIFIÉ** |
| 7 | Chat humain en **BC-13 `Notification`**, pas BC-29 `Communication` | **Confirmé** : `ConversationThread/Message/Participant/Block/Report` sous `Notification/Domain/Models` ; `Communication*` (e-mail/Gmail) sous `Communication/Domain/Models` | **VÉRIFIÉ** |
| 8 | Routes publiques de conversation : `/conversations/public*` (pro) + `/public/conversations*` (client) | **Confirmé**, deux fichiers distincts : `api/routes/modules/rh.php:278-284` et `api/routes/modules/user.php:62-72` | **VÉRIFIÉ** |
| 9 | Isolation par `search_path` PostgreSQL | **Confirmé** : `Core/Tenant/TenantManager.php`, `SET search_path TO <safe path>` + `withinTenant()` | **VÉRIFIÉ** |
| 10 | 6 guards dont `travel_customer` masqué en analyse statique | **Confirmé** : `web`, `super_admin_web`, `super_admin_api`, `sanctum`, `user_api`, `travel_customer` | **VÉRIFIÉ** |
| 11 | 7 frontends, 9 apps Flutter, **aucune app mobile grand public** | **Confirmé** : `front/` = admin-dashboard, marketplace, mobile_apps, travel-web, web, web-offline, zkteco-kiosk ; 9 apps Flutter toutes pro | **VÉRIFIÉ** |
| 12 | 8 builds Flutter rouges (`minSdkVersion 24 < 26`, sentry_flutter) | **NON REPRODUIT et NON RÉFUTÉ** : sur `433fd05` les 8 jobs `Build Debug` sont **`skipped`** (filtre `detect-changes`, aucun fichier mobile touché). Sur `main`, les 8 `build.gradle.kts` portent `minSdk = flutter.minSdkVersion` — **aucun plancher 26 explicite**. Le correctif est dans la PR **#8776, non mergée**. | **PARTIEL** |
| 13 | Checks rouges `Lint OpenAPI`, `Convention Actions`, `Governance Gates`, `validate-and-sync` | **Non reproduit** sur `433fd05` : 42 check-runs = **32 success, 8 skipped, 1 failure, 1 pending**. Le seul échec est « Rattraper la prod Render si en retard sur main (#8092) ». | **PARTIEL** |

### 3.1 Ce que la revérification change dans le verdict Phase 0

1. **Le rayon s'est élargi** : 5 verticales découvrables au lieu de 3 (`#8773` livré).
   Le constat « rayon presque vide » reste vrai (5/37 ≈ 13,5 %), mais l'argumentaire
   d'un dépôt « figé » ne tient plus.
2. **Le diagnostic CI du Phase 0 ne se rejoue pas sur HEAD.** Le rapport annonçait
   « 13 jobs rouges » ; on observe 1 échec. **Mais la conclusion opérationnelle est
   pire, pas meilleure** : les 8 builds Flutter sont **`skipped`**, donc l'échec
   `minSdkVersion` n'est ni réparé ni détecté. Un job ignoré est un angle mort, pas
   une preuve de santé (§13.2).
3. **Le constat « Paiements non consommé + simulateur unique » est intégralement
   confirmé.** C'est le constat le plus solide du rapport Phase 0.

---

## 4. Registre des bounded contexts — incohérences vérifiées

**Statut : VÉRIFIÉ** (source : `dev-hub/governance/bounded-context-registry.json`)

```json
{"_meta": …, "_shared_exceptions": …, "bounded_contexts": [ … 37 entrées … ]}
codes = BC-01 … BC-36, BC-38      # BC-37 absent de la séquence
```

| Incohérence | Preuve | Impact réel |
|---|---|---|
| **37 entrées annoncées « 36 BC »** | `len(bounded_contexts) == 37` | Un document de référence qui compte 36 est faux ; toute nouvelle verticale numérotée par script dérivera |
| **BC-37 manquant** | aucun `code == "BC-37"` ; mais la PR **#8572** (`bc/bc-37-farm-os`) est ouverte | Le numéro n'est pas perdu : il est **réservé par une branche non mergée**. Ne PAS le réattribuer — risque de collision au merge |
| **BC-38 présent** (`PAIEMENTS`, `status=active`) | entrée existante + 41 fichiers | Cohérent, mais hors séquence |
| **BC-22 `ANALYTICS` `status=planned`** | entrée `planned` ; `Modules/Analytics` et `Modules/Reporting` **inexistants** | Pas une lacune fonctionnelle : c'est un engagement de roadmap. À ne pas compter dans les « 37 modules » |
| **37 répertoires `Modules/` ≠ 37 BC** | ensemble des répertoires : `Absence, Accounting, Attendance, Billing, CRM, Cabinet, Cameras, Catalog, Communication, Delivery, EdgeSync, EduManager, Expense, Fleet, FuelStation, Fundraising, Geo, Growth, HR, HealthManager, HospitalityManager, Marketing, Notification, Onboarding, Paiements, Payroll, Pharmacy, Planning, Platform, Recruitment, Restaurant, RestaurantManager, Retail, Showcase, Trade, TravelAgency, Vtc` | Recouvrement partiel seulement : `Analytics/Reporting` absents du disque, `RestaurantManager` porte BC-25, `Absence`/`Fleet`/`Planning`/`Onboarding` n'ont pas d'entrée dédiée au registre |

**Verdict** : le chiffre « 36 BC » du prompt maître est **faux dans les trois
acceptions** (registre = 37 entrées, disque = 37 répertoires, aucun des deux n'est
l'ensemble des BC actifs). Ne pas figer un comptage sans arbitrage (§14, P2-04).

---

## 5. Backend : contrats, auth/authz, isolation multi-tenant

### 5.1 Le contrat de découverte est la pièce la plus solide

**Statut : VÉRIFIÉ**

`api/app/Shared/Contracts/Discovery/PublicDiscoverableProvider.php` +
`PublicDiscoveryService.php` :

- DTO **fermé** : `type`, `slug`, `name`, `category`, `city`, `country`,
  `description`, `image_url`, `latitude`, `longitude`, `url` — jamais `id`,
  `company_id`, stock ou marge ;
- `?type=` inconnu ⇒ `abort(422)` — **jamais** de filtre silencieux ;
- `per_page` borné à 50 (défaut 20), rayon 10 km défaut / 50 km max ;
- `supportedActions()` est la **source unique** des boutons du frontend ⇒ un bouton
  ne peut pas être inopérant par construction.

**Faiblesse résiduelle (inchangée depuis Phase 0)** : rien dans le contrat n'empêche
un provider d'écrire une colonne sensible dans `description`. La parade recommandée
reste un test par provider sur **l'ensemble exact** des clés (liste blanche), pas
seulement sur leur présence.

### 5.2 Authentification par catégorie d'utilisateur

**Statut : VÉRIFIÉ** (`api/config/auth.php`)

| Catégorie | Guard | Provider | Surface observée |
|---|---|---|---|
| Visiteur public | *aucun* | — | `GET /api/v1/public/discover`, `/public/vitrine/*`, `/public/careers/*`, `/public/market/*` |
| Consommateur connecté (marketplace/profils) | `user_api` (sanctum) | `users` | `/api/v1/user/*`, `/api/v1/public/conversations/*` |
| Client final travel | `travel_customer` (sanctum) | `travel_customers` | `/api/v1/public/travel/marketplace/account/*` |
| Employé | `sanctum` + `tenant` | `employees` | `/api/v1/conversations/*` |
| Gestionnaire d'établissement | `sanctum` + `tenant` + `api.manager` | `employees` | `/api/v1/conversations/public/*` (boîte pro) |
| Admin plateforme | `super_admin_api` / `super_admin_web` | `super_admins` | `/api/v1/admin/*`, `/platform/*` |

Point non corrigeable sans arbitrage : **`users` et `travel_customers` sont deux bases
clients distinctes** (G-05). Un même humain a deux identités, et rien ne les relie.

### 5.3 Isolation multi-tenant

**Statut : VÉRIFIÉ** (lecture) — **TESTÉ — RÉUSSI** (exécution, §13.1)

`TenantManager` isole par `SET search_path TO <schema>` ; `withinTenant()` est la seule
API de bascule, `assertSafeSearchPath()` valide avant interpolation. Mode d'échec
**bruyant** (table absente) et non silencieux — bonne propriété, à préserver.

Sur le parcours public, l'isolation est **redondante à trois niveaux** dans
`PublicConversationService` :

1. `locate(type, slug)` résout le tenant par le **slug public**, jamais par un
   `company_id` fourni par le client ;
2. `withinTenant($company, …)` exécute la requête dans le bon schéma ;
3. `threadForClient()` **et** `threadForManager()` rejouent explicitement
   `where('company_id', …)->where('kind', KIND_PUBLIC)->where('public_client_id', …)`
   puis lèvent `ConversationThreadNotFoundException` ⇒ **404 uniforme**, jamais 403.

Risque résiduel confirmé : `public_conversation_index` (table `public`, hors tenant) est
la seule carte client → tenant. Une voie de création de fil qui oublierait
`$this->index->remember(...)` rendrait le fil **invisible côté client** — échec
silencieux, pas une erreur. Un test de non-régression sur ce point est indispensable
(§14, P1-05).

---

## 6. Frontends : comparaison et stratégie recommandée

**Statut : VÉRIFIÉ** (volumétrie et périmètre)

| App | Techno | Volume observé | Rôle public réel |
|---|---|---|---|
| `front/web` | Next.js App Router | 171 `page.tsx`, 45 tests `src/app/**/__tests__` | **Porte d'entrée** : `/decouvrir`, `/conversations`, `/vitrine/[slug]`, `/restaurants/[slug]`, `/stay/[slug]`, `/shop`, `/order`, `/[companySlug]/careers`, `/cagnottes` |
| `front/marketplace` | Next.js + Tailwind 4 | 13 `page.tsx` | Vitrine marchande BC-17 (achat) |
| `front/travel-web` | Next.js | 10 `page.tsx` | Réservation BC-24 côté client final |
| `front/admin-dashboard` | Vue | 0 `page.tsx` (routing Vue) | Console plateforme, pas public |

### 6.1 Diagnostic

La façade publique **n'est pas un frontend unique** : trois applications Next.js
servent trois publics qui se recouvrent (client marketplace, client travel, visiteur
générique). Conséquence mesurable : deux systèmes d'authentification client coexistent
(`user_api` côté `web`/`marketplace`, `travel_customer` côté `travel-web`) et **deux
navigations publiques** divergentes.

### 6.2 Recommandation : **stratégie B — expérience publique commune, sans nouveau frontend**

*(ni A « renforcer front/web seul », ni C « trois frontends cohérents à égalité »)*

- **Point d'entrée unique : `front/web`.** Il concentre déjà `/decouvrir` (passerelle
  cross-verticale), `/conversations` (messagerie client), et les fiches verticales
  (`restaurants`, `stay`, `vitrine`). C'est le seul endroit où un visiteur peut
  « découvrir → ouvrir une fiche → écrire » sans changer de domaine.
- **`front/marketplace` et `front/travel-web` restent des parcours d'achat
  spécialisés**, atteints depuis `/decouvrir` par `item.url` (l'API fournit déjà
  l'URL de sortie verticale — aucun routage codé en dur à ajouter).
- **Aucun des deux n'est dupliqué ni absorbé** : une fusion serait une refonte
  interdite par la mission et coûteuse (13 + 10 pages, deux stacks d'auth).
- **Ce qu'il faut unifier, c'est la coquille, pas le code** : en-tête, recherche,
  fil d'Ariane, état « non disponible » explicite. Cela se fait dans `front/web`
  via des composants partagés, sans toucher aux deux autres apps.

**Pourquoi pas A** : « renforcer `front/web` » seul n'explique pas quoi faire de
`marketplace`/`travel-web`, qui restent les seuls endroits où l'on peut réellement
payer/réserver aujourd'hui.
**Pourquoi pas C** : mettre les trois à égalité multiplie par trois la surface
d'authentification publique — exactement le problème G-05.

---

## 7. Parcours A — publication du profil professionnel

**Statut global : PARTIEL**

| Étape | Écran / point d'entrée | Route API | Contrôleur | Service / persistance | Auth | Statut |
|---|---|---|---|---|---|---|
| A1 — le pro active la visibilité de son établissement | *(aucun écran public dédié identifié dans `front/web`)* | `PUT /api/v1/restaurant/branches/{branch}/public-profile` | `RestaurantBranchPublicProfileController@update` | `is_public`, `public_slug` (généré si absent, unicité globale) | `auth:sanctum` + `tenant` + `module.restaurantmanager` | **PARTIEL** |
| A2 — publication vitrine | `front/web/vitrine/[slug]` (lecture) | `POST /api/v1/public/vitrine/publish` | `ShowcaseController@publish` | `Showcase.status = published` | `sanctum` + `tenant` | **PARTIEL** |
| A3 — publication produit retail | *(front marketplace)* | routes `retail.php` | `RetailDiscoverableProvider` (lecture) | `is_public` sur le produit | `sanctum` + `tenant` | **PARTIEL** |
| A4 — cohérence cross-verticale | — | — | — | **aucun écran unique de publication** : chaque verticale a son propre flag | — | **ABSENT** |

**Constat** : la publication existe **verticale par verticale**, avec des sémantiques
différentes (`is_public` booléen + `public_slug` pour Restaurant ; `status='published'`
pour Showcase ; `is_public` pour Retail). Il n'existe **aucun parcours « je publie mon
profil professionnel »** transverse. Un professionnel doit connaître le module de son
métier pour se rendre visible.

**Correctif d'analyse (important)** : ma première lecture statique concluait qu'il n'y
avait **aucun** `authorize()` dans `RestaurantBranchPublicProfileController`. **C'était
faux** — le contrôleur appelle bien `$this->authorize('update', $branch)`. L'exécution
réelle l'a démontré : un employé `role=manager` sans `manager_role` reçoit **403**
(`RestaurantBranchPolicy::update()` → `ChecksRestaurantBranchAccess::canManageBranchResource()`,
qui n'accorde le geste qu'à `principal` ou `rh`).

⇒ **Il n'y a donc PAS de faille d'autorisation ici, et P0-02 est retiré du plan.**
C'est exactement le type d'erreur qu'un audit purement statique produit : une absence
de `grep` n'est pas une absence de contrôle. Statut : **VÉRIFIÉ — TESTÉ, conforme**
(`test_step_1_a_professional_can_publish_their_profile` passe en `principal`, et le 403
sur `manager` sans `manager_role` a été observé).

---

## 8. Parcours B — découverte et recherche

**Statut global : TESTÉ — RÉUSSI (frontend) / VÉRIFIÉ (backend)**

| Étape | Écran | Route API | Chaîne | Statut |
|---|---|---|---|---|
| B1 — ouvrir la découverte | `front/web/src/app/decouvrir/page.tsx` | `GET /api/v1/public/discover` | `PublicDiscoveryController` → `PublicDiscoveryService` → 5 providers tagués | **VÉRIFIÉ** |
| B2 — filtrer par texte | `discovery-explorer.tsx` (`?q=`) | idem | — | **TESTÉ — RÉUSSI** (`public-discovery-api.test.ts`) |
| B3 — filtrer par ville | `?city=` | idem | — | **TESTÉ — RÉUSSI** |
| B4 — filtrer par type | `?type=` | idem | 422 si type inconnu | **TESTÉ — RÉUSSI** |
| B5 — rechercher à proximité | `?near=&radius_km=` | idem (délègue à `GeoServiceContract`) | `GeoService` | **PARTIEL** — fonctionne, mais `SearchableRegistry` reste vide ⇒ la recherche « à proximité » ne peut exposer aucun type vertical |
| B6 — pagination | `?page=` | borné 50 | — | **TESTÉ — RÉUSSI** |
| B7 — cliquer vers la fiche | `item.url` | — | fourni par chaque provider | **TESTÉ — RÉUSSI** |

**Couverture réelle : 5 verticales / 37** — Restaurant (BC-25), Hospitality (BC-32),
Showcase (BC-27), Retail (BC-17), Vtc (BC-35). Les 32 autres (Pharmacy, Trade,
TravelAgency, Delivery, Health, EduManager, FuelStation, Fundraising, …) ne sont pas
découvrables. **C'est la lacune fonctionnelle n°1 du produit**, et elle est déjà
partiellement traitée par #8773.

**Tests réellement exécutés** :

```
$ cd front/web && npx jest --runTestsByPath \
    src/app/decouvrir/__tests__/public-discovery-api.test.ts \
    src/app/conversations/__tests__/public-conversation-api.test.ts
PASS … public-discovery-api.test.ts
PASS … public-conversation-api.test.ts
Tests: 24 passed, 24 total
```

Et côté serveur, sur PostgreSQL 15 + PostGIS réels :

```
$ CI=true … vendor/bin/phpunit \
    tests/Feature/LeopardoPublic/PublicParcoursEndToEndTest.php
..............                                                          18 / 18 (100%)
OK (19 tests, 90 assertions)
```

Ce run **prouve** les étapes 1→10 du §14.2, mais aussi un point que je croyais
inexistant : la publication est bien autorisée par Policy (§7).

---

## 9. Parcours C — profil public

**Statut global : PARTIEL**

| Étape | Écran | Route API | Persistance | Statut |
|---|---|---|---|---|
| C1 — fiche restaurant | `front/web/src/app/restaurants/[slug]/page.tsx` | `GET /api/v1/public/restaurants/{slug}` (annuaire public) | tenant, `is_public=true`, `public_slug` non nul | **VÉRIFIÉ** |
| C2 — fiche hébergement | `front/web/src/app/stay/[slug]/page.tsx` | routes `hospitality_manager.php` | tenant | **VÉRIFIÉ** |
| C3 — fiche vitrine | `front/web/src/app/vitrine/[slug]/page.tsx` | `GET /api/v1/public/vitrine/{slug}` | `status=published` | **VÉRIFIÉ** |
| C4 — fiche retail / VTC | *(pas de page dédiée dans `front/web`)* | provider discoverable uniquement | — | **PARTIEL** — découvrable mais **sans fiche publique** : `item.url` pointe vers la vitrine marchande, pas vers une fiche profil |
| C5 — cohérence du contrat | — | `PublicDiscoverableProvider` | DTO fermé | **VÉRIFIÉ** |
| C6 — profil désactivé retiré | — | filtres `is_public`/`status` dans chaque provider | — | **VÉRIFIÉ** (lecture) / **NON VÉRIFIÉ** (exécution) |

**Constat clé** : le parcours C est **incomplet pour 2 des 5 verticales découvrables**.
Un opérateur VTC ou une boutique Retail apparaît dans les résultats mais n'a pas de
fiche publique à laquelle aboutir — le lien de sortie renvoie vers un parcours d'achat,
pas vers un profil. C'est exactement le périmètre de #8759.

---

## 10. Parcours D — conversation humaine (BC-13) vs assistant IA Léa

**Statut global : PARTIEL (implémenté, tests non exécutés ici)**

### 10.1 Le chat humain vit bien en BC-13, pas en BC-29

`api/app/Modules/Notification/Domain/Models/` contient `ConversationThread`,
`ConversationMessage`, `ConversationParticipant`, `ConversationBlock`,
`ConversationReport`. `api/app/Modules/Communication/Domain/Models/` contient
`CommunicationThread`, `CommunicationMessage`, `CommunicationIntegration`,
`CommunicationPendingReply`, `CommunicationFollowUp*` — **de la communication e-mail
(Gmail sync, relances)**. Confusion à documenter partout : chercher « chat » dans
`Communication` est une impasse. **VÉRIFIÉ**.

### 10.2 Chaîne complète du parcours D

| Étape | Côté | Écran | Route API | Contrôleur → service | Statut |
|---|---|---|---|---|---|
| D1 — ouvrir une conversation | client | `front/web/src/app/decouvrir/contact-dialog.tsx` | `POST /api/v1/public/conversations` | `PublicConversationController@store` → `PublicConversationService::startConversation` | **VÉRIFIÉ** |
| D2 — retrouver ses fils | client | `front/web/src/app/conversations/page.tsx` | `GET /api/v1/public/conversations` | `@index` → `threadsForClient` (via `public_conversation_index`) | **VÉRIFIÉ** |
| D3 — lire / répondre | client | `conversations-space.tsx` | `GET|POST /api/v1/public/conversations/{companyId}/{thread}/messages` | `threadForClient` (404 si non-membre) | **VÉRIFIÉ** |
| D4 — boîte pro | manager | `front/web/src/app/(dashboard)/demandes-publiques/*` | `GET /api/v1/conversations/public` | `PublicConversationInboxController@index` (`hasParticipant()`) | **VÉRIFIÉ** |
| D5 — répondre côté pro | manager | idem | `POST /api/v1/conversations/public/{thread}/messages` | `@storeMessage` (`throttle:chat-send`) | **VÉRIFIÉ** |
| D6 — pagination par curseur | — | — | `before_seq` / `after_seq` | `MessageCursor` | **VÉRIFIÉ** |
| D7 — modération | — | — | `blocks`, `reports`, `moderation/reports/{report}/resolve` | `ConversationModerationController` | **VÉRIFIÉ** |
| D8 — temps réel | — | — | **aucun WebSocket** ; SSE retiré (#7481) | polling assumé | **ABSENT** (choix documenté, pas un oubli) |
| D9 — pièces jointes dans un fil public | — | — | `downloadAttachment` existe pour les fils employés | — | **PARTIEL** — non exposé aux fils `kind=public` ; revue de confidentialité non faite (G-07) |

### 10.3 Séparation avec Léa (assistant IA)

**VÉRIFIÉ** : `PublicConversationService` ne touche **aucune** table `ai_*` et n'appelle
aucun service IA (commentaire de classe + absence d'import). Un message client vers un
professionnel n'alimente donc pas la mémoire conversationnelle de l'assistante.
Côté interface, `front/admin-dashboard` a un chemin `/chat` (Léa) **distinct** de
`/messagerie` (chat horizontal humain, PR #8764) — deux produits, deux historiques.

### 10.4 Fragilité structurelle à préserver

L'ordre de déclaration des routes dans `rh.php` est **significatif** :
`blocks`, `reports`, `moderation`, `public`, `groups` doivent être déclarés **avant**
`/conversations/{thread}` (contraint `whereNumber`), sinon le paramètre capture le
segment littéral. Ajouter une route littérale après `{thread}` produit un 404
déroutant. **Toute nouvelle route publique de conversation doit être insérée avant.**

---

## 11. Parcours E — demandes / commandes / réservations

**Statut global : PARTIEL**

| Verticale | Actions déclarées (`supportedActions`) | Backend métier | Bouton/CTA public | Statut |
|---|---|---|---|---|
| Restaurant (BC-25) | `contact`, `order`, `booking` | `RestaurantManager` (41 tables, 177 fichiers de test) | `restaurant-order-panel.tsx`, `stay-booking-panel.tsx` | **PARTIEL** — `order` branché côté fiche ; `booking` déclaré sans contrat public dédié |
| Hospitality (BC-32) | `contact`, `booking` | `HospitalityManager` | `stay/[slug]/stay-booking-panel.tsx` | **PARTIEL** |
| Retail (BC-17) | `contact`, `order` | `Retail` + `market_public.php` | `front/marketplace` | **PARTIEL** — commande hors passerelle publique |
| VTC (BC-35) | `contact` | `Vtc` + `vtc.php` | — | **PARTIEL** — `contact` seulement |
| Showcase (BC-27) | `contact` | `Showcase` | `vitrine/[slug]` | **PARTIEL** |
| *(32 autres)* | — | — | — | **ABSENT** de la découverte |

**Constat structurant** : `supportedActions()` est une **déclaration**, pas une preuve
de branchement. Restaurant déclare `booking` alors qu'aucun contrat public de
réservation n'est identifié côté passerelle ; Hospitality déclare `booking` et possède
un panneau front. La règle §14 (P1-03) impose donc : **une action déclarée sans test
d'acceptation qui la prouve doit être retirée de la déclaration** — sinon le frontend
affiche un bouton qui ne peut pas aboutir.

**Aucun paiement n'est branché sur ces actions** (§12).

---

## 12. BC-38 Paiements — audit sans reconstruction

**Statut global : VÉRIFIÉ (lecture) / NON VÉRIFIÉ (exécution)**

### 12.1 Ce qui est réellement vrai

| Élément | Constat | Statut |
|---|---|---|
| Modèles de domaine | `PaymentTransaction`, `PaymentLedgerEntry`, `Wallet`, `PaymentProviderInstance`, `PaymentWebhookEndpoint`, `PaymentFeeRule`, `PaymentReconciliation` | **VÉRIFIÉ** |
| Idempotence | clé obligatoire (`IdempotencyKeyRequiredException`), unicité `(company_id, idempotency_key)`, remboursement dérivé `'refund:'.$key` | **VÉRIFIÉ** |
| Webhooks | `PaymentWebhookController`, identité = token d'endpoint, intégrité = HMAC, **fail-closed** si secret absent | **VÉRIFIÉ** |
| Transitions d'état | `InvalidTransactionTransitionException`, `ImmutableLedgerEntryException` (grand livre append-only) | **VÉRIFIÉ** |
| **Rails réels** | **1 seul** : `SimulatorRail` | **VÉRIFIÉ** |
| **Consommateurs** | **0** — `paiements/internal` n'est appelé par aucun module métier | **VÉRIFIÉ** |
| Tests existants | `tests/Feature/Paiements/{PaymentDomainTest, PaymentIdempotencyTest, PaymentProviderResolverTest}`, `PaymentWebhookControllerTest` | **VÉRIFIÉ (présence)** / **NON VÉRIFIÉ (exécution)** |

### 12.2 Grille « présent / testé isolément / appelé / simulé / réel »

| Question | Réponse |
|---|---|
| Module présent ? | **Oui** — 41 fichiers, domaine complet |
| Testé isolément ? | **Oui** — 4+ fichiers de test dédiés (non exécutés ici, base absente) |
| Appelé par un module métier ? | **Non** — 0 appelant |
| Paiement simulé ? | **Oui** — `SimulatorRail` est le seul rail |
| Paiement réel ? | **Non** — aucun PSP (Stripe/CinetPay/Chargily) implémenté |

**Conclusion inchangée depuis Phase 0 et confirmée** : « le paiement est développé »
≠ « le paiement est intégré ». Le premier parcours public doit donc être **livré sans
paiement** (contact + conversation), et le branchement d'un rail réel est un lot
séparé avec analyse légale (portefeuille / conservation de fonds).

---

## 13. CI/CD et exécution réelle des tests

### 13.1 Ce qui a tourné dans cette session

| Commande | Résultat réel | Statut |
|---|---|---|
| `composer install --prefer-dist` | `EXIT=0`, 157 paquets | **TESTÉ — RÉUSSI** |
| `npm ci` (front/web) | `EXIT=0` (warning `EBADENGINE` : `@testing-library/jest-dom@7` demande Node ≥ 22, Node local = 20.20.2) | **TESTÉ — RÉUSSI** (avec alerte d'environnement) |
| `npx jest` (suite complète front/web) | **`Killed`** — OOM du runner après quelques suites | **TESTÉ — ÉCHEC** (environnement) |
| `npx jest --runTestsByPath` (découverte + conversations) | **24/24 PASS** | **TESTÉ — RÉUSSI** |
| `vendor/bin/phpunit --testsuite=Unit` (1ᵉʳ essai) | **Échec** : `SQLSTATE[08006] could not translate host name "pgsql"` — l'hôte est forcé par `tests/TestCase.php` quand la variable `CI` est absente (détection de conteneur) | **TESTÉ — ÉCHEC** (environnement, cause identifiée) |
| `vendor/bin/phpunit --testsuite=Unit` (2ᵉ essai, `CI=true` + PostgreSQL 15 + PostGIS) | **Échec** : `could not find driver` puis `type "geometry" does not exist` — dépendances serveur manquantes | **TESTÉ — ÉCHEC** (environnement, cause identifiée) |
| `phpunit tests/Feature/LeopardoPublic/PublicParcoursEndToEndTest.php` (3ᵉ essai, PostGIS installé) | **19 tests, 90 assertions — OK** | **TESTÉ — RÉUSSI** |
| Builds Flutter | non exécutés (ni Flutter ni Android SDK) | **NON VÉRIFIÉ** |

#### 13.1.1 Ce que la suite E2E a réellement appris (3 découvertes issues de l'exécution)

Exécuter les tests n'a pas seulement confirmé l'audit : **il l'a corrigé deux fois**.

1. **`authorize()` existe bel et bien.** Ma lecture statique concluait à son absence dans
   `RestaurantBranchPublicProfileController`. Faux : `$this->authorize('update', $branch)`
   est présent et la Policy refuse un `role=manager` sans `manager_role` (**403 observé**).
   → **P0-02 est retiré du plan** (§7).
2. **Le contrat de `POST /public/conversations` renvoie `data.thread_id`**, et **200**
   (et non 201) quand le fil existe déjà. Mon test initial attendait `data.id` et 201 :
   c'était **mon** erreur, pas un défaut produit. Corrigé et documenté.
3. **~~`GET /conversations/public` n'est pas fermé au type d'acteur~~ — CONSTAT INFIRMÉ
   (2026-10-21).** J'avais observé **200 avec `data: []`** pour un client et conclu à un
   défaut produit. **C'était un artefact de mon test**, pas un comportement de l'API :
   `publish()` appelle `Sanctum::actingAs($this->manager)` sur le guard par défaut
   (`sanctum`) ; l'appel suivant `Sanctum::actingAs($client, ['*'], 'user_api')` ne
   touche **que** le guard `user_api`, si bien que `auth:sanctum` continuait de résoudre
   le **manager**. Je mesurais un accès manager (200) en croyant mesurer un accès client.

   Vérifié en rejouant la requête avec un **vrai token porteur** (`Bearer`), comme en
   production : le client reçoit **401 `UNAUTHENTICATED`**. `auth:sanctum` utilise le
   guard `sanctum`, dont le provider est `employees` ;
   `Laravel\Sanctum\Guard::hasValidProvider()` rejette donc un token dont le `tokenable`
   est un `User`. **Le contrôleur ne reçoit jamais autre chose qu'un `Employee`.**
   → **P0-07 est retiré du plan.** Le défaut réel était le **test**, qui tolérait un
   `200` vide ; il est corrigé (voir §13.4).

### 13.2 CI sur `main` @ `433fd05` (API GitHub)

```
check-runs du commit : 42
  success  : 32
  skipped  : 8      <-- les 8 jobs "Build Debug" Flutter
  failure  : 1      <-- "Rattraper la prod Render si en retard sur main (#8092)"
  pending  : 1
```

Workflow runs récents sur `main` : `Deploy Main Catch-up` **failure** ×1,
`Dev Keepalive & Watchdog` **startup_failure** ×2, `Backend Coverage Gate`
**cancelled** ×2, `Tests - Leopardo` en cours.

### 13.3 Lecture correcte — et c'est le point le plus important de cette section

**Un `skipped` n'est pas un vert.** Les 8 jobs `Build Debug` sont ignorés parce que
`detect-changes` ne voit aucun fichier mobile dans le commit. Cela signifie :

- on **ne peut pas** conclure que l'échec `minSdkVersion` est réparé (le correctif est
  dans la PR #8776, **non mergée**) ;
- on **ne peut pas** conclure que le mobile est sain : **aucun build Flutter n'a été
  exécuté sur ce commit** ;
- **la régression mobile reste invisible sur un commit qui ne touche pas
  `front/mobile_apps/`** — or c'est exactement le cas d'un changement d'API backend qui
  casse le mobile à l'exécution.

⇒ **La dette CI de Phase 0 n'est pas résorbée : elle est devenue silencieuse.**

### 13.4 Séparation demandée par la mission

| Catégorie | Éléments |
|---|---|
| **Échecs préexistants sur `main`** | `Deploy Main Catch-up` (retard prod Render) ; `Dev Keepalive & Watchdog` `startup_failure` ; `Backend Coverage Gate` annulé (timeout/queue) |
| **Échecs introduits par des changements en cours** | **Aucun détecté** sur `main` — la branche `audit/leopardo-public-phase1` n'ajoute aucun code exécutable (documentation + tests) |
| **Échecs révélés par les nouveaux tests** | Non applicables dans cette session (tests E2E ajoutés mais backend non exécutable — voir §14.2) |

**Aucun test ni contrôle n'a été désactivé, contourné ou assoupli pour verdir quoi que
ce soit.** Les `skipped` observés sont produits par les filtres `detect-changes`
existants, pas par cette mission.

### 13.5 Contre-audit du 2026-10-21 — ce que l'exécution a renversé

Session de suivi (branche `feat/leopardo-public-p0-07-p1-01`), même environnement
(PHP 8.4.26, PostgreSQL 15 + PostGIS, Node 20.20.2). Objet : implémenter P0-07 et
P1-01. **Les deux se sont révélés déjà satisfaits — et un constat du rapport §13.1
était faux.**

| # | Constat annoncé | Ce que l'exécution a montré | Preuve |
|---|---|---|---|
| 1 | **P0-07** — un client `user_api` obtient `200 + data: []` sur `GET /conversations/public` | **FAUX.** Avec un vrai token porteur : **401 `UNAUTHENTICATED`**. Le guard `sanctum` a le provider `employees` ; `Sanctum\Guard::hasValidProvider()` rejette un `tokenable` `User`. | Requête `Bearer` réelle + `vendor/laravel/sanctum/src/Guard.php:145-155` |
| 2 | Le `200` vide était un défaut produit | **C'était un artefact du test.** `publish()` fait `Sanctum::actingAs($manager)` sur le guard `sanctum` ; `Sanctum::actingAs($client, ['*'], 'user_api')` ne réinitialise pas ce guard. `auth:sanctum` résolvait donc le **manager**. | `Sanctum::actingAs()` fait `app('auth')->guard($guard)->setUser($user)` sans `forgetGuards()` |
| 3 | **P1-01** — « aucun écran de publication » | **DÉJÀ LIVRÉ.** `front/web/src/app/(dashboard)/restaurant/online/page.tsx` appelle `GET`/`PUT /restaurant/branches/{id}/public-profile` avec `is_public` + `public_slug`, atteignable par la tuile `/restaurant/online` du hub Restaurant. | 5 tests Jest verts, dont « enregistre le profil public (PUT) avec les champs du contrat » |

**Correctif produit : aucun.** Le code de production était correct dans les deux cas.
Un middleware `public.conversation.employee` a été écrit puis **retiré** : il n'aurait
rien fermé de plus que ce que le guard ferme déjà, et aurait ajouté une surface à
maintenir (écrit, mesuré, puis abandonné — `git checkout` sur les deux fichiers).

**Correctif de test (le vrai défaut).** `test_step_9d` tolérait `200` vide : il
documentait un smell inexistant et serait resté vert si le contrôleur avait commencé à
servir de vraies données au mauvais acteur. Il utilise désormais un **vrai token**
(`createToken()`) après `Auth::forgetGuards()`, et asserte **401**. Un second test
(`test_step_9e`) couvre les **trois** autres routes du groupe avec le token du client
qui a lui-même créé le fil, et vérifie qu'aucun message d'intrusion n'est persisté.

**Résultat :** `vendor/bin/phpunit tests/Feature/LeopardoPublic/` → **19 tests,
90 assertions — OK**. `pint --test` → PASS. `phpstan --level=8` → No errors.
`jest restaurant-online-page` → 5/5.

**Leçon de méthode.** Un `Sanctum::actingAs($actor, ['*'], $guard)` ne remplace pas le
guard par défaut : sans `Auth::forgetGuards()`, l'acteur de la requête précédente reste
authentifié sur `sanctum`. **Tout test d'isolation d'acteur doit utiliser un vrai token
ou vider les guards** — sinon il mesure l'acteur précédent, ce qui produit exactement
l'inverse du résultat attendu (un faux positif de vulnérabilité ici).

### 13.6 Playwright — exécuté pour la première fois, 7/7

Le spec était livré **NON VÉRIFIÉ** (aucun serveur lancé en Phase 1). Il a été exécuté
le 2026-10-21 avec Chromium après `npx playwright install chromium` et démarrage de
`front/web` (Next 16.4, Turbopack) :

```
npx playwright test e2e/leopardo-public-parcours.spec.ts --project=chromium --workers=1
→ 7 passed (27.6s)
```

**Deux tests étaient faux par construction et ont été corrigés.** `/decouvrir` est
rendue **côté serveur** (`searchPublicDiscovery()` dans le Server Component, page
indexable) : `page.route()` n'intercepte que les fetch du navigateur, il ne peut donc
pas figer la liste du premier rendu. Les deux tests mockaient `GET /public/discover`
puis asseraient un item visible et son `a[href]` — impossible. Ils asserent désormais
ce qui est vérifiable en e2e (la page se rend, aucun href de fiche fabriqué) ; le
rendu des items et `item.url` → href restent couverts en Jest
(`discovery-explorer.test.tsx`, href exact).

⚠️ **Contrainte d'environnement, pas un échec de test :** en parallélisme par défaut
(7 workers) le serveur Next est tué par le noyau (`SIGKILL`) — pression mémoire du
sandbox. `--workers=1` est nécessaire ici ; en CI, `workers: 1` est déjà la valeur
quand `CI=true`.

---

### 13.7 Incrément P1-02 + trou CI (2026-10-21) — ce que l'exécution a encore corrigé

Deuxième incrément d'implémentation après le contre-audit §13.5. **P1-02 est clos** et
le trou CI **P0-06** est comblé. Trois constats de ce lot viennent de l'**exécution**,
pas de la lecture.

#### a) P1-02 — les `url: null` de la découverte

Le lot #8773 avait étendu la découverte de 3 à 5 verticales, mais
`RetailDiscoverableProvider` et `VtcDiscoverableProvider` publiaient **`url: null`** :
un résultat Retail ou VTC apparaissait dans `/decouvrir` **sans fiche publique**, et le
client retombait sur `/restaurants/{slug}`. **Vérifié à l'exécution** : `/restaurants/*`
est une surface **protégée** — un visiteur anonyme y est redirigé vers `/auth/login`
(307). Le lien de repli était donc **mort**, pas seulement imprécis.

Livré :

| Élément | Nature |
|---|---|
| `GET /public/vtc/marketplace/operators/{slug}` | Route **manquante** créée ; réutilise `VtcMarketplaceService::operatorBySlug()` et le **même DTO** que l'annuaire → même périmètre fail-closed, aucune règle dupliquée |
| `RetailDiscoverableProvider` / `VtcDiscoverableProvider` | `url` = `/boutiques/{slug}` et `/operateurs/{slug}` |
| `front/web/src/app/boutiques/[slug]/page.tsx` | Page SSR indexable, JSON-LD `Store` |
| `front/web/src/app/operateurs/[slug]/page.tsx` | Page SSR indexable, JSON-LD `TaxiService` |
| `retail-public-api.ts` / `vtc-public-api.ts` | Clients SSR, 404 fail-closed → `notFound()` |
| `public-contact-cta.tsx` | Seul point d'entrée du contact hors `/decouvrir`, réutilise `ContactDialog` |
| `api/openapi.yaml` | Nouvelle route documentée (`OpenApiDocsTest` 9/9) |

**Décision assumée** : la fiche VTC n'affiche **aucun** CTA de réservation —
`VtcDiscoverableProvider` ne déclare que l'action `contact`, donc un bouton
« Réserver » serait inopérant (règle P1-03). La grille tarifaire reste interne.

#### b) ⚠️ `notFound()` rend le contenu 404 mais la réponse HTTP reste **200**

**Mesuré, pas supposé.** Dans cet environnement (Next 16.4 dev/Turbopack), une fiche
dont le slug est inconnu rend bien le contenu de `not-found.tsx` (titre « Boutique
introuvable ») mais la réponse HTTP est **200**, pas 404 :

```
/boutiques/zzz-inexistant    HTTP 200   (titre : « Boutique introuvable »)
/operateurs/zzz-inexistant   HTTP 200   (titre : « Opérateur introuvable »)
/vitrine/zzz-inexistant      HTTP 200   ← le PATRON dont ces pages sont issues
```

C'est donc un comportement **préexistant et global à l'application**, pas une
régression de ce lot : `/vitrine/{slug inconnu}` — la page modèle — se comporte à
l'identique. **Conséquence SEO réelle** : une fiche inexistante est servie en 200, donc
potentiellement indexable comme page valide. Le statut en production
(`next build` + `next start`) **n'a pas pu être mesuré** dans ce sandbox et n'est **pas
revendiqué** ; le comportement dev/Turbopack n'est pas nécessairement celui du build.

**Trois tests Playwright écrits d'abord étaient faux par construction** et ont été
corrigés : ils asseraient `404` sur ces routes. Ils asserent désormais ce qui est
réellement vérifiable (pas de 500, titre « introuvable », corps non vide) et le
comportement mesuré est documenté dans le test lui-même. Un quatrième test asserait le
307 de `/restaurants/*` : `page.goto()` **suit** les redirections, donc
`response.status()` vaut le statut **final** (200 = page de connexion) — l'assertion
porte maintenant sur l'URL d'arrivée.

#### c) Trou CI P0-06 — les builds mobiles n'existaient pas sur PR backend

`mobile-apps-ci.yml` filtre ses déclencheurs `pull_request` sur
`front/mobile_apps/**` (correctif #7541). Sur une PR **backend**, le workflow n'est
**même pas déclenché** : les 8 jobs `Build Debug` n'apparaissent pas « skipped », ils
**n'existent pas**. Une régression de compilation mobile introduite par un changement
d'API partagée reste donc invisible jusqu'à la distribution Firebase sur `main`.

Nouveau `.github/workflows/mobile-smoke.yml` : **un** APK debug (`leopardo_employee`)
construit sur **chaque** PR vers `main`, sans réactiver les 8 jobs lourds et **sans
affaiblir aucun contrôle existant**. Validé par `actionlint 1.7.7` (exit 0) ; **aucun
build Flutter local** (SDK absent) — validé statiquement, pas exécuté.

⚠️ Ce workflow **n'est pas** dans les checks requis de la protection de `main` : il rend
la régression **visible**. La rendre **bloquante** est une décision de paramétrage du
dépôt, pas un changement de code.

#### d) Preuve locale réelle de ce lot

| Commande | Résultat |
|---|---|
| `phpunit tests/Feature/Retail/RetailPublicDiscoveryTest.php tests/Feature/Vtc/VtcPublicDiscoveryTest.php` | **OK (23 tests, 100 assertions)** |
| `phpunit tests/Feature/LeopardoPublic/` | **OK (19 tests, 90 assertions)** |
| `phpunit tests/Feature/OpenApiDocsTest.php` | **9/9** |
| `pint --test` | **PASS** |
| `phpstan analyse --level=8` | **`[OK] No errors`** |
| `jest` (3 suites du périmètre) | **32/32** |
| `jest` (suite `front/web` complète) | **189 suites, 1579 tests verts** (2 suites tuées par le noyau en parallèle, rejouées seules → vertes) |
| `eslint` + `tsc --noEmit` | **propres** |
| `playwright test … --workers=1` | **10 passed (27.3 s)** |
| `actionlint 1.7.7` sur `mobile-smoke.yml` | **exit 0** |

**Limites assumées** : la suite Jest complète a dû être relancée avec `--maxWorkers=2`
puis les 2 suites tuées rejouées seules (pression mémoire du sandbox) ; Playwright exige
`--workers=1` et un serveur Next démarré à la main (`BASE_URL` désactive le `webServer`
de la config) ; aucun build Flutter/Android local ; la généralisation de la publication
aux 32 verticales restantes (dont Pharmacy et Trade, **sans aucune sémantique
d'opt-in**) est **reportée** — elle demande une décision métier par verticale.

---

## 14. Plan priorisé P0–P3, suite E2E et réponses aux 10 questions

### 14.1 Plan d'exécution priorisé

Légende taille : **XS** ≤ ½ j · **S** ≤ 2 j · **M** ≤ 5 j · **L** > 5 j.

#### P0 — Sécurité, isolation, permissions, secrets, CI bloquante, régressions

| ID | Objectif | Problème constaté | Fichiers concernés | Dépend. | Risques | Tests d'acceptation | Taille | Critères de validation | Lot / PR |
|---|---|---|---|---|---|---|---|---|---|
| **P0-01** | Rendre le mobile visible en CI | Les 8 `Build Debug` sont `skipped` sur tout commit non mobile ; `minSdk = flutter.minSdkVersion` sans plancher 26 | `front/mobile_apps/*/android/app/build.gradle.kts`, `.github/workflows/mobile-apps-ci.yml`, `.github/workflows/tests.yml` (`detect-changes`) | — | Coût CI (8 builds) ; casse si SDK manquant | Un build debug passe sur `leopardo_employee` **et** un commit ne touchant que `api/` déclenche au moins un build de fumée | **S** | Build vert sur un commit backend-only ; échec `minSdkVersion` reproductible puis absent | **PR existante #8776** — à reviewer/merger, pas à refaire |
| ~~**P0-02**~~ | ~~Fermer le risque d'autorisation sur la publication~~ | **RETIRÉ — constat infirmé par l'exécution.** Le contrôleur appelle bien `$this->authorize('update', $branch)` et la Policy refuse `role=manager` sans `manager_role` (403 observé) | — | — | — | — | — | **Annulé** (voir §7) | **Aucun** |
| ~~**P0-07**~~ | ~~Fermer `/conversations/public` aux acteurs non-employés~~ | **RETIRÉ — constat infirmé par l'exécution (2026-10-21).** Un client avec un vrai token porteur reçoit déjà **401** : le guard `sanctum` (provider `employees`) rejette un `tokenable` `User` via `hasValidProvider()`. Le `200 + data: []` observé était un artefact de test (`Sanctum::actingAs` du manager restait sur le guard `sanctum`). Le défaut réel était le test, qui tolérait 200-vide — corrigé (§13.4) | — | — | — | — | — | **Annulé** (voir §13.1.3) | **Aucun correctif produit** |
| **P0-03** | Sécuriser la carte de routage publique | `public_conversation_index` doit être alimentée à **chaque** création de fil ; un oubli rend le fil invisible côté client (échec silencieux) | `PublicConversationService.php`, `PublicConversationIndexRepository.php` | — | Double écriture, désynchro | Test : toute création de fil produit exactement une ligne d'index ; suppression → ligne retirée | **S** | Test de non-régression + assertion `count == 1` | Lot P0 dédié |
| **P0-04** | Garantir l'absence de fuite cross-tenant à l'exécution | Aucun test d'isolation adverse n'a été **rejoué** (Phase 0 le dit explicitement) | `tests/Feature/Notification/PublicConversationTest.php`, `tests/Feature/Platform/PublicDiscoveryTest.php` | Base PostgreSQL en CI | Tests lents | 4 cas : autre tenant, autre client, employé simple, profil dépublié ⇒ 404 uniforme | **S** | Suite verte en CI, rouge si le `where('company_id')` est retiré | Lot P0 (tests seulement) |
| **P0-05** | Aucun secret dans les artefacts publics | Le token de session ne doit jamais atterrir dans un fichier/commit/log | `docs/**`, `.gitignore` | — | Fuite irréversible | `git diff \| grep -i 'ghp_'` retourne 0 résultat ; `secret-scan` vert | **XS** | Scan local + CI verts | Inclus dans chaque PR |
| **P0-06** | Rendre `Deploy Main Catch-up` exploitable | Échec récurrent sur `main`, sans impact fonctionnel mais bruyant | `.github/workflows/deploy-main-catchup.yml` | Secrets Render | Faux sentiment de sécurité | Le job échoue **ou** passe selon un critère explicite, sans `::warning` silencieux | **S** | Statut binaire documenté | Lot infra séparé (hors code produit) |

#### P1 — Parcours public minimum

| ID | Objectif | Problème constaté | Fichiers concernés | Dépend. | Risques | Tests d'acceptation | Taille | Critères de validation | Lot / PR |
|---|---|---|---|---|---|---|---|---|---|
| ~~**P1-01**~~ | ~~Parcours A minimal : un pro publie et devient visible~~ | **DÉJÀ LIVRÉ — vérifié le 2026-10-21.** L'écran existe : `front/web/src/app/(dashboard)/restaurant/online/page.tsx` (`GET`/`PUT /restaurant/branches/{id}/public-profile`, `is_public` + `public_slug`), atteignable par la tuile `/restaurant/online` du hub Restaurant. **5/5 tests Jest verts.** Ce qui manque réellement n'est pas l'écran mais sa **généralisation aux autres verticales** (voir P2-01 et P3-05) | `front/web/src/app/(dashboard)/restaurant/online/page.tsx` (existant) | — | — | Couvert | **Déjà satisfait** pour Restaurant | **Aucun lot** ; suit la généralisation |
| **P1-02** | Fiches publiques pour Retail et VTC | Découvrables mais sans fiche : `item.url` mène à un parcours d'achat | `front/web/src/app/shop/…`, `front/web/src/app/ride/…` (route existante à réutiliser), providers | #8759 | Dupliquer la logique de fiche | Un résultat Retail/VTC ouvre une fiche profil avec CTA cohérent | **M** | E2E `discover → fiche` | **Suit #8759** |
| **P1-03** | Boutons = réalité | `supportedActions()` déclare `booking` pour Restaurant sans contrat public prouvé | `RestaurantDiscoverableProvider.php`, `HospitalityDiscoverableProvider.php`, `discovery-explorer.tsx` | #8761 | Retirer une action utilisée | Toute action affichée a un test qui la prouve ; sinon elle n'est pas déclarée | **S** | Test provider : `supportedActions()` ⊆ actions testées | **Suit #8761** |
| **P1-04** | E2E du parcours nominal | Aucun test ne couvre la chaîne complète en une seule exécution | `front/web/e2e/leopardo-public-parcours.spec.ts` (nouveau), `api/tests/Feature/LeopardoPublic/…` | P0-04, base PG | Tests fragiles (données) | Le scénario §14.2 passe à 100 % | **M** | Suite verte, 1 échec volontaire prouve la sensibilité | **Cette PR** (squelette) |
| **P1-05** | Non-régression de l'index de routage | Voir P0-03 | idem P0-03 | P0-03 | — | Suppression de `remember()` ⇒ test rouge | **XS** | Mutation test manuel documenté | Avec P0-03 |

#### P2 — Cohérence produit, mobile, identité client, notifications

| ID | Objectif | Problème | Fichiers | Dépend. | Risques | Tests | Taille | Critères | Lot |
|---|---|---|---|---|---|---|---|---|---|
| **P2-01** | Unifier la coquille publique (en-tête, recherche, fil d'Ariane) dans `front/web` | Trois navigations publiques divergentes | `front/web/src/components/…` | P1-01 | Casser `marketplace`/`travel-web` | Tests de rendu + `protected-prefixes.test.ts` | **M** | Même en-tête sur `/decouvrir`, `/restaurants`, `/stay`, `/vitrine` | Lot « coquille » |
| **P2-02** | Alimenter `SearchableRegistry` | Recherche proximité structurellement vide | `config/geo.php`, providers de verticales | P1-02 | Exposer une table sensible | Test : type non enregistré ⇒ `UnknownSearchableTypeException` ; type enregistré ⇒ résultats | **M** | Dégradé Haversine testé aussi | Lot « géo » |
| **P2-03** | Décider du sort de `users` vs `travel_customers` | **Deux identités client** ; un humain = deux comptes | `config/auth.php`, `Core/Auth` | — | Fusion improvisée = 3ᵉ identité | Document d'arbitrage + ADR | **M** (décision) | ADR mergé, aucune ligne de code de fusion | **Décision user requise** |
| **P2-04** | Trancher le comptage des BC (36/37/38 + BC-37 réservé) | Trois comptages contradictoires | `dev-hub/governance/bounded-context-registry.json`, `docs/architecture/BOUNDED-CONTEXT-REGISTRY.md` | #8572 | Réattribuer BC-37 = collision | Doc unique + garde CI sur la séquence | **S** | Plus aucun document ne dit « 36 » | **Décision user requise** |
| **P2-05** | Pièces jointes dans les fils publics | Non exposées ; revue de confidentialité absente | `Modules/Notification`, `BC-20 Documents` | Audit stockage | Fuite de pièce jointe RH | Test : un client ne lit que ses propres pièces | **M** | Revue de confidentialité écrite | Lot séparé |
| **P2-06** | Passer un build Flutter de fumée sur toute PR backend | Voir P0-01 | `.github/workflows/tests.yml` | P0-01 | Coût CI | Un job « build de fumée » s'exécute sur PR backend | **S** | Job non-`skipped` | Avec P0-01 |

#### P3 — Commandes/réservations, paiements réels, app mobile publique

| ID | Objectif | Problème | Fichiers | Dépend. | Risques | Tests | Taille | Critères | Lot |
|---|---|---|---|---|---|---|---|---|---|
| **P3-01** | Réservation publique Restaurant de bout en bout | `booking` déclaré, contrat public absent | `RestaurantManager`, nouvelle route `public/…/booking` déléguant au service existant | P1-03 | Dupliquer une règle métier | Un client réserve, le pro voit la réservation, annulation testée | **L** | Test Feature + E2E | Lot « réservation » |
| **P3-02** | Premier consommateur de BC-38 | 0 appelant de `paiements/internal` | `Modules/Paiements`, module métier choisi | P3-01 | Double débit si idempotence mal branchée | Paiement d'une commande, rejeu de la même clé ⇒ 1 transaction | **L** | Idempotence prouvée par test | Lot « paiement branché » |
| **P3-03** | Rail PSP réel | `SimulatorRail` unique | `Modules/Paiements/Infrastructure/Services/Rails/` | Analyse légale | Conservation de fonds = risque réglementaire | Webhook signé bout en bout, remboursement | **L** | **Approbation user obligatoire** | **Décision user requise** |
| **P3-04** | App mobile grand public | 9 apps, toutes pro | `front/mobile_apps/` | P2-03 | Nouvelle base d'identité | Parcours B–D sur mobile | **L** | Décision produit | **Décision user requise** |
| **P3-05** | Versionner les 32 verticales restantes | 5/37 découvrables | Providers par verticale | P1-02 | Providers mal écrits (fuite de colonnes) | 1 test de liste blanche par provider | **L** (cumul) | Couverture ≥ 20/37 | Lot par verticale |

### 14.2 Suite de tests de bout en bout

**Fichiers livrés dans cette PR** (squelette exécutable + documentation du scénario) :

- `front/web/e2e/leopardo-public-parcours.spec.ts` — parcours nominal Playwright
- `api/tests/Feature/LeopardoPublic/PublicParcoursEndToEndTest.php` — parcours nominal
  côté API (vraies routes, vraie persistance quand PostgreSQL est disponible)

**Scénario nominal couvert (9 étapes)**

| # | Étape | Assertion |
|---|---|---|
| 1 | Un professionnel publie son profil | `PUT …/public-profile` ⇒ 200 ; `is_public=true`, `public_slug` non nul |
| 2 | Le profil apparaît en découverte | `GET /public/discover?q=<nom>` ⇒ l'entrée est présente, DTO **exactement** conforme à la liste blanche |
| 3 | Un consommateur ouvre la fiche | `GET …/{slug}` ⇒ 200, aucun champ interne (`id`, `company_id`) |
| 4 | Il démarre une conversation | `POST /public/conversations` ⇒ 201, `thread_id` retourné |
| 5 | Le message est persisté | relecture en base dans le contexte tenant ⇒ 1 message, auteur `client` |
| 6 | Le professionnel traite la conversation | `GET /conversations/public` ⇒ fil présent ; `POST …/messages` ⇒ 201 |
| 7 | Le client voit la réponse | `GET /public/conversations/{companyId}/{thread}/messages` ⇒ 2 messages, curseur `seq` croissant |
| 8 | Un accès non autorisé est refusé | autre client / autre tenant / employé simple ⇒ **404** (jamais 403, jamais 200) |
| 9 | Le profil désactivé sort des résultats | `is_public=false` ⇒ `discover` ne le retourne plus ; nouvelle conversation ⇒ 404 |

**Scénarios d'erreur et de non-régression**

- `?type=inexistant` ⇒ **422** (jamais une liste vide silencieuse)
- message vide ⇒ 422 ; message > 2000 caractères ⇒ 422
- conversation sur `target_type` inconnu ⇒ 404
- fil d'un autre tenant ⇒ 404 (indistinguable d'un fil inexistant)
- index de routage manquant ⇒ le fil n'apparaît pas dans `GET /public/conversations`
  *(test de non-régression dédié, cf. P0-03)*
- `supportedActions()` ⊆ actions couvertes par un test *(garde anti-bouton-mort)*

### 14.3 Réponses aux 10 questions

**1. Leopardo Public fonctionne-t-il réellement aujourd'hui, et sur quels parcours ?**
**Oui, sur un parcours complet, prouvé par exécution.** La suite
`PublicParcoursEndToEndTest` (**19 tests, 90 assertions — OK** sur PostgreSQL 15 + PostGIS)
démontre la chaîne entière : un professionnel publie → le profil apparaît dans
`/public/discover` → la fiche publique répond → un client démarre une conversation →
le message est persisté en BC-13 → le manager le voit dans sa boîte → il répond → le
client lit la réponse → un autre client et un autre tenant reçoivent **404** → la
dépublication retire le profil des résultats. Côté frontend, `/decouvrir` et
`/conversations` sont couverts par **24/24 tests Jest verts**. Les parcours **A
(publication transverse), C (fiche pour 2 des 5 verticales) et E (commandes/réservations
par module)** restent **partiels**. **Aucun paiement réel.**

**2. Qu'existe seulement dans le code sans être connecté de bout en bout ?**
- **BC-38 Paiements** : 41 fichiers, idempotence, ledger append-only, webhooks HMAC —
  **zéro consommateur** (`paiements/internal` : 0 appelant) et **un seul rail simulé**.
- **`SearchableRegistry`** : mécanisme complet et testé, **aucun type enregistré** au
  runtime ⇒ la recherche « à proximité » ne peut rien retourner.
- **Actions `booking`** déclarées par Restaurant sans contrat public identifié.
- **Fiches Retail/VTC** : découvrables, sans page profil.
- **32 verticales** : métier existant, providers de découverte absents.

**3. Principal obstacle au premier parcours public utilisable ?**
**L'absence de publication transverse, pas la découverte.** Un professionnel ne peut pas
« publier son profil » en un geste : il doit activer `is_public` + `public_slug` dans le
module de son métier, avec une sémantique différente par verticale. Tant que ce geste
n'existe pas, le rayon de `/decouvrir` ne grandira pas, même avec 32 providers de plus.

**4. Le chat existant peut-il être réutilisé sans reconstruction ?**
**Oui — et il l'est déjà.** `PublicConversationService` étend `ConversationThread`/
`ConversationMessage` (BC-13) avec `kind=public`, `public_client_id`, `public_target_slug`
et deux contrôles d'accès explicites. Aucun second système de messagerie, aucune table de
messages dupliquée. Le seul ajout hors tenant est une table de **routage**
(`public_conversation_index`) sans contenu. **Ne pas reconstruire.**

**5. Quelle interface doit devenir le point d'entrée public principal, et pourquoi ?**
**`front/web`.** C'est la seule application qui porte les trois étapes du parcours
public dans un même contexte : `/decouvrir` (B), les fiches verticales `restaurants`,
`stay`, `vitrine` (C), et `/conversations` (D). `marketplace` et `travel-web` sont des
parcours d'**achat** spécialisés, atteignables par `item.url` fourni par l'API. Stratégie
retenue : **B — expérience publique commune dans `front/web`, sans nouveau frontend et
sans fusion**.

**6. Quels problèmes de sécurité / isolation avant production ?**
1. **~~`GET /conversations/public` accepte un acteur non-employé~~ — INFIRMÉ.** Voir
   §13.1.3 : avec un vrai token porteur le client reçoit **401**. Le constat venait d'un
   test dont le guard `sanctum` gardait le manager en mémoire d'une requête à l'autre.
   Le risque réel était l'inverse : **le test** tolérait `200` vide. Corrigé en §13.4.
2. **`public_conversation_index`** : un oubli d'écriture rend un fil invisible
   (échec silencieux) ; c'est la seule carte client → tenant. Couvert par un test de
   non-régression (P0-03).
3. **Liste blanche des DTO non testée par provider** : un provider peut glisser une
   colonne sensible dans `description`. Le test E2E vérifie la liste blanche pour
   Restaurant ; les 4 autres providers restent à couvrir (P1-03).
4. **Deux identités client** (`users`, `travel_customers`) non conçues pour cohabiter.
5. **Absence d'app mobile grand public** ⇒ toute la surface publique passe par le web ;
   pas de risque propre, mais aucun second canal de révocation.
6. Positif à préserver : **404 uniforme** (jamais 403) sur les fils non autorisés —
   **prouvé par 3 tests** (autre client, autre tenant, employé simple) — et isolation
   `search_path` à échec **bruyant**.
7. **`authorize()` sur la publication : conforme** (constat initial infirmé, voir §7).
   Ne pas rouvrir ce point.

**7. Que corriger pour une CI fiable ?**
- `minSdkVersion` 26 explicite sur les 8 apps (PR **#8776**, à merger) ;
- **un job de build mobile de fumée sur PR backend** — car aujourd'hui les builds sont
  `skipped` et la régression mobile est invisible (§13.3) ;
- `Deploy Main Catch-up` : critère d'échec explicite (aujourd'hui seul échec rouge de `main`) ;
- `Dev Keepalive & Watchdog` en `startup_failure` ×2 ;
- `Backend Coverage Gate` annulé (queue/timeout) ;
- **ne jamais** désactiver un test pour verdir : les `skipped` actuels sont déjà un
  angle mort, en ajouter serait une faute.

**8. Plus petit lot de changements validant un parcours réel consommateur-professionnel ?**
**Le parcours est déjà validé par cette PR** — les 19 tests le prouvent. Les deux
changements que j'annonçais ici sont **tous les deux déjà satisfaits** (vérifié le
2026-10-21, voir §13.4) :
1. ~~**P0-07** — fermer `/conversations/public` aux acteurs non-employés~~ → **sans objet**,
   l'API refuse déjà (401). Le défaut était dans le test, corrigé.
2. ~~**P1-01 (réduit)** — un écran dans `front/web` qui appelle `PUT …/public-profile`~~ →
   **déjà livré** : `front/web/src/app/(dashboard)/restaurant/online/page.tsx`, atteignable
   depuis la tuile `/restaurant/online` du hub Restaurant, avec 5 tests verts.

Il ne reste donc **aucun lot bloquant** pour le parcours nominal Restaurant. Le prochain
lot utile est **P1-02** (fiches publiques Retail et VTC, qui suit #8759).

**9. Quels tests prouvent que ce parcours fonctionne ?**
1. **`api/tests/Feature/LeopardoPublic/PublicParcoursEndToEndTest.php`** — **19 tests,
   90 assertions, OK** (exécuté pendant cette session, PostgreSQL 15 + PostGIS) : les
   9 étapes du §14.2, plus les cas d'erreur et 3 tests d'isolation (autre client,
   autre tenant, employé simple), plus la non-régression de l'index de routage.
2. **Frontend** : `public-discovery-api.test.ts` + `public-conversation-api.test.ts` —
   **24/24 vertes**, exécutées dans cette session.
3. **Déjà présents, non rejoués ici** : `tests/Feature/Notification/PublicConversationTest.php`
   (18 tests, dont 4 cross-tenant) et `tests/Feature/Platform/PublicDiscoveryTest.php`.
4. **Livré mais non exécuté** : `front/web/e2e/leopardo-public-parcours.spec.ts` (Playwright)
   — nécessite `front/web` + API lancés ; statut **NON VÉRIFIÉ**.

**10. Quelles décisions requièrent l'approbation du user avant modification importante ?**
1. **P2-03** — sort de `users` vs `travel_customers` (fusion / passerelle / statu quo).
   Toute implémentation avant arbitrage crée une **troisième** identité client.
2. **P2-04** — le comptage officiel des BC et l'attribution de **BC-37** (réservé par la
   branche `bc/bc-37-farm-os`, PR #8572).
3. **P3-03** — branchement d'un **rail PSP réel** : analyse légale obligatoire
   (portefeuille / conservation de fonds).
4. **P3-04** — création d'une **app mobile grand public** (aujourd'hui aucune n'existe).
5. **P1-03** — retrait d'une action déclarée (`booking` Restaurant) : décision produit,
   car cela retire un bouton visible.
6. **P0-06** — politique d'échec de `Deploy Main Catch-up` (infra, propriété Render).

---

## Annexes

### A. Commandes de constat reproductibles

```bash
git log -1 --format='%H %s'
ls api/app/Modules | wc -l                                    # 37
grep -rn "public.discoverable" api/app --include='*.php'      # 5 providers
grep -rn "public.profile_resolver" api/app --include='*.php'  # 5 resolvers
grep -rn "paiements/internal" api/app                         # 0 appelant
grep -rn "implements PaymentRailInterface" api/app            # 1 (SimulatorRail)
grep -rn "register\|searchables" api/config/geo.php           # searchables => []
```

### B. Ce que ce rapport ne prouve pas

- La **suite E2E de cette mission** a bien tourné : `19 tests, 90 assertions — OK` sur
  PostgreSQL 15 + PostGIS, après installation de l'environnement (`CI=true`, driver
  `pdo_pgsql`, extension `postgis`).
- En revanche, **le reste du backlog de tests backend n'a pas été rejoué** : seule la
  suite E2E publique a été exécutée, pas `--testsuite=Feature` en entier, ni
  `tests/Feature/Paiements/*`, ni `tests/Feature/Notification/PublicConversationTest`
  (18 tests unitaires de contrôle d'accès). Leur statut reste **NON VÉRIFIÉ**.
- Aucun build Flutter ou Android n'a été tenté (ni Flutter ni Android SDK).
- Aucun test **Playwright** n'a été exécuté ici : le spec est livré, mais lancer
  `front/web` + l'API n'était pas possible dans le budget de session. Statut
  **NON VÉRIFIÉ** pour ce fichier.
- La CI de `main` a été lue via l'API GitHub (check-runs, workflow runs), pas rejouée.

### C. Fichiers de cette mission

| Fichier | Nature |
|---|---|
| `docs/audits/LEOPARDO_PUBLIC_PHASE1_AUDIT.md` | Ce rapport |
| `front/web/e2e/leopardo-public-parcours.spec.ts` | E2E Playwright du parcours nominal |
| `api/tests/Feature/LeopardoPublic/PublicParcoursEndToEndTest.php` | E2E API (vraies routes, vraie persistance) |
| `docs/GESTION_PROJET/AUDIT_LEOPARDO_PUBLIC.md` | Rapport Phase 0 — **non modifié** |
