# REGISTRE DES SCENARIOS DE TESTS

## Objectif 

Fournir une source de verite unique  pour savoir:

- quelles surfaces fonctionnelles doivent etre testees
- dans quel document de scenarios elles sont decrites
- quel workflow CI les execute
- quels artefacts doivent etre produits avant un deploiement

## Regle de gouvernance

Toute nouvelle fonctionnalite, extension de parcours critique ou changement de comportement dans:

- `api/`
- `front/mobile/`
- `front/admin-dashboard/`

doit mettre a jour:

1. le document de scenarios du domaine
2. ou ce registre si le domaine, le workflow, les artefacts ou la criticite changent

Le workflow `Governance Gates` bloque la PR si la surface fonctionnelle change sans mise a jour du registre ou du document de scenarios associe.

## Matrice canonique

| Domaine | Base de scenarios | Workflow source de verite | Artefacts minimums | Gate de deploiement |
|---|---|---|---|---|
| API backend | `docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md` | `Tests - Leopardo RH` | JUnit unit/feature, logs, quality summary, coverage clover + HTML | Obligatoire |
| Mobile Flutter | `docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md` | `Tests - Leopardo RH` | `test-results.json`, `lcov.info`, quality summary, smoke APK | Obligatoire si `front/mobile/**` change |
| Web admin | `docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md` | `Web CI - Leopardo Admin` | rapport Playwright HTML, JUnit Playwright, traces, screenshots, videos en echec | Obligatoire si `front/front/admin-dashboard/**` change |
| Web vitrine / manager | `front/web/src/modules/vitrine/` + `CHANGELOG.md` | `Web Marketing CI - Leopardo Public` | lint Next.js, build Next.js, locale rail valide, metadata stables | Obligatoire si `front/web/**` change |
| Gouvernance repo | `tools/check-governance.ps1` + ce registre | `Tests - Leopardo RH` | journal CI, verifications changelog/scenarios | Obligatoire |
| Deploiement main | `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md` | `Deploy - Leopardo RH` | healthcheck post-deploy, rollback hook si echec | Strictement bloque tant que les workflows requis ne sont pas verts |
| Release readiness | `docs/validation/RELEASE_READINESS_GATE.md` | GitHub Actions + `dev-hub/tools/release-readiness.ps1` | rapport readiness, inventaire tests, statut go/no-go | Obligatoire avant declaration production-ready |

## Definition "tests concluants"

Un SHA est deployable seulement si:

1. `Tests - Leopardo RH` est `success`
2. `Web CI - Leopardo Admin` est `success` si le SHA touche `front/front/admin-dashboard/**`
3. `Web Marketing CI - Leopardo Public` est `success` si le SHA touche `front/web/**`
4. les artefacts minimums du domaine existent
5. aucun job critique n'est `failure`, `cancelled` ou `timed_out`

## Politique artefacts

### Backend

- `backend-test-reports`
- `backend-quality-summary`
- `backend-quality-reports`
- `backend-coverage-summary`
- `backend-coverage-reports`

### Mobile

- `mobile-quality-summary`
- `mobile-test-reports`
- APK smoke si build mobile actif

### Web admin

- `playwright-report`
- `test-results/junit.xml`
- traces Playwright sur premier retry
- videos Playwright retenues en echec

### Web vitrine / manager

- logs de lint Next.js
- logs de build Next.js
- validation du locale rail public et des metadata au travers du build

## Evolution attendue

Quand un domaine gagne une feature significative, ajouter:

- le scenario nominal
- les refus RBAC / tenant
- les erreurs de validation
- les cas de resilience
- l'artefact CI attendu

## Extension 2026-05-07 - I18N enterprise partage

| Domaine transverse | Base de scenarios | Workflow source de verite | Artefacts minimums | Gate de deploiement |
|---|---|---|---|---|
| I18N partage backend/web/mobile | `SCENARIOS_TEST_API_GITHUB_ACTIONS.md` + `SCENARIOS_TEST_MOBILE_FLUTTER.md` + `SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md` | `I18N Enterprise` + workflows de surface | catalogues generes, checksums `versions.json`, validation locale, endpoint distant syntaxiquement valide | Obligatoire si `shared/i18n/**` ou une surface synchronisee change |

## Notes 2026-05-12

