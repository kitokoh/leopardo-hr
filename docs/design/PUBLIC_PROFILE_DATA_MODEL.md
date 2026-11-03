# Couloir 16 — Modèle de données du profil public et de l'index des activités

- **Dépôt** : `kitokoh/leopardo-hr`
- **Portée** : application grand public Leopardo (canal public)
- **Statut** : spécification. **Aucune migration créée par ce document hors celles déjà livrées en M3.**
- **Lot** : M0 (complément) — modèle prescrit par `COULOIR16_PLAN.md` §2, dont le fichier n'existait pas

> **Note sur l'origine de ce document.** `COULOIR16_PLAN.md` §2 spécifie le
> modèle de données du profil public, et son §8 le liste comme livrable M0.
> `COULOIR16_CARTOGRAPHIE.md` §2.1 déclare ce fichier livré. Vérification faite
> (`git cat-file -e`, recherche plein texte sur toutes les branches) : il
> **n'existait pas**. Le plan fait foi ici ; la cartographie est corrigée dans
> la même PR. Ce document reprend la spécification du plan et l'aligne sur les
> migrations M3 réellement écrites, qui diffèrent du plan sur trois points
> (§5, §8, §9).
- **Langues** : `fr`, `en`, `ar` (RTL), `tr`
- **Ville / pays** : **jamais codés en dur** — voir §6

---

## 0. Plan de compréhension (5 lignes)

1. **La surface publique est un canal, pas un Bounded Context** : elle n'a aucune règle métier propre ; elle présente et orchestre des capacités qui appartiennent à d'autres BC.
2. **Conséquence directe sur les données** : tout ce qui est *du client* vit dans le schéma `public` ; tout ce qui est *du commerçant* reste dans le schéma du tenant. Aucun mélange.
3. **Aucune troisième identité** : le client public **est** une ligne `users`, servie par le guard `user_api` — distinct de `sanctum` (employés) mais sur la même table.
4. **Deux natures de tables, à ne jamais confondre** : les tables de **données du client** (favoris, préférences, appareils, jetons) et la table de **routage** (`public_activity_index`), qui ne contient que des références et **n'autorise rien**.
5. **Ce qui reste à créer est petit et additif** : `public_profiles` et `public_device_tokens`. Le reste (activités, favoris, historique) est **déjà livré en M3**.

---

## 1. Principe d'architecture : la frontière du schéma

```
public (schéma plateforme)              tenant (un schéma par entreprise)
├── users                    ─────┐     ├── companies / employees / roles
├── public_profiles          ← données du client, jamais métier
├── public_favorites              │     ├── restaurant_orders
├── public_search_history         │     ├── restaurant_reservations
├── public_device_tokens          │     ├── conversation_threads
├── public_activity_index    ─────┘     └── device_tokens  (tenant : personnel)
└── public_conversation_index
```

**Règle de placement** — une donnée va dans `public` si et seulement si elle
appartient au client **en tant que client**, indépendamment de tout commerçant.
Dès qu'une donnée décrit une relation avec *un* commerçant (une commande, un
fil de discussion, un rendez-vous), elle appartient au **tenant** de ce
commerçant, et `public` n'en garde qu'une **référence**.

**Preuve d'application** : `public_conversation_index`
(`api/database/migrations/public/2026_10_20_000300_8760_create_public_conversation_index_table.php`)
suit déjà exactement ce patron pour la messagerie — c'est le précédent sur
lequel M3 s'est aligné, et non une invention.

---

## 2. `users` — l'identité, et rien d'autre (existant)

