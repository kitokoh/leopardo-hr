# Plan 73 — Module Marketing, Phase 3 : cron de publication + API REST

Date : 2026-07-19
Statut : en execution iterative
Reference : `docs/modules/MARKETING_MODULE_PLAN.md`, Phases 0-2 deja mergees
(#856, #857, #858).

## Contexte / audit

Audit du module `api/app/Modules/Marketing` (Ayrshare / publication reseaux
sociaux) au 2026-07-19 :

- **Phase 0** (validation `manager_role=marketing`) : mergee.
- **Phase 1** (schema `social_accounts` / `social_posts`, modeles Eloquent) :
  mergee.
- **Phase 2** (policies `SocialAccountPolicy`/`SocialPostPolicy`, actions
  applicatives `ConnectSocialAccount`/`CreateSocialPost`/`SchedulePost`,
  `AyrshareClient`, `SocialPublishingService`, 21 tests Feature) : mergee.
  Toutes verifiees vertes localement (Postgres 17, `php artisan test
  tests/Feature/Marketing` → 21 passed / 59 assertions).
- **Manquant / lacunes constatees** :
  - Le module n'a **pas de couche `Interfaces`** : aucun controleur, aucune
    route, aucun `FormRequest`. `MarketingServiceProvider::boot()` reference
    encore un fichier `routes/modules/marketing.php` qui n'existe pas.
    `/dashboard/marketing` existe (resume KPI generique) mais rien ne permet
    de connecter un compte Ayrshare ni de creer/planifier/publier un post
    depuis l'API.
  - **Pas de job planifie** : `SocialPostRepository::findDuePosts()` existe
    deja (prevu pour ca) mais aucune `Console\Commands\*` ni entree dans
    `routes/console.php` ne l'utilise. Les posts `scheduled` ne se publient
    jamais tout seuls.
  - **Pas d'action `DisconnectSocialAccount`** : la policy `disconnect()`
    existe mais aucune Action applicative ne l'implemente (Phase 2 a livre
    `ConnectSocialAccount` seulement).
  - Le module `Marketing` n'apparait pas dans la liste verifiee par
    `module-structure-check` (`.github/workflows/architecture-check.yml`) —
    normal puisqu'il manquait la couche `Interfaces`; a ajouter une fois
    Phase 3 livree.
  - Aucun test Feature HTTP (routes + middleware `tenant`/`auth:sanctum`/
    `throttle`) — logique, il n'y a pas encore de routes.
  - `CreateSocialPostDTO::scheduledAt` est deja parse mais jamais consomme
    par `CreateSocialPost::execute()` (qui cree toujours un draft) : le
    controleur `store()` devra explicitement appeler `SchedulePost` en
    second temps si `scheduled_at` est fourni, plutot que de faire porter
    la planification par l'action de creation.

Ce plan couvre uniquement la **Phase 3** (cron + API), scope explicite du
`docs/modules/MARKETING_MODULE_PLAN.md`. Phases 4 (UI web) et 5 (onglet mobile
`leopardo_manager`) restent hors scope de ce plan et seront traitees dans
des plans separes une fois l'API disponible.

## Lot 73.1 — Action `DisconnectSocialAccount` + commande + cron de publication

- `Application/Actions/DisconnectSocialAccount.php` : passe
  `social_accounts.status` a `revoked`, vide `last_error`, ne supprime rien
  (on garde l'historique/les posts lies). Idempotent si deja revoked.
- `Console/Commands/PublishScheduledSocialPosts.php`
  (`marketing:publish-scheduled-posts`) :
  - Utilise `SocialPostRepositoryInterface::findDuePosts()` puis
    `SocialPublishingService::publishNow()` pour chaque post du.
  - Option `--limit=` (defaut 50, aligne sur le defaut du repository).
  - Log un resume (`publies`, `echecs`) sur le modele
    `AutoCloseAttendanceCommand`.
  - N'interrompt pas le run sur l'echec d'un post individuel (un `catch`
    deja gere dans `SocialPublishingService`, mais on defend en plus au
    niveau commande contre une exception non prevue).
