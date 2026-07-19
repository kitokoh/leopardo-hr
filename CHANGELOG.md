#  CHANGELOG - LEOPARDO RH 
# Format : Keep a Changelog (keepachangelog.com) 
# Versioning : Semantic Versioning (semver.org) 


## [4.23.4] - 2026-07-19

### Changed
- **CI/CD : durcissement supply-chain (P1) + deduplication setup PHP/Flutter (P2)**, suite a `AUDIT_CICD_2026-07-19.md` :
  - Pinning par SHA des actions tierces non-GitHub a plus haut risque (`trufflesecurity/trufflehog`, `shivammathur/setup-php`, `subosito/flutter-action`, `treosh/lighthouse-ci-action`, `wzieba/Firebase-Distribution-Github-Action`, `dawidd6/action-send-mail`), avec commentaire de version en clair pour faciliter les futures revues Dependabot.
  - Uniformisation `actions/checkout` et `actions/upload-artifact` sur une version stable commune dans tous les workflows.
  - Nouvelles actions composites reutilisables `.github/actions/setup-backend-db` (PHP + PostgreSQL + Redis + bootstrap multi-tenant `shared_tenants`) et `.github/actions/setup-flutter-android`, qui remplacent ~360 lignes dupliquees entre `tests.yml` (x2 jobs), `coverage-gate.yml`, `backend-jobs-ci.yml`, `mobile-apps-ci.yml`, `mobile-distribute.yml`, `deploy-main.yml`. Suppression des reusable workflows morts `_setup-php.yml`/`_setup-flutter.yml` (jamais appeles).
  - Voir `PLAN_ACTION_CICD_2026-07-19.md` pour le suivi detaille P0-P4.

## [4.23.3] - 2026-07-19

