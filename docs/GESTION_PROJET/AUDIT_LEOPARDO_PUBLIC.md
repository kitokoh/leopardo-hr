# Audit Phase 0 — Passerelle publique « Leopardo Public »

> **Périmètre** : `kitokoh/leopardo-hr`, branche `main` @ `ab0ca5d2a`.
> **Nature** : audit de constat. Aucune fonctionnalité n'est déclarée opérationnelle
> sur la seule foi de son nom dans un registre : chaque affirmation renvoie à un
> fichier, un comptage ou une commande exécutée.
> **Date** : 2026-10-20.

## Conventions de lecture

| Tag | Signification |
|---|---|
| **[V]** | Vérifié dans le code ou par une commande exécutée dans cette session |
| **[V-prev]** | Vérifié lors d'une session antérieure sur le même dépôt (traçable, non rejoué ici) |
| **[P]** | Implémenté partiellement — le code existe mais le parcours n'est pas complet |
| **[?]** | À valider — élément non vérifié, aucune conclusion tirée |

Rien dans ce document ne constitue un résultat de test : les tests **exécutés**
sont listés explicitement au §12, avec leur statut réel. Tout le reste est de la
lecture de code.

---

## 1. Inventaire des bounded contexts

**Source de vérité** : `dev-hub/governance/bounded-context-registry.json`
(référence humaine : `docs/architecture/BOUNDED-CONTEXT-REGISTRY.md`) **[V]**

### 1.1 Écart de dénombrement — à traiter avant toute nouvelle verticale

La mission annonce **36 BC**. Le registre en déclare **37** **[V]** :

- codes `BC-01` → `BC-36`, puis **`BC-38`** (`PAIEMENTS`) ;
- **`BC-37` n'existe pas** — le code est manquant dans la séquence, sans entrée
  ni `status=planned` ;
- `BC-22` (`ANALYTICS` → `Reporting`) est déclaré `status=planned` : le répertoire
  n'existe pas.