- v4.16.50 : Le seuil coverage mobile par defaut est un ratchet a `21%`, base sur la mesure GitHub Actions `21.85%`. La prochaine cible mobile est `25%`; ne pas augmenter sans nouvelle mesure verte.
- v4.16.78 : Les pages GTM vitrine de la PR #495 doivent rester compatibles avec les composants `CTASection` et le Web Marketing CI doit valider lint + build avant merge.
- v4.16.77 : Les surfaces API integrations/kiosk/ZKTeco de la PR #488 doivent conserver les scenarios device tokens, sync calendrier, heartbeat/sync ZKTeco et extension kiosk (employee info, annonces, leave balance, QR punch), y compris le formatage Pint avant merge.
- v4.16.126 : Plan 21 ajoute les scenarios backend `DemoUserControllerTest` et `ProfileFunctionalReadinessTest` pour verrouiller les personas demo et la matrice d'acces principal/RH/dept/comptable/superviseur/employe. Toute evolution de `/api/v1/demo-users`, des seeders demo ou des sous-roles manager doit garder ces tests comme contrat commercial et QA.
- v4.16.127 : Plan 22 ajoute les scenarios `OpenApiDocsTest` pour `/tester-guide` et `/api-explorer`, plus `DemoUserControllerTest::test_demo_login_recovers_missing_lookup_from_shared_tenant_schema` pour le login demo sans lookup public. Toute evolution de la racine Render, des comptes demo ou de l'explorer API doit garder ces parcours testeur/developpeur accessibles et pre-remplis.
- v4.16.128 : `DemoUserControllerTest::test_demo_users_remain_available_for_public_tester_guides_in_production` verrouille `/api/v1/demo-users` comme contrat public QA, meme si une ancienne config production desactive le mode demo. Les seeders demo doivent verifier les slugs attendus et ne jamais assimiler toute entreprise `shared_tenants` a une demo deja presente.
- v4.16.128 : `DemoUserControllerTest` couvre aussi le contrat complet demo `POST /auth/login` puis `GET /auth/me`. Les tokens Sanctum tenant doivent transporter le contexte schema/email/company/employee pour eviter qu'un `tokenable_id` entier soit resolu dans le mauvais schema shared.
- v4.16.234 : `DemoUserControllerTest::test_demo_once_seeder_keeps_public_super_admin_credentials_usable` verrouille le contrat super-admin demo expose par `/api/v1/demo-users`. Le seeder demo doit resynchroniser le mot de passe public `password123` et retirer le 2FA demo afin que `leopardo_platform_admin` puisse etre teste sur Render.
- v4.16.238 : `GET /api/v1/payroll/mobile-summary` reste dans le perimetre API paie critique. Les scenarios doivent couvrir la tolerance aux colonnes employees optionnelles absentes sur tenants historiques afin d'eviter un 500 mobile manager pendant le lancement.
- v4.16.239 : les soldes paie mobiles doivent rester compatibles avec le contexte `current_company` pose par `TenantMiddleware`. Toute evolution de `PayrollCycleService` doit eviter un rechargement `Company` vulnerable au `search_path` shared PostgreSQL.
- v4.16.240 : `GET /api/v1/employees/{employee}/balance` et `GET /api/v1/payroll/mobile-summary` doivent rester des contrats mobiles resilients : parametre route aligne, isolation tenant et fallback partiel par employe si un calcul individuel echoue.
- v4.16.243 : `GET /api/v1/launch-readiness` fait partie des gates lancement. Il doit utiliser `currentCompany()` en contexte tenant et rester schema-aware sur les champs paie employees pour eviter des faux no-go Render.
- v4.16.245 : le check lancement `communication_governance` depend d'une preference notification par employe actif. `notifications:backfill-preferences` doit creer les lignes manquantes et reparer le `company_id` sans ecraser les choix utilisateur existants ; scenarios couverts par `BackfillNotificationPreferencesCommandTest`, `NotificationPreferenceControllerTest` et `LaunchReadinessControllerTest`.
- v4.16.246 : `DemoCompanyOnceSeeder` doit backfiller les signaux demo necessaires au score lancement (`payroll_base`, `attendance_entry`, `client_experience_tracking`) sans reseed destructif. Scenario couvert par `DemoUserControllerTest::test_demo_once_seeder_backfills_launch_readiness_signals_for_existing_demos`.
- v4.16.128 : `AuthServiceTest::test_login_resolves_public_company_when_tenant_schema_shadows_companies_table` et `DemoUserControllerTest::test_demo_login_recovers_missing_lookup_from_shared_tenant_schema` couvrent le login shared PostgreSQL et `/auth/me` quand le schema tenant masque `public.companies`. Tout smoke demo doit verifier `POST /auth/login` puis `GET /auth/me`, pas seulement `/demo-users`.
- v4.16.49 : Les tests mobiles Plan 14 couvrent desormais navigation GoRouter, surfaces principales, contrats repositories via `ApiClient` mocke et baselines structurelles paie/conges. Le poste Windows local ne fournit pas `flutter`/`dart`; GitHub Actions reste la source de verite pour compiler et executer ces tests.
- v4.16.47 : Les benchmarks performance Plan 14 ajoutent des scripts k6 pour 100 employes simultanes, paie 500 employes et dashboard 10k employes. Les scenarios API doivent garder l'organigramme scope par tenant et le rapport mensuel attendance groupe par employe pour eviter les regressions de scans repetes.
- v4.16.61 : Le sitemap Next `/api/sitemap` liste aussi `/changelog`, `/privacy` et `/terms` pour suivre les routes publiques FR/EN/TR/AR.
- v4.16.60 : La vitrine expose `/changelog` (extrait public du changelog produit) ; le footer pointe vers `/pricing`, `/changelog`, `/blog`, `/privacy`, `/terms`. Le Web Marketing CI doit continuer a valider lint + build ; ajouter un smoke manuel ou E2E du lien « Changelog » si une suite Playwright vitrine est introduite.
- v4.16.29 : La vitrine `front/web` expose maintenant les pages legales `/privacy` et `/terms` en FR/EN/TR/AR avec RTL arabe. Les scenarios Web Marketing doivent verifier les liens footer, le rendu des routes et le changement de langue sur ces pages.
- v4.16.8 : Le cockpit `front/admin-dashboard` consomme maintenant `/platform/metrics/overview` pour les chiffres financiers globaux. Les scenarios web admin doivent verifier MRR, ARR, encaissements 30 jours, impayes et subscriptions sans recalculer ces agregats depuis des listes partielles.
- v4.16.7 : Le contrat `GET /api/v1/platform/metrics/overview` devient une surface plateforme critique pour le cockpit super-admin. Il doit rester protege par `super_admin_api`, exposer uniquement des agregats non nominatifs et rester tolerant aux tables billing absentes pendant les migrations progressives.
- v4.16.0 : Les annotations PHPDoc `@property`, `@return` et `@var Employee` ajoutees dans les modeles, services et controllers ne modifient aucun comportement runtime. Le helper `currentCompany()` est un remplacement fonctionnellement identique de `app('current_company')`. Le binding `LLMClient` dans AppServiceProvider preserve le meme comportement de selection provider. Aucun nouveau endpoint ni modification de contrat API.
- v4.16.1 : Extraction des appels inline `$request->user()->` dans 8 controllers supplementaires. Aucun changement de comportement ni de contrat API.
- v4.16.2 : Extraction des chaines `->fresh()->` nullables et ajout de null checks sur les relations. Aucun changement de comportement ni de contrat API.
- v4.16.27 : Annotations PHPStan Partie 5 — `@mixin` sur 16 Resources, `@property` sur 4 modeles Camera, `@property-read` sur 14 modeles, `@param/@return Builder<static>` sur 48 scopes. Aucun changement de comportement runtime.
- v4.16.3 : Guards `Schema::hasTable()` sur 8 migrations tenant + `$withinTransaction = false` sur migration public. Aucune modification de schema — uniquement idempotence des migrations existantes.