Table `public.users` (`2026_05_02_100001_create_users_and_company_requests_tables.php`),
complétée par `add_phone_e164_to_users` (#8736) et `add_personal_statuses_to_users` (#5540).

| Colonne | Type | Rôle vis-à-vis du canal public |
|---|---|---|
| `id` | bigint PK | clé d'identité, référencée par toutes les tables ci-dessous |
| `email` | varchar, **UNIQUE** | identifiant de connexion principal |
| `phone_e164` | varchar(16), **UNIQUE, nullable** | connexion téléphone + pont d'identité (§7) |
| `password_hash` | varchar, nullable | nullable : un compte peut n'avoir qu'un fournisseur externe |
| `google_id` | varchar, UNIQUE, nullable | connexion Google |
| `preferred_language` | varchar(2) **defaut `fr`** | préférence de langue — **mais voir §3 : la source de vérité est `public_profiles`** |
| `status` | varchar, defaut `active` | `active` / `suspended` — bloque la connexion |
| `email_verified_at` | timestamp, nullable | preuve de vérification e-mail |
| `failed_login_attempts` / `locked_until` | int / timestamp | limitation de débit par compte |

**Décision explicite : on n'ajoute pas de colonne au client dans `users`.**
`users` est partagée avec les flux internes (demandes d'entreprise, liens
employé). Y empiler des préférences d'affichage public mélangerait deux
usages dans une table déjà sollicitée. D'où `public_profiles`.

**Ce que `users` ne porte pas, volontairement** : aucune notion de ville
habituelle, de consentement notification, ni de version de politique de
confidentialité. Ce sont des données de **produit public**, pas d'identité.

---

## 3. `public_profiles` — données du client (à créer)

```
public.public_profiles
  user_id                 bigint  PK → users.id  (cascadeOnDelete)
  preferred_locale        varchar(8)   -- fr | en | ar | tr
  approx_city             varchar(120) NULL   -- libellé issu des données, jamais une constante
  approx_country          varchar(2)   NULL   -- ISO-3166-1 alpha-2
  notifications_opt_in    boolean  NOT NULL DEFAULT false
  notifications_consent_at timestampTz NULL
  privacy_policy_version  varchar(20) NULL
  last_active_at          timestampTz NULL
  created_at / updated_at timestampTz
```

**Pourquoi une clé primaire sur `user_id` et non un `id` propre** : la relation
est 1–1 stricte avec `users` ; une PK surrogate ouvrirait la porte à deux
profils pour un même client, ce qui n'a aucun sens et compliquerait toutes
les lectures.

**Pourquoi `preferred_locale` ici alors que `users.preferred_language` existe** :
`users.preferred_language` est en `varchar(2)` — incapable de porter une
étiquette régionale (`ar-SA`, `fr-CA`) ni de distinguer un arabe RTL d'un
arabe latinisé. Le canal public a besoin de cette précision pour le rendu.
`public_profiles.preferred_locale` **prime** pour la surface publique ;
`users.preferred_language` reste la valeur par défaut à la création du profil.
Aucune des deux n'est supprimée.

**Ville approximative** : `approx_city` est un **libellé**, pas une coordonnée
et pas une clé étrangère vers un référentiel de villes. Le client peut la
laisser vide. Elle ne sert qu'à préremplir la recherche ; **elle n'est jamais
utilisée pour restreindre un résultat** (voir §6).

**Ce que cette table ne contient jamais** : latitude/longitude, adresse
postale, position historique, identifiant publicitaire, numéro de téléphone
(vive `users.phone_e164`), ni aucune donnée d'un commerçant.

### 3.1 Politique de confidentialité et consentement

`notifications_consent_at` et `privacy_policy_version` rendent le consentement
**prouvable et daté**. Un `notifications_opt_in = true` sans
`notifications_consent_at` est un état incohérent : la contrainte applicative
doit l'interdire (les deux champs s'écrivent ensemble).

---

## 4. `public_favorites` — favoris (livré M3)

```
public.public_favorites
  id           bigint PK
  user_id      bigint
  target_type  varchar(40)    -- establishment_type du provider (jamais un enum figé)
  target_slug  varchar(160)   -- le slug EST l'identifiant public
  created_at / updated_at
  UNIQUE (user_id, target_type, target_slug)
  INDEX  (user_id, created_at)
```

**Point de contrat non négociable** : le client envoie `(target_type, target_slug)`.
Il **n'envoie jamais `company_id`**. Le tenant est résolu côté serveur depuis
le slug par le provider de découverte — exactement comme
`POST /public/conversations` résout le tenant depuis `target_slug`
(`routes/modules/user.php:60-66`). Accepter un `company_id` client
permettrait d'écrire un favori dans le tenant d'autrui.

`target_type` réutilise `establishment_type` fourni par le provider : il n'y a
donc **pas de liste de catégories codée en dur** côté public. Ajouter un
provider ajoute des favoris possibles, sans migration.

---

## 5. `public_search_history` — historique de recherche (livré M3)