Conclusion : le « 36 » du prompt maître ne correspond ni au nombre d'entrées du
registre (37) ni aux répertoires présents sous `api/app/Modules` (37 répertoires)
**[V]**. Le chiffre 37 est un hasard de comptage : 37 entrées *et* 37 répertoires,
mais les deux ensembles ne se recouvrent pas (un `planned` d'un côté,
`Reporting`/`Documents` absents de l'autre). Ne pas figer « 36 BC » dans un
document de référence avant arbitrage.

### 1.2 Tableau d'état — preuves par BC

Comptages produits par parcours d'arborescence sur `api/app/Modules/<BC>` **[V]** :
`CTRL` = fichiers `*Controller.php`, `SVC` = classes sous `Application/`,
`MODEL` = classes sous `Domain/Models/`, `ROUTES` = routes déclarées dans les
fichiers de routes **déclarés au registre**, `TABLES` = tables créées par les
migrations tenant dont le nom de fichier cite le module, `TEST` = fichiers de
`api/tests` citant le nom du module.

| Code | Nom | CTRL | SVC | MODEL | ROUTES¹ | TABLES | TEST | État |
|---|---|---:|---:|---:|---:|---:|---:|---|
| BC-01 | PLATFORM | 49 | 18 | 8 | 0 | 2 | 118 | **Opérationnel** |
| BC-02 | TENANT | 0 | 0 | 0 | 0 | 0 | 0 | **Infrastructure** (Core, pas de surface HTTP propre) |
| BC-03 | IDENTITY | 0 | 0 | 0 | 31 | 0 | 0 | **Infrastructure** (Core/Auth + Policies) |
| BC-04 | HR | 35 | 3 | 18 | 306 | 3 | 129 | **Opérationnel** |
| BC-05 | WORKFORCE | 18 | 30 | 33 | 60 | 14 | 234 | **Opérationnel** |
| BC-06 | LEAVE | 1 | 0 | 0 | 10 | 5 | 63 | **Partiel** — 1 contrôleur, aucune classe `Application/` |
| BC-07 | PAYROLL | 28 | 21 | 26 | 93 | 21 | 207 | **Opérationnel** |
| BC-08 | ACCOUNTING | 21 | 8 | 14 | 56 | 15 | 66 | **Opérationnel** |
| BC-09 | EXPENSE | 2 | 2 | 1 | 12 | 5 | 20 | **Partiel** — surface HTTP étroite |
| BC-10 | RECRUITMENT | 4 | 12 | 3 | 0 | 3 | 5 | **Partiel** — 12 actions, routes non déclarées au registre |
| BC-11 | CRM | 18 | 30 | 26 | 83 | 28 | 75 | **Opérationnel** |
| BC-12 | GROWTH | 10 | 10 | 10 | 37 | 6 | 51 | **Opérationnel** |
| BC-13 | COMMS | 9 | 7 | 10 | 0 | 14 | 76 | **Opérationnel** — porte le chat humain (§7) |
| BC-14 | INTEGRATION | 0 | 0 | 0 | 24 | 0 | 0 | **Infrastructure** (Jobs + Contracts/Queue) |
| BC-15 | FUEL | 23 | 10 | 35 | 0 | 0 | 38 | **Partiel** — 23 contrôleurs, 0 migration attribuable au nommage |
| BC-16 | EDU | 27 | 16 | 34 | 102 | 0 | 49 | **Opérationnel** |
| BC-17 | RETAIL | 14 | 9 | 15 | 0 | 11 | 12 | **Opérationnel** — + front public `front/marketplace` |
| BC-18 | FIELD | 5 | 14 | 6 | 0 | 0 | 9 | **Partiel** |
| BC-19 | DEVICE | 12 | 5 | 10 | 19 | 4 | 31 | **Opérationnel** |
| BC-20 | DOCUMENTS | 3 | 2 | 3 | 17 | 3 | 11 | **Partiel** — `Documents` déclaré `planned` |
| BC-21 | BILLING | 14 | 6 | 17 | 28 | 7 | 67 | **Opérationnel** |
| BC-22 | ANALYTICS | 0 | 0 | 0 | 0 | 0 | 0 | **Absent** — `status=planned`, répertoire inexistant |
| BC-23 | AI | 0 | 0 | 0 | 0 | 0 | 0 | **Opérationnel hors `Modules/`** — code dans `api/app/AI` (33 fichiers) et `api/app/Core/AI` **[V]** |
| BC-24 | TRAVEL | 53 | 45 | 64 | 291 | 0 | 130 | **Opérationnel** |
| BC-25 | RESTAURANT | 62 | 41 | 41 | 222 | 41 | 177 | **Opérationnel** — mais **tout dans `RestaurantManager`** ; `Restaurant/` ne contient que 3 fichiers (manifeste, survey, provider) **[V]** |
| BC-26 | DELIVERY | 10 | 4 | 10 | 27 | 14 | 46 | **Opérationnel** |
| BC-27 | SHOWCASE | 5 | 13 | 3 | 0 | 3 | 12 | **Opérationnel** — provider public (§8) |
| BC-28 | CATALOG | 5 | 11 | 3 | 0 | 5 | 41 | **Opérationnel** |
| BC-29 | COMMUNICATION | 11 | 8 | 14 | 30 | 15 | 38 | **Opérationnel** — **communication e-mail/Gmail**, pas le chat (§7) |
| BC-30 | PHARMACY | 10 | 16 | 10 | 34 | 10 | 8 | **Partiel** — couverture de test faible (8 fichiers) |
| BC-31 | HEALTH | 14 | 15 | 17 | 63 | 0 | 9 | **Partiel** — idem |
| BC-32 | HOSPITALITY | 9 | 9 | 5 | 36 | 0 | 8 | **Partiel** — provider public (§8) |
| BC-33 | FUNDRAISING | 6 | 8 | 7 | 22 | 4 | 9 | **Partiel** |
| BC-34 | GEO | 4 | 0 | 0 | 4 | 1 | 35 | **Opérationnel** — core transverse, services sous `Infrastructure/` |
| BC-35 | VTC | 13 | 13 | 7 | 33 | 6 | 17 | **Opérationnel** |
| BC-36 | TRADE | 6 | 6 | 19 | 14 | 19 | 14 | **Opérationnel** |
| BC-38 | PAIEMENTS | 4 | 2 | 7 | 10 | 0 | 4 | **Opérationnel mais non consommé** — voir §6 |

¹ **La colonne `ROUTES` est trompeuse et ne doit pas servir de preuve d'état.**
Elle ne compte que les fichiers de routes *déclarés dans le registre* ; or plusieurs
BC montent leurs routes dans un fichier portant un autre nom. Comptage réel par
fichier **[V]** : `restaurantmanager.php` 218, `travelagency.php` 291, `rh.php` 186,
`fuel_station.php` 140, `hr_extended.php` 113, `edu_manager.php` 102,
`payroll_engine.php` 93, `crm.php` 83, `health_manager.php` 63, `accounting.php` 56,
`retail.php` 44, `hospitality_manager.php` 36, `pharmacy.php` 34, `vtc.php` 33,
`communication.php` 30, `tracking.php` 29, `dashboard.php` 29, `billing.php` 28,
`delivery.php` 27, `marketing.php` 26, `integrations.php` 24, `user.php` 24,
`fundraising.php` 22, `cameras.php` 19, `showcase.php` 18, `market_public.php` 18,
`cabinet.php` 17, `catalog.php` 16, `trade.php` 14, `expense.php` 12,
`growth.php` 11, `absence.php` 10, `paiements.php` 10, `hr_app.php` 7, `sso.php` 7,
`solutions.php` 4, `catalog_public.php` 4, `geo.php` 4, `planning.php` 2 ;
`api.php` 283 et `ai.php` 19 hors modules. **Total ≈ 2 300 routes** **[V]**.

Un `ROUTES = 0` avec `CTRL > 0` signale donc un **défaut de déclaration au
registre**, pas un module mort. C'est une dette de gouvernance, pas une lacune
fonctionnelle — sauf pour BC-22, réellement absent.

---

## 2. Architecture backend

**[V]** Laravel, structure par bounded context :

```
api/app/Modules/<BC>/{Domain,Application,Infrastructure,Interfaces/Api/V1,Providers}
api/app/Core/{Auth,Tenant,Feature}      # socle transverse
api/app/Shared/{Contracts,Discovery,Geo}
api/app/AI                              # BC-23, hors Modules/
api/routes/api.php → routes/modules/*.php (39 fichiers)
```

- **Contrats entre modules** : interfaces sous `api/app/Shared/Contracts/` et
  `api/app/Modules/<BC>/Domain/Contracts/`. Le core ne référence **jamais**
  `App\Modules\*` ; l'inversion se fait par tag de conteneur. Preuve du patron :
  `app/Providers/AppServiceProvider.php` résout `tagged('public.discoverable')`
  **[V]**, et `PublicDiscoverableProvider` documente explicitement la garde
  d'isolation `#5584` (aucune dépendance Shared → Modules) **[V]**.
- **Layer purity** : garde CI `check-layer-purity` — les façades `DB`/`Log` sont
  interdites dans les classes `Application/` (règle `#6568`). Contournement
  légitime : injecter une frontière (repository / runner) plutôt qu'ajouter une
  exception à l'allowlist. **[V-prev]**
- **Actions** : convention « 1 action = 1 `execute()` » vérifiée par le check
  `Convention Actions`. `Recruitment/Application/Actions/` (12 classes) en est
  l'illustration **[V]**.
- **Événements** : `Domain/Events/` par BC + catalogue canonique
  `dev-hub/governance/event-catalogue.json` **[V]** ; outbox plateforme
  (`PlatformOutboxEvent`, `PlatformOutboxConsumer`) pour l'intégration
  asynchrone **[V]**.
- **Migrations** : trois connexions — `api/database/migrations/{tenant,public,edge}`
  (471 / 78 / 2 fichiers) **[V]**. Les tables métier vivent en **tenant**.
- **Guards** : voir §5.
- **Versionnement** : `api/openapi.yaml` est le contrat unique (~60k lignes selon
  la doc interne) **[V-prev]** ; le check `Route → OpenAPI coverage guard` échoue
  sur toute route non documentée **[V-prev]**.

**Point d'attention** : `api/routes/api.php` concentre 283 routes hors modules.
C'est le plus gros risque de couplage transverse du dépôt — à ne pas alimenter
davantage ; les nouveaux parcours doivent passer par `routes/modules/`.

---

## 3. Frontends

**[V]** Sept applications sous `front/` :

| App | Techno | Volume | Rôle | État |
|---|---|---|---|---|
| `front/web` | Next.js App Router | 451 fichiers, **171 `page.tsx`** | Business OS + surfaces publiques | **Opérationnel** |
| `front/admin-dashboard` | Vue | 106 fichiers, **0 `page.tsx`** (routing Vue) | Administration plateforme | **Opérationnel** |
| `front/marketplace` | Next.js, Tailwind 4 | 42 fichiers, 13 pages | Vitrine marchande grand public BC-17 | **Opérationnel** |
| `front/travel-web` | Next.js | 29 fichiers, 10 pages | Client final travel | **Opérationnel** |
| `front/web-offline` | Next.js + nginx | 2 fichiers, 1 page | Repli hors-ligne | **Squelette** |
| `front/zkteco-kiosk` | HTML/JS vanille | — | Pointeuse matérielle | **Opérationnel** (périmètre device) |

Surfaces publiques existantes dans `front/web/src/app` **[V]** :
`(landing)`, `(dashboard)`, `[companySlug]`, `auth`, `cagnottes`, `conversations`,
`decouvrir`, `documents`, `guardian-portal`, `kiosk`, `offline`, `order`,
`restaurants`, `share`, `shop`, `status`, `stay`, `travel`, `view`, `vitrine`.

**Constat structurant** : la « façade grand public » n'est pas un frontend unique.
Elle est aujourd'hui éclatée entre `front/web` (profils, `/decouvrir`,
`/conversations`), `front/marketplace` (achat BC-17) et `front/travel-web`
(réservation BC-24). **C'est un choix assumé du dépôt, pas un oubli** — mais il
signifie que toute nouvelle surface publique doit décider explicitement où elle
vit, au risque de dupliquer la navigation et l'authentification grand public.

