# Leopardo Super-App & Business OS — Audit de maturité et écarts

> **Date** : 2026-10-16
> **Base** : `kitokoh/leopardo-hr` — `origin/main` @ `6d1ce5870` (BC-24 Travel OS, PR #8541)
> **Périmètre** : évolution vers une plateforme multi-services à deux faces (Leopardo.com public /
> Leopardo Business OS professionnel), quatre expériences (client, professionnel, employé,
> admin plateforme).
> **Nature du document** : audit Phase 0 + conception Phase 1 (recommandations). **Aucun code
> applicatif n'est modifié par ce document.**

---

## 1. Résumé exécutif

**Constat principal : la vision « super-app » est déjà réalisée à ~70 %, verticale par verticale.**
Le dépôt contient **38 modules backend** (DDD : Domain / Application / Infrastructure / Interfaces /
Providers), **7 frontends**, et surtout **une surface publique transactionnelle complète et testée
par verticale** : Boutique voyage (Travel), Marché retail (Retail/market), Catalogue B2B (Catalog),
Vitrine entreprise (Showcase), Annuaire restaurants (RestaurantManager), Séjours hôteliers
(Hospitality). Chacune possède son opt-in, son DTO public strict, son isolation tenant et ses tests.

**La lacune réelle n'est pas « construire la super-app » mais « unifier la découverte ».**
Il n'existe **aucune surface de découverte cross-verticale** : le client doit connaître la verticale
avant de chercher. Aucune route `public/discover`, aucun service d'agrégation multi-verticale.
La conséquence produit : Leopardo.com est une **collection de vitrines verticales**, pas encore un
**point d'entrée unique** où l'on cherche « une pharmacie près de moi » sans savoir que la
verticale s'appelle `pharmacy`.

**Blocage d'exécution : l'accès GitHub en écriture est absent** dans cet environnement (vérifié
empiriquement, cf. §6). Toute action GitHub (issues, branches, PR, fusion) est donc impossible :
aucune n'est simulée, aucune n'est revendiquée.

**Recommandation prioritaire (P0, chiffrable)** : implémenter une **API de découverte publique
unifiée** `GET /api/v1/public/discover` qui **agrège les résolutions publiques déjà existantes**
de chaque verticale, via un contrat partagé sur le modèle `PublishedProductsProvider`
(`app/Shared/Contracts/`). Aucune nouvelle entité centrale, aucun nouveau système de tenant,
aucun nouveau rôle : pure composition de l'existant.

---

## 2. État initial constaté (architecture réelle)

### 2.1 Stack

| Couche | Technologie | Preuve |
|---|---|---|
| Backend | Laravel 12, PHP `^8.4.1`, PostgreSQL 15 + PostGIS, Sanctum, Pest/PHPUnit 11 | `api/composer.json`, `api/phpunit.xml` |
| Architecture | Monolithe modulaire DDD, 38 modules, isolation cross-BC garde #5584 | `api/app/Modules/`, `api/ARCHITECTURE.md` |
| Multi-tenant | Deux schémas : `public` (plateforme) + `shared_tenants` (données tenant), scope `BelongsToCompany` | `tests/TestCase.php`, `RefreshTenantDatabase` |
| Frontends | `web` (Next.js, app + landing + verticales), `marketplace` (Leopardo Marché), `travel-web`, `admin-dashboard`, `mobile_apps`, `zkteco-kiosk`, `web-offline` (PWA edge) | `front/*/package.json` |
| Docs canoniques | Les **6 documents exigés existent déjà** | `docs/00_REFERENCE/{TAD,TRD,APP_FLOW,BACKEND_SCHEMA,UIX_UI_DESIGN,IMPLEMENTATION_PLAN}.md` |
| Catalogue de solutions | `Core/Solutions` + `SolutionSurveyController` (pré-qualification publique) | `api/routes/modules/solutions.php` |

### 2.2 Les 8 surfaces publiques existantes (aucune découverte unifiée)

| # | Surface | Route publique | Opt-in | Isolation |
|---|---|---|---|---|
| 1 | Restaurants | `/public/restaurants` (`index`, `show`) | `is_public=true` + flag `restaurantmanager` | `PublicTenantResolver` / DTO strict |
| 2 | Marché retail | `/public/market/products|sellers|orders` | `retail_online_settings.enabled` + flag `retail` | `RetailMarketplaceService::eligibleCompanyIds()` |
| 3 | Catalogue B2B | `/public/catalog/{companySlug}` | slug public + flag | `catalog.public` middleware |
| 4 | Vitrine | `/public/vitrine/{slug}` | `companies.features.company_showcase` | `module.showcase`, fail-closed |
| 5 | Séjours | `/public/hospitality/properties/{slug}` | `is_public=true` + flag `hospitality` | `HospitalityPublicPropertyResolver` |
| 6 | Voyages | `/public/travel/{shop,marketplace}` | jeton boutique SHA-256 | `EnsurePublicShopAccess` |
| 7 | Kiosque resto | `/public/restaurant/kiosk` | jeton boutique | `restaurant.public.shop` |
| 8 | Solutions | `/solutions/{code}/survey` | aucun (CPU pur, throttlé) | n/a |

> **Preuve d'absence de découverte cross-verticale** : `grep -rniE "discover|directory|unified" routes/ app/`
> ne retourne que des annuaires **intra-verticale** (`annuaire public restaurants` RESTO-901,
> `CrmDirectoryController` qui est un annuaire **interne** de leads, et un commentaire « recherche
> globale » dans `market_public.php` qui désigne la recherche produit **intra-marché**).

### 2.3 Contrats partagés réutilisables (la clé de l'architecture cible)

`api/app/Shared/Contracts/` contient déjà les briques d'inversion de dépendance nécessaires :

| Contrat | Rôle | Preuve |
|---|---|---|
| `Catalog/PublishedProductsProvider` | Lecture publique des produits publiés sans import croisé BC | `CatalogPublishedProductsProvider` le bind |
| `Geo/GeoServiceContract` | Façade transverse géospatiale (`distanceMeters`, `nearest`) — moteur PostGIS ou Haversine | `app/Shared/Contracts/Geo/GeoServiceContract.php` |
| `Geo/GeoLocatable` | Registry opt-in `geo.searchables` : une entité devient « la plus proche » sans code core | `app/Shared/Contracts/Geo/GeoLocatable.php` |
| `Notification/PushNotifier`, `EmployeeNotifier` | Notifications transverses | `app/Shared/Contracts/Notification/` |
| `Payments/*` | Résolution des profils de paiement tenant | `app/Shared/Contracts/Payments/` |
| `Services/PublicCommerce/PublicTenantResolver` | **Invariant public mutualisé** : `companyBySlug`, `assertAccessible`, `withinTenant` (fail-closed 404/401, anti-énumération) | utilisé par 6+ surfaces |

C'est exactement le patron à réutiliser pour la découverte unifiée — **il ne faut donc rien inventer**.

---

## 3. Matrice obligatoire — Fonctionnalité / État réel / Preuve / Écart / Action

| Fonctionnalité | État réel | Preuve (fichier, test, parcours) | Écart | Action |
|---|---|---|---|---|
| **Comptes et autorisations** | ✅ **Opérationnel** | `Core/Auth`, `app/Policies`, `App\Providers\AuthServiceProvider` (point unique PA2-ARCH-008), jetons Sanctum, RBAC plateforme `platform.permission:*`, comptes acheteurs marketplace (`MarketplaceBuyer`, `mkb_` + 64 hex, cookie HttpOnly #8096) | Comptes **acheteurs** existent pour le marché retail ; pas de « compte client unifié » couvrant toutes les verticales | P1-B : généraliser le compte acheteur en **compte client plateforme** (tables centrales déjà cross-tenant) — réutiliser, ne pas recréer |
| **Profils professionnels** | ✅ **Opérationnel (par verticale)** | `Companies` (slug, secteur, pays, statut, `features`), `Showcase` (vitrine 1-clic + thème + légal RGPD #6875), `RestaurantBranch.is_public`, `HospitalityProperty.is_public`, `RetailOnlineSettings` | Pas de **profil professionnel unifié multi-établissement** exposant catégorie/sous-catégorie commune ; chaque verticale a sa forme | P1-A : contrat `PublicCompanyProfileProvider` (patron `PublishedProductsProvider`) exposant une shape normalisée par verticale |
| **Catalogue public** | ✅ **Opérationnel** | `Catalog/CatalogPublicController` (`/public/catalog/{companySlug}`), `CatalogPublicCache`, `CatalogPublishedProductsProvider`, `CatalogProductStatus::Published` ; **tests** `CatalogPublicApiTest` | Pas de catalogue **multi-catégories cross-vertical** agrégé | P0 : agrégation lecture seule dans `/public/discover` (aucune nouvelle table) |
| **Commandes** | ✅ **Opérationnel** | Marché retail : `RetailMarketOrderPublicController` (`/public/market/orders`, idempotence clé client, suivi `?token=`), webhook paiement signé fail-closed (#7812) ; Resto : `RestaurantPublicSlugOrderController` + KDS ; **tests** `RetailMarketPublicTest`, `RestaurantPublicOrderTest`, `RestaurantPublicSlugOrderTest` | Aucun **panier cross-vertical** ; commandes cloisonnées par verticale (volontaire, à conserver) | P1 : ne pas fusionner les paniers — exposer un **historique de commandes client unifié** (lecture) |
| **Réservations** | ✅ **Opérationnel (par verticale)** — ⚠️ **pas de découverte** | Hospitality : `/public/hospitality/reservations` (+ `availability`, `cancel`), `HospitalityExpirePendingReservationsCommand` (scheduler 5 min) ; Restaurant : `RestaurantReservation`, tables/sessions ; Travel : `bookings` | **Aucune réservation de restaurant exposée publiquement en tant que telle** (le resto public = commande + avis) ; **aucune découverte de disponibilité cross-vertical** | **P0** : parcours réservation restaurant (le plus rapide à fiabiliser : tables + sessions + statuts existent) ; **P0** : `/public/discover` pour trouver le professionnel |
| **Paiements** | 🟡 **Partiel — prestataire non configuré** | `Shared/Contracts/Payments/*`, `Billing` (Invoice, PaymentGatewaySetting, TenantPaymentProfile), providers Chargily/Mock (`RetailPaymentProviderRegistry`), webhooks signés + idempotents, commande `retail:payments:reconcile` | **Aucun prestataire réel configuré/testé en environnement approprié** ; pas de commission marketplace généralisée | P1-D : activer **après** validation conformité pays/coûts + autorisation explicite ; ne rien déclarer opérationnel avant |
| **Notifications** | ✅ **Opérationnel** | `Modules/Notification` (AppNotification, Notification, NotificationPreference, DeviceToken, CompanyAnnouncement), `Shared/Contracts/Notification/{PushNotifier,EmployeeNotifier}` | Préférences/consentement **par verticale** ; pas de centre de notifications client unifié | P1-B : réutiliser `Notification` + `NotificationPreference`, exposer un flux client agrégé |
| **Fidélité** | 🟡 **Partiel (par verticale, non unifié)** | Resto : `CreditLoyaltyPointsAction`, `RedeemLoyaltyPointsAction` ; Carburant : `FuelLoyaltyService`, `FuelCustomer`, `FuelAccountVisit` | **Pas de programme de fidélité plateforme** ; pas de cumul cross-vertical ; pas de règles explicites publiées | P2 : contrat `LoyaltyProvider` + un solde client unifié (lecture) **sans** fusionner les règles métier des verticales |
| **Messagerie** | ✅ **Opérationnel (interne) + partiel (client)** | `ConversationThread`, `ConversationMessage` (BC-29 Communication, boîte mail connectée + IA #7685), `CrmChannelMessage`, `PlatformSupportTicket`/`PlatformSupportMessage` | Pas de **fil de discussion client contextualisé par commande/réservation** (fil pro↔client) | P1-C : réutiliser `ConversationThread`/`ConversationMessage` avec un lien polymorphe vers `order`/`reservation`, anti-spam throttlé |
| **Supervision** | ✅ **Opérationnel** | `Modules/Platform` (PlatformAnnouncement, PlatformImpersonationSession, PlatformSupportTicket, PlatformOutboxEvent, ScheduledTaskRun, WebhookEvent), `admin-dashboard`, RBAC `platform.permission:*` | Pas de **modération de contenu public** centralisée (avis, vitrines) | P2 : réutiliser `PlatformSupportTicket` + la modération d'avis resto existante (`RestaurantReviewStatus`) |
| **Recherche / découverte** | ❌ **Absent (cross-vertical)** | Aucune route `public/discover` ; `grep` ne trouve que des annuaires intra-verticale | **LA lacune P0** : le client ne peut pas chercher sans connaître la verticale | **P0** : `GET /api/v1/public/discover` — agrégation opt-in via contrat partagé |
| **Recherche géolocalisée** | 🟡 **Opérationnel par verticale, non unifié** | Resto : haversine SQL + `GeoServiceContract::nearest` (PostGIS) GEO-06 #8355 ; registry `geo.searchables` (#8353) | Chaque verticale fait sa propre géo ; pas de requête « tout type, dans 5 km » | **P0** : `/public/discover?near=lat,lng&radius_km=5` s'appuyant sur `GeoServiceContract` (déjà là) |
| **Onboarding professionnel** | ✅ **Opérationnel** | `Modules/Onboarding` (5 couches DDD), `Core/Solutions` + `SolutionSurveyController` (survey public de pré-qualification), `CompanyModuleController::activate`, kit de démo (`DemoDataRegistry`, `SeedRestaurantDemoCommand`) | Onboarding **par solution** ; pas de parcours unifié « je crée mon espace pro multi-établissements » transverse | P1-A : orchestrateur au-dessus de `SolutionCatalogue` + `CompanyModuleController` (existant) |
| **Multi-établissements** | ✅ **Opérationnel** | Resto : `RestaurantBranch` (multi-branches, `public_slug` unique global) ; Hospitality : `HospitalityProperty` ; Retail : `RetailLocation` ; Hotel : `HospitalityUnit`/`RoomType` | Chaque verticale modélise ses établissements ; **pas de vue consolidée propriétaire** | P1-A : vue consolidée **lecture** agrégée par `company_id` (aucune fusion de modèles) |
| **Employés / rôles / affectations** | ✅ **Opérationnel** | `Core/Auth` (Employee), `employees_manager_role_check`, `HospitalityPropertyStaff` (affectation), rôles RBAC, invitations sécurisées | Rôles **par module** (`api.manager:principal,rh,module:x`) ; pas de matrice unique rôle × application × appareil documentée | P0 : produire la **matrice rôles/applications/appareils/flux** (documentation, cf. §7) |
| **KDS / caisse / impression (Restaurant OS)** | 🟡 **Partiel — code prêt, non poussé** | Branche locale `feat/restaurant-os-printing-and-rbac` (7 commits, REST-OS-1→5) : contrat ESC/POS, `PrintJob` persistant + `UNIQUE(order_id, ticket_kind, revision)`, bridge poll/ack, RBAC `restaurant.print_jobs.*` — **27 tests exécutés verts** | **Non poussée** (pas d'accès écriture) ; impression physique non testable sans matériel | Reprise : pousser la branche dès accès disponible ; imprimante thermique + bridge = achat/install côté restaurateur |
| **Super-app mobile dédiée** | ❌ **Non commencé** | `front/mobile_apps` vide ; `mobile_apps` sans `package.json` | Aucune app mobile unifiée | P3 : à justifier par l'usage réel avant tout développement |
| **Horaires / disponibilités** | ✅ **Opérationnel** | `RestaurantHour`, `Hospitality` availability, `Showcase` sections | Pas d'agrégation « ouvert maintenant » toutes verticales | P0 : filtre `open_now` dans `/public/discover` (lecture des horaires existants) |
| **Avis / reviews** | ✅ **Opérationnel (retail + resto)** | `MarketplaceReview` (exige une commande servie/livrée — anti-faux-avis), `RestaurantReview` + `RestaurantReviewStatus` (modération), throttles dédiés | Pas d'avis unifiés cross-vertical | P2 : contrat `PublicReviewsProvider` (lecture), **conserver** la règle « avis authentique post-transaction » |
| **Abonnements / facturation** | ✅ **Opérationnel** | `Modules/Billing` : `Subscription`, `Invoice`, `Feature`, `FeaturePlanMatrix`, `AiCreditLedger`, `PartnerPayoutRequest` | Pas de commission marketplace généralisée | P1-D : à cadrer avec la conformité (jamais de conservation de fonds sans étude) |
| **Recherche produit / commande livrée de bout en bout** | ✅ **Opérationnel** | `front/marketplace` : accueil → `/produits` → `/panier` → `/commande` (idempotence `crypto.randomUUID()`) → `/confirmation` → `/suivi` (`?ref=&token=`) ; CSP nonce (#8022), mobile-first Tailwind 4 | Pas de compte client obligatoire (guest checkout **choisi**) | Conserver ; rattacher l'historique au compte client unifié (P1-B) quand il existe |

**Légende** : ✅ opérationnel · 🟡 partiel · ❌ absent/à vérifier.

---

## 4. Fonctionnalités existantes réutilisées (aucune recréation)

1. **`PublicTenantResolver`** — invariant d'accès public (slug → société → gardes statut/feature/opt-in
   → `withinTenant`). Toute nouvelle surface publique DOIT passer par lui (fail-closed 404,
   anti-énumération). *Aucune duplication.*
2. **`PublishedProductsProvider`** (Shared Contracts) — **patron exact** pour la découverte unifiée :
   contrat partagé + implémentation par module + binding dans le `ServiceProvider` du module.
3. **`GeoServiceContract` + registry `geo.searchables`** — la recherche « près de moi » existe déjà
   au niveau core ; `/public/discover` doit l'appeler, jamais réimplémenter la haversine.
4. **`SolutionCatalogue` + `SolutionSurveyController` + `CompanyModuleController`** — l'entrée
   vitrine et l'activation de module sont en place ; l'onboarding transverse s'y branche.
5. **Comptes acheteurs (`MarketplaceBuyer`)** — déjà des **comptes plateforme cross-tenant** avec
   jeton haché + cookie HttpOnly : base du compte client unifié, à généraliser, pas à recréer.
6. **`ConversationThread` / `ConversationMessage`** — socle messagerie existant (BC-29).
7. **`Modules/Notification` + contrats partagés** — notifications réutilisables, zéro API payante requise.
8. **`RetailMarketplaceService::eligibleCompanyIds()`** — le patron d'agrégation cross-tenant
   **bornée aux opt-in** : il doit être le modèle de `/public/discover`.
9. **Les 6 documents de référence** `docs/00_REFERENCE/*` — **à mettre à jour, jamais dupliquer.**

---

## 5. Lacune prioritaire identifiée — découverte publique unifiée (P0)

### 5.1 Problème

Un client qui ouvre Leopardo.com ne dispose d'aucun point d'entrée neutre : pour trouver une
pharmacie il doit atteindre `/pharmacy`, pour un hôtel `/stay`, pour un restaurant `/restaurants`.
Il existe **8 surfaces publiques**, **0 agrégateur**. C'est précisément l'écart qui sépare
« 8 vitrines verticales » d'« une super-plateforme ».

### 5.2 Recommandation (scopée, sans nouvelle entité centrale)

Créer **une seule surface de lecture** : `GET /api/v1/public/discover`.

- **Contrat partagé** `App\Shared\Contracts\Discovery\PublicDiscoverableProvider` (patron
  `PublishedProductsProvider`) :

  ```php
  interface PublicDiscoverableProvider
  {
      /** @return list<array{type:string, slug:string, name:string, city:?string,
       *   latitude:?float, longitude:?float, open_now:?bool, ...}> */
      public function search(array $filters): array;
  }
  ```

- **Implémentations par module** : `ShowcaseDiscoverableProvider`, `RestaurantDiscoverableProvider`,
  `HospitalityDiscoverableProvider`, `RetailSellerDiscoverableProvider` — chacun **n'expose que ses
  entités déjà publiques** (réutilise la requête publique existante : `is_public=true`, flag actif,
  société non `suspended`/`expired`). Binding dans le `ServiceProvider` du module respectif
  (inversion de dépendance, garde d'isolation #5584).
- **Agrégateur** `PublicDiscoveryService` : collecte les providers, fusionne, trie par distance si
  `near` fourni (via `GeoServiceContract`), **pagine et borne** (≤ 50/page), filtre `open_now`.
- **Routes** : isolées, `throttle:shop-public`, **aucun** middleware tenant/auth (patron BC-27/28).
- **Hors périmètre volontairement** : pas de table, pas de migration, pas de nouvel index, pas de
  nouveau rôle, pas de nouveau tenant, pas de cache nouvelle génération (réutiliser `*PublicCache`).

### 5.3 Critères d'acceptation (à vérifier par un agent avec accès écriture)

1. `GET /api/v1/public/discover` retourne les entités publiques de **plusieurs verticales** en une
   réponse, chacune avec son `type`.
2. Une branche `is_public=false`, une société `suspended`, ou une verticale sans flag n'apparaissent
   **jamais** (fail-closed).
3. `GET /public/discover?near=4.05,9.76&radius_km=5` trie par distance croissante et exclut hors rayon.
4. Le DTO ne contient **aucun** `company_id`, `id` interne, stock, marge ou `meta`.
5. `?type=` inconnu → 422 ; pagination bornée ≤ 50 ; throttle appliqué.
6. Aucune régression : la baseline publique (18 tests / 204 assertions) reste verte.
7. `php -l` sur les fichiers ajoutés ; tests Feature dédiés (isolation, vide, erreur, 404).

---

## 6. Accès GitHub en écriture — **absent** (vérifié empiriquement)

| Contrôle | Résultat |
|---|---|
| `git push --dry-run` (avec `GIT_TERMINAL_PROMPT=0`) | `fatal: could not read Username for 'https://github.com': terminal prompts disabled` (code 128) |
| Variable d'environnement jeton | absente (`env` filtré : seul `GIT_SSL_CAINFO`) |
| Fichiers d'identifiants | `~/.netrc`, `~/.git-credentials`, `~/.config/gh` : inexistants |
| Recherche système de motif de jeton | `grep -rlE 'gh[pousr]_…|github_pat_…' /` : **0 fichier** |
| Profils d'identifiants | `get_credential_profiles` → **0 profil** |
| Outils MCP GitHub | `discover_mcp_tools("github")` → **0** (catalogue : aucun toolkit GitHub connectable) |
| CLI `gh` | absente |

**Conséquence, sans ambiguïté** : dans cette session, **aucune issue n'a été créée, aucune branche
n'a été poussée, aucune PR n'a été ouverte, aucune fusion n'a eu lieu**. Rien de tel n'est revendiqué.

**Prérequis à fournir (par l'humain, dans l'environnement sécurisé — jamais dans le dépôt)** : un
jeton GitHub **fine-grained** sur `kitokoh/leopardo-hr` avec `Contents: RW`, `Pull requests: RW`,
`Issues: RW`, `Metadata: R` (optionnel `Actions: R`), exposé via variable d'environnement ou fichier
d'identifiants **hors dépôt**. Le contenu du jeton n'est jamais affiché ni versionné.

### 6.1 Chevauchement (règle A) — vérifié, aucun conflit

`git fetch --all --prune` + liste des PR ouvertes : les PR actives portent sur Marketing OS (#8574),
dependencies (dependabot #8573/#8511/#8508/#8507/#8503/#8502), **Farm OS BC-37 (#8572)** et
**Trade OS BC-36 (#8487)**. **Aucune** branche/PR de découverte publique ou de super-app.
`routes/api.php` est un **point de conflit chaud** (Farm OS y ajoutera un `require`) : la
recommandation §5 limite le diff à **une ligne de `require`** plus des fichiers neufs.

---

## 7. Conception (Phase 1) — décisions et plan

### 7.1 Décisions d'architecture

| Décision | Justification |
|---|---|
| **Pas de microservice, pas de nouvelle base** | Le monolithe modulaire DDD suffit ; la découverte est de la **composition de lecture** |
| **Pas de nouvelle entité centrale** | Companies + features + slugs publics existent déjà |
| **Pas de nouveau système de tenant** | Tenancy « shared » verrouillée (ADR-0028) |
| **Pas de nouveaux rôles** | RBAC `platform.permission:*` + `api.manager:*` couvrent le besoin |
| **Contrats dans `Shared/Contracts/`** | Garde d'isolation #5584 : aucun import croisé `Modules/A → Modules/B` |
| **Réutilisation de `PublicTenantResolver`** | Invariant public unique, anti-énumération, déjà éprouvé |

### 7.2 Plan d'implémentation par phases

| Phase | Contenu | Priorité | Dépendances |
|---|---|---|---|
| **P0** | `/public/discover` (API + tests) ; matrice rôles/applications/appareils/flux ; parcours réservation restaurant public | **P0** | Aucune (briques existantes) |
| **P1** | Compte client unifié (généralisation `MarketplaceBuyer`) ; profil pro unifié ; notifications client agrégées ; messagerie contextualisée commande/réservation | P1 | P0 |
| **P1-D** | Paiements marketplace généralisés | P1 | **Validation conformité pays/coûts + autorisation explicite** |
| **P2** | Fidélité plateforme (contrat + solde lecture) ; avis unifiés ; modération centralisée | P2 | P1 |
| **P3** | App mobile dédiée ; livraison/transport partenaires ; réservation hôtelière synchronisée ; nouvelles verticales | P3 | Justification d'usage + budget |

### 7.3 UX/UI (principes, cohérents avec l'existant)

- **Mobile-first** : `front/web` et `front/marketplace` le sont déjà (Tailwind 4, CSP nonce).
- **Accueil = recherche centrale** : un champ unique, puis les catégories servies par `/public/discover`.
- **Réutiliser le design system existant** (`UIX_UI_DESIGN.md`) — ne pas créer un second système visuel.
- **i18n** : `api/MULTILANG.md` + `front/web/src/lib/i18n.ts` existent ; le dépôt gère déjà
  l'arabe → **prévoir RTL** dès la première page de découverte (ne pas rétro-fitter).
- **États obligatoires** : vide / chargement / erreur / indisponibilité — **jamais** de faux
  professionnel ni de disponibilité fictive en production.

### 7.4 Sécurité (exigences non négociables)

Isolation tenant (scope `BelongsToCompany` + `withoutGlobalScope` **explicite et borné** par
`whereIn('company_id', $eligible)`) · autorisation **serveur** (jamais un masquage de bouton) ·
validation d'entrée (`$request->validate`) · anti-IDOR (404 fail-closed, slug = identifiant) ·
idempotence des actions critiques (clé d'idempotence + contrainte `UNIQUE`) · journalisation des
événements sensibles (outbox) · pagination et throttles · secrets hors dépôt · **aucune donnée RH,
salariale ou privée dans la vitrine**.

### 7.5 Tests, déploiement, observabilité, retour arrière

- **Baseline mesurée ce jour** (réelle, avec `CI=true`) :
  `RestaurantPublicDirectoryTest` + `RetailMarketPublicTest` + `CatalogPublicApiTest`
  → **18 tests, 204 assertions, 0 échec** (`OK, but there were issues!` = seule alerte
  « No code coverage driver available », sans effet).
  Suite vitrine isolée : **6 tests, 43 assertions, 1 échec pré-existant**
  (`ShowcasePublicApiTest::test_cache_is_used_and_invalidated_on_published_showcase_mutation`,
  hors périmètre, à traiter séparément).
- **Non exécutables ici** : `pint` et `phpstan` exigent PHP ≥ 8.3/8.4 ; le sandbox est en **PHP 8.2.34**
  → **non exécutés, non revendiqués**. La CI réelle les exécute (`phpstan.ci.neon`, niveau 8).
- **Tests Unit** : erreurs pré-existantes massives liées aux handlers PHP (`did not remove its own
  error handlers`), sans lien avec ce périmètre.
- **Migrations** : la recommandation P0 n'ajoute **aucune migration** → risque de retour arrière nul.
- **Observabilité** : réutiliser l'outbox existant (`PlatformOutboxEvent`, `CrmOutboxEvent`) et
  `*PublicCache` (invalidation à la mutation).

### 7.6 Coûts et dépendances externes

| Poste | Coût | Statut |
|---|---|---|
| `/public/discover` | **0 €** (composition de lecture, briques existantes) | recommandé |
| PostGIS / registre géo | déjà installé et financé | opérationnel |
| Prestataire de paiement (P1-D) | variable + frais par transaction | **non configuré — nécessite autorisation** |
| APNs / FCM (push mobile) | offre gratuite dans les limites usuelles | à valider à l'usage |
| Imprimante thermique ESC/POS + poste cuisine | achat matériel côté restaurateur | hors périmètre logiciel |
| App mobile dédiée (P3) | développement + stores | **à justifier par l'usage** |

---

## 8. Rapport final obligatoire (13 sections)

1. **Résumé exécutif** — super-app réalisée à ~70 % par verticale ; lacune unique = découverte
   cross-verticale ; accès GitHub en écriture absent.
2. **État initial constaté** — §2 (stack, 38 modules, 7 fronts, 8 surfaces publiques, docs canoniques existants).
3. **Fonctionnalités réutilisées** — §4 (9 briques, dont `PublicTenantResolver`, `PublishedProductsProvider`,
   `GeoServiceContract`, `MarketplaceBuyer`, `ConversationThread`).
4. **Fonctionnalités implémentées** — **aucune dans cette session** : pas d'accès écriture. La seule
   production est ce document d'audit (+ un commit local, cf. §9).
5. **Fonctionnalités restant à développer** — §7.2 (P0→P3), en tête : `/public/discover`.
6. **Architecture et fichiers modifiés** — **aucun fichier applicatif modifié**. Ce document :
   `docs/AUDIT_LEOPARDO_SUPERAPP_2026-10.md`.
7. **Issues / branche / PR créées avec liens vérifiés** — **néant** (aucun accès écriture ; §6).
   Aucun lien n'est inventé.
8. **Tests réellement exécutés et résultats** — §7.5 : **18 tests / 204 assertions / 0 échec**
   (surfaces publiques Restaurant + Retail + Catalog) ; 6/43/1 (vitrine, échec pré-existant) ;
   PHPUnit 11.5.50 sur PHP 8.2.34, PostgreSQL 15.19, base `leopardo_test` bootstrappée.
   `php -l` disponible ; `pint`/`phpstan` **non exécutables** (PHP trop ancien).
9. **État des contrôles CI** — **non déclenchés** : rien n'a été poussé. `main` distante intacte
   (`6d1ce5870`). Le commit local n'existe que dans ce sandbox.
10. **Risques de sécurité / contraintes réglementaires** — §7.4 ; points chauds : agrégation
    cross-tenant (exiger un `whereIn` borné explicite), IDOR (slug = identifiant, 404 fail-closed),
    paiements/remboursements (conformité pays + aucune conservation de fonds sans étude),
    RGPD (aucune donnée RH/salariale en vitrine).
11. **Coûts éventuels et dépendances externes** — §7.6.
12. **Instructions de configuration et de déploiement** — pour la reprise : (a) fournir le jeton
    fine-grained en variable d'environnement **hors dépôt** ; (b) `service postgresql start` +
    base `leopardo_test` (rôle `leopardo_user`) ; (c) **toujours** `CI=true php vendor/bin/phpunit …`
    (sinon l'hôte DB est réécrit en `pgsql`) ; (d) `NEXT_PUBLIC_MARKET_API_BASE` obligatoire pour
    `front/marketplace` (le build échoue sans elle).
13. **Prochaine priorité recommandée** — **P0 : `/api/v1/public/discover`** (spec §5), puis la
    matrice rôles/applications/appareils/flux, puis le parcours réservation restaurant public.

### Statut par bloc

| Bloc | Statut |
|---|---|
| Phase 0 — Audit + matrice | ✅ **Terminé** (ce document) |
| Phase 1 — Conception (décisions, plan, P0 scopé) | ✅ **Terminé** (recommandations chiffrées) |
| Phase 1 — Mise à jour des 6 docs de référence | 🟡 **Partiel** — ils existent ; mise à jour à faire **dans la même PR que le code** (règle du dépôt) |
| Phase 2 — `/public/discover` | 🔴 **Bloqué** (accès GitHub écriture absent) |
| Phase 2 — Parcours réservation restaurant public | 🔴 **Bloqué** (idem) |
| Phase 3 — Architecture cible | ✅ **Conçue** (§7.1), non implémentée |
| Phase 4 — UX/UI | 🟡 **Principes posés** (§7.3), maquettes à produire avec les écrans |
| Phase 5 — GitHub (issues/branche/PR) | 🔴 **Bloqué** (§6) |
| Rapport final | ✅ **Terminé** (cette section) |

---

## 9. Trace du livrable

- **Fichier** : `docs/AUDIT_LEOPARDO_SUPERAPP_2026-10.md` (ce document).
- **Commit local** : `docs(audit): Phase 0 maturity matrix + gap analysis (no write access to push)`
  — commit **local uniquement**, non poussé (accès en écriture absent).
- **Aucune branche, aucun push, aucune PR, aucune issue** n'a été créé — et cela n'est jamais
  revendiqué.

> Ce document respecte la règle du dépôt (`docs/00_REFERENCE/README.md`) : *« le code sur `main`
> fait foi ; ces documents doivent être mis à jour dans la même PR que le code »*. Les 6 documents
> de référence canoniques **n'ont donc pas été dupliqués** ici : cet audit est un document
> d'analyse **daté**, à fusionner dans `docs/00_REFERENCE/*` lors de la première PR de la mission.
