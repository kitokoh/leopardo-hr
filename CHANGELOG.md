#  CHANGELOG - LEOPARDO RH 
# Format : Keep a Changelog (keepachangelog.com)
# Versioning : Semantic Versioning (semver.org)

## [4.16.133] - 2026-05-24

### Changed

- Mobile : `AttendanceScreen` reconstruit en design v3 final avec horloge live HH:MM:SS, bouton pointage double anneau, icone empreinte custom, carte du jour, semaine recente et resume hebdomadaire.
- Mobile : correction de pointage accessible uniquement via les menus `...` du header ou des lignes jour, avec bottom sheet, controle anti-heure future et retour utilisateur clair.
- Mobile : hint empreinte affiche seulement si `local_auth` confirme une empreinte disponible sur le device.

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
- RBAC Route Matrix : section « Model Policies » ajoutee avec matrice complete viewAny/view/create/update/delete/approve par role.

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

- Plan 18 : documentation `CLIENT_LOGIN_READINESS.md` ajoutée pour formaliser le parcours vitrine -> login -> dashboard, les variables d'environnement et les gardes Playwright.

### Changed

- Web client : page `/auth/login` modernisée avec UX responsive, contexte securite, acces demo, lien support, redirection post-login par role et toggle afficher/masquer le mot de passe.
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

- API : filtres et tris des listes frontends critiques sont maintenant valides par allowlist pour eviter les parametres libres non scalables ou risqués.

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

### Changed — Plan 16 finalisation coverage

- CI : seuil backend coverage par defaut releve de 55% a 56% dans `tests.yml` et `coverage-gate.yml`, aligne sur la mesure CI reelle de 56,14% du PR #510.
- Docs : Plan 16 marque complet cote robustesse production, avec le palier 60% reporte au prochain lot de tests backend cible.
- Securite : lock Composer mis a jour vers les releases Symfony `7.4.12` pour les advisories publiees le 2026-05-20.

## [4.16.91] - 2026-05-19

### Feat — Plan 16 Lot 16.2 : Release readiness + robustesse production

**Release readiness :**
- Nouveau : rapport `RELEASE_READINESS_REPORT_2026-05-19.md` — score 91/100 (15/15 checks passes)
- Nouveau : inventaire secrets/variables cloud obligatoires (Render, Cloudflare, Vercel, Firebase, S3)
- Nouveau : verification URLs publiques API/admin/vitrine

**Robustesse production :**
- Nouveau : `dev-hub/tools/smoke-post-deploy.sh` — smoke API post-deploy (health, auth, tenant, exports, OpenAPI)
- Ameliore : `RUNBOOK_ROLLBACK.md` — ajout procedures rollback admin (Cloudflare Pages), vitrine (Vercel), mobile (Firebase/stores/feature flags)
- Ameliore : `api.js` admin dashboard — breadcrumbs erreurs API avec support Sentry + messages contextuels endpoint/status + gestion 502/503/504

**Verification idempotence :**
- Verifie : migrations `2026_05_18` (device_tokens, calendar_sync, zkteco_devices) toutes protegees par `hasTable()` — safe pour Render/PostgreSQL
## [4.16.92] - 2026-05-19

### Feat — Plan 16 Lot 16.3 : Design vendeur et conversion vitrine

**3 blocs preuves sociales reutilisables (FR/EN/TR/AR) :**
- `SocialProofMetrics` — bandeau metriques clients (500+ entreprises, 50K+ employes, 99.9% SLA, 40% gain temps)
- `TestimonialHighlight` — temoignage vedette grand format avec metrique impact (-40% temps admin)
- `MiniCaseStudies` — 3 mini cas clients (TechAfrika DZ, Atlas Digital MA, SenLogistics SN) avec challenge/resultat

**Screenshots produit :**
- `ProductScreenshots` — mockups admin dashboard, app mobile, kiosque ZKTeco avec descriptions i18n et feature lists

**Integration landing page :**
- Ajout des 4 composants dans la page d'accueil vitrine entre hero/features/pricing/testimonials
- Tous les textes disponibles en FR/EN/TR/AR via le systeme de locale existant
## [4.16.93] - 2026-05-19

### Feat — Plan 16 Lot 16.5 : GTM operationnel

**Scripts video demo :**
- `demo_3min_paie_fr_script.md` — script 8 slides : paie multi-pays, exports bancaires SEPA/CPA, declarations sociales, bulletins mobile
- `demo_3min_dashboard_manager_fr_script.md` — script 8 slides : KPI temps reel, conges, recrutement kanban, exports, Chat IA