Ségrégation par persona dans `front/web` : `(dashboard)` = manager/employé,
`(landing)` = marketing, racine = public/anonyme. Le cloisonnement est porté par
`src/protected-prefixes.ts` + `src/proxy.ts` **[V]**.

---

## 4. Application mobile

**[V]** Neuf applications **Flutter** sous `front/mobile_apps/` :

| App | Fichiers `.dart` | Écrans | Public |
|---|---:|---:|---|
| `leopardo_core` | 176 | 30 | Bibliothèque partagée |
| `leopardo_employee` | 59 | 23 | Employé |
| `leopardo_manager` | 54 | 18 | Manager |
| `leopardo_hr` | 22 | 4 | RH |
| `leopardo_travel_agent` | 21 | 8 | Agent de voyage |
| `leopardo_cameras` | 19 | 5 | Surveillance |
| `leopardo_platform_admin` | 16 | 0 | Superadmin |
| `leopardo_accounting` | 15 | 5 | Comptabilité |
| `leopardo_marketing` | 10 | 4 | Marketing |

**Aucune application mobile grand public.** Les 9 apps sont toutes des outils
professionnels ou internes. Le parcours client du prompt maître (§1–10) n'a donc
aujourd'hui **aucun client mobile natif** — seulement le web responsive.

**Dette CI vérifiée** : les 8 jobs `Build Debug` (Flutter) échouent sur `main`
avant tout lot, cause `minSdkVersion 24 < 26` requis par `sentry_flutter`
**[V-prev]**. Conséquence directe : **une régression mobile est invisible** en
CI. C'est le risque de régression n°1 du dépôt (§10).

---

## 5. Identité, authentification, sessions, permissions, multi-tenant

### 5.1 Guards — cinq régimes distincts **[V]**

`api/config/auth.php` :

| Guard | Driver | Provider | Usage |
|---|---|---|---|
| `web` | session | `employees` | Sessions navigateur internes |
| `sanctum` | sanctum | `employees` | API employé/manager (défaut) |
| `user_api` | sanctum | `users` | **Client grand public** |
| `super_admin_api` | sanctum | `super_admins` | Plateforme |
| `super_admin_web` | session | `super_admins` | Console plateforme |
| `travel_customer` | sanctum | `travel_customers` | Client final travel — **masqué en analyse statique** via `LEOPARDO_STATIC_ANALYSIS` **[V]** |

