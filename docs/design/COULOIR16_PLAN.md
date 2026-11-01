# Couloir 16 — Application grand public Leopardo (M0 : architecture et contrat)

- **Branche** : `feat/couloir16-public-app-lot-a`
- **Base** : `audit/identity-access-control-foundation` @ `a889a77f8`
- **Statut** : M0 — dossier d'architecture. **Aucune ligne de code produit sensible.**
- **Ville pilote** : `[VILLE]`, `[PAYS]` — catégorie moteur `restaurants`
- **Langues** : `fr`, `en`, `ar` (RTL), `tr`

---

## 0. Plan de compréhension (5 lignes)

1. **Ce n'est ni une verticale ni une horizontale, c'est un canal** : la surface publique n'a aucune règle métier propre ; elle présente et orchestre des capacités qui appartiennent déjà à d'autres Bounded Contexts.
2. **La découverte existe déjà et est correctement conçue** : `GET /api/v1/public/discover` agrège des providers tagués `public.discoverable`, et `supportedActions()` est la source unique des boutons — aucun `BOOKING_TYPES` codé en dur côté front.
3. **Le compte public existe déjà** : guard `user_api` (provider `users`), distinct de `sanctum` (employés) — la séparation demandée par le propriétaire est donc déjà en place, pas à créer.
4. **La messagerie publique existe déjà** : `/api/v1/public/conversations` + `public_conversation_index` (table de **routage** en schéma public, sans contenu métier) — exactement le patron que la règle d'architecture n°1 impose pour les activités.
5. **Ce qui manque n'est pas l'ossature mais quatre briques précises** : l'app Flutter `leopardo_public` (absente), le seuil de catégories configurable (aucun `platform_settings`), l'action `booking` (retirée faute de contrat public), et la preuve exécutée des 8 cas du pont d'identité.

---

## 1. Inventaire réel des endpoints existants (vérifié au HEAD)

### 1.1 Découverte et fiche — ✅ existant

| Méthode | Route | Guard | Preuve |
|---|---|---|---|
| GET | `/api/v1/public/discover` | aucun (`throttle:shop-public`) | `routes/api.php:368` |
| GET | `/api/v1/public/restaurant/shop/menu` | jeton boutique signé | `routes/api.php:352` |
| POST | `/api/v1/public/restaurant/shop/orders` | jeton boutique signé | `routes/api.php:353` |
| GET | `/api/v1/public/restaurant/shop/orders/{reference}` | jeton boutique signé | `routes/api.php:354` |
| GET | `/p/{slug}` (SSR HTML) | aucun | `routes/modules/pages_public.php:27` |
| GET | `/api/v1/pages/by-host` | aucun | `routes/modules/pages_public.php:31` |

Le contrat de découverte est **strict** : `PublicDiscoverableProvider` interdit `id` interne, `company_id`, stock et marge ; le `slug` **est** l'identifiant public (`app/Shared/Contracts/Discovery/PublicDiscoverableProvider.php:20-24`).

### 1.2 Contact et messagerie — ✅ existant

| Méthode | Route | Guard | Preuve |
|---|---|---|---|
| POST | `/api/v1/public/contact-requests` | aucun (`throttle:shop-public`) | `routes/api.php:377-378` |
| GET | `/api/v1/public/conversations` | `auth:user_api` | `routes/modules/user.php:76` |
| POST | `/api/v1/public/conversations` | `auth:user_api` | `routes/modules/user.php:77` |
| GET | `/api/v1/public/conversations/{companyId}/{thread}` | `auth:user_api` | `routes/modules/user.php:78-79` |
| GET/POST | `.../{thread}/messages` | `auth:user_api` | `routes/modules/user.php:80-83` |

**Point d'architecture confirmé** : le tenant n'est **jamais** transmis par le client pour démarrer un fil — il est résolu côté serveur depuis `target_slug` (`routes/modules/user.php:60-66`). C'est le patron à réutiliser pour les activités.

### 1.3 Compte public — ✅ existant (partiel)

| Méthode | Route | Guard | Preuve |
|---|---|---|---|
| POST | `/api/v1/user/register` | public (`throttle:auth-sensitive`) | `routes/modules/user.php:20` |
| POST | `/api/v1/user/login` | public | `routes/modules/user.php:21` |
| POST | `/api/v1/user/google-signin` | public | `routes/modules/user.php:22` |
| GET | `/api/v1/user/me` | `auth:user_api` | `routes/modules/user.php:26` |
| POST | `/api/v1/user/logout` | `auth:user_api` | `routes/modules/user.php:29` |
| POST | `/api/v1/auth/login-code/request` | public | `routes/api.php:146` |
| POST | `/api/v1/auth/login-code/verify` | public | `routes/api.php:147` |

### 1.4 Ce qui manque — vérifié, pas supposé