**Templates email prospection :**
- Sequence trial automatique (J1 bienvenue, J3 paie, J7 mi-parcours, J12 expiration)
- 3 emails prospection froide (DRH PME, DG, follow-up J+5)

**Page publique Integrations :**
- `/integrations` — 12 integrations (ZKTeco, Stripe, Chargily, Google/Outlook Calendar, API REST, Webhooks, SSO, Sage, QuickBooks, Firebase, Slack/Teams)
- Filtrage par categorie, badges disponible/bientot, i18n FR/EN

**Pack revendeur :**
- Programme partenaire 3 tiers (Silver 15%, Gold 20%, Platinum 25% MRR)
- Kit de vente inclus (one-pager, PPT, video, comparatif, templates, cas clients, grille tarifaire)
- Processus onboarding revendeur en 3 semaines

## [4.16.90] - 2026-05-12

### Feat — Plan 14 Phase 2-6 : Solidification technique & commerciale

**Securite (Phase 2) :**
- Nouveau : `TokenAutoRefreshMiddleware` — rotation automatique des tokens JWT via header `X-New-Token` quand le token approche l'expiration (fenetre configurable `sanctum.auto_refresh_window`)

**Integrations bancaires (Phase 4.1) :**
- Nouveau : export virement CPA (Credit Populaire d'Algerie) pipe-delimited dans `BankExportGenerator`
- Nouveau : export virement BNA (Banque Nationale d'Algerie) pipe-delimited dans `BankExportGenerator`

**Declarations sociales (Phase 4.2) :**
- Nouveau : export DSN simplifie France (Declaration Sociale Nominative) — `SocialDeclarationGenerator::generateDsnFr()` format S10/S20/S21/S44
- Nouveau : route `POST /api/v1/social-declarations/dsn-fr` avec mapping types contrat CDI/CDD/interim/apprentissage

**Notifications temps reel (Phase 5.1) :**
- Nouveau : `NotificationStreamController` — endpoint SSE `GET /api/v1/notifications/stream` avec heartbeat, reconnect, et timeout 120s
- Nouveau : composable `useNotificationStream.js` — client SSE auto-reconnect pour le dashboard admin

**UX Admin (Phase 5.1) :**
- Nouveau : `CommandPalette.vue` — palette de commandes Ctrl+K avec recherche pages/actions, navigation fleches, dark mode
- Nouveau : `SkeletonLoader.vue` — composant skeleton avec 6 variantes (card, table, chart, kpi-grid, form, text) et support dark mode

**Documentation commerciale (Phase 6.2) :**
- Nouveau : `docs/commercial/DOSSIER_TECHNIQUE_APPELS_OFFRES.md` — dossier technique complet (architecture, securite, modules, SLA, CI/CD)
- Nouveau : `docs/commercial/COMPARATIF_CONCURRENTS.md` — comparatif vs Sage HR, OrangeHRM, PaieNA, Kiwi HR
- Nouveau : `docs/commercial/BENCHMARKS_PERFORMANCE.md` — benchmarks k6 (core, 100 VU, paie 500 emp, dashboard 10K)

## [4.16.82] - 2026-05-19

### Fix — Consolidation connectivite API admin/kiosk

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

### Feat — Iteration 13: Architecture & Performance (D1, D2, D4, D5, B4, B6, D7)

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

### Docs — GTM Case Studies Template

- New `docs/GOTO_MARKET/public/case_studies/README.md` with template and 5 planned case studies

## [4.16.81] - 2026-05-19

### Tests — Iteration 14: Test Coverage Hardening

**New test suites (7 files, 30+ tests):**
- `AuthRefreshTokenTest` — token rotation, old token invalidation, ability preservation
- `TenantCacheServiceTest` — tenant-scoped caching, isolation, put/get/forget round trips
- `SensitiveDataEncryptorTest` — encrypt/decrypt, idempotent double-encrypt, array batch
- `CalendarSyncControllerTest` — auth, validation, provider enum
- `DeviceTokenControllerTest` — auth, platform validation, manager-only send-test
- `PlanningControllerTest` — RBAC on optimize/coverage endpoints
- `ZktecoControllerTest` — device list auth, heartbeat, sync validation
- `CotisationSimulationControllerTest` — auth, RBAC, input validation

## [4.16.79] - 2026-05-18

### Docs - Nettoyage depot distant

- Documentation : ajout dans `AGENTS.md` du retour d'experience sur le nettoyage des branches distantes Devin/GTM/mobile, la synchronisation des PR restantes apres chaque merge et le pruning des refs locales.

## [4.16.78] - 2026-05-18

### Fix — PR #495 GTM / vitrine

- Vitrine : compatibilite `CTASection` avec les contrats `title`/`description`/`primaryCta` utilises par les nouvelles pages GTM.
- Gouvernance : ajout d'une trace changelog pour les nouvelles surfaces GTM avant merge.
## [4.16.77] - 2026-05-17

### Feat — PR #488: API Integrations (G8, L6, L5, H1-H4)

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
- H1: `employeeInfo` — post-punch employee info (name, department, position, today attendance, leave balances)
- H2: `announcements` — active company announcements with priority ordering
- H3: `leaveBalance` — employee leave balance lookup by identifier
- H4: `qrPunch` — QR code-based attendance punching (base64 JSON decode)
- Migration: `kiosk_announcements` table

**Infrastructure:**
- Firebase config added to `config/services.php`
- New route module `routes/modules/integrations.php`
- Updated `SCENARIOS_TEST_API_GITHUB_ACTIONS.md` with all new endpoints
- Maintenance: alignement Pint des nouvelles surfaces kiosk/ZKTeco avant merge de la PR.

## [4.16.76] - 2026-05-17

### Fix — PR #487 consolidation backend gates

- Fix : callbacks SSO publics compatibles UUID entreprise en supprimant la contrainte numerique de route.
- Fix : configuration SSO sans `COALESCE(created_at, NOW())` dans un `INSERT`, incompatible PostgreSQL.
- Fix : workflows IA paie/rapport hebdomadaire alignes avec le schema RH reel (`absence_type_id`, `salary_structure_id` optionnel).
- Fix : predictions IA et planning type-safe pour PHPStan (relations explicites, dates, ids, floats, listes de facteurs).
- Fix : routes planning exposees sur `/api/v1/planning/*` au lieu de `/api/v1/v1/planning/*`.
- Fix : predictions turnover compatibles avec les employes sans departement assigne et notifications proactives tolerantes aux variantes de colonne solde conges (`remaining`, `remaining_days`, `ba[...]
- Tests : fixture MVP ajustee pour `shared_tenants`, `contracts`, `contract_amendments` et `salary_structures`.

## [4.16.72] - 2026-05-17

### Feat — Iteration 12 : E1/E2/E10/E11 completion, C14 planning optimization, WCAG corrections

- Nouveau : onglet "Structures salariales" dans PayrollView (E1 complet — structures + runs + bulletins + export).
- Nouveau : `MetricCard.vue` — composant partage avec tendance, formatage devise/pourcentage (E10).
- Nouveau : `ReportsView.vue` — ecran rapports RH avec MetricCard KPIs et onglets (effectifs, absenteisme, turnover, heures supp., masse salariale) (E8).
- Nouveau : routes `/reports` et navigation sidebar pour rapports RH et journal d'audit.
- Nouveau : `PlanningOptimizer.php` — service IA optimisation planning hebdomadaire avec couverture departement, detection conflits, recommandations et score (C14).
- Nouveau : `PlanningController.php` — endpoints `GET /v1/planning/weekly-optimization` et `GET /v1/planning/shift-rebalancing`.
- Nouveau : `PlanningOptimizationTest.php` — tests Feature planning.
- WCAG : `role="alert"` sur notifications toast, `aria-sort` sur DataTable triable, `type="search"` + `aria-label` sur champ recherche, `caption` sr-only optionnel.
- Plan 15 : E1, E2, E10, E11, C14, F1-F6 passes en DONE.
- Sidebar admin : ajout liens rapports RH et journal d'audit.
## [4.16.75] - 2026-05-17

### Docs — Iteration FINALE : mise a jour documentation globale Plan 15

- Mise a jour : `AGENTS.md` — section "Iterations 7-11 Plan 15" avec 12 lecons operationnelles (predictions IA, SSO stub, WCAG, mobile existant, backlog).
- Mise a jour : `15_PLAN_EXECUTION_CONSOLIDE.md` — synthese globale mise a jour avec pourcentages et declaration de cloture etendue iterations 1-11.
- Mise a jour : date `AGENTS.md` → 2026-05-17.
- Bilan Plan 15 iterations 1-11 : 5 PRs (7-11), 15+ services/controllers, 30+ tests Feature, 3 audits (WCAG, RBAC, conformite), SSO stub, predictions IA, dashboard predictif.

## [4.16.73] - 2026-05-17

### Feat — Iteration 10 : Predictions IA, dashboard predictif, mobile enrichments

- Nouveau : `App\AI\Predictions\TurnoverPredictor` — prediction du turnover par departement et employe, scoring facteurs de risque (anciennete, absences frequentes, departement a fort turnover).
- Nouveau : `App\AI\Predictions\AbsenteeismPredictor` — prediction absenteisme avec saisonnalite, tendances departementales et recommandations IA.
- Nouveau : `App\AI\Predictions\ProactiveNotificationService` — notifications proactives IA (contrats expirants, periodes d'essai, anniversaires, approbations en retard, formations incompletes, [...]
- Nouveau : `PredictionController` — endpoints `/api/v1/predictions/turnover`, `/absenteeism`, `/notifications` avec controle RBAC manager principal/RH.
- Nouveau : `PredictionsView.vue` — dashboard predictif admin avec cartes turnover, absenteisme, notifications proactives, barres de risque departement.
- Route admin : `/predictions` ajoutee au router (lazy import).
- Mobile : enrichissement absences (provider `leaveBalancesProvider`, methode `getLeaveBalances` dans `AbsenceRepository`).
- Verification : E6 FleetView (197 lignes, DONE), E7 ChatView (191 lignes, DONE), G2-G7 mobile (DONE), G9 carte vehicule (DONE).
- Tests : `PredictionControllerTest` — 6 tests Feature (RBAC + structure reponse turnover/absenteisme/notifications).
- Plan 15 : C11, C12, C13, C15, E6, E7, G2-G7, G9 passes en DONE.
- REGISTRE scenarios test API mis a jour.
## [4.16.74] - 2026-05-17

### Feat — Iteration 11 : SSO SAML/OIDC stub + audit WCAG 2.1 AA

- Nouveau : `App\Services\SSO\SSOService` — service SSO multi-protocole (SAML 2.0, OpenID Connect) avec configuration par entreprise, activation/desactivation et callbacks stub.
- Nouveau : `App\Services\SSO\SSOProviderConfig` — DTO configuration SSO (entity_id, sso_url, slo_url, certificate, name_id_format).
- Nouveau : `SSOController` — 6 endpoints : `GET /sso/providers` (public), `GET /sso/status`, `POST /sso/configure`, `DELETE /sso/disable` (RBAC principal), `POST /sso/saml/{id}/callback`, `GET [...]
- Nouveau : migration `create_company_sso_configs_table` — table SSO config par entreprise (provider, config JSONB, is_active), idempotente.
- Nouveau : `routes/modules/sso.php` — routes SSO separees (callbacks publics + gestion authentifiee).
- Nouveau : `docs/security/WCAG_ACCESSIBILITY_AUDIT.md` — audit complet WCAG 2.1 AA (34 criteres, 23 conformes, 11 partiels, 0 non-conformes, score 68%).
- Fix : `DashboardLayout.vue` — ajout lien "Aller au contenu principal" (WCAG 2.4.1) + `id="main-content"` sur `<main>`.
- Fix : `web/src/app/layout.tsx` — ajout lien "Aller au contenu principal" (WCAG 2.4.1).
- Tests : `SSOControllerTest` — 8 tests Feature (providers publics, RBAC status/configure/disable, validation provider, callback SAML).
- Plan 15 : K2 (SSO stub) et K4 (WCAG audit) passes en DONE.

## [4.16.71] - 2026-05-17

### Feat — Iteration 9 : Audit UI, good first issues, release prep

- Nouveau : `AuditLogsView.vue` — journal d'audit admin avec filtres (action, type, recherche), export CSV, panneau detail slide-over avec diff avant/apres (old_values vs new_values).
- Nouveau : route `/audit` dans admin router (lazy import, code splitting conserve).
- Nouveau : `GOOD_FIRST_ISSUES.md` — 10 issues documentees pour contributeurs debutants (validation IBAN, i18n arabe, dark mode, export PDF, tests health, etc.).
- Nouveau : `RELEASE_v0.1.0.md` — notes de release pour la premiere version publique GitHub.
- Confirme : E4 (recrutement pipeline Kanban) est DONE — 308 lignes avec KanbanBoard, 6 stages pipeline, avancer/retourner candidats, creation poste inline.
- Plan 15 : E4, E9, I2, I5 passes en DONE.
- SCENARIOS_TEST_API et REGISTRE mis a jour.