Le masquage de `travel_customer` est délibéré et commenté : sans lui, Larastan
calcule `$request->user()` comme l'union de tous les providers et produit un
typage faux pour le reste de l'application **[V]**. Toute nouvelle identité
grand public doit suivre ce précédent, pas ajouter un provider non masqué.

**Il existe donc déjà deux identités grand public distinctes** : `users`
(marketplace/profils publics) et `travel_customers` (travel). Le prompt maître
demande « un compte peut être client et membre d'un ou plusieurs espaces pro ».
L'état réel est **deux bases clients séparées** — sujet d'arbitrage, pas de
fusion improvisée (§9, G-05).

### 5.2 Multi-tenant

**[V]** `api/app/Core/Tenant/TenantManager.php` — isolation par **`search_path`
PostgreSQL**, pas par colonne discriminante :

- `setTenant(Company)` → `SET search_path TO <schéma>`, restauration par
  `resetToPrevious()` ;
- `withinTenant(Company, Closure)` — API de bascule scopée ;
- `withinSearchPath(string, Closure)` — **seule** API de bascule brute autorisée ;
  un `SET search_path` manuel hors de cette classe est un motif de refus ;
- `assertSafeSearchPath()` — validation avant interpolation SQL ;
- `clearTenant()` → `search_path TO public`.

L'isolation est donc **structurelle** : une requête qui sort du contexte tenant
ne voit pas les tables d'un autre tenant. C'est robuste, mais cela signifie
qu'un service qui oublie `withinTenant()` lit `public` — donc **échoue
bruyamment** plutôt que de fuiter. Bonne propriété ; à ne pas casser.

Migrations `tenant/` (471 fichiers) vs `public/` (78) confirment la séparation
**[V]**. Une table de **routage plateforme** (ex. index de conversations
publiques) va légitimement en `public/`, sans contenu métier.

### 5.3 Permissions

**[V]** Policies sous `api/app/Policies` (BC-03) et
`api/app/Modules/<BC>/Domain/Policies/` — exemple : les 10 policies de
`Communication`. Vérification côté serveur par ressource, jamais par présence
d'un rôle dans le token.

---

## 6. Module de paiement (BC-38 PAIEMENTS)

**[V]** État réel — module **substantiel et bien construit**, 41 fichiers :

- `Domain/Models/` : `PaymentTransaction`, `PaymentLedgerEntry`, `Wallet`,
  `PaymentProviderInstance`, `PaymentWebhookEndpoint`, `PaymentFeeRule`,
  `PaymentReconciliation` ;
- `Domain/Enums/` : `PaymentTransactionStatus`, `PaymentTransactionType`,
  `LedgerDirection`, `ReconciliationStatus`, `ProviderInstanceStatus`,
  `WalletOwnerType`, `FeeMode` ;
- services : `PaymentService`, `LedgerService`, `FeeRuleResolver`,
  `ProviderResolver`, `ProviderTypeRegistry`, `ReconciliationService` ;
- exceptions dédiées : `IdempotencyKeyRequiredException`,
  `InvalidTransactionTransitionException`, `UnbalancedLedgerEntryException`,
  `ImmutableLedgerEntryException`, `InvalidWebhookSignatureException`,
  `ProviderNotResolvableException`.

**Idempotence** — `PaymentService` impose une clé d'idempotence non vide
(`IdempotencyKeyRequiredException`), avec unicité en base sur le couple
`(company_id, idempotency_key)` et réutilisation de la transaction existante
**[V]** (commentaire de classe + garde `findByIdempotencyKey`). Un remboursement
dérive sa clé (`'refund:'.$idempotencyKey`) **[V]** — donc rejouable sans double
débit.

**Webhooks** — `PaymentWebhookController` routé par **token d'endpoint**,
intégrité par **signature HMAC**, **fail-closed** : sans secret configuré, tout
payload est rejeté (`InvalidWebhookSignatureException`) **[V]**. Throttle dédié
`webhooks-inbound` **[V]**.

**Trois régimes d'authentification séparés** (documentés en tête de
`routes/modules/paiements.php`) **[V]** :

1. **interne** — `paiements/internal/*`, middleware `paiements.service` (jeton
   de service, jamais une session utilisateur), idempotence obligatoire ;
2. **webhooks** — sans auth utilisateur, identité = token, intégrité = HMAC ;
3. **tenant** — `auth:sanctum` + `tenant`, le tenant voit son solde et demande un
   retrait, **il ne choisit jamais son fournisseur**.

### 6.1 Lacune critique : aucun consommateur

**Aucun fichier de `api/app/` ne contient la chaîne `paiements/internal`** hors
du fichier de routes lui-même **[V]** (`grep -rn "paiements/internal" app/` →
0 résultat autre que la déclaration). Autrement dit : le module expose une API
interne complète — crédit, débit, transfert, remboursement — que **personne
n'appelle**. Ni `RestaurantManager`, ni `Retail`, ni `Delivery`, ni `Vtc`, ni
`Trade` ne s'y branchent.