## Notes 2026-05-08

- Le workflow `Tests - Leopardo RH` ne doit pas lancer le job mobile uniquement parce que `.github/workflows/tests.yml` change. La dette mobile historique doit rester visible, mais elle ne doit bloquer une PR backend/admin/web que si `front/mobile/**` bouge vraiment.
- La gate `Backend Quality` doit rester veridique sur le code PHP touche par la PR. Tant que tout l'historique PHPStan n'est pas resorbe, privilegier un scope diff-aware plutot qu'un faux vert global ou un blocage hors perimetre.
- Le contrat d'auth plateforme (`/api/v1/platform/auth/*`, `role=super_admin`, `two_fa_enabled`, `202 TWO_FA_REQUIRED`) fait maintenant partie du perimetre admin critique et doit rester documente et teste.
- Les extensions attendance qui rendent la valeur terrain visible (impact business des anomalies, actions manager recommandees, rapport mensuel avec estimation paie, checklist go-live) font partie des scenarios API critiques et doivent rester couvertes par `Tests - Leopardo RH`.
- Le contrat health plateforme (`/api/v1/platform/companies/{company}/health`) est une surface v5.0 critique : il soutient adoption, retention et upsell, et doit rester teste avec isolation tenant et auth super-admin.
- La vue portefeuille health (`/api/v1/platform/companies/health`) est critique pour le pilotage commercial : elle doit garder MRR, repartition des risques et next action par client dans la CI backend.
- Le contrat abonnement plateforme (`/api/v1/platform/companies/{company}/subscription`) est critique pour la commercialisation : il doit rester fournisseur-agnostique et valider plan, statut et dates avant toute integration paiement.
- Le catalogue plans plateforme (`/api/v1/platform/plans`) doit rester teste afin que l'admin-dashboard ne hardcode jamais les `plan_id` ou les limites de packaging.
- Le cockpit admin v5.0 doit afficher les donnees reelles du portefeuille, du detail health, des abonnements et des plans. Toute regression `front/front/admin-dashboard/**` sur ces vues doit rester couverte par build/Playwright.
- L'intake demandes clients de l'admin-dashboard doit rester branche sur `/api/v1/platform/company-requests` : filtres statut, compteurs et actions approuver/rejeter font partie du parcours commercial critique.
- L'accueil admin v5.0 ne doit plus dependre d'endpoints mockes `/admin/dashboard/*`; il synthetise les contrats plateforme existants pour garder un premier ecran exploitable.
- L'approbation d'une demande client doit verifier le provisioning complet : company publique, manager principal tenant, invitation et `approved_company_id`.
- v4.16.28 : CI/CD Hardening Partie 6 — seuil coverage 40%, PHPStan diff-gate elargi a tout `app/`, baseline auto-regen sur main avec delta, 3 suites E2E Playwright (navigation, accessibilite, error-handling). Aucun changement de comportement runtime.
- v4.16.67 : Plan 15 Batch 1 — declarations sociales CNAS DZ / CNSS MA, import employes CSV, compression gzip API. Nouveaux endpoints : `POST /social-declarations/cnas-dz`, `POST /social-declarations/cnss-ma`, `POST /employees/import`, `GET /employees/import-template`. Scenarios ajoutes dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- v4.16.69 : Iteration 7 — IA Workflows metier + simulation cotisations. Nouveaux endpoints : `POST /ai/workflows/prepare-payroll`, `GET /ai/workflows/weekly-report`, `POST /cotisation-simulation`. Tests : `AIWorkflowTest.php`, `CotisationSimulationTest.php`. Scenarios ajoutes dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- v4.16.70 : Iteration 8 — Admin enrichments (E3 contrats detail+alertes, E5 formation detail, E8 rapports RH SPA, D6 indexes etendus, F7 newsletter footer). Ecran `/reports` dans admin-dashboard consomme les 10 endpoints rapports backend. Panneau detail contrat et formation enrichis avec alertes automatiques. Indexes PostgreSQL etendus pour contrats, formation, recrutement, audit, webhooks. Newsletter integree dans le footer vitrine.
- v4.16.73 : Iteration 10 — IA Predictions (turnover, absenteisme, notifications proactives). Nouveaux endpoints : `GET /predictions/turnover`, `GET /predictions/absenteeism`, `GET /predictions/notifications`. Tests Feature RBAC PredictionControllerTest (6 tests). Dashboard predictif admin `PredictionsView.vue`. Mobile absences enrichi (leaveBalancesProvider). Plan 15 : C11, C12, C13, C15, E6, E7, G2-G7, G9 -> DONE.
- v4.16.71 : Iteration 9 — Audit logs UI (E9) avec filtres action/type/recherche, export CSV, panneau detail avec diff old/new values. E4 (recrutement Kanban) confirme DONE. Good first issues (I2) et release notes v0.1.0 (I5) documentes.
- v4.16.74 : Iteration 11 — SSO SAML/OIDC stub (K2) + audit WCAG 2.1 AA (K4). Nouveaux endpoints : `GET /sso/providers`, `GET /sso/status`, `POST /sso/configure`, `DELETE /sso/disable`, `POST /sso/saml/{id}/callback`, `GET /sso/oidc/{id}/callback`. Tests Feature SSOControllerTest (8 tests RBAC). Migration company_sso_configs. Skip-to-content WCAG 2.4.1 admin + vitrine.
- v4.16.72 : Iteration 12 — PayrollView enrichi (onglet structures salariales), MetricCard composant, ReportsView rapports RH, PlanningOptimizer IA (C14), sidebar admin avec liens rapports/audit, corrections WCAG (role alert, aria-sort, search input). E1, E2, E10, E11, C14, F1-F6 DONE.
- v4.16.80 : Iteration 13 — Architecture & Performance. Nouveau endpoint : `POST /auth/refresh-token` (rotation Sanctum token). Nouveaux services : TenantCacheService (D1 cache Redis tenant-scoped), ProcessPayrollBatchJob (D2 queue payroll async), SendBulkNotificationsJob (D2 queue notifications bulk), SensitiveDataEncryptor (D5 AES-256). Nouveaux tests : QueueJobsTest (4 tests dispatch/tags). Runbooks : RUNBOOK_UPTIME_MONITORING.md (B4), RUNBOOK_ALERTING.md (B6). Plan 15 : 98.5% DONE (320/325).
- v4.16.90 : Plan 14 Phases 2-6 — Solidification technique. Nouveaux endpoints : `POST /social-declarations/dsn-fr` (DSN simplifie France S10/S20/S21/S44), `GET /notifications/stream` (SSE temps reel). Nouveau middleware : `TokenAutoRefreshMiddleware` (rotation JWT automatique via X-New-Token). Nouveaux exports bancaires : CPA/BNA format DZ. Nouveaux composants admin : CommandPalette (Ctrl+K), SkeletonLoader (6 variantes). Composable : useNotificationStream.js (client SSE). Documentation commerciale : dossier technique, comparatif concurrents, benchmarks performance. Scenarios ajoutes dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- v4.16.81 : Iteration 14 — Test Coverage Hardening. 7 nouveaux fichiers tests Feature : AuthRefreshTokenTest (rotation token, invalidation ancien token, preservation abilities), TenantCacheServiceTest (cache tenant-scoped, isolation, round-trip), SensitiveDataEncryptorTest (encrypt/decrypt, idempotence, batch array), CalendarSyncControllerTest (auth, validation), DeviceTokenControllerTest (auth, RBAC manager), PlanningControllerTest (RBAC optimize/coverage), ZktecoControllerTest (auth devices, heartbeat), CotisationSimulationControllerTest (auth, RBAC, validation).
- v4.16.131 : Plan 23 Iteration 5-6 — 11 Model Policies (Absence, Contract, Department, Position, Schedule, Site, ApprovalRequest, Loan, ExpenseClaim, Invoice, WebhookEndpoint) enregistrees dans AuthServiceProvider. Select() sur index queries Approval/Site/Schedule. RBAC Route Matrix mise a jour. Scenarios ajoutes dans SCENARIOS_TEST_API_GITHUB_ACTIONS.md.
- v4.16.130 : Plan 23 Iterations 1-4 — API Production-Grade. 11 nouvelles API Resources (Absence, Department, Position, Schedule, Site, Notification, ApprovalRequest, Invoice, AuditLog, WebhookEndpoint, Payroll). 10 FormRequests extraites (Department, Position, Schedule, Site, Webhook store/update). ApiError backed enum ~40 codes i18n FR/EN/AR/TR. DB::transaction sur ContractController::renew, ApprovalController::approve/reject, NotificationController::markRead/markAllRead. 9 controllers refactorises vers Resources+FormRequests. Scenarios ajoutes dans SCENARIOS_TEST_API_GITHUB_ACTIONS.md.
- v4.16.129 : API Consolidation RBAC. Nouveau middleware `EnsureApiManagerMiddleware` (`api.manager`) avec roles parametrables. Routes restructurees : dashboard/exports/billing/payroll/hr_extended avec guards RBAC. `DemoCompanySeeder` enrichi : contrats, formations, recrutement, prets, notes de frais. API Explorer regroupe par categorie. Test `ApiManagerMiddlewareTest` (5 scenarios). Scenarios ajoutes dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- v4.16.183 : Migration HTTP v1 pour Firebase Push Notifications. Job SendPushNotificationJob cree. Scenarios ajoutes dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- v4.16.183 : Sync mobile FCM post-auth. Les apps employee/manager initialisent `PushNotificationService` apres login/hydratation et enregistrent le token via `POST /api/v1/device-tokens`; les checks attendus restent `DeviceTokenControllerTest`, `FrontendApiContractTest` et les analyses `mobile-apps-ci`.
- v4.16.184 : Platform admin mobile auth hardening. Le mobile super-admin garde le login obligatoire, gere explicitement `202 TWO_FA_REQUIRED`, evite `/platform/auth/me` sans token local et valide le formulaire de creation client. Scenarios couverts par `PlatformAuthTest`, `PlatformCompanyProvisioningTest`, `FrontendApiContractTest` et `mobile-apps-ci`.
- v4.16.184 : Cycle de vie FCM mobile. Les apps employee/manager enregistrent le token apres auth et tentent `DELETE /api/v1/device-tokens` avant logout; la deconnexion reste non bloquante si le reseau echoue. Scenarios couverts par `DeviceTokenControllerTest`, `FrontendApiContractTest` et analyses mobile.
- v4.16.185 : Contrats notifications mobiles. Les apps employee/manager consomment `GET /notifications?unread=true`, l'alias `unread_only=true`, `PUT /notifications/{id}/read`, `PUT /notifications/read-all` et `DELETE /notifications/{id}` avec retry court. `NotificationControllerTest` couvre le scope utilisateur, l'audit communication, les alias mobiles et les routes dashboard historiques.
- v4.16.186 : Preferences notifications mobiles. Les ecrans Compte employee/manager doivent charger et sauvegarder `/api/v1/notification-preferences` avec retry court pour app, push, email et heures calmes. Scenarios couverts par `NotificationPreferenceControllerTest`, `FrontendApiContractTest` et analyses `mobile-apps-ci`.
- v4.16.187 : Actions liste notifications mobiles. Les ecrans notifications employee/manager doivent garder marquage lu, suppression par swipe/menu et refresh provider apres mutation. Scenarios couverts par `NotificationControllerTest`, `FrontendApiContractTest` et analyses `mobile-apps-ci`.
- v4.16.231 : Liste equipe mobile manager. `GET /api/v1/employees` doit rester teste avec le payload complet consomme par `EmployeeResource` (`contacts personnels`, `salary_*`, horaire, biometrie, `extra_data`, `work_state`) afin d'eviter les 500 production sur modeles partiellement charges. Scenarios couverts par `EmployeesRbacTest`, `ApiListQueryContractTest`, `MobilePayloadContractTest` et les smokes Plan 69.3.
- v4.16.188 : Bootstrap mobile anti-ecran gris. Les trois apps passent par `StartupGate`, recuperent `offlineCache` si Hive est corrompu et affichent une erreur exploitable au lieu d'un ecran gris. Scenarios couverts par analyses/builds `mobile-apps-ci` et distribution Firebase.
- Plans 60-65 (Redis Upstash backend) : Double validation avances salaire (Plan 60), PayrollCycleService + PayrollCycleController cycles & solde employe (Plan 61), GeneratePaySlipPdfJob PDF async queue `pdf` (Plan 62), QueueHealthCheck + queues nommees Upstash (Plan 63), AutoCloseAttendanceCommand horaire (Plan 64), ProcessBulkPaymentJob + BulkPaymentController paiement masse avec progression Redis (Plan 65). Scenarios ajoutes dans SCENARIOS_TEST_API_GITHUB_ACTIONS.md.
- v4.16.251 : Lots P0-P3 — Webhooks Svix, Onboarding Wizard, Drip Emails, Offline Sync Mobile, Portail Développeur. Scenarios couverts par tests existants et documentation mise a jour. Scenarios ajoutes dans SCENARIOS_TEST_API_GITHUB_ACTIONS.md.
- v4.16.252 : Module Growth - Partenariat et Parrainage. Nouveau middleware PartnerLinkMiddleware (tracking affilie avec cookie 30j et redirection /signup). Service PartnerService (attribution partenaire, prevention auto-referral, calcul commissions). Tests GrowthModuleTest : attribution, prevention auto-referral, calcul commissions, workflow complet partenaire. Schema SQL fixture mis a jour (eferrer_partner_id sur companies). Scenarios API ajoutes dans SCENARIOS_TEST_API_GITHUB_ACTIONS.md.