```
public.public_search_history
  id            bigint PK
  user_id       bigint
  query         varchar(160)
  city          varchar(120) NULL   -- libellé, jamais une coordonnée
  result_count  unsignedInteger DEFAULT 0
  searched_at   timestampTz
  created_at / updated_at
  UNIQUE (user_id, query, city)
  INDEX  (user_id, searched_at)
```

**Aucune coordonnée n'est stockée**, y compris quand la géolocalisation a été
accordée : la position ne sert qu'à trier la réponse, en mémoire. Une fuite de
cette table ne révèle donc pas où le client se trouvait.

L'unicité `(user_id, query, city)` transforme une recherche répétée en mise à
jour de `searched_at` : l'historique reste court et utile, sans doublons.

---

## 6. Ville, catégories et seuils — pilotés par les données

**Aucune ville, aucun pays, aucune catégorie n'est codé en dur**, ni ici, ni
dans les migrations, ni dans le code.

| Élément | Source de vérité | Où |
|---|---|---|
| Ville proposée | libellé renvoyé par la découverte (`approx_city` n'est qu'un préremplissage) | réponse de `/public/discover` |
| Pays | `approx_country` du profil, sinon celui du résultat choisi | `public_profiles` |
| Catégories affichées | `establishment_type` des providers découvrables | `supportedActions()` / providers |
| Seuil d'affichage d'une catégorie | `platform_settings` clé `discovery.category_min_pages` | `public.platform_settings` (existant) |

Le seuil est **réglable par ville** via la valeur JSONB
`{"default": 5, "by_city": {...}}` : la ville pilote n'apparaît donc dans
aucun fichier de code, seulement dans des données d'exploitation. Une catégorie
n'est renvoyée `visible: true` que si son compte atteint le seuil — c'est ce
qui garantit qu'aucun filtre ne mène à une liste vide.

---

## 7. `public_device_tokens` — jetons de notification (à créer)

```
public.public_device_tokens
  id            bigint PK
  user_id       bigint
  token         varchar(255)         -- jeton FCM / APNs
  platform      varchar(16)          -- ios | android | web
  locale        varchar(8)  NULL
  app_version   varchar(20) NULL
  last_seen_at  timestampTz
  revoked_at    timestampTz NULL
  created_at / updated_at
  UNIQUE (token)
  INDEX  (user_id, revoked_at)
```

**Pourquoi pas `device_tokens` du tenant** : la table existante
(`2026_05_18_000001_create_device_tokens_table.php`) vit dans un schéma
**tenant** et sert le personnel d'une entreprise. Un client public n'a pas de
tenant : y écrire supposerait de choisir arbitrairement une entreprise, ce qui
est précisément l'erreur d'architecture à éviter. Deux tables, deux
populations, deux cycles de vie.

**Unicité sur `token` seul, pas sur `(user_id, token)`** : un même appareil
peut changer de compte. Le jeton doit alors être **réassigné**, pas dupliqué —
sinon l'ancien propriétaire continuerait de recevoir les notifications du
nouveau. L'écriture fait donc un `upsert` sur `token` avec réassignation de
`user_id`.

**Révocation** : `revoked_at` plutôt qu'une suppression, pour que l'envoi
puisse constater qu'un jeton a été retiré (appareil perdu, déconnexion) sans
perdre la trace.

---

## 8. `public_activity_index` — routage, jamais autorité (livré M3)

```
public.public_activity_index
  id            bigint PK
  user_id       bigint
  company_id    uuid
  vertical      varchar(40)   -- restaurant | retail | hospitality | travel | vtc
  activity_type varchar(20)   -- order | booking
  external_ref  varchar(80)   -- référence DANS le schéma du tenant
  status        varchar(20)   -- PROJECTION, jamais la vérité
  occurred_at   timestampTz
  UNIQUE (user_id, company_id, vertical, external_ref)
  INDEX  (user_id, occurred_at)
  INDEX  (company_id, vertical, external_ref)
```

### 8.1 L'invariant à ne jamais casser

**Cette table dit *où chercher*, elle n'autorise rien.** Trois conséquences,
toutes appliquées en M3 :

1. **Une ligne d'index n'ouvre pas un accès.** Chaque lecture rejoue le
   contrôle d'appartenance *dans le schéma du tenant* via un
   `PublicActivityProvider` tagué par verticale
   (`api/app/Shared/Contracts/Discovery/PublicActivityProvider.php`).
   Une ligne écrite pour le client A pointant vers la commande du client B
   n'affiche rien.