Conséquence pour Leopardo Public : **le paiement n'est branché sur aucune
commande ni réservation**. Le prompt maître demande de ne pas reconstruire ce
module et de ne pas bloquer artificiellement le premier parcours dessus — c'est
donc la bonne stratégie (parcours sans paiement d'abord), mais il faut cesser de
présenter « le paiement est déjà développé » comme « le paiement est intégré ».

### 6.2 Lacune : un seul rail, simulé

**Une seule implémentation de `PaymentRailInterface`** : `SimulatorRail` **[V]**
(`grep -rn "implements PaymentRailInterface" app/` → 1 résultat). Aucun rail
PSP réel (Stripe, CinetPay, Chargily, etc.). En production, un `cash-in` réel
passe par un simulateur.

**À valider** : `ProviderTypeRegistry` et `ProviderResolver` suggèrent une
extensibilité prévue ; l'ajout d'un rail réel est un lot à part entière, avec
analyse légale — le prompt maître interdit explicitement tout portefeuille /
conservation de fonds sans cette analyse.

**Statuts** : `PaymentTransactionStatus` + `InvalidTransactionTransitionException`
+ `ImmutableLedgerEntryException` **[V]** ⇒ transitions contrôlées et grand livre
append-only. C'est le socle correct ; **c'est l'absence de consommateur qui est
le problème, pas la qualité du module**.

---

## 7. Notifications, chat, géolocalisation, fichiers

### 7.1 Notifications — BC-13 **[V]**

9 contrôleurs, 7 classes `Application/`, 10 modèles, 76 fichiers de test.
Le BC porte à la fois `ConversationThread`/`ConversationMessage` **et** le
service de notification employé. C'est le point d'intégration naturel pour toute
notification de la passerelle publique — et **déjà** celui utilisé par le lot
livré (§8.3).

### 7.2 Chat humain — **dans BC-13, pas BC-29** **[V]

Piège d'architecture à documenter : `BC-29 COMMUNICATION` **n'est pas** le chat.
Ses modèles sont `CommunicationThread`, `CommunicationMessage`,
`CommunicationIntegration`, `CommunicationPendingReply`,
`CommunicationFollowUp*` — c'est de la **communication e-mail (Gmail sync,
relances, réponses assistées)**. `ConversationThread` vit dans
`app/Modules/Notification/Domain/Models/ConversationThread.php` **[V]**.

Routes du chat dans `api/routes/modules/rh.php` **[V]** :

- `GET /conversations`, `POST /conversations`, `POST /conversations/groups` ;
- `GET|POST /conversations/{thread}/messages` (`.whereNumber('thread')`) ;
- modération : `blocks`, `reports`, `moderation/reports/{report}/resolve` ;
- **surface publique** : `GET /conversations/public`,
  `GET|POST /conversations/public/{thread}/messages` **[V]**.

L'ordre de déclaration est significatif et commenté : les segments littéraux
(`blocks`, `reports`, `public`) sont déclarés **avant** `{thread}`, sinon le
paramètre capture le littéral **[V]**. Fragilité réelle à préserver.

Capacités présentes : pagination par curseur (`seq`), compteurs non-lus,
blocage, signalement, groupes, throttle `chat-create` **[V]**.
**Temps réel** : pas de broadcast WebSocket visible dans les routes ; `[?]` sur
l'étage broadcasting — le lot livré a retenu un polling assumé, ce qui reste la
bonne décision tant que l'hébergement ne garantit pas un socket.

### 7.3 Géolocalisation — BC-34 **[V]**

`app/Modules/Geo/` — 19 fichiers, aucune dépendance aux verticales :

- `GeoCapabilities` : détection **runtime** PostGIS, cache `capabilities_cache_ttl`,
  retour `false` si le driver n'est pas `pgsql` — dégradation propre, jamais de
  crash ;
- `HaversineDistanceCalculator` **et** `PostgisDistanceCalculator` : deux
  implémentations de `DistanceCalculatorInterface`, choix selon capacité ;
- `SearchableRegistry` : registre **opt-in fail-closed** des types
  « les plus proches » ; un type inconnu lève
  `UnknownSearchableTypeException` — **aucune table tenant n'est exposée sans
  enregistrement explicite** ;
- garde d'autorisation **par type** (closure) appliquée **uniquement au HTTP**,
  jamais aux appels internes en worker — distinction volontaire et commentée
  (`#8423`).

**Lacune vérifiée** : `config/geo.php` → `'searchables' => []` **[V]**, et
**aucune verticale n'appelle `register()` au runtime** (`grep` sur les
`Providers/*.php` → seul le binding du singleton) **[V]**.
⇒ La recherche « à proximité » existe, est testée, et **n'a aucun type
enregistré** : elle ne peut rien retourner en l'état.

### 7.4 Fichiers / stockage **[?]**

`BC-20 DOCUMENTS` (Cabinet, 17 routes) et la surface `documents` de `front/web`
existent **[V]**. Le disque utilisé (S3 ? local ?), les policies d'accès aux
pièces jointes et la rétention **n'ont pas été vérifiés** dans cette session.
Aucune conclusion n'est tirée. C'est un prérequis direct des pièces jointes dans
les fils publics (§9, G-07).

---

## 8. Marketplace, catalogue, profils publics, recherche

### 8.1 Ce qui existait avant la mission **[V]**

| Brique | Emplacement | État |
|---|---|---|
| Agrégateur de découverte | `api/app/Shared/Discovery/PublicDiscoveryService.php` | Opérationnel |
| Contrat de provider | `api/app/Shared/Contracts/Discovery/PublicDiscoverableProvider.php` | Opérationnel |
| Résolution de profil | `api/app/Shared/Discovery/PublicProfileLocator.php` | Opérationnel |
| Contrat catalogue | `App\Shared\Contracts\Catalog\PublishedProductsProvider` | Opérationnel |
| Vitrine marchande grand public | `front/marketplace` (13 pages) | Opérationnel — BC-17 |
| Client final travel | `front/travel-web` (10 pages) | Opérationnel — BC-24 |
| Pages publiques BC-25 | `front/web/restaurants`, `stay`, `order` | Opérationnel |