| Manque | Preuve du manque | Impact couloir 16 |
|---|---|---|
| **App Flutter `leopardo_public`** | `front/mobile_apps/` ne contient que `leopardo_core`, `leopardo_employee`, `leopardo_manager`, `leopardo_hr`, `leopardo_accounting`, `leopardo_cameras`, `leopardo_marketing`, `leopardo_platform_admin`, `leopardo_travel_agent` — **`leopardo_public` absent** | M1+ bloqué côté mobile ; la PWA web couvre V0 |
| **Seuil de catégories configurable** | aucun modèle `PlatformSetting`, aucune table `platform_settings` dans `database/migrations/public/` | Onglet Accueil : les catégories ne peuvent pas être filtrées par seuil |
| **Action `booking`** | `RestaurantDiscoverableProvider::supportedActions()` retourne `['contact','order']` — `booking` **retiré** le 2026-10-21 faute de contrat public (`:35-48`) | Fiche commerce : pas de bouton « Réserver » pour les restaurants, et c'est **correct** (aucun bouton mort) |
| **OTP par téléphone** | `LoginCodeController:55` valide `email` uniquement | Parcours critique « Contacter » (téléphone + code) non couvert |
| **Favoris / activités / historique** | aucune route `favorite` sous `user_api` ; `marketplace_favorites` existe mais pour la marketplace retail, guard distinct | Onglet Activités vide |
| **Jetons de notification côté public** | `device_tokens` vit en schéma **tenant** (`2026_05_18_000001_create_device_tokens_table.php`) ; aucune route publique | Push M2 bloqué |
| **Catégories par ville** | `pages` n'a pas de colonne `category` ; la catégorie vient de `establishment_type` côté provider | Seuil par catégorie à définir sur `establishment_type` |

---

## 2. Modèle de données proposé (M0 — spécification, pas de migration)

### 2.1 Profil public — schéma `public`, jamais dans un tenant

Conformément à la règle d'architecture n°1, les données propres au client vivent en schéma public.

```
public.public_profiles
  user_id            bigint PK → users.id (guard user_api)
  preferred_locale   varchar(8)   -- fr|en|ar|tr
  approx_city        varchar(120) nullable
  approx_country     varchar(2)   nullable
  notifications_opt_in boolean default false
  consent_at         timestamptz nullable
  created_at / updated_at
```