2. **`status` est une projection.** Il est écrit au moment de l'événement de
   la verticale pour permettre tri et affichage immédiats ; il peut retarder
   d'un événement. Le statut **affiché** est celui relu côté tenant. Un index
   qui prétend `paid` sur une commande `open` affiche `open`.
3. **Aucun contenu métier.** Pas de montant, pas de lignes de commande, pas de
   nom de plat, pas d'adresse. Seulement de quoi router puis relire.

### 8.2 Alimentation par événements

L'index est alimenté par les événements des verticales, pas par le canal
public. L'unicité `(user_id, company_id, vertical, external_ref)` rend le
rejeu d'un événement **idempotent** : un doublon d'événement ne crée pas de
doublon de ligne.

### 8.3 Pourquoi une colonne `public_client_id` côté tenant (M3)

Pour que le provider puisse **prouver** l'appartenance dans son propre schéma,
`restaurant_orders` et `restaurant_reservations` reçoivent `public_client_id`
(nullable, index tenant-first), même patron que
`conversation_threads.public_client_id` (#8760). Sans elle, le provider
devrait faire confiance à l'index — exactement ce que l'invariant interdit.

---

## 9. Cycle de vie et suppression

| Événement | Effet |
|---|---|
| Suppression du compte | cascade sur `public_profiles`, `public_favorites`, `public_search_history`, `public_device_tokens` |
| Suppression du compte | `public_activity_index` **supprimé aussi** : les activités restent dans leur tenant, mais le client n'y a plus de route d'accès |
| Déconnexion d'un appareil | `revoked_at` renseigné, jeton non supprimé |
| Changement de compte sur un appareil | `upsert` sur `token`, `user_id` réassigné |
| Retrait du consentement notification | `notifications_opt_in = false`, jetons révoqués |

**Point d'attention juridique** : la suppression du compte n'efface pas les
commandes du tenant — elles appartiennent au commerçant et relèvent de ses
obligations comptables. La cascade porte sur les données **du client dans
`public`**, et sur sa capacité d'accès, pas sur les registres du commerçant.
Ce point doit être écrit tel quel dans la politique de confidentialité publiée.

---

## 10. Ce qui n'est pas dans ce modèle, et pourquoi

| Absent | Raison |
|---|---|
| Table `public_accounts` | `users` **est** le compte ; une table de plus serait une troisième identité |
| Table `public_sessions` | les sessions vivent dans `personal_access_tokens` (`public`, existant) |
| Table `public_messages` | la messagerie appartient à BC-13 ; `public_conversation_index` ne fait que router |
| Table `public_orders` | contenu métier d'une verticale → schéma tenant, jamais ici |
| Colonne `city_id` sur `users` | une ville est une donnée d'exploitation, pas une propriété d'identité |
| Table de catégories | `establishment_type` vient des providers ; une table figerait la taxonomie |

---

## 11. Récapitulatif de l'état

| Table | État | Lot |
|---|---|---|
| `public.users` | ✅ existe | antérieur |
| `public.platform_settings` | ✅ existe | antérieur |
| `public.public_conversation_index` | ✅ existe | #8760 |
| `public.public_activity_index` | ✅ livré | M3 (#9105) |
| `public.public_favorites` | ✅ livré | M3 (#9105) |
| `public.public_search_history` | ✅ livré | M3 (#9105) |
| `public.public_profiles` | ⬜ **à créer** | M3-bis / M4 |
| `public.public_device_tokens` | ⬜ **à créer** | M2 (après porte b) |

**Aucun nouveau Bounded Context n'est introduit par ce modèle.** Les deux
tables restantes sont additives, sans impact sur les guards, sur le
multi-tenant ni sur les applications existantes.

---

## 12. Vérifications exécutées

| Contrôle | Résultat |
|---|---|
| Modèle confronté aux 3 migrations M3 réellement lues | ✅ colonnes et index conformes |
| `public_device_tokens` absent de `main` | ✅ confirmé (`git cat-file -e`) |
| `public_profiles` absent de `main` | ✅ confirmé |
| Aucune ville/pays/catégorie en dur dans les migrations | ✅ vérifié |
| `users.phone_e164` UNIQUE nullable présent | ✅ `2026_10_24_000100_8736` |
| Alignement sur `public_conversation_index` | ✅ même patron de routage |
