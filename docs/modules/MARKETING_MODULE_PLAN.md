# Module Marketing (Ayrshare) — Plan & Suivi

Dernière mise à jour : 2026-07-19

Ce fichier suit l'avancement du module Marketing (publication sur les
réseaux sociaux via Ayrshare) à travers ses phases. Chaque phase est
livrée par PR séparée, avec CI verte, puis mergée sur `main`.

## Statut global

| Phase | Sujet | Statut | PR |
|---|---|---|---|
| 0 | Fix validation `manager_role=marketing` (Request) | ✅ Mergée | #856 |
| 1 | Schéma DB + modèles (`social_accounts`, `social_posts`) | ✅ Mergée | #857 |
| 2 | Policies, Actions, client Ayrshare, tests | ✅ Mergée | #858 |
| 3 | Cron de publication planifiée + contrôleurs/routes API | ✅ Prête (branche `codex/marketing-phase3-api-cron`) | — |
| 4 | UI web dashboard Marketing | ✅ Prête (branche `codex/marketing-phase4-web-ui`) | — |
| 5 | Onglet Marketing dans l'app mobile `leopardo_manager` | ⏳ À faire | — |

---

## Phase 0 — Fix validation (mergée, PR #856)

- `StoreEmployeeRequest.php` / `UpdateEmployeeRequest.php` : règle
  `manager_role` étendue à `in:principal,rh,dept,comptable,superviseur,marketing`.

## Phase 1 — Schéma & modèles (mergée, PR #857)

- Module `api/app/Modules/Marketing/` créé (Domain, Providers).
- Migrations tenant :
  - `2026_06_22_000001_add_marketing_to_manager_role_enum.php` (placeholder,
    documentation incorrecte — voir bug corrigé en Phase 2 ci-dessous).
  - `2026_07_16_000001_create_social_accounts_table.php`
  - `2026_07_16_000002_create_social_posts_table.php`
- Modèles `SocialAccount` / `SocialPost` (Domain/Models).
- `SocialAccountRepositoryInterface` (Domain/Contracts).
- `SocialAccountNotFoundException` (Domain/Exceptions).
- `MarketingServiceProvider` enregistré dans `bootstrap/providers.php`.
- Test `SocialAccountModelTest`.
- Stockage 100% Ayrshare : uniquement `provider_profile_ref` (chiffré),
  aucun token OAuth brut Meta/LinkedIn/X stocké.

## Phase 2 — Policies, Actions, client Ayrshare (cette PR)

### Fait

- **Bug corrigé (découvert pendant les tests)** : la migration Phase 0
  documentait "pas de changement DDL nécessaire" pour `manager_role`, mais
  sur PostgreSQL `Schema::enum()` génère une vraie contrainte `CHECK`
  (`employees_manager_role_check`) qui n'autorisait toujours pas
  `marketing`. Toute création d'un manager marketing échouait donc au
  niveau base malgré le fix de validation Laravel. Nouvelle migration :
  `2026_07_16_000003_add_marketing_to_manager_role_check_constraint.php`
  (recrée le CHECK avec `marketing` inclus, no-op sur non-pgsql).
- **Domain**
  - `Domain/Exceptions/SocialPostNotFoundException.php` (404)
  - `Domain/Exceptions/SocialAccountNotFoundException.php` (404, corrigé)
  - `Domain/Exceptions/SocialAccountInactiveException.php` (422, nouveau)
  - `Domain/Contracts/SocialPostRepositoryInterface.php` (nouveau)
- **Infrastructure**
  - `Infrastructure/Repositories/SocialAccountRepository.php`
  - `Infrastructure/Repositories/SocialPostRepository.php` (avec
    `findDuePosts()` pour le futur cron Phase 3, bypass tenant-scope
    façon `AutoCloseAttendanceCommand`)
  - `Infrastructure/Services/AyrshareClient.php` (HTTP brut via `Http`
    facade, pas de SDK — pattern `StripeService`. Méthodes :
    `isConfigured`, `createProfile`, `generateJwtLoginUrl`,
    `connectedPlatforms`, `publishPost`)
  - `Infrastructure/Services/SocialPublishingService.php` (orchestration
    `publishNow()` : résout le compte actif, appelle Ayrshare, met à jour
    le statut/erreur du post)
- **Application**
  - `Application/DTOs/CreateSocialPostDTO.php`
  - `Application/DTOs/ConnectSocialAccountDTO.php`
  - `Application/Actions/ConnectSocialAccount.php` (idempotent,
    `updateOrCreate` par `company_id`+`provider`)
  - `Application/Actions/CreateSocialPost.php` (crée uniquement des
    posts en brouillon — pas de publication automatique)
  - `Application/Actions/SchedulePost.php` (publication immédiate si pas
    de date, sinon passage en `scheduled`)