**Justification de ne pas créer de table** : `users` porte déjà `phone_e164` (#8736) et `personal_statuses`. Un `public_profiles` séparé n'est justifié que par les préférences d'affichage et le consentement notification — pas par l'identité. **Aucune troisième identité.**

### 2.2 Index des activités — références seulement

Patron exact de `public_conversation_index` (`2026_10_20_000300_8760`) : identifiants et statuts, **jamais** de contenu métier.

```
public.public_activity_index
  id                 bigint PK
  user_id            bigint      -- users.id
  company_id         uuid        -- tenant hôte
  vertical           varchar(40) -- restaurant|retail|hospitality|travel|vtc
  activity_type      varchar(20) -- order|booking
  external_ref       varchar(80) -- référence DANS le schéma tenant
  status             varchar(20) -- projection, jamais la vérité
  occurred_at        timestamptz
  unique (user_id, company_id, vertical, external_ref)
  index  (user_id, occurred_at desc)
```

**Invariant non négociable** : cette table **n'autorise rien**. Chaque lecture rejoue le contrôle d'accès dans le contexte tenant — exactement comme `PublicConversationIndexRepository` le documente (`:22-26`).

### 2.3 Seuil de catégories — `platform_settings`

```
public.platform_settings
  key         varchar(120) PK
  value       jsonb
  description text nullable
  updated_by  bigint nullable
  created_at / updated_at
```

Clé : `discovery.category_min_pages` → `{"default": 5, "by_city": {"[VILLE]": 5}}`.

---

## 3. Contrat OpenAPI des endpoints manquants (spécification)

Les endpoints **existants** sont déjà documentés dans `api/openapi.yaml` (`/public/discover:5758`, `/public/contact-requests:5900`, `/public/conversations:5993`, `/user/register:4386`, `/user/login:4421`). Seuls les manques sont spécifiés ici.

### 3.1 `GET /api/v1/public/discovery/categories`

```yaml
parameters:
  - name: city
    in: query
    schema: { type: string, maxLength: 120 }
responses:
  '200':
    content:
      application/json:
        schema:
          type: object
          properties:
            data:
              type: array
              items:
                type: object
                properties:
                  key:        { type: string }   # establishment_type
                  label:      { type: string }
                  count:      { type: integer }
                  visible:    { type: boolean }  # count >= seuil
            meta:
              type: object
              properties:
                threshold: { type: integer }
```

**Règle** : une catégorie n'est renvoyée avec `visible: true` que si `count >= threshold`. Le seuil vient de `platform_settings`, jamais d'une constante.

### 3.2 `GET /api/v1/user/favorites` · `POST` · `DELETE /{type}/{slug}`

Guard `auth:user_api`. Le favori stocke `(target_type, target_slug)` — **jamais** `company_id` fourni par le client.

### 3.3 `GET /api/v1/user/activities`

Guard `auth:user_api`. Lit `public_activity_index` puis, pour chaque ligne, ouvre le contexte tenant et rejoue le contrôle d'accès. Un module sans contrat n'apparaît pas.

### 3.4 `POST /api/v1/user/device-tokens` (public)

Guard `auth:user_api`. Table `public.public_device_tokens` — **pas** `device_tokens` (tenant).

### 3.5 `POST /api/v1/user/login-code/request` — extension `phone_e164`

Déjà spécifié dans `docs/design/PUBLIC_ACCOUNT_API_CONTRACT.md` §2.3. **Dépend du lot L1** (envoi OTP fiable).

---

## 4. Les 8 cas du pont d'identité — état et preuve

| # | Cas | État | Preuve |
|---|---|---|---|
| 1 | E-mail seul | ✅ couvert | `UserAuthTest::test_register_creates_user_and_returns_bearer_token` |
| 2 | Téléphone seul | ❌ **manquant** | `LoginCodeController:55` n'accepte que `email` |
| 3 | Compte client agence de voyage (pont téléphone vérifié) | ❌ **manquant** | `travel_customer_accounts` hors graphe `users` |
| 4 | Même personne, plusieurs métiers | ⚠️ partiel | `public_conversation_index` le permet ; aucun test |
| 5 | Client **et** employé — deux comptes séparés | ✅ **prouvé** | `IdentityAccessControlAuditTest` (guards disjoints) |
| 6 | Numéro changé / réattribué | ❌ **manquant** | aucun flux de récupération |
| 7 | SMS indisponible → alternative e-mail | ❌ **manquant** | `LogOnlySmsCodeSender` court-circuite l'e-mail |
| 8 | Appareil perdu → révocation | ⚠️ partiel | `logout` révoque le jeton courant ; pas de `logout-all` |

**Aucun de ces cas n'est déclaré « réussi » sans exécution.** Les cas 1 et 5 sont exécutés dans ce lot (voir §5).

---

## 5. Preuves d'exécution de ce lot

Environnement : PHP 8.4.27, PostgreSQL 15.19, base `leopardo_test`, `CI=true`.

```
$ CI=true php artisan test tests/Feature/User/UserAuthTest.php
  Tests:    12 passed (32 assertions)
  Duration: 15.94s
```

**Prérequis de reproductibilité découvert** : `phpunit.xml:45-50` pointe `leopardo_test` avec le rôle `leopardo_user`, mais `.env` pointe `postgres`/`leopardo`. Sans `CI=true`, `tests/TestCase.php:100-108` force l'hôte `pgsql` → `could not translate host name "pgsql"`. Et la base doit être migrée par `leopardo:migrate` (pas `migrate`), sinon `relation "company_requests" does not exist`.

---

## 6. Portes (gates) — rien ne franchit sans accord explicite

| Porte | Contenu | État |
|---|---|---|
| **(a)** | Fusion de PR | **Fermée** — aucune PR de ce couloir n'est fusionnée |
| **(b)** | M2 (contact + compte + messages + push) | **Fermée** — attend le lot L1 (OTP fiable) et le contrat de L4 |
| **(c)** | Dépense nouvelle (comptes développeur Firebase / Amazon Appstore) | **Fermée** — aucune dépense engagée |

---

## 7. Hors périmètre de cette session — assumé et écrit

- **App Flutter `leopardo_public`** : non scaffoldée. Le dossier n'existe pas ; le créer engagerait `leopardo_core`, la CI Flutter (minSdk 26, #8776) et une chaîne de build absente du sandbox. **Reporté au couloir suivant.**
- **Maquettes 360 px et RTL** : non produites ici (livrable design, pas architecture).
- **Implémentation des endpoints §3** : spécifiés, non codés — M0 est un dossier de contrat.
- **Action `booking`** : reste retirée tant qu'aucun contrat public de réservation n'existe. La réintroduire sans contrat produirait exactement le bouton mort que #8761 interdit.

---

## 8. Ce qui suit (couloir de continuation)

1. Scaffold `front/mobile_apps/leopardo_public` sur `leopardo_core` (M1).
2. Implémentation de `platform_settings` + `GET /public/discovery/categories` (lot C de ce couloir, spécifié §2.3/§3.1).
3. Les 6 cas de pont d'identité manquants (§4 : 2, 3, 4, 6, 7, 8) — dépendent de L1 et L4 de l'audit IAM.
4. Maquettes 360 px + RTL, budget de poids images.