### 8.2 Le contrat de découverte est bien conçu **[V]**

`PublicDiscoveryService` : composition de **lecture pure**, aucune table, aucune
écriture. Invariants explicites et vérifiables dans le code :

- un `?type=` inconnu **est refusé (422)** — jamais de filtre silencieux ;
- `?near=` fourni ⇒ seules les entités géolocalisées sont retenues, dans le
  rayon, triées par distance ;
- `per_page` borné à 50, défaut 20, rayon 10 km, max 50 km ;
- **distance calculée par le core `GeoServiceContract`**, jamais un haversine
  ré-implémenté dans le service.

`PublicDiscoverableProvider` impose un **DTO public strict** : `type`, `slug`,
`name`, `category`, `city`, `country`, `description`, `image_url`, `latitude`,
`longitude`, `url` — **jamais** d'`id` interne, de `company_id`, de stock, de
marge ni de `meta`. Le `slug` **est** l'identifiant public. Fail-closed :
`is_public=true`/`status=published`, société non `suspended`/`expired`, feature
verticale active.

`supportedActions()` est la **source de vérité unique** des actions réellement
disponibles (`contact`, `booking`, `order`) — le frontend n'a aucune liste
codée en dur, donc pas de bouton inopérant par construction **[V]**. C'est
exactement ce que le prompt maître exige (« fonction indisponible = état
explicite »), et c'est déjà en place.

### 8.3 Couverture réelle : **3 verticales sur 37** **[V]**

Providers tagués `public.discoverable` (recherche `grep` exhaustive) :

| Verticale | Provider |
|---|---|
| BC-25 Restaurant | `RestaurantDiscoverableProvider` |
| BC-32 Hospitality | `HospitalityDiscoverableProvider` |
| BC-27 Showcase | `ShowcaseDiscoverableProvider` |

**34 verticales ne sont pas découvrables.** Vtc, Retail, Pharmacy, Trade,
TravelAgency, Delivery, Health, EduManager, FuelStation — toutes absentes.
C'est la lacune fonctionnelle centrale de Leopardo Public : la vitrine existe,
le rayon est presque vide.

### 8.4 Ce qui a été livré par la mission **[V-prev]**

- `/decouvrir` : `page.tsx`, `discovery-explorer.tsx`, `contact-dialog.tsx`,
  `public-discovery-api.ts` ;
- contact public : `PublicContactRequestController` + Action + Event/Listener +
  migration ;
- **messagerie** : `PublicConversationService`, contrôleur client
  (`user_api`), contrôleur boîte pro (Sanctum + tenant), 5 + 4 routes,
  2 migrations (`tenant` : extension de `conversation_threads` ; `public` :
  table de **routage** `public_conversation_index`, sans contenu de message) ;
- `/conversations` (client) et `/(dashboard)/demandes-publiques` (pro),
  `public-conversation-api.ts`, `public-conversation-inbox.ts`, i18n ×4.

Aucun nouveau BC créé, aucun second système de chat : la messagerie publique est
une **extension de BC-13**. C'est conforme au principe architectural du prompt.

### 8.5 Ce qui manque encore

| Manque | Nature |
|---|---|
| Providers publics pour 34 verticales | Implémentation métier |
| `SearchableRegistry` alimenté | Configuration + enregistrement runtime |
| Réservation / commande publique | Contrat d'intégration (étape C) |
| Identité client unifiée (`users` vs `travel_customers`) | Arbitrage d'architecture |
| Profil public enrichi (horaires, services, zones desservies) | Modèle + contrat |
| Paiement branché | Consommateur de BC-38 |

---

## 9. Lacunes vérifiées, par criticité

| # | Criticité | Lacune | Preuve | Module / fichier |
|---|---|---|---|---|
| **G-01** | **Critique** | Paiement sans aucun consommateur : crédit/débit/transfert/remboursement jamais appelés | `grep "paiements/internal" app/` → 0 appelant **[V]** | `Modules/Paiements`, tous BC métier |
| **G-02** | **Critique** | `SearchableRegistry` vide : recherche « à proximité » structurellement vide | `config/geo.php` `'searchables' => []` ; aucun `register()` runtime **[V]** | `Modules/Geo`, `config/geo.php` |
| **G-03** | **Critique** | Seules 3/37 verticales sont découvrables publiquement | 3 providers tagués **[V]** | `Modules/{RestaurantManager,HospitalityManager,Showcase}` |
| **G-04** | **Élevée** | Aucun rail de paiement réel — `SimulatorRail` unique | 1 `implements PaymentRailInterface` **[V]** | `Modules/Paiements/Infrastructure/Services/Rails/` |
| **G-05** | **Élevée** | Deux identités grand public séparées (`users`, `travel_customers`), non conçues pour cohabiter | `config/auth.php` **[V]** | `Core/Auth` |
| **G-06** | **Élevée** | 8 builds Flutter rouges ⇒ régression mobile invisible en CI | `minSdkVersion 24 < 26` (sentry_flutter) **[V-prev]** | `front/mobile_apps/*/android` |
| **G-07** | **Élevée** | Pièces jointes absentes des fils publics | Revue de confidentialité non faite **[?]** | `Modules/Notification` |
| **G-08** | **Moyenne** | BC-37 manquant dans la séquence du registre ; 37 entrées pour « 36 BC » annoncés | `bounded-context-registry.json` **[V]** | `dev-hub/governance/` |
| **G-09** | **Moyenne** | Routes de plusieurs BC non déclarées au registre (BC-10, 15, 17, 18, 27, 28 affichent `ROUTES=0`) | comptage par fichier **[V]** | registre + `routes/modules/*.php` |
| **G-10** | **Moyenne** | BC-25 `Restaurant/` quasi vide (3 fichiers) : le nom du module induit en erreur | `find Modules/Restaurant` **[V]** | `Modules/Restaurant` |
| **G-11** | **Moyenne** | `api/routes/api.php` : 283 routes hors modules, point de couplage transverse | comptage **[V]** | `api/routes/api.php` |
| **G-12** | **Faible** | BC-22 `ANALYTICS` déclaré `planned` sans répertoire | registre **[V]** | — |
| **G-13** | **Faible** | Couverture de test faible sur BC-30/31/32/33/38 (8-9 fichiers) | comptage **[V]** | verticales santé/hospitality |
| **G-14** | **Faible** | Aucune app mobile grand public | 9 apps, toutes pro **[V]** | `front/mobile_apps` |