- **Policies**
  - `app/Policies/SocialAccountPolicy.php`
  - `app/Policies/SocialPostPolicy.php`
  - Restriction : rôles manager `principal` ou `marketing` uniquement.
  - Garde-fous par statut : impossible de modifier/supprimer/publier un
    post déjà `published`.
  - Enregistrées dans `AuthServiceProvider` (section "Marketing (Phase 2)").
- **Configuration**
  - `config/services.php` : bloc `ayrshare` (api_key, base_url).
  - `.env.example` : `AYRSHARE_API_KEY`, `AYRSHARE_BASE_URL`.
  - `MarketingServiceProvider::register()` : bindings
    `SocialAccountRepositoryInterface → SocialAccountRepository`,
    `SocialPostRepositoryInterface → SocialPostRepository`.
- **Tests** (21 tests, 59 assertions, tous verts en local Postgres 17) :
  - `SocialAccountPolicyTest` (5 tests)
  - `SocialPostPolicyTest` (4 tests)
  - `CreateSocialPostActionTest` (2 tests)
  - `ConnectSocialAccountActionTest` (2 tests)
  - `SchedulePostActionTest` (2 tests)
  - `SocialPublishingServiceTest` (4 tests, via `Http::fake()`)
  - `SocialAccountModelTest` (2 tests, Phase 1, toujours verts)
- **Qualité** :
  - PHPStan (`phpstan-modules.neon`, niveau module) : 0 nouvelle erreur
    introduite (36 erreurs préexistantes ailleurs, inchangées).
  - Pint (style Laravel) : tous les fichiers du module formatés, 0 issue
    restante.

### Explicitement hors scope de la Phase 2 (reporté Phase 3)