- Enregistrement dans `api/routes/console.php` (pas `bootstrap/app.php`,
  c'est le fichier utilise par le reste du projet pour le vrai `Schedule::`) :
  `Schedule::command('marketing:publish-scheduled-posts')->everyMinute()
  ->withoutOverlapping()->onOneServer()`.
- Tests : `PublishScheduledSocialPostsCommandTest` (poste du → publie,
  poste non du → ignore, echec Ayrshare → statut `failed` + `attempts++`).

## Lot 73.2 — `SocialAccountController` + routes + FormRequests

- `Interfaces/Api/V1/Requests/ConnectSocialAccountRequest.php` :
  `display_name` requis (string, max 120), `provider` optionnel (in:
  ayrshare — un seul fournisseur supporte pour l'instant).
- `Interfaces/Api/V1/Controllers/SocialAccountController.php` :
  - `show()` : `$this->authorize('view', ...)`, retourne le compte du
    tenant courant (ou 404 explicite si aucun compte connecte).
  - `connect(ConnectSocialAccountRequest)` : `$this->authorize('connect',
    SocialAccount::class)`, delegue a `ConnectSocialAccount::execute()`.
  - `disconnect()` : `$this->authorize('disconnect', $account)`, delegue a
    `DisconnectSocialAccount::execute()`.
  - Toutes les reponses respectent le `$hidden` du modele (jamais
    `provider_profile_ref` en clair).
- Routes (`api/routes/modules/marketing.php`, nouveau fichier) :
  ```php
  Route::middleware(['auth:sanctum', 'tenant', 'throttle:api', 'throttle:api-plan', 'api.manager:marketing,principal'])
      ->prefix('marketing')
      ->group(function (): void {
          Route::get('/social-account', [SocialAccountController::class, 'show']);
          Route::post('/social-account/connect', [SocialAccountController::class, 'connect']);
          Route::post('/social-account/disconnect', [SocialAccountController::class, 'disconnect']);
      });
  ```
  `require __DIR__.'/modules/marketing.php';` ajoute dans `api/routes/api.php`
  (bloc tenant, a cote de `modules/growth.php`).
- Tests Feature HTTP : connect (201/200), disconnect (200), acces refuse
  pour un manager non marketing/principal (403), tenant isolation (le
  compte d'un autre tenant reste invisible).

## Lot 73.3 — `SocialPostController` + routes + FormRequests

- `Interfaces/Api/V1/Requests/StoreSocialPostRequest.php` : `content`
  (required, string, max 5000), `target_platforms` (required, array,
  min:1, chaque valeur `in:` liste des plateformes Ayrshare supportees),
  `media_paths` (nullable, array, chaque valeur `string|max:2000`),
  `scheduled_at` (nullable, date, `after:now`).
- `Interfaces/Api/V1/Requests/UpdateSocialPostRequest.php` : mêmes regles
  en `sometimes`, pas de changement de `target_platforms` vide.
- `Interfaces/Api/V1/Requests/SchedulePostRequest.php` : `scheduled_at`
  (nullable — absent/`null` = publication immediate; sinon `date|after:now`).
- `Interfaces/Api/V1/Controllers/SocialPostController.php` :
  - `index()` : `$this->authorize('viewAny', SocialPost::class)`, pagine
    via `SocialPostRepository::paginateByCompany()`.
  - `store(StoreSocialPostRequest)` : `$this->authorize('create', ...)`,
    construit `CreateSocialPostDTO::fromArray()`, appelle
    `CreateSocialPost::execute()`. Si `scheduled_at` est fourni, appelle
    ensuite `SchedulePost::execute($post, Carbon::parse(...))` (voir note
    d'audit ci-dessus — la creation reste toujours un draft, la
    planification est un deuxieme appel explicite dans le controleur).
  - `show(SocialPost $socialPost)` : verifie `company_id`, `authorize('view', ...)`.
  - `update(UpdateSocialPostRequest, SocialPost $socialPost)` :
    `authorize('update', ...)` (la policy bloque deja les posts
    `published`), met a jour `content`/`media_paths`/`target_platforms`
    uniquement — pas de re-planification implicite.
  - `destroy(SocialPost $socialPost)` : `authorize('delete', ...)`,
    `SocialPostRepository::delete()`.
  - `publish(SchedulePostRequest, SocialPost $socialPost)` :
    `authorize('publish', ...)`, delegue a `SchedulePost::execute()` avec
    ou sans date.
- Routes ajoutees dans le meme `marketing.php` :
  ```php
  Route::get('/social-posts', [SocialPostController::class, 'index']);
  Route::post('/social-posts', [SocialPostController::class, 'store']);
  Route::get('/social-posts/{socialPost}', [SocialPostController::class, 'show']);
  Route::patch('/social-posts/{socialPost}', [SocialPostController::class, 'update']);
  Route::delete('/social-posts/{socialPost}', [SocialPostController::class, 'destroy']);
  Route::post('/social-posts/{socialPost}/publish', [SocialPostController::class, 'publish']);
  ```
- Tests Feature HTTP : create draft, create+schedule, update draft,
  update refuse sur post publie (403 via policy), delete draft, publish
  immediat (mock `Http::fake` Ayrshare), publish planifie (statut
  `scheduled`), pagination, isolation tenant (post d'un autre tenant →
  404), role non autorise (403).

## Lot 73.4 — Qualite, architecture-check, documentation

- `phpstan-modules.neon` : verifier 0 nouvelle erreur sur les nouveaux
  fichiers (`phpstan-bootstrap.php` autoloade Interfaces automatiquement,
  pas d'ajustement attendu sauf erreur reelle).
- Pint : formatter tout le module apres les 3 lots precedents.
- `.github/workflows/architecture-check.yml` (`module-structure-check`) :
  ajouter `Marketing` a la liste des modules verifies (le module aura
  desormais ses 5 couches : Application, Domain, Infrastructure,
  Interfaces, Providers).
- Mettre a jour `docs/modules/MARKETING_MODULE_PLAN.md` : passer la ligne Phase 3 a
  "Mergee" avec le detail livre, inchange pour Phase 4/5 (`A faire`).
- Executer la suite complete `php artisan test tests/Feature/Marketing`
  (attendu : 21 tests Phase 1/2 + nouveaux tests Phase 3, tous verts) avant
  chaque push de lot.

## Notes de sequencement

- Chaque lot ci-dessus correspond a un commit/push separe sur la branche
  de travail (`codex/marketing-phase3-api-cron` ou equivalent), dans
  l'ordre 73.1 → 73.2 → 73.3 → 73.4, avec les tests du lot verts avant de
  passer au lot suivant.
- Pas de creation de PR/merge automatique dans le cadre de cette execution
  (contrainte d'environnement) : les commits sont pousses directement sur
  la branche de travail dediee ; un merge sur `main` reste une decision
  humaine separee, conformement au process de livraison documente dans
  `docs/modules/MARKETING_MODULE_PLAN.md` ("branche → PR → CI verte → merge").