### Security
- **Resolution des 34 alertes Dependabot** (11 high, 16 moderate, 7 low) ouvertes depuis l'activation :
  - `api` (composer) : `symfony/yaml` 8.0.8 -> 8.1.1 (ReDoS `Parser::cleanup()`, exponential memory allocation via alias recursion).
  - `api` (npm, tooling Vite assets) : `form-data` corrige (CRLF injection via noms de champs/fichiers multipart non echappes).
  - `front/web` (vitrine Next.js) : `npm audit fix` (`form-data`, `ws`, `js-yaml`) + `postcss` fixe a `>=8.5.10` via `overrides` (XSS `</style>` non echappe).
  - `front/admin-dashboard` (Vue/Vite) : `npm audit fix` (`form-data`, `ws`, `js-yaml`) ; `vite` 5.4.21 -> 6.4.3 et `@vitejs/plugin-vue` 4.5.2 -> 5.2.4 (esbuild dev-server SSRF, path traversal `.map`, fuite hash NTLMv2 sur Windows) ; suppression de la dependance `vue-echarts` non utilisee (aucun import dans `src/`), ce qui a permis de monter `echarts` 5.6.0 -> 6.1.0 (XSS).
  - `front/web-offline` (PWA offline, export statique) : `next` 14.2 -> 16.2.10, `eslint` 8 -> 9, `eslint-config-next` correspondant (SSRF via upgrade WebSocket, bypass middleware i18n, DoS Server Components, XSS via CSP nonces/scripts `beforeInteractive`, cache poisoning, HTTP request smuggling dans les rewrites, croissance illimitee du cache d'images) ; `postcss` fixe a `>=8.5.10` via `overrides` ; ajout d'un `.gitignore` manquant pour ce package.
  - Verification : `npm audit --audit-level=low` et `composer audit` a 0 resultat sur tous les packages concernes ; `npm run build` verifie localement pour les 4 packages frontend.

## [4.23.2] - 2026-07-16

### Fixed
- **CI/Securite : alertes CodeQL high sur `deploy-main.yml`** : `github.event.workflow_run.head_branch` etait interpole directement dans un bloc `run:` shell (risque de cache-poisoning/injection) ; deplace vers une variable d'environnement (`WR_HEAD_BRANCH`). Le trigger `workflow_run` (privilegie, acces secrets) ne verifiait pas que le run d'origine provenait bien du repo de base (et non d'un fork) ; ajout d'une verification `head_repository == github.repository` en plus des checks existants conclusion/event/branch.
- **CI : permissions GITHUB_TOKEN manquantes** : ajout d'un bloc `permissions: contents: read` explicite sur `architecture-check.yml`, `i18n-enterprise.yml` et `lighthouse.yml` (alertes CodeQL `actions/missing-workflow-permissions`).
- **`api/phpstan-modules.neon` ne chargeait pas l'extension Larastan** : contrairement a `api/phpstan.neon`, l'include `vendor/larastan/larastan/extension.neon` etait absent, privant PHPStan de la connaissance des scopes Eloquent locaux (`scopeActive()` -> `active()`, `scopeForCountry()` -> `forCountry()`, `scopeForYear()` -> `forYear()`, etc.) et des relations magiques Eloquent. Cause racine des 36 erreurs "Call to an undefined method" qui faisaient echouer le job "PHPStan — Modules Architecture" sur `main` et sur PR #858.

### Security
- Activation de Dependabot alerts sur le repo (34 vulnerabilites detectees a l'activation : 11 high, 16 moderate, 7 low — suivi separe requis).
- Branch protection `main` : revue obligatoire (1 approbation) desormais requise pour les contributeurs non-admin avant merge.
### Added
- **Module Marketing (Phase 2) : policies, actions applicatives, client Ayrshare** : construit sur le schema de la Phase 1 (`social_accounts`/`social_posts`).
  - `Domain/Contracts/SocialPostRepositoryInterface.php` + implementations `Infrastructure/Repositories/{SocialAccountRepository,SocialPostRepository}.php` (isolation tenant standard, `findDuePosts()` prevu pour le futur job de publication planifiee, Phase 3).
  - `Infrastructure/Services/AyrshareClient.php` : client HTTP brut (pattern `StripeService`, pas de SDK) pour l'API Ayrshare (`POST /api/post`, `/api/profiles/profile`, `/api/profiles/generateJWT`), auth `Bearer` + header `Profile-Key` par tenant.
  - `Infrastructure/Services/SocialPublishingService.php` : orchestre la publication (`publishNow()`), resout le compte social actif du tenant, met a jour le statut/erreur du post.
  - Actions applicatives `ConnectSocialAccount` (idempotente), `CreateSocialPost` (cree uniquement des brouillons), `SchedulePost` (publication immediate ou planification).
  - `SocialAccountPolicy`/`SocialPostPolicy` : reservent la gestion des reseaux sociaux aux managers `principal`/`marketing`, avec garde-fous d'etat (impossible de modifier/supprimer/republier un post deja publie). Enregistrees dans `AuthServiceProvider`.
  - Config `services.ayrshare` (`AYRSHARE_API_KEY`, `AYRSHARE_BASE_URL`).
  - 21 tests Feature (59 assertions) couvrant policies, actions et publication (via `Http::fake()`).

### Fixed
- **Bug latent decouvert en ecrivant les tests Phase 2 : contrainte CHECK Postgres bloquait toujours `manager_role = 'marketing'`** : la migration `2026_06_22_000001_add_marketing_to_manager_role_enum.php` (Phase 0) affirmait a tort qu'aucun changement DDL n'etait necessaire car la colonne serait un simple VARCHAR nullable. En realite, sur PostgreSQL, `Schema::enum()` (utilise dans `2026_04_01_000101_create_employees_table.php`) genere une colonne VARCHAR **accompagnee d'une contrainte CHECK** enumerant les valeurs autorisees, jamais mise a jour pour inclure `marketing`. Consequence : meme apres le fix de validation Laravel (Phase 0), toute creation/mise a jour d'un employe avec `manager_role = 'marketing'` echouait au niveau base (`employees_manager_role_check` violation). Nouvelle migration `2026_07_16_000003_add_marketing_to_manager_role_check_constraint.php` recree la contrainte avec `marketing` inclus (no-op sur les drivers non-pgsql).

## [4.23.1] - 2026-07-16

### Added
- **Module Marketing (Phase 1) : schema et modeles de base** : creation du module `api/app/Modules/Marketing/` (Domain/Providers), suivant le pattern DDD des modules existants (Growth, Notification).
  - Migrations tenant `create_social_accounts_table` et `create_social_posts_table`.
  - `social_accounts` : connexion d'un tenant a un profil d'agregateur de publication (Ayrshare par defaut). Ne stocke volontairement aucun token OAuth Meta/LinkedIn/X brut — uniquement une reference chiffree (`provider_profile_ref`, cast `encrypted`, `hidden` en serialisation) au profil agregateur, qui gere lui-meme le cycle de vie OAuth. Reduit la surface d'exposition par rapport a un stockage de tokens par plateforme.
  - `social_posts` : contenu, medias, plateformes cibles, statut (`draft|scheduled|publishing|published|failed`), planification, tracking des tentatives et erreurs. Prevu pour etre consomme par le futur job `PublishScheduledSocialPost` (Phase 4).
  - Modeles Eloquent `SocialAccount`/`SocialPost` avec `BelongsToCompany` (isolation tenant standard du projet), casts, relation `hasMany`/`belongsTo`.
  - `MarketingServiceProvider` enregistre dans `bootstrap/providers.php`.
  - Tests Feature (`SocialAccountModelTest`) verifiant migrations + comportement modeles + chiffrement au repos. `phpstan-modules.neon` : 0 erreur sur le nouveau module. Formate via Pint.
  - Pas encore d'endpoints API/Policies/UI (Phases 2-6 a suivre).

## [4.23.0] - 2026-07-16

### Fixed
- **Role manager `marketing` invalidable via l'API malgre le support modele existant (Module Marketing - Phase 0)** : la migration `2026_06_22_000001_add_marketing_to_manager_role_enum.php` documentait deja `manager_role = 'marketing'` (colonne VARCHAR) et `Employee::isMarketing()` existait deja dans le modele, mais `StoreEmployeeRequest`/`UpdateEmployeeRequest` validaient toujours `manager_role` avec `in:principal,rh,dept,comptable,superviseur` — sans `marketing`. Il etait donc impossible de creer/mettre a jour un manager avec ce sous-role via `POST /api/v1/employees` ou `PATCH /api/v1/employees/{id}`. Ajout de `marketing` a la liste autorisee dans les deux Requests. Prepare la Phase 1 du futur module Marketing (gestion des reseaux sociaux du tenant).

## [4.22.8] - 2026-07-12

### Added
- **Drip Email onboarding (Lot 2 - P2)** : `SendTrialDripEmailJob` dispatche automatiquement 3 emails de nurturing (J+1, J+3, J+7) dès qu'une entreprise trial est provisionnée via `SelfServiceTrialController`. Le job vérifie que le statut est encore `trial` avant envoi et retente 3 fois avec backoff de 5 min.
- **Modèle `OnboardingProgress`** : nouveau modèle Eloquent `App\Modules\HR\Domain\Models\OnboardingProgress` avec migration `2026_07_12_115602_create_onboarding_progresses_table`. Scoppé par `company_id` + `employee_id`, stocke `current_step`, `completed_steps` (JSON), `is_completed` et `metadata`. Relation `hasOne` ajoutée sur `Employee`.
- **Wizard Onboarding mobile manager** : `onboarding_screen.dart` refactorisé en wizard premium — barre de progression animée (TweenAnimationBuilder), icônes par étape, badge `Requis`, boutons `Marquer complété` / `Passer` inline, état d'erreur avec retry. Corrige le bug de routing : les appels utilisent désormais `step.key` (String) au lieu de `step.id` (int) pour matcher la route API `PATCH /onboarding-setup/{stepKey}/complete`.
- **Modèle Flutter `OnboardingStep` enrichi** : expose `status`, `order`, `required` ; `completed` et `skipped` deviennent des getters dérivés de `status`. Lit `step_key` de l'API (avec fallback `key`).
- **Repository `OnboardingRepository` étendu** : méthode `getProgress()` (GET `/onboarding-setup/progress`), `completeStep(String stepKey)` et `skipStep(String stepKey)` utilisent PATCH — méthode correcte déclarée dans `billing.php`.
- **k6 corrigé** : `onboarding-trial-stress.js` cible désormais `/onboarding-setup/checklist` et `/onboarding-setup/progress` (routes réelles), ajoute un check sur `progress 200`.

### Fixed
- **Import `App\Models\Company` / `App\Models\Employee` invalides** : `SendTrialDripEmailJob`, `TrialDayOneMail`, `TrialDayThreeMail`, `TrialDaySevenMail` utilisaient les alias génériques (`App\Models\*`) absents dans l'architecture DDD. Remplacés par `App\Core\Tenant\Domain\Models\Company` et `App\Core\Auth\Domain\Models\Employee`.

## [4.22.7] - 2026-07-05

### Fixed
- **CI cassee sur `main` : ParseError PHP dans les regles de paie pays** : 7 fichiers sous `api/app/Modules/Payroll/Infrastructure/Services/CountryRules/` (Algerie, France, Maroc, Senegal, Tunisie, Turquie, classe abstraite) declaraient `namespace App\\Modules\Payroll...` (double backslash, token PHP invalide), plus leurs 7 shims de compatibilite sous `api/app/Services/Payroll/CountryRules/` avec un namespace incomplet (`App\Services\;`) et un `class_alias` mal echappe. Corrige les 14 fichiers ; resout les 4 echecs `PayrollCountryRulesTest`.
- **Bug metier : la pause (`break_minutes`) n'etait jamais deduite des heures travaillees** : `AttendanceLog` a une colonne `schedule_id` (FK) mais aucune relation Eloquent `schedule()`, alors que `AttendanceService::checkOut()`/`recalculateLog()` accedent partout a `$log->schedule`. Sans la relation, Eloquent renvoie silencieusement `null` au lieu d'echouer, donc la pause configuree sur le planning n'etait jamais soustraite du temps travaille pour aucune entreprise en production. Ajout de la relation manquante `schedule(): BelongsTo`. Restaure aussi les casts `decimal:2`/`decimal:8` sur `hours_worked`/`overtime_hours`/`gps_lat`/`gps_lng` (degrades en `float` pendant la migration DDD), retrouvant le contrat string (`'8.00'`, `'36.77000000'`) deja verifie par les tests Feature (`CheckOutTest`, `ManualUpdateTest`) et consomme par les apps mobiles via `double.tryParse()`.
- **Warning `Undefined array key "net_imposable"` dans `SocialDeclarationGenerator::generateDsnFr()`** : `net_imposable` et `hours_worked` sont des champs optionnels dans le payload employe mais etaient lus sans fallback. Ajout de valeurs par defaut (`net_imposable` -> `net_salary`, `hours_worked` -> `0.0`).
- **CI "Architecture Quality" (PHPStan modules) cassee sur `main` par le meme bug de namespace double-backslash** : 10 fichiers additionnels introduits par des merges recents avaient `namespace App\\Core...`/`namespace App\\Modules...` (double backslash) : `SSOService`, `SSOProviderConfig`, `SensitiveDataEncryptor`, `TenantCacheService`, `CommunicationService`, `AuditMessageProvider`, `NotificationPreferenceProvisioner`, `TraccarService`, `PayrollCalculator`, `CountryRulesInterface`. Corrige les 10 fichiers ; resout le `ParseError` bloquant `phpstan analyse --configuration=phpstan-modules.neon`.
- **16 fichiers PHP avec double backslash dans le namespace** : `SSOService`, `SSOProviderConfig`, `SensitiveDataEncryptor`, `TenantCacheService`, `TraccarService`, `CommunicationService`, `NotificationPreferenceProvisioner`, `AuditMessageProvider`, `CountryRulesInterface`, `PayrollCalculator`, `AbstractCountryRules` et 5 rules pays (`Algeria`, `France`, `Morocco`, `Senegal`, `Tunisia`, `Turkey`) avaient `namespace App\\Module` au lieu de `namespace App\Module`, causant un ParseError PHPStan systematique en CI.
- **Scripts de validation dev-hub alignes sur l'architecture DDD** : `validate-mobile-notification-production-proof.ps1` pointe desormais sur `Modules/Notification/.../DeviceTokenController.php` et `PushNotificationService.php`; `validate-mobile-location-readiness.ps1` pointe sur `Modules/Attendance/.../CheckInRequest.php`, `CheckOutRequest.php` et `DTOs/CheckInDTO.php`; `mobile-workflow-contracts.json` pointe sur `Modules/HR/.../MobileExperienceService.php`.
- **Governance Gates** : `docs/api/README.md` contient desormais le marker `/docs/openapi.yaml`; `validate-code-quality-governance.ps1` attend `26/26` conformement au gate strict du 2026-06-01.
- **Admin dashboard tokens de lancement** : `CompanyDetailView` porte le bouton `Activer client` avec `id="btn-activer-client"`; `CompaniesView` porte `id="btn-creer-le-client"`; `LoginView` porte `aria-label="Utiliser le compte demo super-admin"`.
- **Vitrine signup** : `route.ts` et `forms.ts` utilisent `requestedWorkflow: 'guided_trial'` au lieu de `self_service_trial`.

## [4.22.6] - 2026-07-05

### Security
- **Fuite du token SSE en query parameter (admin-dashboard)** : `useNotificationStream.js` passait le bearer token complet en clair dans l'URL `EventSource` (`?token=...`), exposant un secret longue-duree dans les logs serveur/proxy et l'historique navigateur. Le backend exposait deja un endpoint dedie (`POST /api/v1/notifications/sse-token` -> `SseTokenController`) emettant un jeton a usage unique de 60s, mais le frontend ne l'utilisait pas. Le composable echange desormais le bearer token contre ce jeton court avant d'ouvrir le flux SSE (`?sse_token=...`), avec repli sur reconnexion si l'echange echoue.
- **Mot de passe Upstash Redis expose dans l'historique git** (`api/.env.example`, commit anterieur) : le mot de passe reel a ete remplace par un placeholder dans une revision passee, mais reste recuperable via l'historique git d'un depot public. Documente dans `AUDIT.md` comme action urgente hors code (rotation cote dashboard Upstash + mise a jour Render).

### Docs
- **`AUDIT.md` remis a jour** : la checklist finale (generee le 2026-07-01) decrivait plusieurs points comme non resolus alors qu'ils etaient deja corriges dans le code (signature Stripe/Chargily, Google Sign-In employee, Mailables bienvenue/invitation/abonnement, variables Google/Firebase/Chargily dans `.env.example`, Background Worker Render, filtres CI `tests.yml`/`web-marketing-ci.yml`). Checklist recoupee ligne par ligne avec le code de `main` et corrigee avec references precises.

### Added
- **`front/web/.env.local.example`** : fichier d'exemple manquant pour la vitrine Next.js, documentant `STRIPE_SECRET_KEY`, `LEOPARDO_API_URL`, `NEXT_PUBLIC_API_URL` et l'ensemble des variables lues par le proxy API, le checkout Stripe et les formulaires marketing.
### Added
- **Verification email OTP pour le signup trial (P0.1)** : le parcours d'inscription a l'essai gratuit passe desormais par une verification email a 6 chiffres avant de provisionner le tenant. Cela empeche les inscriptions spam et valide l'email du prospect.
  - Nouveau endpoint `POST /api/v1/trial/verify` : recoit l'email et le code OTP, provisionne le tenant si valide.
  - `POST /api/v1/trial/signup` ne provisionne plus immediatement : il cree une `CompanyRequest` en statut `pending`, genere un OTP a 6 chiffres valide 30 minutes, et l'envoie par email via `TrialVerificationMail`.
  - Nouveau mailable `TrialVerificationMail` localise (FR/EN/AR/TR) avec template Blade.
  - Migration `add_verification_fields_to_company_requests_table` : colonnes `verification_token`, `verification_expires_at`, `signup_payload` (jsonb).
  - Frontend vitrine : `SignupForm.tsx` refactored en 3 etapes (formulaire → saisie OTP → credentials), nouvelle route Next.js `/api/forms/verify`, nouveau helper `submitVerifyForm`.
  - Tests mis a jour : `SelfServiceTrialTest` et `GrowthModuleTest` couvrent le flow 2 etapes.
## [4.22.6] - 2026-07-05

### Fixed
- **Gate CI "PHPStan — Modules Architecture" casse sur `main`** : `AbsenceService::request()` et `LeavePolicyController::balances()` (module Absence) accedaient a `LeaveBalance::$allocated` et `LeaveBalance::$carried_over`, deux colonnes qui n'existent pas sur la table `leave_balances` (qui n'a que `balance`/`used`/`pending`, cf. migration `2026_05_10_000003_create_leave_management_tables.php`). PHPStan bloquait donc systematiquement le merge avec `Access to an undefined property`. Le calcul de disponibilite passe desormais par `balance - used`, et l'endpoint `balances()` expose les colonnes reellement presentes (`balance`, `used`, `pending`, `remaining`).

## [4.22.5] - 2026-07-05

### Fixed
- **Isolation multi-tenant des Jobs en file d'attente** : `TenantMiddleware` positionne correctement le `search_path` PostgreSQL et le binding `current_company` pour les requetes HTTP, mais aucun `Job` en queue ne le faisait. Chaque job touchant des donnees tenant (paie, notifications, PDF/documents, webhooks, EdgeSync) se contentait d'un filtre `->where('company_id', ...)`. Cela fonctionne aujourd'hui en mode "shared schema", mais casserait silencieusement des qu'une entreprise passerait en mode "schema isole" (deja supporte par `Company::schema_name`/`tenancy_type` et `TenantManager::withinTenant()`) : le `search_path` de la connexion DB du worker ne serait jamais mis a jour pour ce job.
  - Nouvelle interface `App\Contracts\Queue\TenantScopedJob` : tout job necessitant un contexte tenant declare `tenantCompanyId()`.
  - Nouveau middleware de job `App\Jobs\Middleware\EnsureTenantContext` : enveloppe `handle()` dans `TenantManager::withinTenant()` pour l'entreprise resolue (meme mecanisme que `TenantMiddleware` en HTTP). Relache (ne fait pas echouer) le job si l'entreprise referencee n'est pas trouvee, pour survivre a un retard de replication sans perdre le payload.
  - Applique a tous les jobs tenant-scoped : `ProcessPayrollBatchJob`, `SendBulkNotificationsJob` (+ correction d'un bug independant : filtrait `User::where('company_id', ...)` alors que la table `users` (schema public) n'a pas de colonne `company_id` — le lien tenant passe par `user_employee_links.company_id` via `employeeLinks()`), `GeneratePaySlipPdfJob`, `GeneratePaymentDocumentJob`, `ProcessBulkPaymentJob`, `WarmPaySlipPdfPathsForPayrollRunJob`, `DispatchCommunicationJob`, `SendPushNotificationJob`, `DispatchWebhook`, et `ProcessSyncQueueJob` (EdgeSync).
  - Correction des annotations PHPDoc `@property int|null $company_id` obsoletes en `string|null` (`companies.id` est un uuid) sur `Employee`, `WebhookEndpoint`, `PayrollRun`.
  - Couverture ajoutee : `tests/Unit/Jobs/EnsureTenantContextTest.php` (pass-through hors tenant, tenant id null, etablissement/nettoyage du contexte, relache si entreprise absente) et mise a jour de `QueueJobsTest` (uuid company ids + assertion que chaque job implemente `TenantScopedJob`).
### Chore
- **Nettoyage du monorepo (artefacts CI + binaires vendorises commis par erreur)** : suppression de `api/composer.phar`, `api/database/database.sqlite`, des rapports de tests generes (`api/storage/test-results*/`, `backend-quality-reports/`, `backend-test-reports/`, `front/web/playwright-report/index.html`) qui ne devraient jamais etre commites (deja regeneres a chaque run CI). `.gitignore` etendu pour empecher leur reapparition.
- **Rangement des scripts epars a la racine** : `api/start-local.ps1` -> `api/scripts/start-local.ps1`, `api/test_script.php` -> `api/scripts/test_script.php`, `capture_screenshots.py` -> `scripts/capture_screenshots.py`, avec un `api/scripts/README.md` documentant leur usage. References mises a jour dans `api/README.md`, `docs/DEMARRAGE_RAPIDE.md`, `docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md`, `docs/notes/archive/ARBORESCENCE_PROJET_COMPLET.md`.
### Docs
- **Consolidation architecture** : fusion des ADR dupliques (`docs/architecture/adr-00X-*.md` -> `docs/architecture/adr/000X-*.md`), suppression de `docs/architecture/SYSTEM_DESIGN.md` (contenu redondant avec `ARCHITECTURE.md`), mise a jour croisee de `ARCHITECTURE.md`, `README.md`, `docs/QUICKSTART.md`, `docs/ai/README.md`, `docs/validation/README.md`, `docs/PLAN_ACTION/00_SOMMAIRE.md` pour pointer vers les documents canoniques uniques.
- **`PILOTAGE.md`** : ajout d'une section "Gouvernance documentaire" clarifiant la hierarchie des sources de verite (PILOTAGE.md pour priorites/regles operationnelles, ARCHITECTURE.md pour la structure technique), et corrige une auto-reference obsolete.
### Chore
- **Coherence tooling monorepo** : suppression de `.github/workflows/mobile-ci.yml` (duplique de la matrice mobile deja couverte par `tests.yml`), dedoublonnage de `openapi/openapi.yaml` (le spec canonique reste `api/openapi.yaml`), retrait de `turbo.json`/de la dependance Turbo inutilisee (le monorepo pilote deja ses taches via `melos` pour Flutter et npm workspaces pour le web), ajout du package `melos` manquant a `package.json`. Mise a jour de `ARCHITECTURE.md`, `DEVELOPMENT.md`, `docs/ARCHITECTURE_CICD.md`, `docs/MONOREPO_TOOLING.md`, `docs/README.md`, `docs/api/README.md`, `docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md` en consequence.
### Added
- **12 nouvelles Actions Application (couche DDD)** completant les modules `Growth` (`ApplyAsPartner`, `ApprovePartner`, `RequestPayout`), `Onboarding` (`CompleteStep`, `SeedDefaultSteps`, `SkipStep`), `Platform` (`ActivateCompany`, `ProvisionCompany`, `SuspendCompany`) et `Training` (`CompleteEnrollment`, `CreateCourse`, `EnrollEmployee`) qui n'avaient jusqu'ici qu'un Controller/Service sans Action isolee, cassant la coherence du pattern Command deja en place ailleurs.
- **Migration EdgeSync** : `EdgeController`/`EdgeDownloadController` deplaces dans `App\Modules\EdgeSync\Interfaces\Api\V1` (ils vivaient encore dans le namespace legacy `App\Http\Controllers\Api\V1` malgre la migration DDD du reste du module).
- `api/phpstan-modules.neon` : leve au niveau 5 sur les modules concernes.

## [4.22.4] - 2026-07-04

### Security
- **Suppression du code mort `PaymentWebhookController::stripe()`** : cette methode et ses 3 handlers prives traitaient un payload Stripe sans aucune verification de signature. Elle n'etait branchee sur aucune route (`/api/v1/webhooks/stripe` pointe reellement vers `StripeWebhookController`, qui verifie deja la signature HMAC via `StripeService::verifyWebhookSignature()`), mais restait presente comme piege pour un futur branchement accidentel. Supprimee entierement ; `/webhooks/chargily` (seule route servie par `PaymentWebhookController`) reste inchangee et continue de verifier la signature Chargily.

### Fixed
- **`api/.env.example`** : ajout des variables `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URL` (Google OAuth / Socialite, deja lues par `config/services.php` mais absentes du fichier d'exemple) et `FIREBASE_SERVER_KEY`, `FIREBASE_PROJECT_ID`, `FIREBASE_SERVICE_ACCOUNT_JSON` (push FCM, idem). Sans ces entrees documentees, une premiere config en production oubliait facilement ces variables requises par `AuthController::redirectToGoogle()`/`handleGoogleCallback()` et `PushNotificationService`.
### Added
- **PA2-AUTO-002 — Validation des dependances tickets PLAN_ACTION2 (#762)** : `dev-hub/tools/validate-plan-action2.ps1` detecte maintenant les cycles de dependances entre tickets PA2 (DFS sur le graphe `Dependencies` du CSV), en plus des dependances vers un ID inconnu deja couvertes. Le script echoue avec le chemin complet du cycle (ex: `PA2-X -> PA2-Y -> PA2-X`).

### Fixed
- **Cycle de dependance reel detecte dans `03_GITHUB_PROJECT_IMPORT.csv`** : `PA2-MKT-007` (Funnel CRM marketing) et `PA2-ADM-004` (Pipeline CRM platform) se referencaient mutuellement. Le pipeline admin affiche les leads produits par le funnel marketing — la dependance correcte est unidirectionnelle (`PA2-ADM-004 -> PA2-MKT-007`) ; la dependance inverse sur `PA2-MKT-007` a ete retiree.
## [4.22.4] - 2026-07-04

### Fixed
- **Suite du fix CI v4.22.3 : 136 echecs restants sur 899 tests (Backend)** :
  - Meme bug que sur `Absence`/`ExpenseClaim` (cf 4.22.3), cette fois sur `App\Modules\Absence\Domain\Models\AbsenceType` : `company_id` (NOT NULL sur `absence_types`) absent du `$fillable` et trait `BelongsToCompany` manquant -> `QueryException: null value in column "company_id"` sur 43 tests (`AbsenceApproveTest`, `AbsenceRejectTest`, `AbsenceIndexTest`, `AbsenceShowTest`, `AbsenceStoreTest`, `AbsenceCancelTest`, etc). Le modele declarait aussi des colonnes fictives jamais presentes en base (`paid`, `max_days_per_year`, `requires_document`, `color`, jamais utilisees ailleurs dans le code) au lieu des colonnes reelles (`is_paid`, `deducts_leave`, `requires_proof`, `max_days_once` — deja correctement modelisees sur le doublon `App\Modules\Planning\Domain\Models\AbsenceType`, utilise comme reference).
  - 37 tests `Feature/Edge/*` (`EdgeSilentNodeDetectionTest`, `EdgeConflictResolutionTest`, `EdgeLicenseExpiryTest`, `EdgeMultiTenantIsolationTest`, `EdgeOfflinePunchTest`, `EdgeSyncOnReconnectTest`) remplacent temporairement la table canonique `edge_nodes` par un schema legacy pour la duree du test, puis la `Schema::dropIfExists('edge_nodes')` en `tearDown()`. Sans `CASCADE`, PostgreSQL refusait le drop tant que `sync_logs`/`sync_queue`/`edge_licenses` referencaient encore la table via FK -> `QueryException: cannot drop table edge_nodes because other objects depend on it`. Remplace par `DB::statement('DROP TABLE IF EXISTS edge_nodes CASCADE')`.
  - `PaymentWebhookControllerTest::test_stripe_webhook_rejects_invalid_signature` : passait un `json_encode(...)` (string) en 2e argument de `postJson()`, qui attend un array (il encode lui-meme) -> `TypeError`. Corrige pour passer l'array brut.
  - `App\Modules\Attendance\Domain\Models\AttendanceLog` sans `$casts` pour `check_in`/`check_out`/`date`/`punch_meta` -> `Error: Call to a member function diffInSeconds()/format()/greaterThan() on string` (Attendance*Test, EstimationServiceTest, AutoCloseAttendanceCommandTest) et `ErrorException: Array to string conversion` a l'insertion de `punch_meta` (CheckInTest, 4 tests). Ajoute les casts `date`, `datetime`, `boolean`, `array`.
  - `AttendanceLog`, `AttendanceKiosk`, `BiometricEnrollmentRequest` : meme bug d'isolation tenant que `AbsenceType` (trait `BelongsToCompany` manquant alors que `company_id` est NOT NULL sur les 3 tables) -> `TenantModelIsolationTest` echouait sur les 3 (`assertCount(1, ...)` recevait 2). Les usages cross-tenant existants (`AutoCloseAttendanceCommand`, `PlatformCompanyHealthService`) utilisaient deja `withoutGlobalScopes()` explicitement et ne sont pas affectes par l'ajout du scope global.
  - `App\Core\Feature\Infrastructure\Services\AnnotationReader::parseMethodAttributes()` et `ReflectionService::isApiController()` comparaient les noms de classes d'attributs/namespaces au namespace legacy `App\Attributes\*`/`App\Http\Controllers\Api`, jamais mis a jour lors du refactor DDD vers `App\Shared\Attributes\*` et `App\Modules\*\Interfaces\Api\*` / `App\Core\*\Interfaces\Api\*` -> `AnnotationReaderTest`, `FeatureDetectorTest`, `ReflectionServiceTest` echouaient (attributs `#[ApiFeature]`/`#[RequiresPermission]` jamais reconnus, aucun controleur de module detecte comme "API controller"). Les deux services reconnaissent maintenant les deux namespaces (legacy + DDD).
  - `EmployeeController.php` (+ `Onboarding`, `RoleAssignmentController`, `EvaluationController`) : chaines de caracteres accentuees corrompues en mojibake (UTF-8 relu comme CP1252, ex. `EmployÃ©s` au lieu de `Employés`) dans les commentaires PHPDoc et les arguments de `#[ApiFeature]` -> `AnnotationReaderTest`/`FeatureDetectorTest` comparaient `'Liste des Employés'` a `'Liste des EmployÃ©s'`. Ré-encodage corrige.
  - `App\Modules\Expense\Domain\Models\ExpenseItem::\$fillable` declarait `expense_date` (colonne inexistante) au lieu de `date` (colonne reelle, NOT NULL) -> `ExpenseClaimController::store()` inserait silencieusement `date=null` -> `QueryException: null value in column "date"` sur `ExpenseClaimControllerTest`. Corrige pour utiliser `date`.
  - `SocialDeclarationGenerator::generateCnssMa()` lisait `\$emp['days_worked']` sans fallback alors que l'appelant peut ne pas fournir cette cle (convention CNSS Maroc : trimestre complet par defaut) -> `SocialDeclarationGeneratorTest::test_generates_cnss_ma_declaration_with_default_days_and_csv_safe_delimiter` attendait `78` (26 jours ouvres x 3 mois) et recevait une erreur d'acces a une cle manquante. Ajoute le fallback `?? 78`.
  - `FeatureManifestController::filterFeaturesByPermissions()` lisait la cle `required_permissions` sur le tableau retourne par `Feature::toManifestArray()`, qui expose en realite `permissions` (`required_permissions` n'existe sur aucun modele de ce module) -> le filtre ne bloquait jamais aucune feature protegee, `FeatureManifestApiTest::it_filters_features_by_permissions` echouait. Corrige pour lire `permissions`.
### Added
- **PA2-AUTO-002 — Validation des dependances tickets PLAN_ACTION2 (#762)** : `dev-hub/tools/validate-plan-action2.ps1` detecte maintenant les cycles de dependances entre tickets PA2 (DFS sur le graphe `Dependencies` du CSV), en plus des dependances vers un ID inconnu deja couvertes. Le script echoue avec le chemin complet du cycle (ex: `PA2-X -> PA2-Y -> PA2-X`).

### Fixed
- **Cycle de dependance reel detecte dans `03_GITHUB_PROJECT_IMPORT.csv`** : `PA2-MKT-007` (Funnel CRM marketing) et `PA2-ADM-004` (Pipeline CRM platform) se referencaient mutuellement. Le pipeline admin affiche les leads produits par le funnel marketing — la dependance correcte est unidirectionnelle (`PA2-ADM-004 -> PA2-MKT-007`) ; la dependance inverse sur `PA2-MKT-007` a ete retiree.
- **Suite du fix CI v4.22.3 : 136 echecs restants sur 899 tests (Backend)** :
  - Meme bug que sur `Absence`/`ExpenseClaim` (cf 4.22.3), cette fois sur `App\Modules\Absence\Domain\Models\AbsenceType` : `company_id` (NOT NULL sur `absence_types`) absent du `$fillable` et trait `BelongsToCompany` manquant -> `QueryException: null value in column "company_id"` sur 43 tests (`AbsenceApproveTest`, `AbsenceRejectTest`, `AbsenceIndexTest`, `AbsenceShowTest`, `AbsenceStoreTest`, `AbsenceCancelTest`, etc). Le modele declarait aussi des colonnes fictives jamais presentes en base (`paid`, `max_days_per_year`, `requires_document`, `color`, jamais utilisees ailleurs dans le code) au lieu des colonnes reelles (`is_paid`, `deducts_leave`, `requires_proof`, `max_days_once` — deja correctement modelisees sur le doublon `App\Modules\Planning\Domain\Models\AbsenceType`, utilise comme reference).
  - 37 tests `Feature/Edge/*` (`EdgeSilentNodeDetectionTest`, `EdgeConflictResolutionTest`, `EdgeLicenseExpiryTest`, `EdgeMultiTenantIsolationTest`, `EdgeOfflinePunchTest`, `EdgeSyncOnReconnectTest`) remplacent temporairement la table canonique `edge_nodes` par un schema legacy pour la duree du test, puis la `Schema::dropIfExists('edge_nodes')` en `tearDown()`. Sans `CASCADE`, PostgreSQL refusait le drop tant que `sync_logs`/`sync_queue`/`edge_licenses` referencaient encore la table via FK -> `QueryException: cannot drop table edge_nodes because other objects depend on it`. Remplace par `DB::statement('DROP TABLE IF EXISTS edge_nodes CASCADE')`.
  - `PaymentWebhookControllerTest::test_stripe_webhook_rejects_invalid_signature` : passait un `json_encode(...)` (string) en 2e argument de `postJson()`, qui attend un array (il encode lui-meme) -> `TypeError`. Corrige pour passer l'array brut.
  - `App\Modules\Attendance\Domain\Models\AttendanceLog` sans `$casts` pour `check_in`/`check_out`/`date`/`punch_meta` -> `Error: Call to a member function diffInSeconds()/format()/greaterThan() on string` (Attendance*Test, EstimationServiceTest, AutoCloseAttendanceCommandTest) et `ErrorException: Array to string conversion` a l'insertion de `punch_meta` (CheckInTest, 4 tests). Ajoute les casts `date`, `datetime`, `boolean`, `array`.
  - `AttendanceLog`, `AttendanceKiosk`, `BiometricEnrollmentRequest` : meme bug d'isolation tenant que `AbsenceType` (trait `BelongsToCompany` manquant alors que `company_id` est NOT NULL sur les 3 tables) -> `TenantModelIsolationTest` echouait sur les 3 (`assertCount(1, ...)` recevait 2). Les usages cross-tenant existants (`AutoCloseAttendanceCommand`, `PlatformCompanyHealthService`) utilisaient deja `withoutGlobalScopes()` explicitement et ne sont pas affectes par l'ajout du scope global.
  - `App\Core\Feature\Infrastructure\Services\AnnotationReader::parseMethodAttributes()` et `ReflectionService::isApiController()` comparaient les noms de classes d'attributs/namespaces au namespace legacy `App\Attributes\*`/`App\Http\Controllers\Api`, jamais mis a jour lors du refactor DDD vers `App\Shared\Attributes\*` et `App\Modules\*\Interfaces\Api\*` / `App\Core\*\Interfaces\Api\*` -> `AnnotationReaderTest`, `FeatureDetectorTest`, `ReflectionServiceTest` echouaient (attributs `#[ApiFeature]`/`#[RequiresPermission]` jamais reconnus, aucun controleur de module detecte comme "API controller"). Les deux services reconnaissent maintenant les deux namespaces (legacy + DDD).
  - `EmployeeController.php` (+ `Onboarding`, `RoleAssignmentController`, `EvaluationController`) : chaines de caracteres accentuees corrompues en mojibake (UTF-8 relu comme CP1252, ex. `EmployÃ©s` au lieu de `Employés`) dans les commentaires PHPDoc et les arguments de `#[ApiFeature]` -> `AnnotationReaderTest`/`FeatureDetectorTest` comparaient `'Liste des Employés'` a `'Liste des EmployÃ©s'`. Ré-encodage corrige.
  - `App\Modules\Expense\Domain\Models\ExpenseItem::\$fillable` declarait `expense_date` (colonne inexistante) au lieu de `date` (colonne reelle, NOT NULL) -> `ExpenseClaimController::store()` inserait silencieusement `date=null` -> `QueryException: null value in column "date"` sur `ExpenseClaimControllerTest`. Corrige pour utiliser `date`.
  - `SocialDeclarationGenerator::generateCnssMa()` lisait `\$emp['days_worked']` sans fallback alors que l'appelant peut ne pas fournir cette cle (convention CNSS Maroc : trimestre complet par defaut) -> `SocialDeclarationGeneratorTest::test_generates_cnss_ma_declaration_with_default_days_and_csv_safe_delimiter` attendait `78` (26 jours ouvres x 3 mois) et recevait une erreur d'acces a une cle manquante. Ajoute le fallback `?? 78`.
  - `FeatureManifestController::filterFeaturesByPermissions()` lisait la cle `required_permissions` sur le tableau retourne par `Feature::toManifestArray()`, qui expose en realite `permissions` (`required_permissions` n'existe sur aucun modele de ce module) -> le filtre ne bloquait jamais aucune feature protegee, `FeatureManifestApiTest::it_filters_features_by_permissions` echouait. Corrige pour lire `permissions`.

## [4.22.3] - 2026-07-04

### Fixed
- **Suite du fix CI v4.22.2 : 160 echecs restants sur 899 tests (Backend + Backend Coverage)** :
  - Modele canonique `App\Modules\Absence\Domain\Models\Absence` (utilise via le shim `App\Models\Absence`) ne declarait ni `company_id` (NOT NULL en base) dans `$fillable` ni le trait `BelongsToCompany` -> `QueryException: null value in column "company_id"`. Reecrit pour matcher le schema reel de la table `absences` (`days_count`, `proof_path`, `rejected_reason`) ; `AbsenceService::approve()`/`reject()` adaptes en consequence.
  - Meme bug sur `App\Modules\Expense\Domain\Models\ExpenseClaim` : `company_id` absent du `$fillable`. Ajoute, avec `paid_at`/`payment_reference` (deja en base, absents du modele).
  - Colonne `rejection_reason` manquante sur `expense_claims` : `ExpenseClaimController::reject()` y ecrit depuis l'origine du module mais ni la migration ni la fixture de test ne la definissaient -> `QueryException` au premier appel de `PUT .../reject`. Ajoutee a la migration additive `2026_07_04_000001_add_missing_updated_at_columns.php` et a la fixture de test.
  - `EdgeSyncTest::actingAsCompanyAdmin()` creait un `App\Core\Auth\Domain\Models\User` avec `company_id`/`role` — colonnes qui n'existent pas sur ce modele dans ce codebase (c'est `Employee` qui les porte). Remplace par `Employee::factory()`.

## [4.22.2] - 2026-07-04

### Fixed
- **CI casse sur `main` malgre le fix v4.21.1 (Backend + Backend Coverage toujours en echec, 633/902 tests)** :
  - **75 fichiers shim `app/Models/*.php`** (aliases DDD generes en v4.22.0) : `class_alias(App\Modules\...\Foo::class, ...)` resolvait le nom de classe cible relativement au namespace courant (`App\Models`) au lieu du namespace absolu -> `Class "App\Models\App\Modules\...\Foo" not found` sur la quasi-totalite des tests Feature/Unit touchant ces modeles. Correction : `\App\Modules\...\Foo::class` (backslash absolu) dans chaque shim.
  - `AppServiceProvider` : ajout de `Factory::guessFactoryNamesUsing()` — les modeles deplaces en DDD (`Core/Tenant/Domain/Models/Company`, etc.) faisaient echouer `Company::factory()` avec `Class "Database\Factories\Core\Tenant\Domain\Models\CompanyFactory" not found` car Laravel calcule par defaut le namespace complet du modele, alors que toutes les factories vivent a plat dans `database/factories/{Model}Factory.php`.
  - Migration `2026_06_30_000001_create_edge_nodes_table` : le `down()` supprimait `edge_nodes` sans condition alors que le `up()` se neutralise deja si la table existe (creee par la migration EdgeSync du 29/06, schema UUID). Au rollback, `sync_logs`/`sync_queue`/`edge_licenses` (FK vers `edge_nodes`) n'etaient pas encore droppees -> `cannot drop table edge_nodes because other objects depend on it`. Correction : ne dropper que si c'est le schema legacy (presence de la colonne `node_id`).
  - 21 modeles `app/Modules/*` referencaient une classe courte (`Employee`, `Company`, `Payment`, `Partner`, `Commission`, `Invoice`, `Department`, `Position`, `User`) sans `use`, resolue par PHP dans le namespace courant du fichier -> `Class not found`. Ajout du `use` manquant vers le FQCN canonique deja utilise ailleurs dans le codebase.
  - `AbsenceType`, `AttendanceLog`, `Absence` (Absence module), `LeaveBalanceLog` : trait `HasFactory` manquant malgre une factory existante dans `database/factories/` -> `BadMethodCallException: Call to undefined method ...::factory()`.
  - Tables `absence_types` et `expense_items` : colonne `updated_at` manquante alors que les modeles Eloquent utilisent les timestamps par defaut -> `QueryException: column "updated_at" does not exist`. Migration additive `2026_07_04_000001_add_missing_updated_at_columns.php`.
  - 6 tests `Feature/Edge/*` (Phase 4, commit 19c4308b) ciblent un systeme Edge legacy (bigint + `node_id`, `EdgeController`/`DetectSilentEdgeNodes`, code mort non route/planifie) et entraient en collision avec la table `edge_nodes` canonique du module EdgeSync DDD (uuid + slug) deja creee par la fixture de test commune. Fix : `dropIfExists('edge_nodes')` avant de recreer le schema legacy pour la duree de chacun de ces tests.

## [4.22.1] - 2026-07-02

### Fixed
- **Nettoyage documentation projet (lisibilite/coherence, pas de changement fonctionnel)** :
  - `docs/dossierdeConception/19_diagrammes_uml/{01,02,03,04}_*.md` : correction d'un encodage casse (mojibake, ex. `employe9s` -> `employés`, `de9tecte9` -> `détecté`) sur les tableaux d'explication des 4 diagrammes de sequence auth/paie/absences/pointage.
  - Suppression de `assets/diagrams/*.md` (9 fichiers) : copies orphelines et jamais referencees des diagrammes UML canoniques de `docs/dossierdeConception/19_diagrammes_uml/`, avec une corruption d'encodage plus severe que l'original. `assets/README.md` annonce des diagrammes SVG/PNG dans ce dossier, pas du Markdown duplique.
  - `docs/GOTO_MARKET/README.md` : le fichier contenait tout son contenu deux fois (conflit de merge non nettoye), avec des tableaux de statut contradictoires ("100% Complet" vs "A creer 0%") pour des sous-dossiers `02_MARKET/` a `99_EXECUTIVE/` qui n'existent pas dans le depot. Remplace par une version unique alignee sur la structure reelle du dossier (`01_PRODUCT/`, `2026_MARKET_LAUNCH_COMPANY_OS/`, `ASSETS_PRODUCTION/`, `GOTO_MARKET_AUDIT.md`).
  - 9 liens Markdown casses corriges (chemins relatifs faux) : `docs/README.md`, `docs/ai/README.md`, `docs/testing/TESTING.md`, `docs/api/VERSIONING.md`, `docs/api/README.md`, `docs/contributing/GUIDELINES.md`, `assets/README.md`, `docs/DEMO_ACCOUNTS.md` (moved from `demo/DEMO_ACCOUNTS.md`).
  - `front/web/src/modules/vitrine/README.md` : suppression de 3 liens morts vers `.kiro/specs/vitrine-restructure/` (dossier retire du depot par le nettoyage "Janitor: remove stale .kiro bot artifacts"), remplaces par une note explicative.

## [4.21.1] - 2026-07-01

### Fixed
- **CI cassé sur `main` — bloquait tous les merges** :
  - Migration `2026_06_29_000202_create_employee_attendance_preferences_table.php` : apostrophes échappées en style PHP (`\'`) dans un commentaire SQL PostgreSQL au lieu du style SQL (`''`) — `SQLSTATE[42601]` sur chaque exécution des migrations tenant (Backend, Backend Coverage, Jobs & Queues Contracts).
  - `.github/workflows/tests.yml` et `.github/workflows/phpstan-baseline.yml` : `vendor/larastan/larastan/extension.neon` inclus deux fois (déjà inclus via `phpstan.neon`) — PHPStan refusait de démarrer ("This file is included multiple times").
  - Migration `2026_06_30_000001_create_edge_nodes_table.php` (legacy, `App\Http\Controllers\Api\V1\EdgeController`, non relié à aucune route active) recréait la table `edge_nodes` déjà créée par `2026_06_29_000001_create_edge_sync_tables.php` (module EdgeSync DDD actif) — `SQLSTATE[42P07]` Duplicate table. Migration legacy neutralisée via garde `Schema::hasTable()`.
## [4.22.0] - 2026-07-01

### Changed
- **Nettoyage architectural Phase 2 — modèles, services, FormRequests**
  - **17 modèles orphelins** placés dans `Core/Tenant/Domain/Models/`, `Core/Auth/Domain/Models/`,
    `Shared/Models/`, `Modules/*/Domain/Models/`, `AI/Models/` — aliases shims backward-compat dans `app/Models/`.
  - `app/Models/` est désormais 100% composé d'alias shims (92 fichiers) — zéro breaking change.
  - **13 services** dans `app/Services/` (non-doublons) migrés vers `Core/Feature/`, `Core/Auth/`,
    `Modules/Platform/`, `Modules/HR/`, `Modules/Onboarding/`, `Modules/Notification/` — shims en place.
  - **64 FormRequests** copiés dans leurs modules (`Modules/*/Interfaces/Api/V1/Requests/`) —
    22 consommateurs mis à jour, shims backward-compat dans `app/Http/Requests/`.
  - `api/ARCHITECTURE.md` mis à jour avec bilan complet et TODOs restants.

## [4.21.0] - 2026-07-01

### Changed
- **Nettoyage architectural API — suppression des doublons legacy**
  - **90 controllers** dans `app/Http/Controllers/Api/V1/` supprimés (doublons migrés dans `app/Modules/*/Interfaces/Api/V1/`). Restent : `EdgeController`, `EdgeDownloadController`, `SSO/SSOController`.
  - **26 services** dans `app/Services/` supprimés (doublons migrés dans `app/Modules/*/Infrastructure/Services/`). Tous les imports consommateurs mis à jour (51 fichiers, 55 remplacements).
  - **4 couches `Infrastructure/`** créées dans `Modules/{Growth,Platform,Onboarding,Training}` — corrige le CI Module Structure Validator.
  - Tests unitaires (`AnnotationReaderTest`, `FeatureDetectorTest`, `ReflectionServiceTest`) mis à jour pour référencer les controllers DDD.
  - `FormRequests` Webhook redirigés vers `App\Modules\Billing\Interfaces\Api\V1\WebhookController`.
  - `ARCHITECTURE.md` mis à jour avec l'état d'avancement complet et les TODOs priorisés.

## [4.19.0] - 2026-06-30

### Added
- **Edge Phase 4 — Tests scénarios réels (offline/sync/conflit/licence/multi-tenant)**
  - `tests/Feature/Edge/EdgeOfflinePunchTest.php` : 4 tests — pointages persistés hors-ligne, N pointages simultanés, health endpoint autonome, pending_count cohérent.
  - `tests/Feature/Edge/EdgeSyncOnReconnectTest.php` : 6 tests — identification logs non-syncés, pending_count→0 après sync, signal sync_requested_at Cloud→Edge, idempotence (pas de doublons), node repasse online après heartbeat, endpoint heartbeat.
  - `tests/Feature/Edge/EdgeConflictResolutionTest.php` : 4 tests — détection conflit (même session key), stratégie "Cloud wins", audit trail punch_meta, sync sans conflit normale.
  - `tests/Feature/Edge/EdgeLicenseExpiryTest.php` : 8 tests — licence expirée→invalid, licence valide, renouvellement automatique, nœud révoqué non-relicenciable, exclusion révoqués des alertes, endpoint public-key 503/200, détection expiration proche.
  - `tests/Feature/Edge/EdgeMultiTenantIsolationTest.php` : 7 tests — isolation attendance_logs, edge_nodes, employés, sync_requests; 3 tenants partitionnement strict; N nodes même tenant; alertes silence scopées tenant.
  - `tests/Feature/Edge/EdgeSilentNodeDetectionTest.php` : 8 tests — commande sans nœud silencieux, --dry-run, nœud récent non détecté, nœud silencieux détecté, muted non alerté, notification construite correctement, null lastSeenAt, seuil custom.
## [4.18.1] - 2026-06-30

### Fixed
- **PHPStan/Larastan — Vagues 1 à 4** : Réduction de ~5 430 → ~1 277 erreurs baseline (−76%).
  - **Vague 1** : Ajout de `vendor/larastan/larastan/extension.neon` dans `phpstan.neon` (−2 189 erreurs `property.notFound` Eloquent). Corrections `mixed` dans `AttendanceMonthlyReportService`, `AttendanceAnomalyService`, `AttendanceService`, `PlatformCompanyHealthService`.
  - **Vague 2** : Annotations `@param/@return array<string, mixed>` dans `CameraService` (−100 erreurs).
  - **Vague 3** : Types explicites dans `EvaluationController`, `TaskController`, `AbsenceService`, `PayrollCalculator` (−288 erreurs).
  - **Vague 4** : `FeatureRegistry` — `cache->remember()` typé, `getStatistics()` Builder<Feature> via `newQuery()`, `synchronize()` cast explicites. `PlatformCompanyRequestController` — `DB::table()->value()` cast `int`, `$result['company']` typé. `HrReportController` — closure `groupBy->map()` typée. Tests Absences — `str_pad((string))`, `firstOrFail()`.
## [4.20.0] - 2026-06-30

### Added
- **EdgeSync Module** : Module DDD complet `App\Modules\EdgeSync` avec services, modèles, jobs et notifications pour la synchronisation bidirectionnelle Cloud ↔ Edge.
  - `CloudDeltaBuilder` : Calcul des deltas à synchroniser depuis le Cloud vers les nœuds Edge.
  - `EdgeLicenseService` : Gestion des licences RS256 (validation, renouvellement, révocation).
  - `SyncEngineService` : Moteur de sync avec queue, retry, conflict detection.
  - `ProcessSyncQueueJob` : Job queued pour traitement asynchrone des syncs.
  - `EdgeSyncDaemonCommand` : Démon long-running pour les déploiements Edge.
  - `MonitorEdgeNodesCommand` : Monitoring des nœuds Edge silencieux.
  - `EdgeDownloadController` : Endpoints téléchargements (docker-compose, env-example).
  - `EdgeNode`, `EdgeLicense`, `SyncLog`, `SyncQueue` : Modèles Eloquent du module.
  - Migration `create_edge_sync_tables` : Tables `edge_nodes`, `edge_licenses`, `sync_logs`, `sync_queue`.
  - ZKTeco kiosk : Support routing `edge_first/cloud_first/edge_only` dans `app.js`.
## [4.22.0] - 2026-07-01

### Changed
- **Nettoyage architectural Phase 2 — modèles, services, FormRequests**
  - **17 modèles orphelins** placés dans `Core/Tenant/Domain/Models/`, `Core/Auth/Domain/Models/`,
    `Shared/Models/`, `Modules/*/Domain/Models/`, `AI/Models/` — aliases shims backward-compat dans `app/Models/`.
  - `app/Models/` est désormais 100% composé d'alias shims (92 fichiers) — zéro breaking change.
  - **13 services** dans `app/Services/` (non-doublons) migrés vers `Core/Feature/`, `Core/Auth/`,
    `Modules/Platform/`, `Modules/HR/`, `Modules/Onboarding/`, `Modules/Notification/` — shims en place.
  - **64 FormRequests** copiés dans leurs modules (`Modules/*/Interfaces/Api/V1/Requests/`) —
    22 consommateurs mis à jour, shims backward-compat dans `app/Http/Requests/`.
  - `api/ARCHITECTURE.md` mis à jour avec bilan complet et TODOs restants.

## [4.21.0] - 2026-06-30

### Added
- **Phase 4-5 Routes + Modules DDD** : Migration de 0 routes legacy vers namespaces DDD.
  - `architecture-check.yml` : extension de la vérification à 17 modules (HR, Billing, Cameras, Absence, Expense, Growth, Platform, Onboarding, Training, Notification, ...).
  - Modules Billing, Growth, HR, Notification, Onboarding, Platform : controllers migrés vers `Interfaces/Api/V1/Controllers/`.
  - Routes `dashboard`, `growth`, `hr_app`, `integrations`, `planning`, `sso`, `tracking`, `user` : migration legacy routes.
  - `openapi.yaml` : endpoints Growth et Onboarding ajoutés.
  - `api/bootstrap/providers.php` : GrowthServiceProvider + OnboardingServiceProvider enregistrés.
## [4.18.1] - 2026-06-30

### Fixed
- **PHPStan vagues 1→4** : Réduction de ~1000 erreurs mixed-types sur les modules Cameras, HR, Payroll, Platform, Services.
  - `api/phpstan.neon` : Extension Larastan activée + niveaux progressifs.
  - `CameraService` : Annotations `@var`, `@param`, `@return` sur les méthodes de traitement vidéo.
  - `AbsenceService`, `AttendanceService`, `AttendanceAnomalyService`, `AttendanceMonthlyReportService` : Types explicites sur collections et retours.
  - `FeatureRegistry` : Suppression des `mixed` sur `registry[]`.
  - `PayrollCalculator` : Types sur éléments de calcul.
  - `PlatformCompanyHealthService` : Annotations métriques.
  - `EvaluationController`, `HrReportController`, `PlatformCompanyRequestController`, `TaskController` : Types de retour explicites.

## [4.18.0] - 2026-06-30

### Added
- **Edge — Nœuds offline (Phase 3.x + 5.x)** : Infrastructure complète pour le déploiement de nœuds Edge offline Leopardo.
  - `front/mobile_apps/leopardo_core` : dépendances Drift ajoutées (`drift`, `drift_flutter`, `sqlite3_flutter_libs`, `drift_dev`, `build_runner`) + base de données locale `EdgeDatabase` (4 tables : `AttendanceLogs`, `EmployeeCache`, `SyncQueue`, `EdgeConfig`) avec code généré `edge_database.g.dart`.
  - `api/.env.example` : 9 nouvelles variables `EDGE_*` (`EDGE_ENABLED`, `EDGE_NODE_ID`, `EDGE_TOKEN`, `EDGE_LICENSE_PRIVATE_KEY`, `EDGE_LICENSE_PUBLIC_KEY`, `EDGE_LICENSE_TTL_DAYS`, `CLOUD_API_URL`, `EDGE_SILENCE_THRESHOLD_MINUTES`, `EDGE_LOCAL_URL`).
  - `front/web-offline/` : PWA complète service-worker Cache-First + Background Sync, manifest installable, client IDB offline queue avec `flushOfflineQueue()`.
  - `front/admin-dashboard` : Vue `EdgeNodesView.vue` (liste nœuds, statut online/offline, force sync, revoke, polling 60s) + route `/edge`.
  - `front/zkteco-kiosk` : Support mode Edge dans `config.example.json` (bloc `edge{}`) et routing dynamique `edge_first/cloud_first/edge_only` dans `app.js`.
  - `api/app/Console/Commands/DetectSilentEdgeNodes.php` : Commande `edge:detect-silent-nodes` (--threshold, --dry-run) planifiée toutes les 5 min.
  - `api/app/Notifications/EdgeNodeSilentAlert.php` : Notification mail `ShouldQueue` envoyée aux managers en cas de nœud silencieux.
  - `api/app/Http/Controllers/Api/V1/EdgeController.php` : Endpoints `GET /edge/install.sh`, `GET /edge/download/docker-compose.yml`, `GET /edge/license-public-key`, `POST /edge/heartbeat`, gestion nœuds platform.
  - `api/routes/modules/edge.php` : Routes Edge complètes (publiques + platform super-admin).
  - `api/config/edge.php` : Configuration centralisée `edge.*`.
  - `edge/` : Image Docker `leopardo/edge-api:1.0.0` (FrankenPHP + PHP 8.4 + SQLite + PWA embarquée), `docker-compose.yml`, `Caddyfile.edge`, `docker-entrypoint.edge.sh`, `README.md`.

### Changed
- `api/routes/console.php` : Ajout planification `edge:detect-silent-nodes` (everyFiveMinutes, withoutOverlapping, onOneServer).
- `api/routes/api.php` : Inclusion `routes/modules/edge.php`.

### Database
- Migration `2026_06_30_000001_create_edge_nodes_table` : table `edge_nodes` (node_id, company_id, status, last_seen_at, pending_count, license_valid, license_expires_at, alert_muted, revoked_at).
## [4.17.9-fix] - 2026-06-30

### Fixed
- **DepartmentController** : Suppression du `select(['...', 'manager_id'])` explicite — `manager_id` est chargé via la relation `with('manager')`, évitant `column "manager_id" does not exist` sur les environnements où la migration altérée n'a pas encore été appliquée dans le schema de test.
## [4.17.9-fix2] - 2026-06-30

### Fixed
- **NotificationController** : Ajout de la méthode `unread()` manquante (GET `/notifications/unread` répondait 500 avec `Call to undefined method`).
- **NotificationController** : `markRead()` et `markAllRead()` créent désormais des entrées `CommunicationEvent` pour l'audit trail des lectures (fix `communication_events` table empty assertion).
- **Phase 2 — DDD Contracts/Exceptions/DTOs complets** : `Domain/Contracts`, `Domain/Exceptions`, `Application/DTOs` ajoutés sur les 10 modules qui en manquaient (Absence, Attendance, Billing, Cabinet, Cameras, Expense, Fleet, Notification, Planning, Recruitment). 12/12 modules 100% complets.
- **Phase 3 — Migration routes/modules/* vers namespaces DDD** : 0 import `App\Http\Controllers\Api\V1` restant dans `routes/modules/`. Module Growth créé (13ème module DDD).
- **Phase 4 — Migration routes/api.php + 3 nouveaux modules** : 25 imports legacy supprimés de `routes/api.php`. Modules Platform, Onboarding, Training créés (16 modules totaux). Coverage Gate activé comme required check (65% strict).
- **Phase 5 — openapi.yaml** : 13 nouveaux paths documentés (Growth: /partner/*, Onboarding: /onboarding-setup/*). Nouveaux tags Growth, Onboarding, Training, Platform Admin. Architecture-check étendu à 16 modules.
- **i18n** : `lang/ar/dashboard.php` ajouté (manquait vs EN). Couverture ar/fr/en complète.

### Fixed
- **Architecture CI** : 9 violations de structure corrigées (Infrastructure/ manquants pour Growth/Platform/Onboarding/Training, Cameras/Providers créé). PHPStan modules ignores ajoutés pour modules Phase 3-4.
- **OpenAPI CI** : Clé dupliquée `/me/qr-profile` supprimée de `openapi.yaml` (ligne 7985).

## [4.18.0] - 2026-06-30

### Added
- **Edge Sync — Offline-First Architecture (Phase 4)** : PR #813 — Finalisation complète du module Edge Sync.
  - `edge_database.g.dart` : code généré Drift v2.14 pour les 5 tables (`LocalAttendanceLogs`, `LocalAbsences`, `LocalEmployees`, `LocalSyncQueue`, `LocalDepartments`). Livré dans le dépôt pour garantir la reproductibilité CI sans SDK Dart installé.
  - `sync_service.dart` : adaptation API `connectivity_plus ^6` — callback `onConnectivityChanged` reçoit désormais `List<ConnectivityResult>` au lieu de `ConnectivityResult`.
  - `EdgeSyncServiceProvider` : correction du chemin `mergeConfigFrom` (5 niveaux `../../../../../` au lieu de 6) pour pointer correctement vers `api/config/edge.php`.
  - `api/.env.example` : ajout des variables `EDGE_NODE_ID`, `EDGE_TOKEN`, `EDGE_LICENSE_PRIVATE_KEY`, `EDGE_LICENSE_PUBLIC_KEY`, `CLOUD_API_URL`.
  - PWA offline `front/web-offline/` : interface Next.js statique avec service worker pour l'accès à `http://leopardo.local` sans mobile.
  - Dashboard UI Edge nodes : page admin Next.js pour la liste, le statut online/offline, le sync manuel et les licences.
  - Kiosque ZKTeco : `config.example.json` mis à jour pour pointer vers `http://leopardo.local` quand Edge est actif.
  - Monitoring Laravel : commande `edge:detect-silent-nodes` + cron toutes les 30 min — détecte les nodes silencieux depuis >30 min et notifie le manager.
## [4.17.9] - 2026-06-29

### Added
- **HR — Domain/Contracts** : Ajout de `EmployeeRepositoryInterface`, `DepartmentRepositoryInterface`, `ContractRepositoryInterface` dans `App\Modules\HR\Domain\Contracts\`. Pose les interfaces DDD nécessaires pour découpler l'infrastructure de persistance du domaine métier HR.
- **Branch Protection** : `Backend Coverage Gate` ajouté comme required check dans `BRANCH_PROTECTION_REQUIRED.md`.

### Changed
- **Absence — Migration controller vers module DDD** : `App\Modules\Absence\Interfaces\Api\V1\Controllers\AbsenceController` remplace `App\Modules\Planning\Interfaces\Api\V1\AbsenceController` (fichier orphelin supprimé). Le controller dispose maintenant de : RBAC complet (employee vs manager), `AbsenceResource`, filtres month/year/status, pagination configurable, méthode `destroy`. Correction du double-prefix `v1/v1` dans `absence.php` (les routes étaient mortes) : les routes sont maintenant correctement montées sous `/api/v1/absences`.
## [4.17.5-fix3] - 2026-06-30

### Fixed
- **Smart Attendance — Migration FK** : Suppression de la FK explicite `employees` dans `create_employee_attendance_preferences_table` — évitait une erreur `column does not exist` en CI si la migration s'exécute avant `employees`.
- **Frontend TypeScript** : `client-features.ts:266` — typage explicite `let moduleKey: ClientModuleKey | undefined` pour corriger `Type 'ClientModuleKey | undefined' is not assignable to type 'ClientModuleKey'`.

## [4.17.5] - 2026-06-29

### Fixed
- **Expense Routes — Middleware sécurisé** : Toutes les routes expense-claims sont maintenant protégées par `['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan']`. Suppression de la route `POST /approve` dupliquée (conservation uniquement de `PUT /approve`). Nettoyage des commentaires TODO dans `hr_extended.php`.
## [4.17.6] - 2026-06-29

### Added
- **Tests Expense — Couverture complète** : 8 nouveaux tests Feature pour `ExpenseClaimController` couvrant : rejet avec raison, validation du champ `reason` obligatoire, interdiction non-manager d'approuver/rejeter, isolation cross-tenant (404) pour approve/reject, re-soumission impossible (422), accès cross-tenant à `show` (404).
## [4.17.8] - 2026-06-29

### Added
- **Tests Google OAuth** : Amélioration de `GoogleAuthGlobalLookupTest` — mock Socialite renforcé avec `once()` sur `stateless()` + `userFromToken()`, nouveau test de rejet `401` pour email inconnu, nouveau test cross-tenant pour vérifier l'absence de violation d'isolation.

### Changed
- **Coverage Gate** : Seuil de couverture backend relevé de 60% à 65% dans `coverage-gate.yml`.

### Fixed
- **Expense Routes — Route reject** : Ajout de `PUT /expense-claims/{expenseClaim}/reject` en complément de `POST` pour cohérence avec les conventions REST et les tests.
## [4.17.7] - 2026-06-29

### Added
- **Documentation DDD** : `docs/ARCHITECTURE_STATUS.md` — audit complet de l'état DDD pour les 12 modules (Domain, Application, Infrastructure, Interfaces, Providers, Tests).
- **Tests EmployeeLoan** : Suite de tests Feature couvrant création, liste, remboursement, et isolation multi-tenant pour `EmployeeLoanController`.

## [4.17.4] - 2026-06-28

### Fixed

- **Auth — Employee implements HasApiTokens** : `Employee` déclare maintenant explicitement `implements HasApiTokensContract` pour résoudre le `TypeError` dans `LogoutAction` et `RefreshTokenAction` lors de l'appel à `execute()` avec un `Employee`.
- **Modules\HR\UserInvitationService — import TenantManager** : Ajout du `use App\Services\TenantManager` manquant qui causait un `BindingResolutionException` à l'injection de dépendance.
- **Recruitment\RecruitmentService — PHPStan CarbonInterface** : `published_at` est maintenant assigné via `Carbon::instance(now())` pour satisfaire le type `Carbon|null` déclaré sur `JobPosting::$published_at`.

## [4.17.4] - 2026-07-04

### Changed

- **EdgeSync** : Migre `EdgeController` et `EdgeDownloadController` de `App\Http\Controllers\Api\V1` vers `App\Modules\EdgeSync\Interfaces\Api\V1`. Routes gérées par `EdgeSyncServiceProvider`. Les anciens fichiers plats sont supprimés.
- **PHPStan modules** : Niveau relevé de 3 → 5 avec suppressions ciblées pour les modules en cours de migration.

### Added

- **Growth/Application/Actions** : `ApplyAsPartner`, `ApprovePartner`, `RequestPayout` — couche Application enrichie.
- **Training/Application/Actions** : `CreateCourse`, `EnrollEmployee`, `CompleteEnrollment` — use cases DDD complets.
- **Platform/Application/Actions** : `ProvisionCompany`, `ActivateCompany`, `SuspendCompany` — cycle de vie tenant.
- **Onboarding/Application/Actions** : `SeedDefaultSteps`, `CompleteStep`, `SkipStep` — parcours onboarding modulaire.

## [4.17.3] - 2026-06-28

### Changed

- **Architecture DDD — Migration complète des routes vers les modules** : Correction des namespaces de 17 contrôleurs HR (`ContractController`, `DepartmentController`, `EmployeeController`, `SelfServiceController`, `OrgChartController`, `TrainingController`, etc.) vers `App\Modules\HR\Interfaces\Api\V1\Controllers`. Mise à jour des docs de gouvernance CI.
- **PHPStan modules** : Configuration `phpstan-modules.neon` consolidée avec `excludePaths` pour les modèles DDD non stabilisés, level 3, sans BOM.

## [4.17.2] - 2026-06-28

### Added

- **Mobile Apps — Séparation des applications Manager et RH** :
  - L'application `leopardo_manager` a été scindée pour créer une application spécifique `leopardo_hr`.
  - Intégration de `leopardo_hr` dans la matrice de CI canonique `mobile-distribute.yml` pour le déploiement sur Firebase.
  - Suppression de `mobile-hr-distribution.yml` redondant.
  - Résolution des chemins de routes et des conflits dans `app.dart` pour le manager et tests associés.

### Changed

- **Architecture DDD — Migration complète des routes vers les modules** : Correction des namespaces de 17 contrôleurs HR (`ContractController`, `DepartmentController`, `EmployeeController`, `SelfServiceController`, `OrgChartController`, `TrainingController`, etc.) vers `App\Modules\HR\Interfaces\Api\V1\Controllers`. Mise à jour des docs de gouvernance CI.
- **PHPStan modules** : Configuration `phpstan-modules.neon` consolidée avec `excludePaths` pour les modèles DDD non stabilisés, level 3, sans BOM.

## [4.17.2] - 2026-06-28

### Added

- **Mobile Apps — Séparation des applications Manager et RH** :
  - L'application `leopardo_manager` a été scindée pour créer une application spécifique `leopardo_hr`.
  - Intégration de `leopardo_hr` dans la matrice de CI canonique `mobile-distribute.yml` pour le déploiement sur Firebase.
  - Suppression de `mobile-hr-distribution.yml` redondant.
  - Résolution des chemins de routes et des conflits dans `app.dart` pour le manager et tests associés.

### Changed

- **Architecture — Migration Finale des Routes vers les Modules (DDD)** :
  - Création des 8 derniers contrôleurs modulaires (`MeController`, `SiteController`, `EstimationController`, `NotificationStreamController`, `AdvancedReportController`, `AuditLogController`, `EmployeeLoanController`, `PredictionController`).
  - Suppression définitive des anciens contrôleurs dans `app/Http/Controllers/Api/V1/` devenus obsolètes.
  - Refactorisation de `AuthController` avec l'implémentation de 6 nouvelles classes d'Actions (`LoginAction`, `LogoutAction`, `RefreshTokenAction`, etc.).
  - Nettoyage et correction des types stricts dans les Actions Auth pour PHPStan.
  - Mise à jour du document de référence `api/ARCHITECTURE.md` pour refléter l'état 100% migré des routes.

- **Architecture — Auth migré vers Clean Architecture (DDD)** :
  - Contrôleurs Auth déplacés de `App\Http\Controllers\Api\V1` vers `App\Core\Auth\Interfaces\Api\V1` (AuthController, PlatformAuthController, UserAuthController).
  - Services Auth déplacés de `App\Services` vers `App\Core\Auth\Infrastructure\Services` (AuthService, UserAuthService).
  - Modèles `Employee` et `User` déplacés de `App\Models` vers `App\Core\Auth\Domain\Models`.
  - 389+ fichiers mis à jour pour les nouveaux namespaces (contrôleurs, services, tests, seeders, factories).
  - `config/auth.php` mis à jour pour pointer vers les nouveaux modèles Core.
  - `routes/modules/user.php` mis à jour pour le nouveau UserAuthController.
  - `newFactory()` ajouté aux modèles pour garantir la résolution des factories depuis le nouveau namespace.
  - Exceptions dupliquées supprimées dans `Core/Auth/Domain/Exceptions` (les originaux dans `App\Exceptions` font foi).
  - `phpstan-baseline.neon` mis à jour pour les nouveaux namespaces.
  - `declare(strict_types=1)` ajouté à tous les fichiers Core/Auth.
  - Services Auth passés en `readonly class` (PHP 8.2+).

### Removed

- Anciens fichiers dupliqués supprimés : `app/Http/Controllers/Api/V1/AuthController.php`, `PlatformAuthController.php`, `UserAuthController.php`, `app/Services/AuthService.php`, `UserAuthService.php`, `app/Models/Employee.php`, `app/Models/User.php`.

## [4.17.1] - 2026-06-28

### Fixed

- **PHPStan — Modules Architecture** : Résolution de 91 erreurs dans les modules `Payroll` et `Planning`.
  - `SocialDeclarationGenerator` : Suppression des opérateurs `??` inutiles sur les offsets de tableaux garantis par PHPDoc (`employee`, `metadata`).
  - `ClientEvent` (Planning/Domain/Models) : Ajout des imports manquants pour `Company` et `Employee`.
  - `AbsenceService` (Planning/Infrastructure) : Signature de `logBalanceChange()` accepte désormais `int|string|null` pour `$companyId` au lieu de `string` uniquement.

## [4.16.255] - 2026-06-21   
## [4.17.0] - 2026-06-23  
## [4.16.255] - 2026-06-21
## [4.17.0] - 2026-06-23

### Added 

- **Architecture multi-app** : Nouvelle architecture RBAC multi-application. L'API supporte désormais plusieurs apps mobiles distinctes selon le `manager_role` de l'employé.
- **App Mobile RH** : Routes dédiées `/api/v1/hr/**` accessibles uniquement aux `manager_role: rh` (et `principal` par héritage).
  - `GET /hr/me` — profil RH avec contexte app
  - `GET /hr/dashboard` — stats RH (employés actifs, invitations en attente, nouveaux du mois)
  - `GET /hr/team-overview` — vue compacte de l'équipe
  - `GET /hr/employees` — liste paginée et filtrable
  - `POST /hr/employees` — ajouter un employé (role=employee forcé, sans assignation de manager_role)
  - `GET /hr/employees/{id}` — détail employé
  - `PATCH /hr/employees/{id}` — modifier employé (sans toucher aux rôles)
- **HrController** : Nouveau contrôleur dédié à l'app RH avec logique d'isolation stricte (le RH ne peut pas créer de managers).
- **EnsureAppContextMiddleware** : Middleware optionnel `app.context` — valide la cohérence entre le header `X-App-Context` et le rôle de l'utilisateur. Utilisé pour l'audit et la sécurité cross-app.
- **MobileExperienceService amélioré** : Modules et quick_actions différenciés par rôle — le `principal` voit la gestion des rôles, le `rh` voit les outils RH, l'employé voit le self-service. Nouveau champ `app` dans la réponse indique quelle app mobile l'employé devrait utiliser.
- **Documentation** : `docs/architecture/MULTI_APP_ARCHITECTURE.md` — cartographie complète des apps, rôles, règles d'assignation et routes par app.
- **Tests** : `HrAppRoutesTest` — couverture des routes HR app (accès RH, refus employé standard, refus marketing manager, isolation ajout employé sans escalade de rôle).

## [4.16.255] - 2026-06-21

### Added

- Vitrine : Ajout du plan **Free** dans `pricing.ts` et `PricingSection` — accès gratuit avec feature set limité pour élargir le tunnel d'acquisition.
- Vitrine : Intégration de **Google OAuth** sur `/auth/login` — bouton "Continuer avec Google" avec provider `google` branché sur le flux NextAuth existant.
- Vitrine : Nouveau **checkout flow modernisé** — pages `/checkout` et `/checkout/success` avec sélection de plan, récapitulatif et confirmation post-paiement.
- Vitrine : Route API `/api/billing/checkout` (Next.js Route Handler) pour créer une session Stripe Checkout côté serveur.
- Vitrine : Composant `StickyMobileCTA` — CTA flottant mobile visible après 400px de scroll, localisé FR/EN/TR/AR.
- Vitrine Phase-3 : Remplacement des composants `Legacy*` par les nouvelles sections premium (`HeroSection`, `FAQSection`, `CTASection`, `TestimonialsSection`, `FeaturesSection`) dans `landing/page.tsx`.

### Fixed

- Vitrine : Export de `QuickTrialEmailForm` depuis `HeroSection.tsx` pour permettre son usage dans le test E2E Playwright marketing-funnel.
- CI : Correction du test Playwright `marketing-funnel.spec.ts` — le formulaire hero email-trial (`section form input[type="email"]`) est désormais dans le DOM via `LegacyHeroSection → HeroSection` qui inclut `QuickTrialEmailForm`.
### Fixed

- Vitrine : Export de `QuickTrialEmailForm` depuis `HeroSection.tsx` pour permettre son usage dans le test E2E Playwright marketing-funnel.
- CI : Correction du test Playwright `marketing-funnel.spec.ts` — le formulaire hero email-trial (`section form input[type="email"]`) est désormais dans le DOM via `LegacyHeroSection → HeroSection` qui inclut `QuickTrialEmailForm`.
### Fixed

- Growth Module : Correction des marqueurs de conflit Git résiduels dans `routes/modules/growth.php`, `PartnerDashboardController.php`, `CommissionService.php` et `front/web/src/app/(dashboard)/partner/page.tsx` — les fichiers contenaient des  non résolus causant un `ParseError` au boot Laravel (`php artisan package:discover`).
- Growth Module Auth : Le middleware des routes `/partner/*` utilise désormais `auth:sanctum` (token Employee) au lieu de `auth:user_api`. Le contrôleur `PartnerDashboardController` résout l'utilisateur global (`public.users`) via `resolveGlobalUser()` — compatible avec les tokens Sanctum Employee émis par l'app web et mobile.
- Growth Module Frontend : La page `partner/page.tsx` affiche maintenant l'état `not_applied` en cas d'erreur réseau au lieu de rester bloquée sur "Chargement de votre espace".
- CommissionService : Suppression des exceptions de debug temporaires (`throw new RuntimeException('Failed X')`) remplacées par des retours `null` propres conformes à la logique production.

## [4.16.254] - 2026-06-21

### Fixed

- Growth Module CI : Correction de `GrowthModuleTest` — ajout de `tearDown()` appelant `tearDownMvpSchema()` pour eviter les violations de contrainte unique entre tests.
- Growth Module CI : Ajout de la colonne `referrer_partner_id bigint NULL` dans le fixture PostgreSQL `mvp_schema.pgsql.sql` (manquante dans le schema de test, causant des echecs `column not found`).
- Growth Module CI : Correction PHPStan dans `PartnerLinkMiddleware` — operateur null-safe sur `$link->partner?->status`, cast `(string)` sur `$link->partner_id`, et typage explicite `@var Response` sur le retour de `$next($request)`.
- Governance : Mise a jour de `SCENARIOS_TEST_API_GITHUB_ACTIONS.md` et `REGISTRE_SCENARIOS_TESTS.md` avec les scenarios Growth Module (partenariat et parrainage).

## [4.16.253] - 2026-06-20
### Added

- Growth Module Hardening : Renforcement de la sécurité, de la précision financière et des workflows opérationnels.
- Workflow Partenaire : Ajout du cycle de candidature (Postuler → En attente → Approuvé/Refusé).
- Finance : Support du calcul de commission sur base HT (tax_rate configurable par partenaire).
- Finance : Gestion des demandes de paiement (Payouts) avec seuil minimal (threshold) et vérification du solde.
- Sécurité : Chiffrement des coordonnées bancaires des partenaires via `SensitiveDataEncryptor`.
- Performance : Commande d'archivage automatique des clics de tracking (`growth:archive-clicks`).
- Conformité : Intégration du consentement cookie dans le middleware de parrainage.
- Administration : Nouveau cockpit admin multi-onglets (Partenaires, Payouts, Audits).

## [4.16.252] - 2026-06-19

### Added

- Growth Module : Implémentation initiale du système de parrainage et de commissions.

## [4.16.251] - 2026-06-13

### Added

- Planification : extension PLAN_ACTION2 v1.1 a 130 tickets avec scopes pays/paie/pointage, communication/annonces/discussions et supervision GitHub Projects multi-agents.
- Outillage : ajout des scripts alidate-plan-action2.ps1, pick-plan-action2-task.ps1, sync-plan-action2-project.ps1 et du workflow PLAN_ACTION2 Project Sync pour valider, selectionner et synchroniser les tickets PA2 vers GitHub Projects.

- API : ajout de `POST /api/v1/trial/signup` â€” endpoint public de provisioning self-service qui cree un tenant trial (30 jours) avec manager principal en < 30 secondes, sans intervention super-admin.
- API : ajout de `SelfServiceTrialController` avec generation de mot de passe lisible, detection de doublon email, creation `CompanyRequest` pour tracabilite CRM, et fallback defensif search_path.
- API : ajout de `StripeService` â€” integration Stripe Checkout (sessions, portail client) et webhooks (checkout.session.completed, invoice.paid, customer.subscription.updated/deleted) via API REST directe sans SDK.
- API : ajout de `StripeWebhookController` â€” endpoint public `POST /api/v1/webhooks/stripe` avec verification signature HMAC-SHA256 et retry-safe (200 meme en cas d'erreur de traitement).
- API : ajout des routes `POST /billing/checkout` et `GET /billing/portal` dans le module billing pour les managers principal.
- API : ajout de la configuration Stripe dans `config/services.php` et `.env.example` (STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET, STRIPE_PRICE_STARTER/BUSINESS/ENTERPRISE).
- Vitrine : la route `POST /api/forms/signup` appelle desormais le backend `POST /api/v1/trial/signup` pour provisioner instantanement le tenant; en cas d'echec API, fallback vers le guided trial existant.
- Vitrine : `SignupForm` affiche les credentials (email + mot de passe temporaire avec copier-coller) apres provisioning reussi, avec boutons "Se connecter" et "Telecharger l'app".
- Planification : ajout de `docs/PLAN_ACTION2/`, un backlog atomique importable dans GitHub Projects pour piloter la prochaine phase produit, securite, stabilite, marketing, mobile, kiosk, API, finance, i18n et operations.
- Vitrine : ajout de `LaunchOperatingSystemSection` pour vendre un lancement pilote en 7 jours avec resultats concrets par etape.
- Tests : ajout des tests unitaires pour `PushNotificationService` afin de securiser l'architecture des notifications push.

### Changed

- API : `BillingController` injecte desormais `StripeService` et retire `chargily` de la validation payment_method (Stripe seul pour le lancement).
- Vitrine : pricing mis a jour avec les offres Pilot, Operations et Enterprise, essai 30 jours, puis tarification par employe actif pour les PME terrain.
- Vitrine : FAQ mise a jour pour refleter l'essai de 30 jours et la creation instantanee d'espace.
- Vitrine : CTA du formulaire signup change de "Recevoir mon acces d'essai" a "Creer mon espace d'essai gratuit".
- Vitrine : le hero remplace les chiffres marketing invérifiables par des preuves produit concretes (3 apps mobiles, 2 apps web, essai 30 jours, pilote 7 jours) et repositionne Leopardo comme systeme operationnel terrain.
- `PILOTAGE.md` : reecrit completement pour refleter v4.16.250+ â€” 8 surfaces, 87 modeles, 93 controleurs, 25 workflows CI/CD, 72 plans livres, priorites P0-P3, cibles MRR.
- Module billing routes : suppression des webhooks Chargily orphelins, ajout des routes Stripe Checkout et Customer Portal.

### Fixed

- API provisioning : les templates secteur creent/reutilisent maintenant un departement `Operations` avant de generer les postes lorsque `positions.department_id` est obligatoire, ce qui evite les 500 pendant la creation entreprise et le self-service trial.
- API self-service : la resolution des plans lit explicitement `public.plans` et restaure toujours le `search_path` apres la detection d'email tenant, afin d'eviter les 500 `relation plans does not exist`.
- API billing : les webhooks Stripe acceptent les payloads sandbox/test lorsque le secret webhook est absent, traitent `invoice.paid`/`invoice.payment_failed` sur les invoices existantes, et conservent la route Chargily legacy.
- Tests backend : `SelfServiceTrialTest` utilise maintenant le refresh public/tenant du projet afin de couvrir le vrai schema `plans` + `companies`.
- API self-service : si `public.plans` existe mais ne contient encore aucun plan actif, le provisioning cree un plan `Trial` defensif au lieu de retourner un 503 au prospect.
- API self-service : la trace CRM `company_requests` renseigne aussi les champs legacy `manager_name`, `manager_phone` et `notes` pour rester compatible avec les bases historiques.
- API self-service : la deduction automatique du nom manager reste stable pour les emails simples (`founder@newtech.dz` -> `Founder Newtech.dz`).
- CI : l'upload du resume qualite mobile legacy n'est plus bloquant quand `front/mobile/quality-summary.md` n'est pas produit par un run backend/coverage.
- CI : le workflow principal publie de nouveau le contexte requis `Mobile Flutter (Stable Channel)` via un job de compatibilite leger, en attendant la mise a jour de la protection `main` vers `mobile-apps-ci.yml`.

- Tests web : le smoke manager marque le tenant mock comme deja onboarde afin de tester la navigation dashboard/equipe/pointage/absences sans modal d'onboarding parasite.

## [4.16.250] - 2026-06-06

### Added 

- Mobile manager/API : les horaires deviennent des regles entreprise enrichies avec pauses structurees, jours de repos, regles conges et notes internes.
- API manager : ajout de `POST /api/v1/schedules/{schedule}/assign-employees` pour affecter une regle horaire a plusieurs employes du tenant courant avec garde anti-fuite inter-tenant.
- Mobile manager : ajout de l'action "Affecter aux employes" dans l'ecran Horaires afin d'appliquer une regle a une selection d'employes existants.
- Plan 71 : cadrage go-to-market admin plateforme, multi-pays, i18n, vitrine essai, kiosk et audit commercial-technique.
- Audit go-to-market 2026-06-06 avec verdict pilote, risques et priorites commerciales/techniques.
- Backend : ajout de `CountryDefaults` pour deriver langue, devise et timezone a partir du pays lors du provisioning plateforme.
- API platform : ajout de `GET /api/v1/platform/country-defaults` pour exposer la source de verite pays/devise/timezone/langue aux frontends super-admin.
- I18n : ajout du guide Jules EN/AR/TR et du garde `validate-i18n-debt.ps1` pour mesurer les textes hardcodes par surface.
- Plan 72 : ajout d'un contrat de workflows lancement multi-surface et du validateur `validate-launch-workflows.ps1`.
- Validation lancement : ajout du rapport `LAUNCH_WORKFLOW_CONTRACTS_2026_06_06.md` avec preuves Plan 72.1 et release readiness `27/27`.
- Recette API lancement : ajout de `launch-api-profile-smoke.ps1` et du rapport `LAUNCH_API_PROFILE_SMOKE_2026_06_06.md` pour verifier les endpoints publics, employee, manager/RH, platform admin et kiosk avec tokens proteges.
- CI lancement : ajout du workflow manuel `Launch API Profile Smoke` pour executer cette recette avec secrets GitHub et artefact de rapport.
- CI lancement : ajout d'un provisioning kiosque controle au smoke API pour enregistrer un appareil temporaire via manager demo puis verifier `roster` et `announcements` avec le vrai `X-Kiosk-Token`.
- Vitrine : ajout d'une section de preuve operationnelle localisee pour presenter les 3 apps mobiles, 2 apps web, kiosk/biometrie et API production avant les fonctionnalites.
- Vitrine : ajout d'un formulaire hero email-only `hero_email_trial` qui capture une demande d'essai guidee via `/api/forms/signup` sans mot de passe ni carte bancaire.

### Changed

- Admin dashboard : le cockpit clients permet maintenant de creer un client plateforme complet depuis `/companies`, avec pays/devise/timezone/langue derives par `/platform/country-defaults`, statut `trial` ou `active`, manager principal et redirection vers la fiche creee.
- Admin dashboard : la fiche detail client est modernisee et expose une action directe `Activer client` adossee au contrat `/platform/companies/{company}/subscription`.
- Admin dashboard : la page de connexion super-admin est modernisee et expose un bouton direct `Utiliser le compte demo super-admin` avec le mot de passe demo public `password123`.
- Admin dashboard : le cockpit plateforme expose maintenant un panneau de workflows critiques pour creer/activer les clients, traiter les demandes, surveiller les risques, piloter abonnements, verifier systeme et ouvrir les integrations.
- Mobile manager : l'ecran Horaires devient une surface explicite de regles entreprise avec repos/conges visibles et affectation employes preselectionnee quand une regle est deja appliquee.
- Mobile platform admin : la fiche client affiche maintenant une action directe `Activer client` pour convertir un tenant en essai vers `active` sans passer par le formulaire complet d'abonnement.
- App mobile platform admin : le formulaire de creation client propose un choix pays controle, affiche devise/timezone/langue, et permet de creer un client en essai ou actif.
- App mobile platform admin : le formulaire pays consomme maintenant l'API `country-defaults` avec fallback local non bloquant.
- API platform : `POST /api/v1/platform/companies` accepte maintenant `status=trial|active` et ne force plus DZD/Africa-Algiers quand le pays indique une autre devise.
- Vitrine : `/signup` devient une demande d'essai guidee par email, sans mot de passe fantome, avec qualification entreprise/role/taille et prochaine etape explicite sous 24h ouvrables.
- Vitrine : les CTA d'essai des guides RH/planning/paie pointent maintenant vers `/signup` avec source marketing, et la copie arabe de la page essai est lisible en RTL.
- Vitrine : la navigation arabe et les tarifs arabes visibles ne contiennent plus de texte corrompu, et la page pricing aligne FR/EN/TR/AR sur une offre d'essai de 30 jours.
- Kiosk : refonte de l'interface ZKTeco autour du geste biometrie doigt/visage, suppression des IDs HTML dupliques, confirmation de pointage plus lisible et protection contre les doubles clics.
- Mobile employee/manager : ajout d'une vue d'ensemble moderne dans `Compte` pour clarifier identite portable, parcours, documents, QR/biometrie, notifications, securite et session sans ajouter de boutons non fonctionnels.
- Admin dashboard : integration de la refonte premium du cockpit interne (tables, cartes, analytics, clients, paie, utilisateurs) avec tokens `glass-*` et `premium-text`.
- Audit go-to-market : mise a jour du rapport 2026-06-06 apres execution des lots 71.4 a 71.6 et ajout des decisions restantes sur essai sandbox, recette device, i18n et branches distantes.
- OpenAPI et contrat mobile platform admin alignes avec le nouveau provisioning multi-pays.
- Migrations publiques : la reconciliation `company_requests` garantit explicitement les colonnes modernes (`user_id`, contact, review) sur PostgreSQL public quand l'ancienne table `employee_id` existe deja.
- Documentation i18n : `shared/i18n/README.md` pointe maintenant vers le workflow traducteur et le rapport de dette.
- Securite backend : mise a jour de `laravel/framework` vers `^12.60` / `v12.61.1` pour lever l'advisory Composer `CVE-2026-48019`.
- API frontend tooling : mise a jour npm de `axios` 1.17.0, `concurrently` 10.0.3 et `vite` 8.0.16 pour garder le build Laravel/Vite a jour.
- Gouvernance lancement : les workflows visibles web/mobile/kiosk doivent maintenant declarer leurs fichiers, routes, endpoints et tokens critiques dans `launch-workflow-contracts.json`.
- Release readiness : le gate verifie maintenant aussi la presence du smoke API par profil Plan 72.2.
- Release readiness : le gate verifie maintenant aussi la presence du workflow manuel de smoke API par profil.
- Vitrine : le hero marketing et le CTA final alignent maintenant l'essai commercial sur 30 jours en FR/EN/TR/AR, coherent avec la page pricing.
- I18n vitrine : la copie du formulaire rapide `hero_email_trial` est centralisee dans `vitrine-locale.ts` au lieu d'etre portee par le composant hero.
- Contrats lancement : `guided_trial_signup` couvre aussi le formulaire email-only de la premiere vue et le test Playwright associe.

### Fixed

- Vitrine : les boutons d'installation mobile de `/download` utilisent maintenant des URLs publiques configurables par app (`NEXT_PUBLIC_LEOPARDO_*_ANDROID_URL` / `*_IOS_URL`) et basculent vers une demande testeur qualifiee au lieu de liens morts `#android/#ios`.
- Vitrine : `/download` utilise maintenant les liens Firebase App Distribution Android du README comme fallback reel pour Employee, Manager et Platform Admin lorsque les variables publiques de store ne sont pas configurees.
- Mobile employee/manager : les montants de pointage, avances, salaires et fiches de paie utilisent maintenant la devise renvoyee par l'API ou le profil tenant au lieu d'afficher `DZD` en dur sur les clients multi-pays.
- API manager/mobile : `GET /api/v1/employees` rattache maintenant le `currentCompany()` resolu par le middleware tenant au payload liste, afin de garder `company`, `currency` et `features` non nuls sur Render/shared PostgreSQL meme si `shared_tenants` masque `public.companies`.
- E2E staging vitrine : le smoke cible l'entree acquisition email de la landing au lieu de supposer que le premier formulaire est toujours une newsletter.
- CI lancement : correction du passage de `LEOPARDO_API_BASE_URL` au workflow `Launch API Profile Smoke` et protection du script contre les tokens absents afin de produire `SKIP` au lieu de faux echecs manager.
- CI lancement : durcissement supplementaire du smoke API avec splat PowerShell interne pour que les tokens vides ne decalient jamais les parametres.
- CI lancement : correction du workflow manuel `Launch API Profile Smoke` pour transmettre `BaseUrl` via splat hashtable PowerShell au lieu d'un array positionnel.
- CI lancement : le smoke API peut maintenant resoudre automatiquement les tokens employee, manager et platform admin via `/demo-users` lorsque les secrets ne sont pas configures.
- CI lancement : le smoke de creation entreprise platform admin peut maintenant verifier un statut `trial` ou `active` via `PlatformProvisioningStatus`.
- CI lancement : le smoke kiosque peut maintenant sortir du `SKIP` via `IncludeKioskProvisioning` quand les secrets `LEOPARDO_KIOSK_DEVICE_CODE` / `LEOPARDO_KIOSK_TOKEN` ne sont pas fournis.
- API kiosque : `roster` et `announcements` resolvent maintenant l'entreprise depuis `public.companies` afin d'eviter les 500 PostgreSQL quand `shared_tenants` masque la table publique.
- API kiosque : `announcements` reste tolerant aux colonnes optionnelles absentes sur une table tenant historique (`is_active`, dates, priorite, timestamps) afin de retourner une liste exploitable au lieu d'un 500 Render.
- API kiosque : `announcements` retourne maintenant `data=[]` si une table tenant historique ne contient pas `company_id`, afin d'eviter a la fois un 500 et toute fuite inter-tenant.
- API kiosque : `announcements` passe en fail-open journalise pour que les annonces non critiques ne bloquent jamais un appareil kiosque en production lorsqu'un tenant historique a une table non queryable.

## [4.16.249] - 2026-06-05

### Added

- Go-to-market 2026 : ajout du dossier `docs/GOTO_MARKET/2026_MARKET_LAUNCH_COMPANY_OS/` avec audit marche/produit, positionnement, messaging, offres et direction commerciale.
- Plan 70 : ajout de `70_PLAN_MARKET_LAUNCH_2026_COMPANY_OS.md` avec 72 actions nouvelles pour readiness marche, monetisation, preuves terrain, IA gouvernee, operations et expansion.
- Contexte IA : ajout de `docs/CONTEXT/` pour donner a un nouvel agent le contexte produit, technique, operationnel et les priorites courantes.
- Validation : ajout de `MARKET_LAUNCH_AUDIT_2026_06_05.md` avec verdict go pilote payant controle et sources marche 2026.

### Fixed

- Mobile Platform Admin : le bouton `Utiliser le compte demo` remplit maintenant le mot de passe seede `password123` au lieu de l'ancien `admin`.
- Mobile Plan 29 : le garde CI bloque maintenant un retour au mauvais mot de passe demo platform admin.

## [4.16.248] - 2026-06-01

### Changed

- Readiness lancement : mise a jour des rapports Plan 69 apres smoke Render post-#695 (`launch-readiness score=100`, `go_live_ready=true`, zero bloqueur requis) et cloture du lot observabilite API.

## [4.16.247] - 2026-06-01

### Fixed

- Readiness Render : `DemoCompanyOnceSeeder` repare maintenant les signaux readiness des demos existantes meme quand `DISABLE_DEMO_SEEDING=true`, sans creer/recreer de demos.
- Bootstrap notifications : `notifications:backfill-preferences` force le `search_path` PostgreSQL sur `shared_tenants, public` afin de creer les preferences dans le schema consomme par les APIs tenant.

## [4.16.246] - 2026-06-01

### Fixed

- Readiness demo : `DemoCompanyOnceSeeder` backfill maintenant les signaux de lancement manquants des demos existantes (salaire actif minimal, geofence, kiosque actif, evenement client recent) afin que `/api/v1/launch-readiness` puisse atteindre le seuil go-live apres deploiement sans reseed destructif.

## [4.16.245] - 2026-06-01

### Added

- Observabilite lancement : ajout de `notifications:backfill-preferences` et d'un provisioner partage pour creer/reparer les preferences notifications des employes actifs.

### Fixed

- Bootstrap Render : l'entrypoint backfill maintenant les preferences notifications apres migrations/seeders afin de lever le bloqueur `communication_governance` du cockpit `/api/v1/launch-readiness` sans intervention SQL manuelle.

## [4.16.244] - 2026-06-01

### Added

- Plan 69.6 : ajout du rapport `LAUNCH_OBSERVABILITY_SMOKE_2026_06_01.md` avec preuve Render health/live/ready, manager digest et launch-readiness, et verdict go-live conditionnel lie a `communication_governance`.

## [4.16.243] - 2026-06-01

### Fixed

- API readiness lancement : `GET /api/v1/launch-readiness` reutilise maintenant `currentCompany()` et garde le controle paie schema-aware afin d'eviter les faux 404/500 sur Render avec tenants shared PostgreSQL.

## [4.16.242] - 2026-06-01

### Fixed

- Mobile startup P0 : `PushNotificationService` initialise maintenant Firebase/FCM de facon lazy et protegee, sans `FirebaseMessaging.instance` eager avant `Firebase.initializeApp()`, afin qu'un echec Firebase/FCM ne bloque jamais Employee, Manager ou Platform Admin au lancement.
- Mobile Android : les trois apps separees appliquent maintenant le plugin Gradle `com.google.gms.google-services` en plus des `google-services.json`, pour garantir les ressources Firebase natives en release.
- Mobile CI : `validate-mobile-runtime-smoke.ps1` verifie le contrat anti-ecran-noir, le plugin Google Services et l'absence de Firebase Messaging eager.

## [4.16.241] - 2026-06-01

### Added

- Plan 69.5 : ajout du rapport `PAYROLL_FINANCE_API_SMOKE_2026_06_01.md` avec preuve Render avance double validation, paiement masse, documents PDF asynchrones, confirmation employe, resume paie mobile et solde employe manager.

## [4.16.240] - 2026-06-01

### Fixed

- API paie mobile : `GET /api/v1/employees/{employee}/balance` utilise maintenant le parametre de route correct et `GET /api/v1/payroll/mobile-summary` degrade un solde employe isole en payload partiel au lieu de faire tomber toute la synthese manager.

## [4.16.239] - 2026-06-01

### Fixed

- API paie mobile : `PayrollCycleService` reutilise l'entreprise courante resolue par le middleware tenant pour calculer les soldes, afin d'eviter les 500 lies au `search_path` quand une table tenant masque `public.companies`.

## [4.16.238] - 2026-06-01

### Fixed

- API paie mobile : `GET /api/v1/payroll/mobile-summary` selectionne maintenant les colonnes employe selon le schema courant afin d'eviter les 500 Render sur tenants historiques avant les backfills complets.

## [4.16.237] - 2026-06-01

### Added

- Plan 69.4 : ajout du rapport `PLATFORM_ADMIN_API_SMOKE_2026_06_01.md` avec preuve Render login super-admin, creation entreprise et fiche client platform.

## [4.16.236] - 2026-06-01

### Fixed

- API platform admin : les endpoints detail entreprise utilisent maintenant `PlatformCompanyLookup` avec table qualifiee `public.companies` afin d'eviter les faux `404/500` lies au `search_path` PostgreSQL sur Render.

## [4.16.235] - 2026-06-01

### Fixed

- API platform admin : les endpoints detail entreprise (`/platform/companies/{id}/health`, `/subscription`, `/features`) forcent maintenant `search_path=public` avant de charger `Company`, pour eviter les `404` apres provisioning ou apres une requete tenant.

## [4.16.234] - 2026-06-01

### Fixed

- Demo / platform admin : `DemoCompanyOnceSeeder` resynchronise le compte super-admin demo expose par `/api/v1/demo-users` (`admin@leopardo-rh.com` / `password123`) et desactive le 2FA demo si necessaire, afin que l'app mobile platform admin puisse etre testee sur Render.

### Added

- Test de regression `DemoUserControllerTest::test_demo_once_seeder_keeps_public_super_admin_credentials_usable`.

## [4.16.233] - 2026-06-01

### Added

- Plan 69.3 : rapport manager/RH mis a jour apres deploiement Render #680 avec preuve finale `GET /employees`, isolation tenant, creation/suppression tache et creation/archivage collaborateur temporaire.

## [4.16.232] - 2026-06-01

### Fixed

- API manager/RH : `GET /api/v1/employees` filtre aussi les colonnes des relations `company` / `schedule`, de la recherche et du tri attendance selon le schema courant afin d'eviter les 500 Render quand un tenant historique n'a pas encore toutes les colonnes optionnelles.

## [4.16.231] - 2026-06-01

### Fixed

- API manager/RH : `GET /api/v1/employees` selectionne maintenant les champs exposes par `EmployeeResource` uniquement quand ils existent dans le schema courant, et `EmployeeResource` tolere les attributs optionnels absents afin d'eviter les erreurs production sur liste equipe.

### Added

- Plan 69.3 : ajout du rapport `MANAGER_RH_API_SMOKE_2026_06_01.md` avec le smoke manager/RH Render et le no-go partiel liste equipe corrige.

## [4.16.230] - 2026-06-01

### Fixed

- Mobile employee/manager : les formulaires d'absence lisent maintenant les soldes self-service via `/me/leave-balances` au lieu de la route manager `/leave-balances`.
- Demo Render : `DemoCompanySeeder` et `DemoCompanyOnceSeeder` creent/backfill les `leave_balances` des entreprises demo pour que les demandes d'absence testeur puissent recuperer un `absence_type_id`.

### Added

- Plan 69.2 : ajout du rapport `EMPLOYEE_TERRAIN_API_SMOKE_2026_06_01.md` couvrant login, lectures employee, pointage multiple, avance et blocage absence corrige.

## [4.16.229] - 2026-06-01

### Added

- Plan 69.1 : ajout du rapport `MOBILE_RELEASE_DEVICE_QA_2026_06_01.md` apres distribution Firebase staging des trois apps mobiles.

### Changed

- Plan 69 : lot 69.1 marque livre cote CI/Firebase, avec device QA encore a confirmer par testeurs physiques.

## [4.16.228] - 2026-06-01

### Added

- Ajout du Plan 69 `69_PLAN_EXECUTION_LANCEMENT_MOBILE_FIRST_COMPANY_OS.md` comme prochain cycle d'execution post-audit.
- Ajout du rapport `NEXT_PRODUCT_PLAN_2026_06_01.md` pour cloturer le Plan 68 et prioriser les lots lancement.

### Changed

- Plan 68 : lot 68.5 et statut final marques livres, avec suite canonique vers Plan 69.
- Sommaire des plans : ajout du Plan 69.

## [4.16.227] - 2026-06-01

### Added

- Plan 68.4 : ajout du garde `validate-production-ops-readiness.ps1` et du rapport `PRODUCTION_OPS_READINESS_REPORT_2026_06_01.md`.

### Changed

- `DEPLOYMENT_GUIDE.md` documente les secrets CI/CD/mobile critiques pour Render et Firebase Distribution.
- Release readiness : le gate strict passe a `26/26` en incluant la preuve operations production.

## [4.16.226] - 2026-06-01

### Added

- Plan 68.3 : ajout du garde `validate-code-quality-governance.ps1` et du rapport `CODE_QUALITY_GOVERNANCE_REPORT_2026_06_01.md`.

### Changed

- Documentation API : `docs/api/README.md` pointe vers `/docs`, `/docs/openapi.yaml`, `/api-explorer` et les SDK canoniques.
- Sommaire des plans : remplacement de l'ancien chemin `openapi/v1.yaml` par `api/openapi.yaml` et `/docs/openapi.yaml`.
- Release readiness : le gate strict passe a `25/25` en incluant la preuve qualite code post-67.

## [4.16.225] - 2026-06-01

### Added

- Plan 68.2 : ajout du garde `validate-frontend-api-contract-governance.ps1` pour relier matrice frontend/API, `FrontendApiContractTest`, contrats mobiles et OpenAPI CI sur les routes critiques.
- Ajout du rapport `FRONTEND_API_CONTRACT_GOVERNANCE_REPORT_2026_06_01.md`.

### Changed

- Release readiness : le gate strict passe a `24/24` en incluant la gouvernance contrats front/API.

## [4.16.224] - 2026-06-01

### Added

- Ajout du Plan 68 `68_PLAN_AUDIT_POST_67_QUALITE_CODE_LANCEMENT.md` pour organiser l'audit post-Plan 67 avant le prochain cycle produit.
- Ajout du garde `repository-hygiene-report.ps1` et du rapport `REPOSITORY_HYGIENE_REPORT_2026_06_01.md` pour verifier branches distantes/locales et alignement `origin/main`.

### Changed

- Sommaire des plans : ajout du Plan 68 comme suite canonique apres cloture du Plan 67.

## [4.16.223] - 2026-06-01

### Added

- Plan 67.7 : cadrage open core/marketplace avec ADR 0004, guide dedie et rapport readiness marketplace.
- Ajout du garde `validate-open-core-boundaries.ps1` pour verifier que les bornes open source, enterprise-only, secrets, licences, scopes API et webhooks sont documentes.

### Changed

- Release readiness : le gate strict passe a `23/23` en incluant la preuve open core/marketplace.
- Guide partenaires : rappel que les integrations passent par API publique, webhooks signes et scopes documentes, sans import direct du code interne.

## [4.16.222] - 2026-06-01

### Changed

- Plan 67.6 : `release-readiness.ps1 -Strict` controle maintenant 22 points, incluant les trois apps mobiles de lancement, les gardes Plan 67, la distribution Firebase, la vitrine web et le kiosk.
- Release readiness : ajout du rapport profile-based `RELEASE_READINESS_REPORT_2026_06_01.md` avec scores employee, manager/RH, platform admin, API, vitrine, kiosk et operations.
- Documentation gate release : mise a jour du score attendu `22/22` et des surfaces mobile multi-app, vitrine et kiosk.

## [4.16.221] - 2026-06-01

### Added

- Plan 67.5 : ajout du garde `validate-mobile-notification-production-proof.ps1` pour figer le cycle FCM employee/manager, les actions notifications et les routes backend push.
- API docs : documentation OpenAPI de `GET/POST/DELETE /device-tokens` et `POST /push-notifications/send`.
- Tests backend : `DeviceTokenControllerTest` couvre maintenant register/upsert/list/delete et l'envoi test manager via `CommunicationService`.
- Ajout du rapport `MOBILE_NOTIFICATIONS_PRODUCTION_PROOF_2026_06_01.md` avec scenarios employee, manager et limite explicite du super-admin push.
- Scenarios API GitHub Actions : ajout de la note Plan 67.5 pour les contrats `/device-tokens` et `/push-notifications/send`.

### Fixed

- API push : `PushNotificationService::registerToken()` renseigne maintenant `company_id` lorsque la table `device_tokens` l'exige, tout en restant compatible avec les anciens schemas sans colonne tenant.

## [4.16.220] - 2026-06-01

### Added

- Plan 67.4 : ajout du socle `TenantBranding` / `TenantTheme` / `TenantBrandMark` dans `leopardo_core`.
- Apps employee/manager : lecture tolerante de `/company/branding`, application du theme tenant et affichage nom/logo entreprise sur la home.
- CI mobile : ajout du garde `validate-mobile-tenant-branding.ps1` et du rapport `MOBILE_TENANT_BRANDING_REPORT_2026_06_01.md`.

## [4.16.219] - 2026-06-01

### Added

- Plan 67.3 : ajout de `AttendanceLocationService` dans `leopardo_core` pour collecter le GPS au moment du pointage sans bloquer l'UX.
- Apps employee/manager : ajout des permissions GPS Android/iOS, envoi de `gps_lat`, `gps_lng`, `gps_accuracy` et feedback doux si le geofence detecte un hors-zone.
- API attendance : validation et exposition de `gps_accuracy` via `gps.accuracy_m`.
- CI mobile : ajout du garde `validate-mobile-location-readiness.ps1` et du rapport `MOBILE_GPS_GEOFENCE_REPORT_2026_06_01.md`.

## [4.16.218] - 2026-06-01

### Changed

- Plan 67.2 : l'app mobile platform admin recupere maintenant le client cree par `POST /platform/companies` et redirige directement vers sa fiche.
- `PlatformCompany.fromProvisioningResponse()` couvre le payload `data.company` et conserve les UUID comme strings pour le routing mobile.
- Ajout du rapport `PLATFORM_ADMIN_E2E_REPORT_2026_06_01.md` et d'un test modele platform admin pour le mapping creation client.

## [4.16.217] - 2026-06-01

### Added

- Plan 67.1 : ajout du garde `validate-mobile-runtime-smoke.ps1` pour bloquer les regressions de demarrage mobile avant premier rendu.
- `mobile-apps-ci.yml` execute maintenant `Validate mobile runtime smoke` sur les changements `front/mobile_apps/**`.
- Ajout du rapport `MOBILE_RUNTIME_SMOKE_REPORT_2026_06_01.md` documentant le contrat anti page noire/logo infini pour employee, manager et platform admin.

## [4.16.216] - 2026-06-01

### Added

- Plan 66.1 : ajout de la matrice anti-oubli `docs/validation/PLAN_ACTION_COVERAGE_MATRIX_2026_06_01.md`, couvrant les 44 points consolides avec statut, plan source, preuve et prochain lot.
- Ajout du Plan 67 `67_PLAN_AUDIT_FINAL_QUALITE_PRODUIT.md` pour reprendre les derniers lots launch-readiness : runtime mobile, super-admin E2E, GPS natif, theming tenant, notifications et rapport release.
- Le sommaire des plans reference maintenant le Plan 67 comme suite canonique apres cloture/cartographie des plans 01-66.

## [4.16.215] - 2026-06-01

### Added

- Plan 58 : ajout du contrat tenant `GET/PATCH /api/v1/company/branding` pour nom affiche, logo, couleurs et mode visuel.
- Mobile manager : ajout de l'ecran `Identite entreprise` avec preview logo/couleurs et sauvegarde via l'API branding.
- OpenAPI, matrice frontend/API, contrats workflow mobile et tests backend couvrent maintenant la personnalisation entreprise.

## [4.16.214] - 2026-06-01

### Added

- Plan 65 : ajout des tables/modeles `payment_batches`, `payment_items` et `payment_confirmations` pour suivre les paiements en masse au-dela de l'ancien job Redis.
- Ajout des endpoints manager `GET/POST /api/v1/payment-batches`, `GET /api/v1/payment-batches/{paymentBatch}` et `POST /api/v1/payment-batches/{paymentBatch}/mark-paid`.
- Ajout de l'endpoint employee `POST /api/v1/payment-confirmations/{paymentItem}/confirm`, idempotent et trace avec device signature, IP, user-agent et version document.
- `mark-paid` declenche les documents de paiement async via `GeneratePaymentDocumentJob`.

## [4.16.213] - 2026-06-01

### Added

- Plan 64 : le pointage accepte maintenant `device_timezone` et expose UTC, heure locale entreprise, GPS et geofence doux dans `AttendanceLogResource`.
- Plan 64 : ajout de `AttendanceGeofenceService` pour calculer la distance Haversine et determiner `geofence.inside` depuis un site employe ou `company.metadata.attendance_geofence`.
- Mobile employee/manager : les repositories de pointage envoient le contexte timezone device et acceptent GPS optionnel sans bloquer l'UX.

### Fixed

- `attendance:auto-close` respecte maintenant `company.metadata.attendance_auto_close`, trace la fenetre de correction dans `punch_meta.auto_close` et n'utilise plus le statut invalide `auto_closed`.

## [4.16.212] - 2026-06-01

### Added

- Plan 63 : `queue:health-check` expose maintenant les profondeurs des queues `documents`, `pdf`, `payroll`, `notifications`, `webhooks`, `default`, la connexion queue active et le compteur `failed_jobs`.
- Plan 63 : cache tenant court sur `dashboard/manager-digest`, cache schedules avec invalidation create/update/delete, et invalidation employees cache sur create/update/archive.
- Scheduler backend : `attendance:auto-close --threshold=12` est planifie toutes les heures et `queue:health-check` toutes les 5 minutes lorsque `QUEUE_CONNECTION=redis`.

### Changed

- Redis utilise `predis` par defaut pour rester compatible Upstash/TLS, avec `REDIS_SCHEME` configurable.
- Le runbook de deploiement worker documente la queue `documents` et la commande worker production `documents,pdf,payroll,notifications,webhooks,default`.

## [4.16.211] - 2026-06-01

### Added

- Plan 62 mobile : l'app employee affiche maintenant les documents de paiement (`pending/generating/available/failed`) dans l'ecran paie et permet le telechargement des fichiers disponibles.
- Plan 62 mobile : l'app manager expose une action `Documents paiement` sur chaque ligne paie pour consulter le statut de generation des documents d'un cycle et telecharger les fichiers disponibles.
- Le contrat mobile multi-app documente maintenant les endpoints `/me/payment-documents`, `/me/payment-documents/{id}/download` et `/payments/{payrollRun}/documents`.

## [4.16.210] - 2026-06-01

### Added

- Plan 62 backend : ajout du contrat tenant `payment_documents` pour indexer les recus, bordereaux, bulletins et resumes paie generes en arriere-plan.
- Ajout de `GeneratePaymentDocumentJob` sur queue `documents`, avec etats `pending/generating/available/failed` et generation PDF non bloquante.
- Ajout des endpoints `GET /api/v1/me/payment-documents`, `GET /api/v1/me/payment-documents/{paymentDocument}/download`, `GET /api/v1/payroll-runs/{payrollRun}/payment-documents` et alias `GET /api/v1/payments/{payrollRun}/documents`.
- La declaration de paiement d'une avance cree maintenant un recu asynchrone, et le paiement en masse cree aussi des documents de paiement lies aux bulletins.
- OpenAPI, matrice frontend/API et tests de controle d'acces documents paiement mis a jour.

## [4.16.209] - 2026-06-01

### Added

- Plan 61 solde employe : ajout des endpoints `GET /api/v1/me/balance` et `GET /api/v1/payroll/mobile-summary`, avec payload stable `gross_due`, `advances`, `paid`, `remaining`, devise et cycle courant.
- Mobile employee/manager : les ecrans paie affichent maintenant un bloc solde avant les bulletins, afin que l'utilisateur voie le reste a recevoir et les avances deduites sans attendre un PDF.

### Fixed

- `PayrollCycleService` ne depend plus d'une propriete inexistante `Company::$settings` et lit les parametres de paie depuis `metadata.payroll` ou `company_settings`, avec fallback mensuel.
- Tests backend : `PayrollCycleIntegrationTest` couvre le solde self-service, la deduction des avances et l'isolation tenant du resume mobile manager.

## [4.16.208] - 2026-06-01

### Fixed

- Plan 60 avances salaire : ajout de tests backend couvrant le workflow financier complet `manager-approve -> mark-paid -> confirm-received`, ainsi que les blocages si paiement ou confirmation sont faits trop tot.
- Tests backend : le schema MVP de test et le fixture PostgreSQL incluent maintenant les colonnes de double validation des avances (`validation_status`, declaration paiement, confirmation employe), afin que les contrats Plan 60 soient rÃ©ellement testables.

## [4.16.207] - 2026-06-01

### Fixed

- Mobile employee/manager/platform admin : `StartupGate` ne peut plus laisser une app bloquee indefiniment sur l'overlay de demarrage apres timeout ou erreur d'initialisation critique. L'utilisateur voit un message degrade court puis l'app s'ouvre automatiquement.
- Mobile core : le test widget `StartupGate` couvre maintenant l'auto-continuation apres timeout critique, afin d'eviter une regression page noire/logo fige lors des distributions Firebase.

## [4.16.206] - 2026-06-01

### Fixed

- Mobile employee/manager : durcissement des derniers repositories API hors telechargements payroll. Les flux `user_auth`, IA chat/voice et demande entreprise utilisent maintenant `requestWithRetry`, des timeouts explicites et le parsing tolerant `extractDataMap`/`extractDataList`.
- Mobile employee/manager : les appels `/user/*`, `/ai/*` et `/company-requests` ne contournent plus les protections cold-start Render, ce qui reduit les risques de spinner infini ou de payload mal caste sur les ecrans secondaires.

## [4.16.205] - 2026-06-01

### Fixed

- Mobile employee/manager : durcissement des repositories auth et settings avec `requestWithRetry`, timeouts courts pour `/auth/me`, parsing tolerant `extractDataMap()` et uploads biometrie avec timeout dedie. Les flux login Google, register, logout, profil, langue, mot de passe, QR employe et biometrie ne reposent plus sur des appels Dio directs fragiles.
- Mobile employee : les donnees Compte critiques (parcours professionnel, stats placard numerique, QR profil et scan QR entreprise) utilisent maintenant le client retry-aware pour reduire les ecrans bloques quand Render ou l'API repond lentement.

## [4.16.204] - 2026-06-01

### Fixed

- Mobile manager : durcissement des repositories modules et organigramme avec `requestWithRetry`, timeouts courts et parsing tolerant des collections API. Les listes evaluations, avances, bulletins, paies, notifications et organigramme ne dependent plus de casts directs `response.data['data'] as List`.
- Mobile manager : le digest d'accueil utilise maintenant le client API retry-aware et le helper `extractDataMap()` afin d'eviter un etat vide fragile si Laravel enveloppe differemment la reponse.

## [4.16.203] - 2026-06-01

### Fixed

- Mobile employee/manager : durcissement des repositories du placard numerique avec `requestWithRetry`, timeouts explicites et parsing tolerant `extractDataList`/`extractDataMap`. Les dossiers, documents, partages et statistiques cabinet ne dependent plus de casts directs fragiles ni de reponses Laravel non paginees.
- Mobile cabinet : les uploads gardent un timeout dedie plus long, tandis que les actions courantes restent courtes pour eviter les spinners infinis sur les ecrans Compte.

## [4.16.202] - 2026-06-01

### Fixed

- Mobile platform admin : durcissement du repository super-admin avec timeouts courts, `requestWithRetry` explicite et parsing tolerant des listes `data.items`. Les ecrans entreprises, plans, demandes client et metriques ne dependent plus de casts directs fragiles pendant la navigation.
- Mobile core : `extractDataList()` supporte maintenant les payloads Laravel `{data: {items: [...]}}` et `extractDataMap()` supporte `{data: {item: {...}}}` pour unifier les contrats API utilises par les trois apps.

## [4.16.201] - 2026-06-01

### Fixed

- Mobile employee/manager : durcissement des repositories secondaires (projets, taches, paie, depenses, contrats, formations, evaluations, onboarding, positions vehicule, approvals, horaires et tokens push) avec `requestWithRetry`, timeouts courts et parsing tolerant des collections API. Les listes de modules mobiles restent exploitables meme si l'API renvoie un format pagine Laravel.

## [4.16.200] - 2026-06-01

### Fixed

- Mobile employee/manager : les repositories pointage et absences utilisent maintenant `requestWithRetry` + parsing tolerant `extractDataList`/`extractDataMap` pour les historiques, resumes jour/mois, estimations rapides, taches du jour, corrections et soldes conges. Cela evite les chargements infinis quand Laravel renvoie une liste paginee ou un payload enveloppe.

## [4.16.199] - 2026-06-01

### Changed

- Mobile manager/employee : stabilisation des listes RH critiques avec parsing tolerant des reponses paginees Laravel pour equipes, absences et avances, afin d'eviter les chargements infinis quand la reponse API est enveloppee.
- Avances salaire : le mobile manager utilise maintenant le workflow double validation (`manager-approve` puis `mark-paid`), et le mobile employee peut confirmer la reception quand le paiement est declare.
- API RH : les ressources avances/absences exposent davantage de contexte lisible mobile (`company_name`, email employe, statut de validation, dates paiement/reception) et la documentation OpenAPI inclut les routes de double validation.

## [4.16.198] - 2026-06-01

### Added

- Mobile core : ajout de tests widget `StartupGate` couvrant l'affichage immediat du garde de demarrage et le mode degrade apres timeout, afin de bloquer les regressions page noire/logo infini avant distribution testeurs.
- CI mobile : `Mobile Apps CI - Flutter` execute maintenant `flutter test` pour `leopardo_core`, ce qui rend le garde startup obligatoire sur chaque PR mobile.

## [4.16.197] - 2026-05-31

### Fixed

- Mobile employee/manager/platform admin : `StartupGate` attend maintenant le premier frame Flutter avant de lancer les initialisations Hive/intl/Firebase/Google, et affiche un garde-fou visuel lisible au lieu d'une page noire si une initialisation bloque ou expire.
- Mobile bootstrap : les initialisations critiques Hive et locales sont isolees par etape ; un echec de cache local ou de formatage de date est journalise et ne bloque plus l'ouverture de l'espace utilisateur.

## [4.16.196] - 2026-05-31

### Fixed

- Mobile Android employee/manager/platform admin : suppression du logo du splash natif (`launch_background`) et neutralisation du splash Android 12+ avec une icone transparente. Si Flutter ne rend pas son premier frame, le testeur ne voit plus un faux etat "logo charge" qui masque le diagnostic.
- Mobile distribution : les noms de build APK/AAB sont maintenant prefixes par app (`employee-*`, `manager-*`, `platform-admin-*`) au lieu de rester generiques (`main-*` / `manual-*`).

## [4.16.195] - 2026-05-31

### Fixed

- Mobile employee/manager/platform admin : suppression du splash obligatoire au router et demarrage direct sur welcome/login afin qu'un `checkAuth`, Hive, Firebase ou Google Sign-In lent ne puisse plus figer l'app sur le logo. Le `StartupGate` lance les initialisations en arriere-plan et affiche immediatement l'application.
- Mobile core : `SecureStorage`, `AppPreferences` et `TranslationCatalogCache` tolerent une box Hive `offlineCache` pas encore ouverte via fallback memoire, ce qui evite les crashs/ANR pendant les premiers frames.

## [Unreleased]
- fix(ci): skip mobile hr firebase upload if secrets are missing

### Added
- Plan 60: Double validation des avances salaire (migration, contrÃ´leur, routes)
- Plan 61: Service cycles de paie et solde employÃ© (PayrollCycleService, PayrollCycleController)
- Plan 62: GÃ©nÃ©ration PDF bulletins de paie async (GeneratePaySlipPdfJob, queue `pdf`)
- Plan 63: Architecture Redis Upstash â€” queues nommÃ©es (pdf, notifications, payroll, webhooks), QueueHealthCheck
- Plan 64: ClÃ´ture automatique prÃ©sences (AutoCloseAttendanceCommand, scheduler horaire)
- Plan 65: Paiement en masse (ProcessBulkPaymentJob, BulkPaymentController avec progression Redis)
- Redis Upstash TLS configurÃ© dans database.php et queue.php
- README: section architecture complÃ¨te (Render, Vercel, Cloudflare, Upstash, Firebase)

### Changed
- SalaryAdvance: nouveaux champs double validation (manager_approved_at, payment_declared_at, employee_confirmed_at, validation_status)
- TenantCacheService: helpers TTL Upstash-compatibles (rememberEmployees, rememberAttendanceReport)
## [4.16.194] - 2026-05-31

### Fixed

- Mobile employee/manager/platform admin : ajout d'une barriere anti-logo infini avec timeout court du bootstrap critique, Firebase platform admin sorti du chemin bloquant et timeout explicite de l'hydratation auth avant redirection vers l'ecran de connexion.

## [4.16.193] - 2026-05-31

### Added

- Mobile employee / manager / platform admin : ajout d'un `SplashScreen` natif Flutter (logo Leopardo + glow Ã©meraude + barre de progression) affichÃ© pendant `checkAuth()`. L'app ne reste plus sur un Ã©cran vide pendant le cold start Render.

### Changed

- Mobile employee / manager / platform admin : le router dÃ©marre sur `/splash` (ou `/platform/splash`) et redirige automatiquement vers `/welcome` (non connectÃ©) ou `/` (connectÃ©) dÃ¨s que le bootstrap auth est terminÃ©. Plus de cas oÃ¹ `isLoading=true` laisse l'utilisateur sur un Ã©cran blanc/logo figÃ©.

## [4.16.192] - 2026-05-31

### Changed

- Mobile startup : `StartupGate` ne montre plus l'Ã©cran "Preparation de votre espace" pendant le bootstrap. L'app dÃ©marre directement sur fond neutre (< 300ms sur device normal) ; seule une erreur critique affiche un panneau de rÃ©cupÃ©ration.
- Mobile employee / manager : refonte des Ã©crans d'accueil (`WelcomeScreen`) â€” suppression du carousel storytelling ; accÃ¨s direct aux CTA principaux (Se connecter / Demo) avec logo, tagline et grille de modules visibles d'emblÃ©e.
- Mobile employee / manager : refonte des Ã©crans de connexion (`LoginScreen`) â€” formulaire direct sans bloc hero verbeux, snackbar d'erreur floating, boutons aux bonnes tailles (52px principal), disposition compacte sur petits Ã©crans (< 700px).

## [4.16.191] - 2026-05-31

### Fixed

- Mobile employee/manager/platform admin : correction du blocage logo infini introduit par le timeout global de 8s dans `StartupGate`. Les ops critiques (`_openOfflineCache`, `_initializeLocales`) sont dÃ©sormais exÃ©cutÃ©es sans timeout via `criticalInitializer` ; seul Google Sign-In (optionnel) est soumis Ã  `optionalTimeout`. L'app ne peut plus rester bloquÃ©e sur l'Ã©cran de chargement Ã  cause d'un timeout silencieux sur Hive ou les locales.

## [4.16.190] - 2026-05-29

### Changed

- Plan 57 : renforcement de l'ecosysteme developpeur avec API Explorer enrichi, base API configurable, sections sandbox/auth/erreurs/webhooks, guide partenaire et OpenAPI mis a jour avec les conventions d'erreurs, rate limiting et serveur Render actuel.

## [4.16.189] - 2026-05-29

### Added

- Documentation : ajout des Plans 57 a 65 pour cadrer les retours testeurs produit sur documentation API/developer ecosystem, branding tenant, positionnement Workforce OS, avances double validation, solde employe, PDF asynchrones, architecture pics de charge, cloture/timezone/GPS et paiement en masse/signature.

## [4.16.188] - 2026-05-29

### Fixed

- Mobile employee/manager/platform admin : correction du demarrage gris en appelant `runApp()` avant les initialisations natives longues, avec ecran de demarrage controle, recuperation de la box Hive `offlineCache` et erreurs Flutter visibles.
- Mobile employee/manager : l'initialisation Google Sign-In devient non bloquante afin qu'une config native OAuth absente ou invalide ne bloque plus l'application complete.

## [4.16.187] - 2026-05-29

### Changed

- Mobile employee/manager : les listes de notifications deviennent actionnables avec suppression par swipe ou menu, tout en conservant le marquage lu et le rafraichissement automatique.

## [4.16.186] - 2026-05-29

### Added

- Mobile core : ajout du modele partage `NotificationPreferences` pour consommer le contrat `/api/v1/notification-preferences`.
- Mobile employee/manager : l'ecran Compte expose maintenant les preferences notifications app, push, email et heures calmes avec sauvegarde API retry-aware.

## [4.16.185] - 2026-05-29

### Changed

- API notifications : compatibilite mobile renforcee avec le filtre `unread_only`, suppression scoppÃ©e utilisateur via `DELETE /api/v1/notifications/{notification}` et audit `communication_events`.
- Mobile employee/manager : les listes notifications utilisent `requestWithRetry`, timeouts courts et parsing robuste des collections paginees Laravel pour eviter les spinners vides apres reveil Render.
- Mobile manager : le module notifications consomme le filtre canonique `unread` et garde les actions lire/tout lire/supprimer sur les endpoints mobiles versionnes.

## [4.16.184] - 2026-05-29

### Changed

- Mobile platform admin : durcissement de la session super-admin, gestion explicite du `TWO_FA_REQUIRED`, bouton compte demo et validation du formulaire de creation client.
- Mobile employee/manager : les tokens FCM sont retires via `DELETE /api/v1/device-tokens` avant la deconnexion pour eviter les pushes vers des sessions fermees.
- Documentation : ajout du Plan 56 platform admin mobile auth hardening.

## [4.16.183] - 2026-05-29

### Added

- API : crÃ©ation de `SendPushNotificationJob` pour asynchroniser l'envoi de push sans bloquer la requÃªte utilisateur.
- API : intÃ©gration de Firebase HTTP v1 en natif dans `PushNotificationService` avec cache de 50 minutes pour le JWT OAuth 2.0.
- Documentation : mise Ã  jour des guides et du walkthrough pour l'intÃ©gration mobile HTTP v1.

- Mobile employee/manager : synchronisation automatique du token FCM avec `/api/v1/device-tokens` apres authentification ou refresh token Firebase.

## [4.16.182] - 2026-05-28

- Mobile core : ajout du composant partage `LeopardoQrCard` avec rendu QR visuel scannable via `qr_flutter`.
- Mobile employee : l'espace compte affiche maintenant un vrai QR employe et un collage explicite du QR entreprise.
- Mobile manager : le QR entreprise est rendu comme vrai QR scannable, l'import QR employe facilite le collage et les erreurs d'ajout employe affichent le message API lisible.
- Documentation : ajout du Plan 54 QR onboarding reel et ajout employe fiable.

## [4.16.181] - 2026-05-28

### Fixed

- CI mobile : correction des here-doc Node dans `deploy-main.yml` et `mobile-distribute.yml` pour que la verification Firebase App Distribution ne casse plus le workflow Bash post-merge.

## [4.16.180] - 2026-05-28

### Changed

- API employees : la liste expose maintenant `work_state` / `work_state_label` pour la vue operationnelle manager mobile (present, pause, conge, mission, absent, hors ligne).
- API employees : la modification des roles RH est reservee au manager principal ; un RH ne peut plus nommer/revoquer un autre RH depuis un PATCH employe.
- Mobile manager : la liste equipe affiche une synthese operationnelle, le statut terrain de chaque collaborateur et des raccourcis fiche/statistiques/pointages/taches.
- Mobile manager : le manager principal peut nommer ou revoquer un RH directement depuis la fiche action collaborateur.
- Documentation : ajout du Plan 53 equipe manager, statuts operationnels et roles RH.

## [4.16.179] - 2026-05-28

### Changed

- API RH : les avances sur salaire exposent maintenant le contexte manager utile (`company_id`, demandeur, email, montant, motif, date, remboursement, decision).
- Mobile manager : les listes absences et avances affichent clairement le demandeur, le motif, la date, le montant/duree et le contexte avant decision.
- Mobile manager : les repositories absences, avances et equipe utilisent des timeouts/retry explicites via `requestWithRetry` pour eviter les chargements infinis sur reseau lent ou reveil Render.
- Documentation : ajout du Plan 52 contexte demandes manager.

## [4.16.178] - 2026-05-28

### Changed

- Mobile employee : pointage rendu plus naturel. Le premier pointage de la journee passe directement en arrivee normale, et le premier depart passe directement sans bottom sheet.
- Mobile employee : les choix avances de pointage (`pause`, `reprise`, `heures supplementaires`, `mission`, `deplacement`) restent disponibles uniquement lorsque la journee est deja segmentee.
- Documentation : ajout du Plan 51 pointage intelligent employee.

## [4.16.177] - 2026-05-27

### Added

- Mobile manager : creation de taches enrichie avec categorie, frequence ponctuelle/journaliere/hebdomadaire et templates metier.
- Mobile manager : ajout de templates agriculture, elevage, maintenance, commerce, logistique et RH, branches sur les champs API existants `category`, `template_key`, `recurrence_rule` et `estimated_minutes`.
- Documentation : ajout du Plan 50 templates taches manager.

## [4.16.176] - 2026-05-27

### Added

- Mobile employee : les trois points d'une ligne de semaine ouvrent maintenant un choix entre `Details de la journee` et correction.
- Mobile employee : ajout d'une bottom sheet de details journaliers avec sessions multiples, pauses estimees, heures supp, duree travaillee et gain estime.
- Documentation : ajout du Plan 49 details pointage employee.

## [4.16.175] - 2026-05-27

### Changed

- CI mobile : renommage explicite des workflows/artifacts historiques en `Legacy Mobile` pour eviter toute confusion avec les apps store, tout en conservant le nom de check protege `Mobile Flutter (Stable Channel)`.
- Release : l'APK de l'ancienne app unique est publie comme `leopardo-rh-legacy-*`; les apps employee, manager et platform admin restent distribuees par `mobile-distribute.yml`.
- Documentation : clarification que `front/mobile_apps/` est la source canonique des apps mobiles de lancement et que `front/mobile/` reste seulement en maintenance.

## [4.16.174] - 2026-05-27

### Changed

- I18N mobile : `sync-mobile.js` synchronise maintenant les ARB vers `front/mobile/lib/l10n` et `front/mobile_apps/leopardo_core/lib/l10n`.
- CI i18n : le workflow enterprise surveille aussi les catalogues du core mobile multi-app.
- Documentation : le Plan 24 et AGENTS incluent le chemin `front/mobile_apps/leopardo_core/lib/l10n` pour Jules.
- Documentation : ajout du Plan 47 alignement i18n mobile multi-app.

## [4.16.173] - 2026-05-27

### Added

- Mobile platform admin : la fiche client permet maintenant de modifier l'abonnement via `PATCH /platform/companies/{company}/subscription`.
- Mobile platform admin : la fiche client permet d'activer/desactiver les modules via `PATCH /platform/companies/{company}/features`, avec `rh` verrouille actif.
- Mobile platform admin : ajout de la lecture du catalogue `GET /platform/plans` pour les formulaires d'abonnement.
- Contrats mobile : le garde workflow couvre les actions d'edition abonnement/modules platform admin.
- Documentation : ajout du Plan 46 controles tenant platform admin.

## [4.16.172] - 2026-05-27

### Added

- Mobile platform admin : ajout d'une fiche client accessible depuis la liste des entreprises.
- Mobile platform admin : la fiche client consomme les APIs `health`, `subscription` et `features` pour afficher sante, adoption pointage, abonnement, modules actifs et prochaines actions.
- Mobile platform admin : correction de `PlatformCompany.id` en string afin de supporter les UUID plateforme au lieu de retomber sur `0`.
- Contrats mobile : ajout de la route `/platform/companies/:companyId` et de ses endpoints au garde `validate-mobile-workflow-contracts.ps1`.
- Documentation : ajout du Plan 45 fiche client platform admin.

## [4.16.171] - 2026-05-27

### Changed

- Mobile multi-app : le garde `validate-mobile-workflow-contracts.ps1` couvre maintenant aussi `leopardo_platform_admin`.
- Mobile platform admin : les routes `/platform/*`, les endpoints `/platform/auth/*`, `/platform/metrics/overview`, `/platform/companies` et `/platform/company-requests` sont declares dans le contrat bouton/route.
- CI mobile : le validateur supporte les fichiers router non standards et les routes declarees avec guillemets simples ou doubles.
- Documentation : ajout du Plan 44 contrats actions/routes mobile.

## [4.16.170] - 2026-05-27

### Changed

- Mobile employee : le menu haut du pointage ouvre maintenant les taches du jour dans une bottom sheet reelle au lieu d'un placeholder.
- Mobile employee : l'entree `Historique` du menu haut pointe vers `/history`; `Preferences` et `Parametres` restent dedies aux reglages.
- Documentation : ajout du Plan 43 menu pointage employee.

## [4.16.169] - 2026-05-27

### Changed

- API pointage : les resumes journaliers et estimations mensuelles agregent maintenant toutes les sessions d'une journee, pas seulement `session_number = 1`.
- API mobile : `AttendanceTodayResource` expose `sessions_count` et renvoie heures/gains agregees pour les pointages multi-evenements.
- Web manager/employe : dashboards et historiques utilisent des resumes multi-sessions pour eviter les sous-estimations.
- Tests : ajout d'une regression multi-pointage normal + heure supplementaire + resume mensuel.
- Documentation : ajout du Plan 42 estimations multi-sessions.

## [4.16.168] - 2026-05-27

### Added

- Mobile multi-app : remplacement des icones Flutter par defaut par des icones Android/iOS distinctes pour employee, manager et platform admin.
- Mobile Android : ajout des adaptive icons, splash screens sombres avec logo et icones de notification monochromes par app.
- Mobile iOS : generation des AppIcon complets et des LaunchImage personnalisees pour les trois apps.
- Documentation : ajout du Plan 41 branding mobile natif et des previews visuels dans `docs/assets/mobile-branding/`.

## [4.16.167] - 2026-05-27

### Added

- API attendance : ajout de la file manager `GET /api/v1/attendance/corrections` et des decisions `PUT /api/v1/attendance/corrections/{id}/approve|reject`.
- API attendance : l'approbation d'une correction applique ou cree le pointage manuel, recalcule les champs derives et reste tenant-scope.
- Mobile manager : remplacement des placeholders `/manager/attendance`, `/manager/anomalies` et `/manager/corrections` par des ecrans connectes aux APIs reelles.
- Mobile manager : le digest accueil ouvre maintenant la file de corrections de pointage quand une decision RH est attendue.
- Tests/OpenAPI : couverture de la file corrections, decisions manager, isolation tenant et interdiction employee.
- Documentation : ajout du Plan 40 monitoring presence manager mobile.

## [4.16.166] - 2026-05-27

### Added

- Mobile employee : refonte de l'ecran `Mon mois complet` avec socle visuel mobile, etat de chargement explicite, etat vide exploitable et lien vers l'historique.
- Backend : couverture du contrat `GET /api/v1/me/monthly-summary` pour un mois sans pointage, qui doit retourner un payload zero au lieu de laisser le mobile sans issue.
- Backend : `year` et `month` du resume mensuel sont renvoyes comme entiers meme quand ils viennent de la query string.
- Garde mobile : verification des libelles de secours du parcours attendance mensuel.
- Documentation : ajout du Plan 39 mois complet mobile readiness.

## [4.16.165] - 2026-05-27

### Added

- API tasks : validation tenant-scope des `assigned_to.*` sur creation/mise a jour pour eviter toute assignation cross-company.
- Mobile manager : ajout de l'ecran `/tasks` pour lister les taches du jour et assigner une tache a un collaborateur avec templates metier.
- Mobile employee : les taches du jour visibles sur l'ecran pointage peuvent maintenant etre marquees terminees avec temps reel et note.
- OpenAPI/contracts : documentation de `/tasks/today` et des champs execution/performance des taches.
- Tests : couverture anti assignation cross-tenant et completion employee avec score performance.
- Migrations/tests : rattrapage des anciennes tables `tasks` sans `category`, `checklist` ou `visibility` et alignement du fixture PostgreSQL sur `assigned_to` JSONB pour eviter les crashs sur tenants deja existants.
- Documentation : ajout du Plan 38 taches du jour et pointage mobile.

## [4.16.164] - 2026-05-27

### Added

- API employees : `PATCH /api/v1/employees/{employee}` accepte maintenant `contract_start`, `salary_type`, `salary_base` et `hourly_rate` pour les corrections RH terrain.
- Mobile manager : ajout d'une fiche collaborateur lisible depuis l'equipe avec telephone, poste, departement, lieu, salaire et horaire.
- Mobile manager : ajout d'un formulaire de modification collaborateur connecte au PATCH API, avec rafraichissement de la liste equipe.
- Tests : couverture de mise a jour collaborateur avec horaire, salaire, date d'embauche et poste.
- Documentation : ajout du Plan 37 fiche collaborateur manager mobile.

## [4.16.163] - 2026-05-27

### Added

- API employees : `schedule_id` est maintenant accepte et expose sur la creation/mise a jour employe avec validation tenant-scope.
- API onboarding QR : la creation employee depuis QR accepte `schedule_id` pour affecter directement l'horaire.
- Mobile manager : le formulaire ajout employe charge les horaires disponibles, permet d'en choisir un et affiche l'horaire dans la liste equipe.
- Tests : garde de creation employe avec horaire tenant et refus d'un horaire d'une autre entreprise.
- Documentation : ajout du Plan 36 assignation horaires employes.

## [4.16.162] - 2026-05-27

### Added

- Mobile manager : ajout de l'ecran `/schedules` pour gerer horaires, pauses, jours travailles, tolerances retard et seuils d'heures supplementaires.
- Mobile manager : ajout d'un CTA `Horaires` depuis la home manager.
- API/contracts : documentation OpenAPI et matrice frontend/API pour `GET/POST/PUT/DELETE /api/v1/schedules`.
- Tests : ajout de `ScheduleControllerTest` pour verifier autorisation manager, refus employe et isolation tenant.
- Documentation : ajout du Plan 35 horaires manager mobile.

## [4.16.161] - 2026-05-27

### Added

- API dashboard : ajout de `GET /api/v1/dashboard/manager-digest` pour exposer les signaux manager du jour avec scope tenant/equipe.
- Mobile manager : la carte "A surveiller aujourd hui" consomme maintenant les donnees reelles de l'API, avec refresh, etats reseau et CTA vers presences/anomalies/actions.
- Tests : couverture de l'isolation company et du scope manager direct pour eviter les fuites de donnees entre managers.
- Documentation : ajout du Plan 34, de la matrice frontend/API et du contrat OpenAPI du digest manager.

## [4.16.160] - 2026-05-27

### Added

- API onboarding QR : ajout de `GET /api/v1/me/qr-profile`, `GET /api/v1/company/qr-onboarding`, `POST /api/v1/company/qr-onboarding/scan-employee`, `POST /api/v1/company/qr-onboarding/create-employee` et `POST /api/v1/me/company-qr/scan`.
- Mobile manager : ajout du flux "Ajouter depuis QR employe" avec pre-remplissage controle, creation employee via API et conservation du formulaire classique.
- Mobile employee : ajout du bloc "QR professionnel" dans `Compte` pour copier son QR et soumettre une demande d'integration via QR entreprise.
- Tests : garde Feature onboarding QR et extension du contrat routes frontend/API.
- Documentation : ajout du Plan 33 et mise a jour de la matrice frontend/API.

### Fixed

- Mobile manager : le formulaire d'ajout employe ne bloque plus la fermeture de la feuille sur un refresh reseau complet apres creation ; la liste est invalidee puis rechargee naturellement.
- Securite dependances : mise a jour du lock backend vers `symfony/http-foundation` 7.4.13 et `symfony/routing` 7.4.13 pour corriger les advisories Composer Audit CVE-2026-48736 et CVE-2026-48784.

## [4.16.159] - 2026-05-27

### Added

- API profil : ajout des champs personnels durables `personal_email`, `recovery_email` et `personal_phone` pour que l'utilisateur conserve son compte au-dela d'une entreprise.
- API self-service : ajout de `GET /api/v1/me/career` pour exposer le parcours professionnel mobile et la disponibilite pour une nouvelle entreprise.
- Mobile employee : enrichissement de la page `Compte` avec parcours professionnel, placard numerique et contacts personnels facultatifs.
- Placard numerique : les documents, dossiers et partages sont maintenant resolus par proprietaire `employee_id`, ce qui evite les erreurs UUID/bigint historiques et preserve l'espace personnel de l'utilisateur.
- Tests : garde Feature sur mise a jour profil durable, parcours professionnel et stats du placard numerique.
- Documentation : ajout du Plan 32 et mise a jour de la matrice frontend/API et de la spec OpenAPI.

## [4.16.158] - 2026-05-27

### Added

- API attendance : support des pointages multi-sessions par jour via `session_number` dynamique et contexte `work_type` (`normal`, `overtime`, `break`, `resume`, `mission`, `travel`, `training`, `other`).
- API attendance : `GET /api/v1/attendance/today` expose maintenant `sessions` et `summary` pour les details de journee mobile.
- API tasks : ajout des champs execution (`estimated_minutes`, `completed_minutes`, `completed_at`, `completion_note`, `performance_score`, `recurrence_rule`, `template_key`) et de `GET /api/v1/tasks/today`.
- Mobile employee : le bouton de pointage propose pause/reprise/heures supp/mission/deplacement et affiche les taches du jour.
- Documentation : ajout du Plan 31 `docs/PLAN_ACTION/31_PLAN_POINTAGE_TACHES_MOBILE.md`.

### Fixed

- Mobile employee : `Mon mois complet` utilise le client API avec timeout/retry controle pour eviter le spinner infini.
- API attendance : la vue manager du pointage du jour filtre explicitement les employes par `company_id` pour renforcer l'isolation tenant.
## [4.16.158] - 2026-05-31

### Changed

- Dependencies : bump `vite` de 8.0.13 a 8.0.14 dans `api/` (correctif securite/maintenance patch).

## [4.16.161] - 2026-05-31

### Fixed

- Mobile (Employee, Manager) : resolution de la race condition entre GoRouter et AuthNotifier
  qui causait un ecran noir au demarrage. `AuthState` initialise maintenant avec `isLoading: true`
  et `checkAuth()` est appele via `Future.microtask` pour laisser le router se construire.
- Mobile (Employee, Manager) : timeout `/auth/me` reduit a 10 secondes pour eviter le blocage
  sur l'ecran splash en cas de cold-start Render ou de reseau lent.
- Mobile (Platform Admin) : `timeoutOverride: 10s`, `maxRetriesOverride: 1` sur le bootstrap
  pour aligner le comportement avec les apps Employee/Manager.
- CI : `predis/predis ^2.3` restaure dans `api/composer.json` (perdu lors du merge #638).
  `composer.lock` regenere automatiquement via workflow `fix-composer-lock.yml`.
## [4.16.159] - 2026-05-31

### Added

- OpenAPI : documentation complete de ~250 routes manquantes (Plan 33, iterations 1-4) portant la couverture de 41% a quasi-complete.
- OpenAPI : 40+ nouveaux schemas (Employee, Absence, Payroll, Task, Notification, Cabinet, etc.).

### Fixed

- OpenAPI : suppression de 3 schemas en double (`PaginationMeta`, `Task`, `NotificationPreference`) qui causaient une erreur de parsing YAML.

## [4.16.157] - 2026-05-26

### Changed

- CI/CD mobile : verification bloquante que chaque secret Firebase Android App ID correspond au `mobilesdk_app_id` du `google-services.json` et au package natif attendu avant tout upload.
- CI/CD mobile : si le readback via service account echoue en mode non strict, le workflow retente maintenant la lecture Firebase App Distribution avec `FIREBASE_TOKEN` avant de passer en warning.
- Documentation : etat App Distribution mis a jour avec les releases Android `employee`, `manager` et `platform_admin` publiees le 2026-05-26.

## [4.16.156] - 2026-05-26

### Changed

- CI/CD mobile : `FIREBASE_SERVICE_ACCOUNT_JSON` active le readback Firebase via service account, mais ne rend plus ce readback bloquant par defaut apres un upload App Distribution reussi.
- CI/CD mobile : ajout du secret optionnel `FIREBASE_READBACK_REQUIRED=true` pour rendre la verification readback strictement bloquante une fois le compte de service rote et correctement permissionne.

## [4.16.155] - 2026-05-26

### Fixed

- CI/CD mobile : correction du schema `workflow_dispatch` de `mobile-distribute.yml` en typant explicitement `release_notes` pour eviter l'erreur GitHub Actions `links/0/schema nil is not an object`.

### Security

- Documentation : rappel que toute cle `FIREBASE_SERVICE_ACCOUNT_JSON` exposee hors GitHub Secrets doit etre revoquee et regeneree.

## [4.16.154] - 2026-05-26

### Added

- API : ajout du Plan 30 `docs/PLAN_ACTION/30_PLAN_API_WORKFLOW_HARDENING.md` pour verrouiller les workflows frontends/API.
- Tests : extension de `FrontendApiContractTest` aux routes employee/manager mobile et Platform Admin mobile.
- Documentation : matrice `FRONTEND_API_CONTRACT_MATRIX.md` enrichie avec les workflows mobiles equipe, avances, approvals et plateforme.

### Changed

- API Platform Admin : `POST /api/v1/platform/companies` accepte maintenant le payload minimal de l'app mobile et applique des defaults serveur controles.
- API Platform Admin : `GET /api/v1/platform/companies` et `GET /api/v1/platform/company-requests` supportent des filtres allowlistes et une pagination avec `meta`.

## [4.16.153] - 2026-05-26

### Added

- Mobile Firebase : installation des fichiers natifs Android/iOS `leopardo_platform_admin` (`com.leopardo.platformadmin`).
- CI/CD mobile : distribution Firebase automatique des trois apps `leopardo_employee`, `leopardo_manager` et `leopardo_platform_admin` lors des changements `front/mobile_apps/**` sur `main`.
- CI/CD mobile : ajout de `platform_admin` au workflow manuel `Mobile - Build and Firebase Distribution`.
- Documentation : procedure complete pour configurer le secret GitHub `FIREBASE_SERVICE_ACCOUNT_JSON`.

## [4.16.152] - 2026-05-26

### Added

- Mobile : ajout du Plan 29 pour une troisieme app `leopardo_platform_admin` dediee aux super-admins plateforme.
- Mobile : premier socle Platform Admin avec login `/platform/auth/login`, cockpit metriques, liste entreprises, creation client et demandes clients.
- CI mobile : ajout du validateur `validate-mobile-plan29.ps1` et du build debug `leopardo_platform_admin`.
- CI/CD mobile : le readback Firebase App Distribution devient strict via `FIREBASE_SERVICE_ACCOUNT_JSON` quand le secret est configure.

## [4.16.151] - 2026-05-26

### Changed

- CI/CD mobile : la verification Firebase App Distribution retente la lecture des releases pour absorber la latence read-after-write.
- CI/CD mobile : si `firebase-tools` ne peut pas lister les releases apres un upload deja accepte, le deploy reste vert avec un warning explicite au lieu de masquer la distribution reussie.

## [4.16.150] - 2026-05-26

### Added

- Mobile : ajout du Plan 28 `docs/PLAN_ACTION/28_PLAN_MOBILE_MULTI_APP_EXCELLENCE.md` pour verrouiller l'architecture mobile employee/manager.
- Mobile : nouveau validateur `dev-hub/tools/validate-mobile-plan28.ps1` execute par `mobile-apps-ci.yml`.

### Changed

- Mobile employee : suppression des methodes repository d'approbation/refus heritees pour absences et avances.
- CI mobile : le garde Plan 28 verifie la separation employee/manager, les configs Firebase Android/iOS, le read-after-write App Distribution et les preconditions iOS.

## [4.16.149] - 2026-05-26

### Changed

- CI/CD mobile : les workflows Firebase App Distribution verifient maintenant la visibilite de la release apres upload avec `firebase appdistribution:releases:list`.
- CI/CD mobile : le deploy `main` et le workflow manuel echouent si Firebase accepte l'upload mais que la release attendue n'est pas relue dans App Distribution.

## [4.16.148] - 2026-05-26

### Added

- Mobile : installation des configurations Firebase natives pour `leopardo_employee` et `leopardo_manager` sur Android et iOS.

### Changed

- Mobile : `install-mobile-firebase-configs.ps1` choisit maintenant le fichier Android le plus specifique disponible pour eviter qu'un export multi-client ecrase une app avec un fichier moins cible.
- Documentation : etat Firebase mis a jour avec le second lot de fichiers valide et le rappel de restriction des cles API Google/Firebase.

## [4.16.147] - 2026-05-26

### Added

- Mobile : documentation `docs/validation/MOBILE_FIREBASE_DISTRIBUTION.md` pour la distribution Firebase employee/manager.
- Mobile : script `dev-hub/tools/install-mobile-firebase-configs.ps1` pour installer uniquement les fichiers Firebase correspondant aux IDs natifs stabilises.

### Changed

- CI/CD : `deploy-main.yml` prepare la distribution Firebase staging des deux apps mobiles avec secrets separes `FIREBASE_EMPLOYEE_ANDROID_APP_ID` et `FIREBASE_MANAGER_ANDROID_APP_ID`.
- CI/CD : `mobile-distribute.yml` devient multi-app et permet de distribuer `employee`, `manager` ou `both`.
- Documentation : Plan 27 enrichi avec le lot Firebase App Distribution multi-app et les mismatches detectes dans les fichiers recus.

## [4.16.146] - 2026-05-26

### Added

- Mobile : contrat automatisable `dev-hub/tools/mobile-workflow-contracts.json` pour verrouiller les workflows critiques Plan 27.
- Mobile : garde `dev-hub/tools/validate-mobile-workflow-contracts.ps1` pour verifier routes, endpoints, navigations statiques et tokens d'action des apps employee/manager.

### Changed

- CI : `mobile-apps-ci.yml` execute maintenant le garde workflow mobile apres le garde release readiness.
- Mobile : correction du lien espace personnel employee/manager vers la route declaree `/company-request`.

## [4.16.145] - 2026-05-26

### Added

- Documentation : Plan 27 `docs/PLAN_ACTION/27_PLAN_MOBILE_RELEASE_READINESS.md` pour readiness App Store / Play Store.
- Documentation : checklist `docs/validation/MOBILE_STORE_READINESS.md` couvrant boutons, workflows et criteres no-go mobile.
- Mobile : script `dev-hub/tools/validate-mobile-release-readiness.ps1` pour verifier identites store, routes critiques, endpoints et handlers vides.

### Changed

- Mobile : identites natives distinctes pour `leopardo_employee` (`com.leopardo.employee`) et `leopardo_manager` (`com.leopardo.manager`) sur Android et iOS.
- CI : `mobile-apps-ci.yml` execute aussi le garde release readiness avant analyze/build.

## [4.16.144] - 2026-05-26

### Added

- Documentation : Plan 26 `docs/PLAN_ACTION/26_PLAN_MOBILE_MULTI_APP_PRODUCTION.md` pour durcir la separation mobile employee/manager.
- Mobile : script `dev-hub/tools/validate-mobile-apps-split.ps1` ajoutant des garde-fous de structure multi-app.
- CI : `mobile-apps-ci.yml` execute maintenant le garde de separation avant les analyses Flutter.

### Changed

- Documentation : README `front/mobile_apps/README.md` enrichi avec les controles Plan 26 et la procedure de validation.

## [4.16.143] - 2026-05-26

### Added

- Mobile : creation de `front/mobile_apps/` avec archive `leopardo_mobile_legacy`, package partage `leopardo_core`, app `leopardo_employee` et app `leopardo_manager`.
- Mobile : `leopardo_core` centralise API client, stockage, theme, widgets de base, modeles et i18n pour les deux futures apps.
- Mobile : app employe allegee sans routes equipe, approvals, organigramme ni tableaux manager.
- Mobile : app manager/RH conserve le perimetre complet et prepare les routes placeholders `/manager/dashboard`, `/manager/attendance`, `/manager/anomalies` et `/manager/corrections`.
- CI : workflow `mobile-apps-ci.yml` ajoute pour analyser `leopardo_core`, `leopardo_employee`, `leopardo_manager` et builder les deux APK debug.
- Documentation : README `front/mobile_apps/README.md` ajoutant les regles de contribution mobile multi-app.

## [4.16.142] - 2026-05-26

### Added

- Mobile : lot 25.5 documente la readiness demo commerciale dans `docs/validation/MOBILE_MARKETING_READINESS.md`.
- Mobile : smoke Flutter marketing-readiness couvrant decisions manager/RH et annulation self-service employe sur absences/avances.
- Documentation : matrice frontend/API enrichie avec les routes mobiles d'approbation/refus absences et avances.

## [4.16.141] - 2026-05-25

### Added

- Mobile : lot 25.4 demarre les decisions manager/RH sur absences et avances directement depuis les listes mobiles.
- Mobile : routes repository ajoutees pour `PUT /absences/{id}/approve`, `PUT /absences/{id}/reject`, `PUT /salary-advances/{id}/approve` et `PUT /salary-advances/{id}/reject`.
- Mobile : composant partage `MobileDecisionActions` et bottom sheet de commentaire pour les refus manager/RH.
- Mobile : tests repository ajoutÃ©s pour verrouiller les routes de decision manager/RH.

## [4.16.140] - 2026-05-25

### Added

- Mobile : workflows employe Plan 25.3 enrichis avec annulation des demandes d absence en attente via `DELETE /absences/{id}`.
- Mobile : annulation des demandes d avance en attente via `DELETE /salary-advances/{id}`.
- Mobile : tests repository ajoutÃ©s pour verrouiller les routes self-service d'annulation absence/avance.

## [4.16.139] - 2026-05-25

### Changed

- Mobile : lot 25.2 demarre la coherence visuelle premium avec composants partages `MobileEmptyLoading`, `MobileErrorPanel`, `MobileListCard` et `MobileMetricTile`.
- Mobile : accueil allege pour reduire la surcharge, avec trois actions rapides prioritaires et quatre modules actifs visibles.
- Mobile : ecrans Absences, Avances et Equipe modernises sur les surfaces sombres communes, avec etats de chargement, erreur et retry lisibles.
- Mobile : demande d absence rendue actionnable depuis l ecran Absences via les soldes/types existants puis `POST /absences`.
- Mobile : ecran Equipe manager/RH modernise sans perdre les champs metier critiques : date d embauche, role, type de paie, salaire/taux horaire et invitation.

## [4.16.138] - 2026-05-25

### Added

- Documentation : Plan 25 de modernisation mobile marketing-ready, couvrant pointage fiable, design system mobile, workflows employe/manager/RH et readiness lancement.
- Mobile : helper teste `attendanceHistoryMonthKey()` pour garantir que l'historique pointage reste cle par mois et non par tick d'horloge.

### Fixed

- Mobile : l'historique pointage n'est plus reobserve avec un `DateTime` qui change chaque seconde, ce qui evitait des rechargements API continus pendant l'horloge live.
- Mobile : pointage protege contre les doubles taps et garde timeout provider pour que l'etat `isPunching` retombe toujours, meme si l'API ou le reseau ne repond plus.

## [4.16.137] - 2026-05-25

### Changed

- Mobile : ecran pointage rendu plus direct, sans spinner visible de synchronisation semaine ; l'historique passe en chargement court non bloquant et les actions pointage echouent vite avec message clair si l'API ne repond pas.
- Mobile : formulaire Equipe enrichi avec date d'embauche, matricule, type de paie, salaire/taux horaire, poste, departement et lieu de travail.
- Mobile : apres creation d'un employe depuis Equipe, la liste collaborateurs est rafraichie immediatement pour afficher le nouvel ajout.
- Mobile : module Avances rendu actionnable avec bottom sheet de demande d'avance, montant, motif et duree de remboursement.
- API : creation et liste employes exposent maintenant les champs salaire (`salary_type`, `salary_base`, `hourly_rate`, `currency`) attendus par mobile/RH.

### Tests

- Mobile : contrats repository ajoutes pour verifier le payload creation employe RH/salaire et la demande d'avance avec plan de remboursement.

## [4.16.136] - 2026-05-25

### Changed

- Mobile : bouton pointage epure en icone empreinte seule, sans libelle interne redondant.
- Mobile : pointage separe de la synchronisation ecran via `isPunching`, avec feedback SnackBar au tap, confirmation succes/echec et spinner strictement lie a l'action.
- Mobile : appels check-in/check-out/corrections limites a un retry court pour eviter les attentes interminables sur reveil Render ou reseau faible.
- Mobile : base API par defaut alignee sur Render hors configuration explicite `API_BASE_URL` ou `USE_LOCAL_API=true`, afin que les builds mobiles testent le vrai backend.
- Mobile : parsing attendance plus tolerant des payloads API `data` directs ou `data.item`.

### Tests

- Mobile : tests repository attendance enrichis pour les payloads `data.item`, check-in/check-out, historique et actions de correction.

## [4.16.135] - 2026-05-25

### Changed

- Mobile : contraste du socle sombre releve pour eviter les libelles et etats illisibles sur fond bleu nuit.
- Mobile : accueil allege avec moins de cartes narratives, actions rapides limitees et modules actifs priorises.
- Mobile : page pointage rendue non bloquante pendant la synchronisation historique, avec retry API existant sur today/check-in/check-out/history.
- Mobile : menu pointage renomme en `Modifier`; la soumission affiche `Demander une modification` pour un employe et `Modifier` pour manager principal/RH.
- Mobile : managers principal/RH appliquent une correction de pointage via le vrai endpoint `PUT /attendance/{id}` au lieu d'une simulation locale.
- Mobile : bouton deconnexion ajoute en bas de l'espace Compte.
- API : endpoint tenant `POST /api/v1/attendance/corrections` et table `attendance_correction_requests` pour que les employes soumettent une vraie demande de modification.
- API : correction directe `PUT /attendance/{id}` restreinte aux managers `principal` et `rh`.

### Tests

- Mobile : tests de contraste `MobileSurface` et propagation `logId` des resumes de pointage ajoutes.

## [4.16.134] - 2026-05-24

### Changed

- Mobile : theme sombre par defaut aligne sur le design pointage v3 (`#0B1120`, cartes `#111B2E`, bordures fines, actions compactes).
- Mobile : ajout d'un kit de surfaces partagees (`MobileSurface`, panels, top bars, pills, bulles icones) pour eviter les styles eparpilles entre les ecrans.
- Mobile : ecrans Accueil, Modules RH, Notifications, Absences, Fiches de paie et Parametres polis dans un style plus epure, dense et coherent avec le mockup `leopardo_attendance_v3_final.html`.

## [4.16.133] - 2026-05-24

### Changed

- Mobile : `AttendanceScreen` reconstruit en design v3 final avec horloge live HH:MM:SS, bouton pointage double anneau, icone empreinte custom, carte du jour, semaine recente et resume hebdomadaire.
- Mobile : correction de pointage accessible uniquement via les menus `...` du header ou des lignes jour, avec bottom sheet, controle anti-heure future et retour utilisateur clair.
- Mobile : hint empreinte affiche seulement si `local_auth` confirme une empreinte disponible sur le device.
- API : consolidation RBAC ajustee pour conserver les contrats JSON existants des absences, notifications, contrats et rapports RH tout en ajoutant Resources/FormRequests et middleware `api.manager`.

## [4.16.132] - 2026-05-24

### Added

- Web vitrine : proxy Next `/api/v1/[...path]` vers l'API Render pour fiabiliser le login client depuis Vercel sans dependance CORS navigateur.
- Documentation : plan multilingue Jules avec fichiers autorises, regles de traduction et prompts anglais, arabe et turc.
- Web vitrine : menu "Installer Leopardo" pour Windows, macOS, Android et iPhone.

### Changed

- Web vitrine : pricing repositionne sur une offre SaaS RH plus credible avec forfait minimum, prix par employe, 30 jours offerts et Enterprise sur devis.
- Web vitrine : navigation commerciale clarifiee, Docs deplace sous Ressources et Blog renomme en Insights RH / HR Insights.
- Web login : les comptes demo sont charges depuis `/api/v1/demo-users` avec fallback local, et un acces Google OAuth est expose.
- Mobile : ecran pointage modernise dans l'esprit du mockup fourni, avec header sombre, bouton pulse plus lisible et style coherent Leopardo.

## [4.16.131] - 2026-05-24

### Added

- Policies : 11 nouvelles classes Policy (AbsencePolicy, ContractPolicy, DepartmentPolicy, PositionPolicy, SchedulePolicy, SitePolicy, ApprovalRequestPolicy, LoanPolicy, ExpenseClaimPolicy, WebhookEndpointPolicy, InvoicePolicy) avec RBAC granulaire par role.
- AuthServiceProvider : les 11 nouvelles policies sont enregistrees via Gate::policy() pour tous les modeles metier.
- RBAC Route Matrix : section Â« Model Policies Â» ajoutee avec matrice complete viewAny/view/create/update/delete/approve par role.
## [4.16.130] - 2026-05-24

### Added

- API Resources : 11 nouvelles classes Resource (AbsenceResource, DepartmentResource, PositionResource, ScheduleResource, SiteResource, NotificationResource, ApprovalRequestResource, InvoiceResource, AuditLogResource, WebhookEndpointResource, PayrollResource) normalisent les contrats JSON API.
- FormRequests : 10 classes extraites (StoreDepartment, UpdateDepartment, StorePosition, UpdatePosition, StoreSchedule, UpdateSchedule, StoreSite, UpdateSite, StoreWebhookEndpoint, UpdateWebhookEndpoint) avec validation et authorize gates.
- ApiError enum : catalogue centralise de ~40 codes erreur API avec traductions FR/EN/AR/TR, codes HTTP semantiques et methode `->response()`.
- Traductions api_errors : fichiers i18n `lang/{en,fr,ar,tr}/api_errors.php` pour les messages erreur API.
- Plan 23 : document `docs/PLAN_ACTION/23_PLAN_API_PRODUCTION_GRADE.md` â€” audit architecture + plan 8 iterations production-grade.

### Changed

- Controllers refactorises : AbsenceController, DepartmentController, PositionController, ScheduleController, SiteController, NotificationController, WebhookController, ApprovalController, ContractController utilisent desormais les API Resources au lieu de serialisations manuelles.
- DB::transaction ajoutees : ContractController::renew, ApprovalController::approve/reject, NotificationController::markRead/markAllRead protegent les ecritures multi-tables.
- FormRequests injectees dans les signatures store/update des controllers Department, Position, Schedule, Site, Webhook â€” la validation et l'autorisation quittent le corps du controller.
## [4.16.129] - 2026-05-24

### Added

- API : `EnsureApiManagerMiddleware` â€” RBAC paramÃ©trÃ© par rÃ´le (`api.manager`, `api.manager:principal,rh`) pour protÃ©ger les routes sensibles.
- Routes : dashboard (managers only), exports (P/RH/FIN), billing (principal), payroll engine (P/FIN), hr_extended 3-tier RBAC.
- Seeder : `DemoCompanySeeder` enrichi avec contrats, formations, recrutement, prÃªts et notes de frais pour faciliter les tests API.
- API Explorer : boutons endpoints groupÃ©s par catÃ©gorie (auth, dashboard, self-service, paie, billing, plateforme).
- SÃ©curitÃ© : matrice RBAC mise Ã  jour dans `docs/security/RBAC_ROUTE_MATRIX.md` avec documentation `api.manager`.

### Tests

- Backend : `ApiManagerMiddlewareTest` couvre 5 scÃ©narios (allow any manager, reject employee, allow specific roles, reject wrong role, reject unauthenticated).

## [4.16.128] - 2026-05-23

### Fixed

- Demo Render : `/api/v1/demo-users` reste public pour le guide testeur et l'API Explorer meme si une ancienne variable `DEMO_MODE_ENABLED=false` existe encore.
- Demo seed : `DemoCompanyOnceSeeder` ne confond plus une entreprise reelle en schema shared avec les entreprises demo ; il verifie les slugs demo attendus avant de poser le lock.
- Demo seed : `DemoCompanySeeder` accepte l'appel controle depuis `DemoCompanyOnceSeeder`, afin que le deploiement Render puisse auto-amorcer les comptes testeurs une seule fois.
- Demo seed : `DemoCompanyOnceSeeder` efface maintenant un ancien lock stale si les slugs demo attendus manquent encore, pour reparer Render sans intervention SQL manuelle.
- Auth : `TenantMiddleware` peut rehydrater l'employe Sanctum depuis `public.user_lookups` avant de poser le tenant, ce qui restaure le flux `login -> /auth/me` pour les comptes demo shared.
- Auth : le login recharge explicitement l'entreprise depuis `public.companies` quand un `search_path` tenant masque la table publique, afin d'eviter `COMPANY_NOT_FOUND` sur les comptes demo shared.
- Auth : `/auth/me` recharge aussi l'entreprise depuis `public.companies` pendant la rehydratation tenant Sanctum, afin que le parcours demo `login -> auth/me` reste valide en production shared.
- CI/CD : le workflow manuel `Deploy - Leopardo RH` sur `main` deploie sans refaire le lookup `workflow_run`, afin de garder un bouton ops utilisable pour relancer Render.

### Tests

- Backend : `DemoUserControllerTest` couvre la disponibilite publique des personas demo en environnement production.
- Backend : `AuthServiceTest` couvre le cas PostgreSQL ou `shared_tenants.companies` masque `public.companies` pendant le login.
- Backend : `DemoUserControllerTest` couvre maintenant le meme masquage pendant `GET /auth/me` avec token Bearer.

## [4.16.127] - 2026-05-23

### Added

- API : page publique `/tester-guide` pour guider les testeurs sur web client, mobile, admin plateforme et contrats API.
- API : page publique `/api-explorer` avec profils demo pre-remplis, login Bearer et endpoints critiques testables depuis Render.
- Documentation : Plan 22 pour demo runtime, API Explorer avance, notifications temps reel et QA commerciale.

### Changed

- Auth : le login retrouve un employe dans les schemas tenants connus quand `public.user_lookups` manque, puis regenere le lookup.
- Web client, mobile et admin : la selection d'un compte demo lance maintenant la connexion directement.
- Notifications web/mobile : rafraichissement regulier, lecture immediate et actions de marquage lues.

### Tests

- Backend : `DemoUserControllerTest` couvre la recuperation login sans lookup public.
- Backend : `OpenApiDocsTest` couvre les nouvelles entrees racine, guide testeur et API Explorer.

## [4.16.126] - 2026-05-22

### Added

- Documentation : Plan 21 readiness fonctionnelle par profil, avec matrice super-admin, principal, RH, manager departement, comptable, superviseur, employe et kiosk.
- Documentation : registre scenarios tests aligne avec les nouveaux tests de profils et personas demo.
- API : `/api/v1/demo-users` expose maintenant les personas operationnels, leurs surfaces, routes conseillees et cas de test.
- Seeders : `DemoCompanySeeder` enrichit la demo avec preferences de notification, evenements communication, evenements client, tokens device, kiosk actif et demandes biometrie quand les tables sont disponibles.

### Tests

- Backend : `DemoUserControllerTest` verrouille le contrat public des comptes demo.
- Backend : `ProfileFunctionalReadinessTest` couvre les acces API/web critiques par profil.

## [4.16.125] - 2026-05-22

### Fixed

- CI/CD : le workflow `Launch Observability Smoke` relance maintenant les probes en timeout, 5xx ou latence transitoire avant d'ouvrir un incident rouge.
- Observabilite : le rapport JSON du smoke expose le nombre de tentatives et les parametres de retry pour diagnostiquer les reveils Render sans masquer une panne persistante.

## [4.16.124] - 2026-05-22

### Added

- API : endpoint `GET /api/v1/communication/analytics` pour exposer aux managers `principal`/`rh` les volumes, echecs, statuts, canaux et templates de communication du tenant.
- API : endpoint `GET /api/v1/launch-readiness` pour calculer un score go-live tenant, les blocages requis et les prochaines actions avant lancement marketing/client.
- Web client : carte readiness lancement dans le dashboard manager, non bloquante si le role courant n'a pas acces au cockpit.
- Documentation : Plan 20 readiness lancement production avec lots support et go-live automatique.

### Changed

- Communication : l'orchestrateur applique les heures calmes sur les canaux externes, avec bypass securite configurable.
- Communication : SMS/WhatsApp respectent des quotas mensuels configurables (`COMMUNICATION_SMS_MONTHLY_QUOTA`, `COMMUNICATION_WHATSAPP_MONTHLY_QUOTA`, `0` = illimite).
- OpenAPI, matrice RBAC et scenarios API alignes avec analytics communication et readiness lancement.

### Tests

- Backend : `CommunicationServiceTest` couvre heures calmes et quotas mensuels.
- Backend : `CommunicationAnalyticsControllerTest` couvre analytics tenant et RBAC.
- Backend : `LaunchReadinessControllerTest` couvre tenant pret, blocages requis et refus employe.
- Backend : `FrontendApiContractTest` garde le contrat `/api/v1/launch-readiness` utilise par le dashboard client.

## [4.16.123] - 2026-05-22

### Changed

- Web vitrine : les CTA d'acquisition `Essai gratuit`, hero, pricing et CTA final pointent maintenant vers `/signup` au lieu du login existant, afin de garder un funnel public clair avant connexion.
- Web vitrine : la navigation principale expose aussi `/demo` en plus de `/blog` et des guides pour fluidifier le parcours marketing.
- Web vitrine : la section lancement RH ajoute une carte inscription directe vers l'espace client.
- SEO : ajout d'images OpenGraph/Twitter generees par Next en PNG pour des partages sociaux plus robustes que l'ancien SVG statique.

### Tests

- Web vitrine : lint, TypeScript et build Next.js a executer sur ce lot.

## [4.16.122] - 2026-05-22

### Added

- API : tables tenant `notification_preferences` et `communication_events` pour le socle communication interne Plan 19.1.
- API : endpoints authentifies `GET/PATCH /api/v1/notification-preferences`.
- API : `CommunicationService`, `DispatchCommunicationJob` et `MessageProviderInterface` pour orchestrer app, email, push, SMS et WhatsApp avec audit centralise.
- API : provider SMS/WhatsApp audit-only par defaut afin de livrer le flux sans cout externe ni secret fournisseur en CI.
- Web client : centre de notifications visible dans le header dashboard avec badge non lu et dernieres notifications.
- Web client : page `/settings/notifications` pour gerer canaux, categories et heures calmes.

### Changed

- Notifications : la lecture d'une notification et le marquage global creent maintenant un evenement d'audit communication.
- Push : l'envoi test manager passe par l'orchestrateur communication pour respecter preferences et audit.
- Plan 18 : cloture fonctionnelle documentee avant demarrage Plan 19.
- OpenAPI, RBAC route matrix et frontend/API matrix alignes avec les preferences de notification.

### Tests

- Backend : `NotificationPreferenceControllerTest` couvre auth, defaults, update, validation et audit.
- Backend : `CommunicationServiceTest` couvre creation notification app, opt-out, provider email fake et payloads SMS/WhatsApp sans donnees sensibles.
- Backend : `NotificationControllerTest` verifie l'audit communication sur lecture de notifications.

## [4.16.121] - 2026-05-22

### Added

- Web vitrine : section de conversion lancement RH reliant demo, blog/guides et pricing au parcours espace client.
- Web vitrine : assets SEO/PWA `icon.svg`, `favicon.svg`, image OpenGraph et manifeste nettoye pour eviter les icones fantomes.
- Documentation : Plan 19 communication interne, guide liens plateforme/serveurs/outils gratuits, et integration des PDFs de conception ajoutes.

### Changed

- Web vitrine : navigation et footer exposent des liens reels vers blog, guides, pricing, demo, integrations et contact.
- Web vitrine : metadonnees OpenGraph/Twitter/SEO repositionnees sur le message SaaS RH multilingue terrain.
- Plan 18 : definition de fin enrichie avec la vitrine marketing reliee au funnel client.

### Fixed

- Backend : migration tenant `client_events` alignee sur le type non declare de `$withinTransaction` attendu par Laravel.

## [4.16.120] - 2026-05-22

### Added

- API : endpoint authentifie `POST /api/v1/client-events` pour persister les evenements UX client tenant-scopes.
- Backend : table tenant `client_events`, modele `ClientEvent`, FormRequest allowlist et rate limiter `client-analytics`.
- OpenAPI : contrat `ClientEventRequest` / `ClientEventResponse` documente.

### Changed

- Web client : `trackClientEvent` persiste les evenements authentifies sans bloquer l experience utilisateur.
- Plan 18 : observabilite UX mise a jour avec stockage tenant-safe et minimisation des proprietes.

### Tests

- Backend : `ClientEventControllerTest` couvre creation tenant-scopee, authentification obligatoire et rejet d evenements non allowlistes.

## [4.16.119] - 2026-05-21

### Added

- Plan 18 : observabilite UX client avec evenements `login_success`, `login_failed`, `dashboard_loaded`, `feature_blocked` et `demo_user_selected`.
- Web client : captures Playwright login/dashboard attachees au rapport CI `web-client-playwright-report`.
- Documentation : `CLIENT_UX_OBSERVABILITY.md` formalise les evenements, seuils Web Vitals/Lighthouse et objectifs login -> dashboard.

### Changed

- CI vitrine : le smoke authentifie execute aussi les captures visuelles client et publie le rapport Playwright.
- Lighthouse vitrine : la page `/auth/login` rejoint les URLs auditees.
- Kiosque ZKTeco : etat offline clarifie avec derniere synchronisation lisible et evenement navigateur `leopardo:kiosk-status`.

### Tests

- Web client : Playwright verifie les evenements analytics critiques et le temps dashboard utilisable sous 5 secondes en environnement mocke.

## [4.16.118] - 2026-05-21

### Changed

- Web client : dashboard post-login enrichi avec etat entreprise, modules actifs/a upgrader et actions prioritaires manager.
- Web client : premiere experience employee dediee pour pointage, absences, bulletins et preference langue.
- Web client : experience super-admin clarifiee avec orientation vers le dashboard plateforme via `NEXT_PUBLIC_ADMIN_URL`.

### Tests

- Web client : smoke auth etendu pour verifier qu un employe hydrate depuis sa session arrive sur un dashboard employe utile.

## [4.16.117] - 2026-05-21

### Added

- Plan 18 : moteur UI `client-features` pour calculer les modules web client depuis les capabilities, les features entreprise/plan et le role utilisateur.
- Web client : ecran upgrade explicite pour les modules non inclus afin d eviter les 404 confuses ou les pages metier cassees.

### Changed

- Web client : la navigation dashboard indique les modules actifs, en trial ou a upgrader, avec blocage role/plan centralise dans le layout.
- CI vitrine : le smoke Playwright authentifie couvre aussi les feature gates client.

### Tests

- Web client : tests Playwright ajoutes pour module accessible, module verrouille, module trial et blocage role employe sur la paie manager.

## [4.16.116] - 2026-05-21

### Added

- Plan 18 : documentation `CLIENT_LOGIN_READINESS.md` ajoutÃ©e pour formaliser le parcours vitrine -> login -> dashboard, les variables d'environnement et les gardes Playwright.

### Changed

- Web client : page `/auth/login` modernisÃ©e avec UX responsive, contexte securite, acces demo, lien support, redirection post-login par role et toggle afficher/masquer le mot de passe.
- Client API web : les `401` du login ne declenchent plus de redirection globale afin d'afficher les erreurs d'identifiants sur la page login.

### Tests

- Web client : smoke Playwright etendu pour couvrir login manager valide, mauvais identifiants, session expiree, affichage/masquage du mot de passe et dashboard tenant non vide.

## [4.16.115] - 2026-05-21

### Added

- Observabilite lancement : workflow `Launch Observability Smoke` planifie toutes les 30 minutes pour sonder API health, docs, vitrine et admin avec rapport JSON artefact.
- Ops : dashboard de lancement `LAUNCH_OBSERVABILITY_DASHBOARD.md` et runbook `RUNBOOK_MARKETING_ROLLBACK.md` pour couper proprement acquisition, webhooks, queues et deploy en cas d'incident.
- Roadmap : Plan 18 cree pour securiser la connexion client reelle, l'acces aux features par plan et la modernisation UX des pages de login.

### Changed

- Plan 17 : lot 17.5 marque livre avec surveillance lancement, alerting externe minimal et rollback marketing formalise.

## [4.16.114] - 2026-05-21

### Changed

- CI : le workflow k6 force les actions JavaScript en Node 24 pour eviter les annotations de deprecation Node 20.

## [4.16.113] - 2026-05-21

### Fixed

- Performance : le smoke k6 borne les VUs a 1 minimum pour eviter un echec de configuration quand un workflow manuel recoit `0`.

## [4.16.112] - 2026-05-21

### Added

- Performance : workflow manuel `k6 Load Smoke - Leopardo RH` ajoute pour lancer le smoke API read-only contre staging et publier le resume JSON en artefact.

## [4.16.111] - 2026-05-21

### Added

- Staging : smoke optionnel `staging-demo-auth-smoke.sh` pour verifier les vrais logins demo manager RH, employe et super-admin quand les secrets/flags staging sont actives.
- CI : `e2e-staging.yml` peut lancer ce smoke via `workflow_dispatch` (`demo_auth_smoke=true`) ou secret `STAGING_DEMO_AUTH_SMOKE=true`.

## [4.16.110] - 2026-05-21

### Tests

- Client web : smoke Playwright "journee RH" ajoute pour verifier login manager, dashboard, equipe, pointage, absences et logout.
- CI vitrine : le workflow preview execute maintenant ce smoke manager avec les tests funnel et auth existants.

## [4.16.109] - 2026-05-21

### Changed

- Load testing : le smoke k6 API couvre maintenant `auth/me`, `dashboard/summary` et `dashboard/recent-activity` cote manager afin de mesurer le parcours dashboard client reel.

## [4.16.108] - 2026-05-21

### Tests

- Contrats frontend/API : ajout de `/api/v1/dashboard/recent-activity` dans la matrice canonique et le garde `FrontendApiContractTest`.

## [4.16.107] - 2026-05-21

### Docs

- Validation : rapport release readiness 2026-05-21 ajoute avec score, livraisons, risques restants, commandes executees et echecs classes.

## [4.16.106] - 2026-05-21

### Tests

- Mobile : contrat auth ajoute pour verifier que le login sauvegarde le token, hydrate `/auth/me` avec `Authorization: Bearer`, puis conserve `manager_role`, capabilities, modules et preference langue/RTL.

## [4.16.105] - 2026-05-21

### Tests

- Web client : smoke Playwright ajoute pour verifier le flux login RH/employe `auth/login -> auth/me -> dashboard` avec donnees dashboard tenant mockees.
- Admin plateforme : smoke Playwright ajoute pour verifier le flux login super-admin `platform/auth/login -> platform/auth/me -> dashboard`.
- CI vitrine : le job `Web Marketing Funnel E2E` execute aussi le smoke auth client afin de bloquer les regressions de connexion web avant merge.
- Client web : correction d'une boucle de rendu du layout dashboard provoquee par un snapshot `useSyncExternalStore` non stable sur l'utilisateur stocke.

## [4.16.104] - 2026-05-21

### Changed

- Client web : le dashboard manager charge maintenant les compteurs tenant reels depuis `/dashboard/summary` et les dernieres activites depuis `/dashboard/recent-activity`, au lieu d'afficher uniquement des donnees statiques apres login.
- Admin plateforme : le bouton "Acces Demo" de l'espace administration plateforme ne propose plus de comptes RH/employes tenant incompatibles avec `/platform/auth/login`.
- Securite front web : Next.js et `eslint-config-next` passent de 16.2.4 a 16.2.6 pour supprimer les advisories high de `npm audit`.

### Tests

- API : contrats de session ajoutes pour verifier qu'un token issu de `/auth/login` ouvre bien `/auth/me` avec role, langue, capabilities et entreprise.
- API : contrats plateforme ajoutes pour verifier que `/platform/auth/login` retourne `role=super_admin`, `two_fa_enabled`, `token_type=Bearer` et ouvre `/platform/auth/me`.

## [4.16.103] - 2026-05-21

### Added

- API : contrats de listes RH critiques renforces pour `employees`, `absences`, `attendance`, `me/pay-slips` et `notifications` avec tests JSON de pagination, filtres, tri allowliste, payload vide et validation d'erreur.
- Mobile : tests de payload detailles ajoutes pour les conges, bulletins et notifications afin de figer les champs consommes avant lancement marketing.

### Changed

- API : filtres et tris des listes frontends critiques sont maintenant valides par allowlist pour eviter les parametres libres non scalables ou risquÃ©s.

## [4.16.102] - 2026-05-22

### Added 

- UX : bouton "Acces Demo" sur toutes les pages de connexion (admin, client web, mobile employe, mobile personnel, kiosque) permettant de selectionner un utilisateur demo depuis les seeders et pre-remplir le formulaire de login.
- API : endpoint public `GET /api/v1/demo-users` retournant la liste des comptes demo par entreprise et role (desactive en production sauf `DEMO_MODE_ENABLED=true`).
- Mobile : widget `DemoUserBottomSheet` partage entre les deux ecrans de connexion Flutter (employe et personnel).
- Kiosque : modal de selection employe demo avec pre-remplissage du matricule dans tous les champs identifiant.

## [4.16.101] - 2026-05-21

### Changed

- Dependencies : mise a jour des dependances frontend du package `api` (`axios`, `postcss`, `vite`) avec lockfile regenere et audit npm sans vulnerabilite.

## [4.16.100] - 2026-05-21

### Added

- Vitrine : page publique `/signup` ajoutee pour fermer le tunnel essai gratuit au lieu de laisser les CTA pointer vers une route absente.
- Vitrine : endpoints server-side `demo`, `newsletter`, `signup` et `contact` raccordes a une capture lead commune avec identifiant lead, locale, source, metadata UTM, log structure et forwarding optionnel CRM/email via webhooks.
- CI : job `Web Marketing Funnel E2E` ajoute au workflow vitrine pour tester signup, demande demo, newsletter et contrat d'erreur API sur preview production-like.

### Changed

- Vitrine : composant `Input` converti en `forwardRef` pour fiabiliser `react-hook-form` sur les formulaires de conversion.
- SEO : JSON-LD article enrichi avec URLs/images absolues et `inLanguage` pour les contenus blog localises.
- Tests : timeout Playwright webServer configurable via `PLAYWRIGHT_WEB_SERVER_COMMAND`, avec support `next build && next start` pour les tests preview.

## [4.16.99] - 2026-05-21

### Added

- Tests : extension `FrontendApiContractTest` aux routes critiques mobile (pointage, conges, bulletins, notifications, push tokens).
- Tests : extension du contrat kiosque aux routes sync offline, employee-info et leave-balance.
- Docs : matrice `FRONTEND_API_CONTRACT_MATRIX.md` completee pour mobile, admin client et kiosk.

### Changed

- Docs : Plan 17 met a jour le statut du lot mobile/kiosque readiness et isole le reste a faire sur les tests JSON payload mobile detailles.

## [4.16.98] - 2026-05-21

### Added

- Vitrine : blog et articles localises en FR/EN/TR/AR via `getBlogPosts(locale)` / `getBlogPost(locale)`.
- SEO : sitemap enrichi avec alternates/hreflang compatibles avec le rail `?lang=` et metadata canonical multilingue.
- Vitrine : formulaire newsletter enrichi avec la locale courante pour qualifier les leads.

### Changed

- Vitrine : cartes blog, grille, article et newsletter acceptent des libelles localises pour dates, pagination, temps de lecture et messages formulaire.
- Docs : Plan 17 mis a jour avec l'etat reel du sous-lot blog/SEO.

## [4.16.97] - 2026-05-21

### Added

- Vitrine : pages `/pricing`, `/demo` et `/integrations` raccordees au rail FR/EN/TR/AR avec `dir=rtl` pour l'arabe.
- Vitrine : formulaire demo enrichi avec la locale courante pour qualifier les leads marketing.

### Changed

- Vitrine : composants pricing/FAQ reutilisables capables de recevoir les libelles de periode, prix sur devis et filtre "Tous" localises.
- Docs : Plan 17 mis a jour avec l'etat reel du lot vitrine multilingue conversion.

## [4.16.96] - 2026-05-20

### Added

- Tests : couverture unitaire des generateurs de declarations sociales CNAS DZ, CNSS MA et DSN FR.
- Tests : couverture etendue des exports bancaires SEPA, CCP DZ, CPA/BNA DZ et metadata formats inconnus.
- CI : seuil backend coverage par defaut releve de 56% a 57% apres mesure GitHub Actions a 57,51% (`9341/16242`) sur PR #512.
- Tests : couverture `TraccarService` via `Http::fake` pour les endpoints devices, positions, trips, events, geofences et permissions.
- Tests : couverture `CalendarSyncService` avec connexions, deconnexion, synchro conges Google, synchro formation Outlook, fallback CalDAV, erreurs provider et listing chronologique.
- Tests : alignement de la fixture MVP `calendar_connections` / `calendar_events` avec la migration tenant calendrier reelle.
- CI : seuil backend coverage par defaut releve de 57% a 58% apres mesure GitHub Actions a 58,76% (`9543/16242`) sur PR #514.
- Tests : couverture API des declarations sociales CNAS DZ, CNSS MA et DSN FR avec validation, RBAC manager, isolation tenant, attendance et champs reglementaires.
- Fix : les declarations sociales lisent les salaries via le modele `Employee` pour respecter le chiffrement `national_id`, et utilisent les metadonnees entreprise au lieu de colonnes inexistantes `tax_id` / `hire_date`.
- CI : seuil backend coverage par defaut releve de 58% a 60% apres mesure GitHub Actions a 60,01% (`9748/16243`) sur PR #515.
- Tests : contrats JSON frontend pour dashboard admin, export employees, erreurs API standardisees et endpoints kiosque token-only.
- Fix : les extensions kiosque `employee-info`, `announcements`, `leave-balance` et `qr-punch` ne dependent plus d'un bearer Sanctum utilisateur et restent authentifiees par `X-Kiosk-Token`.
- Fix : `KioskController` importe `KioskAnnouncement` et expose les soldes conges kiosque depuis le schema reel `leave_balances` (`balance`, `used`, `pending`).

## [4.16.95] - 2026-05-20

### Added

- CI : workflow dedie `Backend Jobs CI` pour tester les contrats queues/jobs (`QueueJobsTest` + warmup PDF paie).
- Docs : creation du `docs/PLAN_ACTION/17_PLAN_COVERAGE_LANCEMENT.md` pour piloter le prochain vrai lot avant lancement marketing.
- Docs : synchronisation des items `T-ARCH-19` et `T-CI-07` avec l'etat reel du depot.

## [4.16.94] - 2026-05-20

### Changed â€” Plan 16 finalisation coverage

- CI : seuil backend coverage par defaut releve de 55% a 56% dans `tests.yml` et `coverage-gate.yml`, aligne sur la mesure CI reelle de 56,14% du PR #510.
- Docs : Plan 16 marque complet cote robustesse production, avec le palier 60% reporte au prochain lot de tests backend cible.
- Securite : lock Composer mis a jour vers les releases Symfony `7.4.12` pour les advisories publiees le 2026-05-20.

## [4.16.91] - 2026-05-19

### Feat â€” Plan 16 Lot 16.2 : Release readiness + robustesse production

**Release readiness :**
- Nouveau : rapport `RELEASE_READINESS_REPORT_2026-05-19.md` â€” score 91/100 (15/15 checks passes)
- Nouveau : inventaire secrets/variables cloud obligatoires (Render, Cloudflare, Vercel, Firebase, S3)
- Nouveau : verification URLs publiques API/admin/vitrine

**Robustesse production :**
- Nouveau : `dev-hub/tools/smoke-post-deploy.sh` â€” smoke API post-deploy (health, auth, tenant, exports, OpenAPI)
- Ameliore : `RUNBOOK_ROLLBACK.md` â€” ajout procedures rollback admin (Cloudflare Pages), vitrine (Vercel), mobile (Firebase/stores/feature flags)
- Ameliore : `api.js` admin dashboard â€” breadcrumbs erreurs API avec support Sentry + messages contextuels endpoint/status + gestion 502/503/504

**Verification idempotence :**
- Verifie : migrations `2026_05_18` (device_tokens, calendar_sync, zkteco_devices) toutes protegees par `hasTable()` â€” safe pour Render/PostgreSQL
## [4.16.92] - 2026-05-19

### Feat â€” Plan 16 Lot 16.3 : Design vendeur et conversion vitrine

**3 blocs preuves sociales reutilisables (FR/EN/TR/AR) :**
- `SocialProofMetrics` â€” bandeau metriques clients (500+ entreprises, 50K+ employes, 99.9% SLA, 40% gain temps)
- `TestimonialHighlight` â€” temoignage vedette grand format avec metrique impact (-40% temps admin)
- `MiniCaseStudies` â€” 3 mini cas clients (TechAfrika DZ, Atlas Digital MA, SenLogistics SN) avec challenge/resultat

**Screenshots produit :**
- `ProductScreenshots` â€” mockups admin dashboard, app mobile, kiosque ZKTeco avec descriptions i18n et feature lists

**Integration landing page :**
- Ajout des 4 composants dans la page d'accueil vitrine entre hero/features/pricing/testimonials
- Tous les textes disponibles en FR/EN/TR/AR via le systeme de locale existant
## [4.16.93] - 2026-05-19

### Feat â€” Plan 16 Lot 16.5 : GTM operationnel

**Scripts video demo :**
- `demo_3min_paie_fr_script.md` â€” script 8 slides : paie multi-pays, exports bancaires SEPA/CPA, declarations sociales, bulletins mobile
- `demo_3min_dashboard_manager_fr_script.md` â€” script 8 slides : KPI temps reel, conges, recrutement kanban, exports, Chat IA

**Templates email prospection :**
- Sequence trial automatique (J1 bienvenue, J3 paie, J7 mi-parcours, J12 expiration)
- 3 emails prospection froide (DRH PME, DG, follow-up J+5)

**Page publique Integrations :**
- `/integrations` â€” 12 integrations (ZKTeco, Stripe, Chargily, Google/Outlook Calendar, API REST, Webhooks, SSO, Sage, QuickBooks, Firebase, Slack/Teams)
- Filtrage par categorie, badges disponible/bientot, i18n FR/EN

**Pack revendeur :**
- Programme partenaire 3 tiers (Silver 15%, Gold 20%, Platinum 25% MRR)
- Kit de vente inclus (one-pager, PPT, video, comparatif, templates, cas clients, grille tarifaire)
- Processus onboarding revendeur en 3 semaines

## [4.16.90] - 2026-05-12

### Feat â€” Plan 14 Phase 2-6 : Solidification technique & commerciale

**Securite (Phase 2) :**
- Nouveau : `TokenAutoRefreshMiddleware` â€” rotation automatique des tokens JWT via header `X-New-Token` quand le token approche l'expiration (fenetre configurable `sanctum.auto_refresh_window`)

**Integrations bancaires (Phase 4.1) :**
- Nouveau : export virement CPA (Credit Populaire d'Algerie) pipe-delimited dans `BankExportGenerator`
- Nouveau : export virement BNA (Banque Nationale d'Algerie) pipe-delimited dans `BankExportGenerator`

**Declarations sociales (Phase 4.2) :**
- Nouveau : export DSN simplifie France (Declaration Sociale Nominative) â€” `SocialDeclarationGenerator::generateDsnFr()` format S10/S20/S21/S44
- Nouveau : route `POST /api/v1/social-declarations/dsn-fr` avec mapping types contrat CDI/CDD/interim/apprentissage

**Notifications temps reel (Phase 5.1) :**
- Nouveau : `NotificationStreamController` â€” endpoint SSE `GET /api/v1/notifications/stream` avec heartbeat, reconnect, et timeout 120s
- Nouveau : composable `useNotificationStream.js` â€” client SSE auto-reconnect pour le dashboard admin

**UX Admin (Phase 5.1) :**
- Nouveau : `CommandPalette.vue` â€” palette de commandes Ctrl+K avec recherche pages/actions, navigation fleches, dark mode
- Nouveau : `SkeletonLoader.vue` â€” composant skeleton avec 6 variantes (card, table, chart, kpi-grid, form, text) et support dark mode

**Documentation commerciale (Phase 6.2) :**
- Nouveau : `docs/commercial/DOSSIER_TECHNIQUE_APPELS_OFFRES.md` â€” dossier technique complet (architecture, securite, modules, SLA, CI/CD)
- Nouveau : `docs/commercial/COMPARATIF_CONCURRENTS.md` â€” comparatif vs Sage HR, OrangeHRM, PaieNA, Kiwi HR
- Nouveau : `docs/commercial/BENCHMARKS_PERFORMANCE.md` â€” benchmarks k6 (core, 100 VU, paie 500 emp, dashboard 10K)

## [4.16.82] - 2026-05-19

### Fix â€” Consolidation connectivite API admin/kiosk

- Fix : normalisation du `VITE_API_URL` admin pour supporter une base `/api/v1` sans doubler les chemins `/v1/*`.
- Fix : exports admin telecharges via Axios authentifie au lieu de `window.open('/api/v1/...')` relatif au domaine du dashboard.
- Nouveau : endpoints exports backend pour contrats, vehicules, bulletins, absences, formations et historique afin d'aligner l'admin avec les ressources API exposees.
- Fix : kiosk ZKTeco normalise `apiBaseUrl` pour eviter `.../api/v1/api/v1/...` quand la config contient deja la version API.
- Tests : couverture Feature des exports dashboard admin ajoutee.
- Fix CI : smoke E2E staging aligne sur la vraie route auth `/api/v1/auth/me`, pages blog Next 16 compatibles `params` asynchrones, et backup PostgreSQL sans dependance `awscli` via apt.
- Fix CI : le smoke API staging envoie `Accept: application/json` afin de recevoir les vrais statuts API Laravel au lieu d'une redirection HTML vers `/login`.
- Fix CI : l'E2E vitrine staging utilise `BASE_URL` sans demarrer Next localement et limite le run a Chromium, le navigateur installe par le workflow.
- Fix CI : l'E2E vitrine staging utilise une URL web separee (`DEFAULT_WEB_STAGING_URL`) au lieu de tester la landing page contre le backend Render.
- Fix CI : le gate vitrine staging lance une suite smoke dediee (`e2e/staging-smoke.spec.ts`) centree sur les contrats publics deployes, au lieu de rejouer les parcours de conversion complets contre la production.
- Docs/Tests : matrice contractuelle frontends/API ajoutee avec garde `FrontendApiContractTest` sur les routes critiques admin, mobile et kiosk.

## [4.16.80] - 2026-05-18

### Feat â€” Iteration 13: Architecture & Performance (D1, D2, D4, D5, B4, B6, D7)

**Redis Cache (D1):**
- New `TenantCacheService` with tenant-scoped keys (`tenant:{companyId}:{key}`), configurable TTL, pattern-based invalidation

**Queue Jobs (D2):**
- New `ProcessPayrollBatchJob` (queue: payroll, 3 retries, 600s timeout) for async batch payroll calculation
- New `SendBulkNotificationsJob` (queue: notifications, 3 retries, 120s timeout) for bulk notification dispatch

**JWT Refresh Token (D4):**
- New `POST /api/v1/auth/refresh-token` endpoint for Sanctum token rotation
- Preserves token abilities, creates new token, deletes old one

**AES-256 Encryption (D5):**
- New `SensitiveDataEncryptor` service for encrypting sensitive data (IBAN, SSN) with prefix-based detection

**Monitoring Docs (B4, B6):**
- New `RUNBOOK_UPTIME_MONITORING.md` for UptimeRobot/BetterUptime configuration
- New `RUNBOOK_ALERTING.md` consolidating alerting procedures, severity levels, escalation matrix

**Job Tests (D7):**
- New `QueueJobsTest` with 4 tests covering dispatch, queue routing, and tagging

**Plan 15 Update:**
- Marked 38 additional items as DONE (B1-B6, C1-C8, D1-D7, E6-E7, G1, G8, G10, H1-H4, I1-I8, K3, L5-L6)
- Plan 15 now at **98.5%** (320/325 tasks DONE)
- All implementable code items DONE; only non-code GTM tasks (J1-J14) and long-term DDD refactor (A5) remain

### Docs â€” GTM Case Studies Template

- New `docs/GOTO_MARKET/public/case_studies/README.md` with template and 5 planned case studies

## [4.16.81] - 2026-05-19

### Tests â€” Iteration 14: Test Coverage Hardening

**New test suites (7 files, 30+ tests):**
- `AuthRefreshTokenTest` â€” token rotation, old token invalidation, ability preservation
- `TenantCacheServiceTest` â€” tenant-scoped caching, isolation, put/get/forget round trips
- `SensitiveDataEncryptorTest` â€” encrypt/decrypt, idempotent double-encrypt, array batch
- `CalendarSyncControllerTest` â€” auth, validation, provider enum
- `DeviceTokenControllerTest` â€” auth, platform validation, manager-only send-test
- `PlanningControllerTest` â€” RBAC on optimize/coverage endpoints
- `ZktecoControllerTest` â€” device list auth, heartbeat, sync validation
- `CotisationSimulationControllerTest` â€” auth, RBAC, input validation

## [4.16.79] - 2026-05-18

### Docs - Nettoyage depot distant

- Documentation : ajout dans `AGENTS.md` du retour d'experience sur le nettoyage des branches distantes Devin/GTM/mobile, la synchronisation des PR restantes apres chaque merge et le pruning des refs locales.

## [4.16.78] - 2026-05-18

### Fix â€” PR #495 GTM / vitrine

- Vitrine : compatibilite `CTASection` avec les contrats `title`/`description`/`primaryCta` utilises par les nouvelles pages GTM.
- Gouvernance : ajout d'une trace changelog pour les nouvelles surfaces GTM avant merge.
## [4.16.77] - 2026-05-17

### Feat â€” PR #488: API Integrations (G8, L6, L5, H1-H4)

**Push Notifications (G8):**
- New `PushNotificationService` with FCM HTTP v1 support, batch sending (500 tokens/chunk), automatic token invalidation
- New `DeviceTokenController`: register/unregister/list tokens, send test notifications (manager only)
- Migration: `device_tokens` table with employee_id, token, platform (ios/android/web)

**Calendar Sync (L6):**
- New `CalendarSyncService` with Google Calendar and Microsoft Outlook Graph API integration
- Syncs approved leaves and training sessions as calendar events
- New `CalendarSyncController`: connect/disconnect providers, trigger sync, list events
- Migrations: `calendar_connections` and `calendar_events` tables

**ZKTeco Integration (L5):**
- New `ZktecoIntegrationService`: device management, attendance sync (pull), user push
- New `ZktecoController`: full CRUD for devices, heartbeat endpoint, attendance sync, sync logs
- Attendance records mapped to `attendance_logs` table with punch type resolution
- Migrations: `zkteco_devices`, `zkteco_sync_logs` tables
- Device-to-server endpoints (heartbeat, sync) operate without Sanctum auth

**Kiosk Extensions (H1-H4):**
- H1: `employeeInfo` â€” post-punch employee info (name, department, position, today attendance, leave balances)
- H2: `announcements` â€” active company announcements with priority ordering
- H3: `leaveBalance` â€” employee leave balance lookup by identifier
- H4: `qrPunch` â€” QR code-based attendance punching (base64 JSON decode)
- Migration: `kiosk_announcements` table

**Infrastructure:**
- Firebase config added to `config/services.php`
- New route module `routes/modules/integrations.php`
- Updated `SCENARIOS_TEST_API_GITHUB_ACTIONS.md` with all new endpoints
- Maintenance: alignement Pint des nouvelles surfaces kiosk/ZKTeco avant merge de la PR.

## [4.16.76] - 2026-05-17

### Fix â€” PR #487 consolidation backend gates

- Fix : callbacks SSO publics compatibles UUID entreprise en supprimant la contrainte numerique de route.
- Fix : configuration SSO sans `COALESCE(created_at, NOW())` dans un `INSERT`, incompatible PostgreSQL.
- Fix : workflows IA paie/rapport hebdomadaire alignes avec le schema RH reel (`absence_type_id`, `salary_structure_id` optionnel).
- Fix : predictions IA et planning type-safe pour PHPStan (relations explicites, dates, ids, floats, listes de facteurs).
- Fix : routes planning exposees sur `/api/v1/planning/*` au lieu de `/api/v1/v1/planning/*`.
- Fix : predictions turnover compatibles avec les employes sans departement assigne et notifications proactives tolerantes aux variantes de colonne solde conges (`remaining`, `remaining_days`, `ba[...]
- Tests : fixture MVP ajustee pour `shared_tenants`, `contracts`, `contract_amendments` et `salary_structures`.

## [4.16.72] - 2026-05-17

### Feat â€” Iteration 12 : E1/E2/E10/E11 completion, C14 planning optimization, WCAG corrections

- Nouveau : onglet "Structures salariales" dans PayrollView (E1 complet â€” structures + runs + bulletins + export).
- Nouveau : `MetricCard.vue` â€” composant partage avec tendance, formatage devise/pourcentage (E10).
- Nouveau : `ReportsView.vue` â€” ecran rapports RH avec MetricCard KPIs et onglets (effectifs, absenteisme, turnover, heures supp., masse salariale) (E8).
- Nouveau : routes `/reports` et navigation sidebar pour rapports RH et journal d'audit.
- Nouveau : `PlanningOptimizer.php` â€” service IA optimisation planning hebdomadaire avec couverture departement, detection conflits, recommandations et score (C14).
- Nouveau : `PlanningController.php` â€” endpoints `GET /v1/planning/weekly-optimization` et `GET /v1/planning/shift-rebalancing`.
- Nouveau : `PlanningOptimizationTest.php` â€” tests Feature planning.
- WCAG : `role="alert"` sur notifications toast, `aria-sort` sur DataTable triable, `type="search"` + `aria-label` sur champ recherche, `caption` sr-only optionnel.
- Plan 15 : E1, E2, E10, E11, C14, F1-F6 passes en DONE.
- Sidebar admin : ajout liens rapports RH et journal d'audit.
## [4.16.75] - 2026-05-17

### Docs â€” Iteration FINALE : mise a jour documentation globale Plan 15

- Mise a jour : `AGENTS.md` â€” section "Iterations 7-11 Plan 15" avec 12 lecons operationnelles (predictions IA, SSO stub, WCAG, mobile existant, backlog).
- Mise a jour : `15_PLAN_EXECUTION_CONSOLIDE.md` â€” synthese globale mise a jour avec pourcentages et declaration de cloture etendue iterations 1-11.
- Mise a jour : date `AGENTS.md` â†’ 2026-05-17.
- Bilan Plan 15 iterations 1-11 : 5 PRs (7-11), 15+ services/controllers, 30+ tests Feature, 3 audits (WCAG, RBAC, conformite), SSO stub, predictions IA, dashboard predictif.

## [4.16.73] - 2026-05-17

### Feat â€” Iteration 10 : Predictions IA, dashboard predictif, mobile enrichments

- Nouveau : `App\AI\Predictions\TurnoverPredictor` â€” prediction du turnover par departement et employe, scoring facteurs de risque (anciennete, absences frequentes, departement a fort turnover).
- Nouveau : `App\AI\Predictions\AbsenteeismPredictor` â€” prediction absenteisme avec saisonnalite, tendances departementales et recommandations IA.
- Nouveau : `App\AI\Predictions\ProactiveNotificationService` â€” notifications proactives IA (contrats expirants, periodes d'essai, anniversaires, approbations en retard, formations incompletes, [...]
- Nouveau : `PredictionController` â€” endpoints `/api/v1/predictions/turnover`, `/absenteeism`, `/notifications` avec controle RBAC manager principal/RH.
- Nouveau : `PredictionsView.vue` â€” dashboard predictif admin avec cartes turnover, absenteisme, notifications proactives, barres de risque departement.
- Route admin : `/predictions` ajoutee au router (lazy import).
- Mobile : enrichissement absences (provider `leaveBalancesProvider`, methode `getLeaveBalances` dans `AbsenceRepository`).
- Verification : E6 FleetView (197 lignes, DONE), E7 ChatView (191 lignes, DONE), G2-G7 mobile (DONE), G9 carte vehicule (DONE).
- Tests : `PredictionControllerTest` â€” 6 tests Feature (RBAC + structure reponse turnover/absenteisme/notifications).
- Plan 15 : C11, C12, C13, C15, E6, E7, G2-G7, G9 passes en DONE.
- REGISTRE scenarios test API mis a jour.
## [4.16.74] - 2026-05-17

### Feat â€” Iteration 11 : SSO SAML/OIDC stub + audit WCAG 2.1 AA

- Nouveau : `App\Services\SSO\SSOService` â€” service SSO multi-protocole (SAML 2.0, OpenID Connect) avec configuration par entreprise, activation/desactivation et callbacks stub.
- Nouveau : `App\Services\SSO\SSOProviderConfig` â€” DTO configuration SSO (entity_id, sso_url, slo_url, certificate, name_id_format).
- Nouveau : `SSOController` â€” 6 endpoints : `GET /sso/providers` (public), `GET /sso/status`, `POST /sso/configure`, `DELETE /sso/disable` (RBAC principal), `POST /sso/saml/{id}/callback`, `GET [...]
- Nouveau : migration `create_company_sso_configs_table` â€” table SSO config par entreprise (provider, config JSONB, is_active), idempotente.
- Nouveau : `routes/modules/sso.php` â€” routes SSO separees (callbacks publics + gestion authentifiee).
- Nouveau : `docs/security/WCAG_ACCESSIBILITY_AUDIT.md` â€” audit complet WCAG 2.1 AA (34 criteres, 23 conformes, 11 partiels, 0 non-conformes, score 68%).
- Fix : `DashboardLayout.vue` â€” ajout lien "Aller au contenu principal" (WCAG 2.4.1) + `id="main-content"` sur `<main>`.
- Fix : `web/src/app/layout.tsx` â€” ajout lien "Aller au contenu principal" (WCAG 2.4.1).
- Tests : `SSOControllerTest` â€” 8 tests Feature (providers publics, RBAC status/configure/disable, validation provider, callback SAML).
- Plan 15 : K2 (SSO stub) et K4 (WCAG audit) passes en DONE.

## [4.16.71] - 2026-05-17

### Feat â€” Iteration 9 : Audit UI, good first issues, release prep

- Nouveau : `AuditLogsView.vue` â€” journal d'audit admin avec filtres (action, type, recherche), export CSV, panneau detail slide-over avec diff avant/apres (old_values vs new_values).
- Nouveau : route `/audit` dans admin router (lazy import, code splitting conserve).
- Nouveau : `GOOD_FIRST_ISSUES.md` â€” 10 issues documentees pour contributeurs debutants (validation IBAN, i18n arabe, dark mode, export PDF, tests health, etc.).
- Nouveau : `RELEASE_v0.1.0.md` â€” notes de release pour la premiere version publique GitHub.
- Confirme : E4 (recrutement pipeline Kanban) est DONE â€” 308 lignes avec KanbanBoard, 6 stages pipeline, avancer/retourner candidats, creation poste inline.
- Plan 15 : E4, E9, I2, I5 passes en DONE.
- SCENARIOS_TEST_API et REGISTRE mis a jour.