**Ce qui n'est PAS une lacune** (à ne pas « corriger ») :
`Geo` sans contrôleur métier, `Tenant`/`Identity`/`Integration` sans surface HTTP,
`Communication` sans route `/conversations` — ce sont des choix d'architecture
corrects, pas des manques.

---

## 10. Risques de sécurité et de régression

### 10.1 Isolation multi-tenant

**Risque** : toute lecture hors `withinTenant()` s'exécute sur `public` **[V]**.
Le mode d'échec est bruyant (table absente) et non silencieux — bonne propriété.
Le vrai danger est un service qui **capture** l'échec et rend une liste vide :
la fuite devient indiscernable d'une absence de résultat.
**Recommandation** : aucun `catch` ne doit absorber une erreur de schéma.

### 10.2 Accès aux conversations

Le lot livré applique la règle correcte **[V-prev]** : `404` uniforme (jamais
`403`) pour un fil d'un autre tenant, un fil supprimé **ou** un employé non
manager — indistinguables par conception. Corollaire à ne pas casser : le `404`
ne doit **jamais** être présenté comme une panne dans l'UI (le lot livré le
respecte).

**Risque résiduel** : `public_conversation_index` (table `public`, hors tenant)
est la seule carte client → tenant. Elle doit être alimentée par le service à
chaque création de fil public **[V-prev]**. Toute nouvelle voie de création qui
l'oublie rend le fil **invisible côté client** — échec silencieux, pas une erreur.

### 10.3 Données pro non publiques

Le contrat `PublicDiscoverableProvider` interdit par construction `id`,
`company_id`, stock, marge, `meta` **[V]**. Le risque n'est donc pas le DTO
nominal mais **un provider mal écrit** : rien dans le contrat n'empêche un
provider de lire une colonne sensible et de la placer dans `description`.
**Recommandation** : un test par provider vérifiant l'ensemble **exact** des clés
retournées (liste blanche), pas seulement la présence des clés attendues.

### 10.4 Dette CI — le risque le plus sous-estimé

Checks rouges sur `main` indépendamment de tout lot **[V-prev]** :
`Lint OpenAPI contract`, `Convention Actions`, `validate-and-sync`,
`Governance Gates`, `Check PR/issue governance`, 8× `Build Debug`, 4×
`Distribute * to Firebase` (secrets absents).

Conséquence : les 4 checks **requis** peuvent être verts alors que 13 jobs sont
rouges. **Une régression mobile ou OpenAPI passe sans être vue.** C'est le vrai
bloqueur de qualité du dépôt, prioritaire sur toute nouvelle verticale.

### 10.5 Secrets et tokens

`paiements.service` (jeton de service) et les secrets HMAC des webhooks sont des
points sensibles : un secret absent doit faire **échouer** l'appel (fail-closed,
déjà implémenté **[V]**), jamais dégrader en « succès présumé ».

---

## 11. Plan d'implémentation ciblé

### Étape C — Intégrations métier

**C-1 · Alimenter la découverte (débloque G-03)** — *priorité absolue, meilleur
rapport valeur/risque*
- Ajouter un `PublicDiscoverableProvider` par verticale déjà publiable :
  **Vtc** (BC-35), **Retail** (BC-17), **Pharmacy** (BC-30), **Trade** (BC-36).
- Fichiers : `Modules/<V>/Infrastructure/Services/<V>DiscoverableProvider.php`
  + une ligne `$this->app->tag([...], 'public.discoverable')` dans le
  `ServiceProvider` du module.
- Aucun impact sur `Shared/` : c'est l'inversion prévue par le contrat.
- Test par provider : liste blanche **exacte** des clés (§10.3).

**C-2 · Enregistrer les types géo (débloque G-02)**
- `registerSearchable()` au runtime dans les providers des verticales concernées,
  avec closure d'autorisation quand le type est sensible.
- Fichiers : `Modules/<V>/Providers/*ServiceProvider.php`,
  `config/geo.php` (types non sensibles uniquement).
- Vérifier le dégradé Haversine (sans PostGIS) dans le même test.

**C-3 · Une verticale de bout en bout pour le service métier (étape 8)**
- Choisir **une** verticale dont le processus est réel : BC-25
  (`RestaurantManager`, 41 tables, 177 fichiers de test) plutôt que Retail.
- Le bouton `order`/`booking` n'apparaît que si `supportedActions()` le déclare —
  mécanisme **déjà en place** **[V]** : ne pas ajouter de liste frontend.