- Contrôleurs API (`SocialAccountController`, `SocialPostController`).
- Routes (`api/routes/modules/marketing.php`).
- Command console + cron `marketing:publish-scheduled-posts`
  (`bootstrap/app.php` `withSchedule()`, alerte Sentry en cas d'échec).
- Ajout du module "Marketing" à la liste validée par
  `.github/workflows/architecture-check.yml` (Module Structure
  Validator) si nécessaire.

## Phase 3 — Cron + API (prête, branche `codex/marketing-phase3-api-cron`)

Plan détaillé : `docs/archive/PLAN_ACTION/73_PLAN_MARKETING_PHASE3_API_CRON.md`.

- [x] `Application/Actions/DisconnectSocialAccount.php` (idempotent, ne
      supprime jamais la ligne — historique des posts conservé).
- [x] `Console/Commands/PublishScheduledSocialPosts.php`
      (`marketing:publish-scheduled-posts`), utilise
      `SocialPostRepository::findDuePosts()` + `SocialPublishingService`.
- [x] Enregistrement dans `routes/console.php` (fichier réellement utilisé
      par le reste du projet pour `Schedule::`, pas `bootstrap/app.php`) :
      `->everyMinute()->withoutOverlapping()->onOneServer()`.
- [x] `Interfaces/Api/V1/Controllers/SocialAccountController.php`
      (connect/disconnect/show).
- [x] `Interfaces/Api/V1/Controllers/SocialPostController.php`
      (index/store/show/update/destroy/publish — `store` planifie via
      `SchedulePost` si `scheduled_at` est fourni, la création reste
      toujours un draft en interne).
- [x] `api/routes/modules/marketing.php` + `require` dans `api/routes/api.php`
      (même empilement middleware que `dashboard.php` :
      `auth:sanctum,tenant,throttle:api,throttle:api-plan,api.manager:marketing,principal`).
- [x] Form Requests de validation (`ConnectSocialAccountRequest`,
      `StoreSocialPostRequest`, `UpdateSocialPostRequest`,
      `SchedulePostRequest`).
- [x] Tests Feature HTTP (routes + middleware `tenant`/`auth:sanctum`/
      `throttle`) : 15 tests, isolation tenant, 403 rôles non autorisés.
- [x] "Marketing" ajouté à la liste modules dans `architecture-check.yml`
      (module-structure-check) — les 5 couches existent désormais
      (Application, Domain, Infrastructure, Interfaces, Providers).
- Tests : 21 (Phase 1/2) + 21 nouveaux (Phase 3) = 42 tests Feature
  Marketing, tous verts (Postgres 17 local, même setup que CI
  `tests.yml`). PHPStan (`phpstan-modules.neon`) : 0 erreur. Pint :
  0 issue restante après formatage.
- Hors scope Phase 3 (reporté) : alerte Sentry dédiée sur échec du cron
  (le job logue déjà les échecs via `Log::error`/`Log::warning`, mais pas
  d'intégration Sentry explicite ajoutée — le reste du projet n'a pas de
  pattern établi pour ça sur les autres `Schedule::command()`).

## Phase 4 — UI Web (prête, branche `codex/marketing-phase4-web-ui`)

Plan détaillé : `docs/archive/PLAN_ACTION/74_PLAN_MARKETING_PHASE4_WEB_UI.md`.

- [x] `src/lib/client-features.ts` : nouveau module `marketing` (href
      `/social-marketing` — collision de route évitée avec la page
      vitrine publique `(landing)/marketing`), entrée `CLIENT_MODULES`,
      `ROUTE_TO_MODULE`, et cas spécial dans `hasRoleAccess()` limitant
      l'accès manager aux `manager_role` `principal` ou `marketing`
      (même pattern que `billing`/`integrations`).
- [x] `src/app/(dashboard)/social-marketing/page.tsx` (nouveau) :
  - Panneau compte social : état "non connecté" (404
    `SOCIAL_ACCOUNT_NOT_FOUND` traité comme état normal, pas une
    erreur) avec formulaire de connexion (`display_name` →
    `POST /marketing/social-account/connect`), ou état "connecté"
    (plateformes liées, statut, bouton "Déconnecter" →
    `POST /marketing/social-account/disconnect`).
  - Stats rapides (total / planifiées / publiées / échecs) une fois le
    compte connecté.
  - Formulaire de création de post : contenu texte, sélection de
    plateformes cibles (liste `SUPPORTED_PLATFORMS` dupliquée en dur,
    à garder synchronisée manuellement avec
    `StoreSocialPostRequest::supportedPlatforms()` côté API — pas
    d'endpoint de découverte des plateformes), date de planification
    optionnelle. `POST /marketing/social-posts`.
  - Liste des posts (`GET /marketing/social-posts`, pagination
    "Charger plus"), actions rapides "Publier maintenant"
    (`POST .../publish`) et "Supprimer" (`DELETE ...`) visibles
    uniquement sur les posts `draft`/`scheduled`, cohérent avec
    `SocialPostPolicy`.
  - Pas d'upload média : les posts sont texte + plateformes uniquement
    (l'API accepte `media_paths` en URLs déjà hébergées, aucun
    composant d'upload direct existant dans `front/web` — hors scope,
    lot ultérieur si demandé).
  - Réutilise `apiFetch` (`src/lib/api-client.ts`) et `ModulePageShell`
    tels quels, pas de nouveau client HTTP ni design system.
- [x] `e2e/client-feature-gates.spec.ts` : deux nouveaux tests —
      accès manager `marketing` au module (`/social-marketing`), et
      blocage rôle pour un manager non `marketing`/`principal`.
- [x] Vérification : `npm run lint` (0 warning), `npm run build`
      (Next.js, Turbopack — 0 erreur TypeScript), `npx tsc --noEmit`,
      et `client-feature-gates.spec.ts` (6/6, dont les 2 nouveaux tests)
      + smokes existants (`auth-client-smoke`, `manager-workday-smoke`,
      `client-visual-smoke`) tous verts en local (chromium).
- Hors scope Phase 4 (reporté) : upload média direct (S3), calendrier
  visuel des posts planifiés (la liste triée par date de création
  suffit pour ce lot).

## Phase 5 — Mobile (à faire)

- [ ] Onglet Marketing conditionnel dans `leopardo_manager` UNIQUEMENT,
      affiché si `employee.managerRole == 'marketing'`.
- [ ] **Ne rien ajouter** à `leopardo_employee` (aucun marqueur de rôle
      manager) — contrainte vérifiée par
      `validate-mobile-apps-split.ps1` en CI.

---

## Notes techniques persistantes

- Ayrshare : `POST https://api.ayrshare.com/api/post`, auth
  `Authorization: Bearer API_KEY` + header `Profile-Key: PROFILE_KEY`
  pour la publication par profil tenant.
- Stockage social : uniquement `ayrshare_profile_key` /
  `provider_profile_ref` (chiffré, hidden) — jamais de tokens OAuth bruts.
- CI requise sur `main` : check `Backend Coverage (PHP 8.4 + PostgreSQL 16)`.
- Process de livraison : branche → PR → CI verte → merge (jamais de push
  direct sur `main`).