- Fichiers : provider de la verticale (déclaration d'action) + un contrôleur
  public qui **délègue** au service métier existant (jamais de règle dupliquée).

**C-4 · Arbitrer l'identité client (débloque G-05)** — *décision, pas code*
- Documenter `users` vs `travel_customers` : fusion, passerelle, ou statu quo
  assumé. Toute implémentation avant cet arbitrage crée une troisième identité.

### Étape D — Stabilisation

**D-1 · Réparer la CI de `main` (débloque G-06)** — *avant toute nouvelle feature*
- `minSdkVersion` 24 → 26 (`android/app/build.gradle`) sur les 8 apps Flutter.
- `ResumeOnboardingStep::read()/moveTo()` → `execute()` + sites d'appel
  (débloque `Convention Actions`).
- Secrets `FIREBASE_TOKEN` / `FIREBASE_APP_ID_SECRET_NAME` : décision
  d'infrastructure, hors code.
- `no-ambiguous-paths` sur `api/openapi.yaml` (débloque `Lint OpenAPI contract`).

**D-2 · Tests d'isolation et d'autorisation**
- Accès à un tenant tiers, conversation non autorisée, données pro non publiques
  par manipulation d'identifiant — sur chaque nouveau provider et chaque route
  publique.

**D-3 · Performance de la recherche**
- `PublicDiscoveryService` interroge **tous** les providers à chaque requête.
  Avec 4 providers c'est négligeable ; à 10+ il faut borner (`limit` par
  provider — déjà présent **[V]**) et mesurer. Ne pas optimiser avant d'avoir
  plus de 4 providers.

**D-4 · Migrations et compatibilité**
- Toute nouvelle migration `tenant` exige un `DROP SCHEMA shared_tenants CASCADE`
  avant de relancer les tests (sinon le schéma canonique masque la migration).

**D-5 · Pièces jointes publiques (G-07)**
- Prérequis : §7.4 (stockage, policies, rétention) — **à valider d'abord**.
- Ne pas exposer le mécanisme interne tel quel : un fil public n'a pas les mêmes
  ayants droit qu'un fil RH.

---

## 12. Tests exécutés

### 12.1 Dans cette session — commandes de constat uniquement

Aucun test applicatif n'a été exécuté dans cette session d'audit. Les commandes
lancées sont des lectures et des comptages :

| Commande | Objet | Résultat |
|---|---|---|
| `git status` / `git log` | état du dépôt | arbre propre, `main` @ `ab0ca5d2a` |
| `ls api/app/Modules` | répertoires BC | 37 |
| lecture `bounded-context-registry.json` | registre | 37 entrées, `BC-37` absent |
| parcours d'arborescence + `grep` | contrôles, services, modèles, routes, tests | tableau §1.2 |
| comptage routes par fichier | volume réel | ≈ 2 300 |
| `grep "paiements/internal"` | consommateurs paiement | **0** |
| `grep "implements PaymentRailInterface"` | rails | **1** (`SimulatorRail`) |
| `grep "public.discoverable"` | verticales découvrables | **3** |
| `grep register` sur `Geo` | types recherchables | **0** runtime |
| lecture `config/auth.php` | guards | 6 (dont 1 masqué) |
| lecture `TenantManager.php` | isolation | `search_path` PostgreSQL |

### 12.2 Résultats de tests **rapportés d'une session antérieure**

Exécutés lors du lot « messagerie publique », non rejoués ici **[V-prev]** :
`PublicConversationTest` 18/18, non-régression chat + contact 35/35,
backend 53/53 (239 assertions), `public-conversation-api.test.ts` 17/17,
frontend `/decouvrir` + `/conversations` 37/37, PHPStan level 8 et Pint sans
écart, `tsc --noEmit` et `eslint --max-warnings 0` propres,
`I18N_VALIDATION_OK` sur 4 locales, `protected-prefixes.test.ts` 114/114,
`public-conversation-inbox.test.ts` 14/14.

**Ces chiffres ne sont pas une validation de cet audit** : ils portent sur le lot
livré, pas sur les constats des §1–§11. Aucun test d'isolation multi-tenant
adverse n'a été rejoué ici.

---

## 13. Synthèse — vérifié / implémenté / à valider

**Vérifié dans le code (cette session)**
Architecture modulaire et contrats ; 37 BC dont `BC-37` manquant ; isolation
`search_path` ; 6 guards ; chat humain en BC-13 (et non BC-29) ; `BC-25` porté
par `RestaurantManager` ; contrat de découverte fail-closed ; 3/37 verticales
découvrables ; `SearchableRegistry` vide ; paiement sans consommateur et à rail
unique simulé ; 7 frontends (dont 4 Next.js) ; 9 apps Flutter sans client grand
public ; ≈ 2 300 routes dont 283 hors modules.

**Implémenté (lot « Leopardo Public », session antérieure)**
`/decouvrir`, contact public, messagerie client ↔ pro sur BC-13, boîte de
réception pro, 2 migrations, i18n ×4, documentation OpenAPI.

**À valider (non vérifié — aucune conclusion)**
Disque et policies de stockage (§7.4) ; étage broadcasting du chat ; secrets
Firebase ; comportement réel des rails de paiement en production ; cohérence
`users` / `travel_customers` ; couverture de test réelle des verticales santé.

**Verdict** : Leopardo Public a aujourd'hui une **colonne vertébrale solide et
réutilisable** (contrat de provider, agrégateur, isolation tenant, messagerie
adossée à BC-13) et un **rayon presque vide** (3 verticales). La prochaine
valeur n'est pas une nouvelle surface frontend : c'est **C-1** (plus de
providers) et **D-1** (CI de `main` réparée), dans cet ordre.
