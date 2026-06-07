# AGENTS.md - Guide de travail Leopardo RH

Derniere mise a jour : 2026-06-06 (v4.16.250)

Ce fichier doit etre lu au debut de chaque nouvelle session agent. Il doit aussi etre mis a jour a chaque push ou merge vers `main`, comme le `CHANGELOG.md`, des qu'une lecon operationnelle peut eviter de perdre du temps plus tard.

## Regles obligatoires

- Avant de travailler sur une branche existante, faire `git fetch origin main` puis comparer avec `origin/main`.
- `main` distant est la source de verite. Le local doit rester aligne sur `origin/main` apres chaque intervention terminee.
- Ne pas pousser directement sur `main` si la branche est protegee. Creer un PR, attendre les checks GitHub Actions, puis merger et supprimer la branche.
- Apres un merge dans `main`, supprimer la branche distante et nettoyer les branches locales devenues inutiles.
- Ne jamais perdre les stashes existants. Verifier `git stash list` avant toute operation destructive.
- Chaque changement de comportement, migration, CI ou procedure doit avoir une entree `CHANGELOG.md`.
- Chaque connaissance utile pour les prochains agents doit etre ajoutee ici.

## Strategie CI rapide

Depuis la session du 2026-05-06, la meilleure strategie est d'utiliser GitHub Actions comme source de verite au lieu d'insister sur les checks locaux Windows.

- Preferer `gh pr checks <numero>` pour voir l'etat global.
- Preferer `gh run view <run-id> --log-failed` pour lire uniquement les erreurs rouges.
- Corriger l'erreur exacte, push, puis repeter.
- Eviter les longues commandes locales si elles bloquent sur Windows : `dart format`, `jest`, `npm run build`, `flutter analyze` peuvent etre lents ou produire du bruit localement.
- `npx tsc --strict --noEmit` est acceptable localement quand il faut verifier vite une erreur TypeScript evidente.
- Les checks GitHub Actions qui ont permis de merger le PR #268 : backend, backend quality, mobile, build, lint, type-check, test Node 20, CodeQL, governance.
- Les workflows web `build.yml`, `lint.yml` et `test.yml` doivent rester limites aux changements `web/**` ou a leur propre fichier workflow pour eviter les CI inutiles sur des PR backend/docs.
- Si une simplification CI/CD est envisagee, prioriser d'abord les gains de signal (Playwright dedie, coverage backend visible, tests critiques, secret scan) avant la fusion cosmetique des fichiers YAML.
- Si les workflows web sont fusionnes plus tard, conserver absolument les filtres `paths:` qui ont reduit le bruit CI a partir du 2026-05-06.
- Pour Composer en CI, preferer un cache base sur `composer.lock` ou le cache officiel plutot qu'un cache brut de `vendor/`.
- Pour la coverage backend, mesurer puis activer un seuil progressif ; ne pas imposer `60%` d'un coup sans baseline reelle.
- Le workflow `coverage-gate.yml` doit creer `api/storage/coverage` avant tout `tee` vers `storage/coverage/summary.txt`. Depuis v4.16.96, son seuil par defaut est `60%` (coverage mesuree 60,01% sur PR #515); tout prochain ratchet doit s'appuyer sur une mesure CI verte.
- Le workflow `backend-jobs-ci.yml` couvre les contrats queues/jobs critiques (`QueueJobsTest` et warmup PDF paie). Toute modification de `api/app/Jobs/**`, listeners d'evenements ou dispatch paie doit garder ce workflow vert.
- Le runbook backup existe deja dans `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` ; en cas de plan CI/CD, penser mise a jour/allegement avant creation d'une nouvelle doc.
- Le depot porte deux surfaces frontend distinctes : `admin-dashboard/` pour la plateforme interne et `web/` pour la vitrine / portail manager Next.js. Ne pas confondre les workflows ni les URLs de deploiement.
- Pour `admin-dashboard/`, garder `web-ci.yml` cible sur `admin-dashboard/**` avec lint/build/Playwright.
- Depuis v4.16.250, `front/admin-dashboard` porte une refonte premium basee sur les tokens `glass-*`, `premium-text`, `shadow-glass-*` et les surfaces `card`. Toute evolution UI admin doit reutiliser ces primitives plutot que revenir aux anciennes cartes plates `rounded-lg bg-white shadow`.
- Pour `web/`, utiliser un workflow dedie vitrine (`web-marketing-ci.yml`) sur `web/**` au lieu de recycler les checks admin.
- La vitrine `front/web` doit garder les liens commerciaux reels dans la navigation et le footer (`/blog`, `/guides/rh-startup`, `/pricing`, `/demo`, `/contact`). Eviter les CTA `#` ou routes API relatives cote Vercel.
- Depuis v4.16.250, `/download` expose les apps mobiles via les variables publiques `NEXT_PUBLIC_LEOPARDO_EMPLOYEE_ANDROID_URL`, `NEXT_PUBLIC_LEOPARDO_EMPLOYEE_IOS_URL`, `NEXT_PUBLIC_LEOPARDO_MANAGER_ANDROID_URL`, `NEXT_PUBLIC_LEOPARDO_MANAGER_IOS_URL`, `NEXT_PUBLIC_LEOPARDO_PLATFORM_ADMIN_ANDROID_URL` et `NEXT_PUBLIC_LEOPARDO_PLATFORM_ADMIN_IOS_URL`. Si un store/TestFlight/Firebase public n'est pas encore configure, le fallback doit rester une demande testeur `/signup?source=download_<app>_<platform>`, jamais un lien mort `#android-*` ou `#ios-*`.
- Depuis v4.16.250, les parcours visibles de lancement doivent rester couverts par `dev-hub/tools/launch-workflow-contracts.json` et `dev-hub/tools/validate-launch-workflows.ps1`. Toute nouvelle action critique vitrine/web admin/mobile/kiosk doit declarer ses fichiers, routes/endpoints et tokens UI/API avant PR.
- Depuis v4.16.250, `dev-hub/tools/launch-api-profile-smoke.ps1` peut couvrir employee, manager, platform admin et kiosk sans secrets tenant via demo login. Pour le kiosk, utiliser explicitement `-IncludeKioskProvisioning` ou l'input GitHub `include_kiosk_provisioning=true` : le script enregistre alors un appareil temporaire via le manager demo, recupere le `device_code` et le `sync_token`, puis teste `roster` et `announcements`. Ne pas activer cette option sur production client hors fenetre de recette controlee.
- Les assets SEO/PWA canoniques de la vitrine sont `front/web/public/icon.svg`, `favicon.svg`, `brand/opengraph.svg` et `manifest.json`; si un PNG est ajoute plus tard, verifier qu'il existe vraiment avant de le declarer dans le manifeste.
- Depuis v4.16.250, `/signup` sur la vitrine est une demande d'essai guidee et non une creation de compte immediate : ne pas reintroduire de mot de passe dans `SignupForm` tant que le provisioning automatique sandbox/email verification n'existe pas. `POST /api/forms/signup` doit garder email, entreprise, role, taille, telephone optionnel, `requestedWorkflow=guided_trial` et `nextStep=contact_under_24h` pour CRM/platform admin.
- Depuis v4.16.250, le hero de la vitrine expose aussi un formulaire rapide email-only (`source=hero_email_trial`) branche sur `/api/forms/signup`. Il derive une entreprise depuis le domaine email pour capturer le lead sans mot de passe ni carte bancaire ; sa copie localisee vit dans `vitrine-locale.ts`, et le test Playwright marketing + le contrat `guided_trial_signup` doivent rester alignes si ce point d'entree change.
- Depuis v4.16.250, les CTA d'essai vitrine doivent pointer vers `/signup` ou `/demo`, jamais vers l'ancienne route `/auth/signup`. Les pages localisees doivent garder l'arabe en Unicode lisible, pas en mojibake.
- Depuis v4.16.250, la home vitrine expose une section `OperationalProofSection` pour vendre clairement les 3 apps mobiles, 2 apps web, kiosk/biometrie et API production. Toute evolution commerciale majeure doit rester localisee FR/EN/TR/AR et verifier l'absence de `???`, `Ø`, `Ù` ou mojibake visible.
- Depuis v4.16.250, `front/zkteco-kiosk/index.html` ne doit pas reintroduire d'IDs dupliques pour `checkInButton`, `checkOutButton` ou `statusBox`. Le kiosk reste offline-first via `/local/punch`; les endpoints Laravel `/kiosks/{deviceCode}/punch`, `/sync`, `/roster`, `/qr-punch` sont atteints par le bridge local quand la connexion revient. Le matching biometrie brut reste cote terminal/SDK ZKTeco, pas cote HTML.
- Depuis v4.16.250, `GET /api/v1/kiosks/{deviceCode}/announcements` doit rester tolerant aux tenants historiques dont la table `kiosk_announcements` existe mais sans toutes les colonnes modernes (`company_id`, `is_active`, `starts_at`, `expires_at`, `priority`, `created_at`). Utiliser `Schema::getColumnListing`/`hasColumn` + `DB::table` defensif plutot que le scope Eloquent complet pour eviter les 500 Render. Sans `company_id`, retourner `data=[]` pour proteger l'isolation tenant. Les annonces kiosque etant non critiques, garder un fail-open journalise (`data=[]`) si la table reste non queryable.
- Depuis v4.16.250, les ecrans `Compte` employee/manager doivent garder la deconnexion en bas et eviter les boutons sans action. La section "Vue d ensemble" sert a organiser identite portable, parcours, placard, QR/biometrie, notifications et session sans creer de routes fictives.
- Le Plan 19 communication interne vit dans `docs/PLAN_ACTION/19_PLAN_COMMUNICATION_INTERNE.md`; le guide des URLs, serveurs et options gratuites vit dans `docs/GUIDES/GUIDE_LIENS_PLATEFORME_ET_COMMUNICATION.md`.
- Depuis v4.16.122, le socle communication interne commence par `CommunicationService`, `notification_preferences` et `communication_events`. Toute nouvelle notification multi-canal doit passer par cet orchestrateur, respecter les preferences utilisateur, ecrire un evenement d'audit communication et eviter les donnees sensibles dans les metadonnees. SMS/WhatsApp restent en provider audit-only tant qu'un fournisseur production, des signatures webhook et des quotas par plan ne sont pas actives.
- Depuis v4.16.124, `CommunicationService` applique aussi les heures calmes sur les canaux externes et les quotas mensuels SMS/WhatsApp (`0` = illimite). Les analytics communication passent par `GET /api/v1/communication/analytics`, reserve aux managers `principal` et `rh`.
- Depuis v4.16.185, les notifications mobiles doivent rester compatibles avec `GET /api/v1/notifications?unread=true` et l'ancien alias `unread_only=true`. Les clients employee/manager doivent utiliser `ApiClient.requestWithRetry` avec timeout court, parser les collections paginees Laravel (`data`) et garder `DELETE /api/v1/notifications/{notification}` scoppé a l'utilisateur authentifie.
- Depuis v4.16.186, les preferences notifications employee/manager sont pilotables depuis les ecrans Compte via `/api/v1/notification-preferences`. Toute evolution UI doit garder le modele partage `leopardo_core/lib/models/notification_preferences.dart`, la sauvegarde retry-aware et les heures calmes compatibles avec `CommunicationService`.
- Depuis v4.16.187, les listes notifications employee/manager doivent rester actionnables : tap = marquer lu, swipe/menu = supprimer via `DELETE /api/v1/notifications/{notification}`, puis refresh provider. Ne pas supprimer l'action de suppression sans retirer aussi le contrat frontend/API.
- Depuis v4.16.245, le readiness lancement exige que tous les employes actifs aient une ligne `notification_preferences` tenant-scope. Utiliser `php artisan notifications:backfill-preferences` pour reparer un environnement, et garder l'entrypoint Render qui lance ce backfill apres les seeders. Ne pas corriger le bloqueur `communication_governance` en abaissant le seuil du cockpit.
- Depuis v4.16.221, le lot notifications mobile production est garde par `dev-hub/tools/validate-mobile-notification-production-proof.ps1`. Employee/manager doivent continuer a initialiser `PushNotificationService` apres auth, synchroniser `/device-tokens`, supprimer le token au logout, et garder OpenAPI/documentation sur `/device-tokens` + `/push-notifications/send`. `PushNotificationService::registerToken()` doit renseigner `company_id` quand la colonne existe. Ne pas brancher `leopardo_platform_admin` sur les routes tenant `/device-tokens` : le super-admin necessite un contrat public separe avant FCM reel.
- Depuis v4.16.242, les apps mobiles separees ne doivent jamais acceder a `FirebaseMessaging.instance` avant un `Firebase.initializeApp()` protege et timeboxe. Garder `PushNotificationService` lazy/non bloquant, conserver `StartupGate` comme premier rendu visible, et appliquer `com.google.gms.google-services` dans chaque app Android qui embarque `google-services.json`.
- Depuis v4.16.206, les apps employee/manager ne doivent plus ajouter d'appels API mobiles via `apiClient.dio.*` sauf cas volontairement bas niveau comme `dio.download` pour un fichier. Utiliser `requestWithRetry`, timeouts explicites et `extractDataMap`/`extractDataList` afin de conserver la protection Render cold-start et les payloads Laravel pagines.
- Depuis v4.16.188, les trois apps mobiles ne doivent plus bloquer `runApp()` sur Hive, Google Sign-In ou intl. Le bootstrap passe par `StartupGate`; Hive `offlineCache` doit tenter une recuperation par suppression/reouverture en cas de corruption, et Google Sign-In reste non bloquant. Ne pas remettre d'`await` natif fragile avant le premier frame, sinon les testeurs peuvent revoir une page grise.
- Depuis v4.16.195, `StartupGate` ne doit plus bloquer le premier vrai ecran : il lance le bootstrap en arriere-plan et rend l'app immediatement. Les routers employee/manager demarrent sur `/welcome`, le platform admin sur `/platform/login`, et `checkAuth` ne doit jamais forcer un retour vers un splash. `SecureStorage`, `AppPreferences` et `TranslationCatalogCache` doivent rester tolerants si Hive `offlineCache` n'est pas encore ouvert.
- Depuis v4.16.196, les apps Android employee/manager/platform admin ne doivent plus afficher de logo dans le splash natif. Garder `launch_background` en fond simple et le splash Android 12+ avec `splash_transparent`; les logos appartiennent au premier ecran Flutter, pas au launch screen natif. Les builds Firebase doivent garder des noms prefixes par app (`employee-*`, `manager-*`, `platform-admin-*`) pour eviter la confusion entre releases `main` et `manual`.
- Depuis v4.16.197, `StartupGate` demarre le bootstrap apres le premier frame Flutter et garde un ecran de garde lisible tant que le bootstrap critique n'est pas termine. Ne pas remettre Hive, intl, Firebase, Google Sign-In ou un appel reseau avant le premier rendu : en cas de lenteur native, le testeur doit voir un etat Leopardo explicite, jamais une page noire.
- Depuis v4.16.217, `dev-hub/tools/validate-mobile-runtime-smoke.ps1` est le garde CI anti page noire/logo infini des trois apps mobiles. Toute modification de `main.dart`, `StartupGate` ou des routes initiales doit garder ce script vert : `runApp()` avant tout `await`, `StartupGate`, `ErrorWidget.builder`, route `/welcome` employee/manager et `/platform/login` platform admin.
- Depuis v4.16.219, le GPS mobile de pointage passe par `leopardo_core` (`AttendanceLocationService`) et reste non bloquant avec timeout court. Employee/manager doivent conserver les permissions natives Android/iOS, envoyer `gps_lat`, `gps_lng`, `gps_accuracy` quand disponibles, accepter le pointage sans GPS, et garder `validate-mobile-location-readiness.ps1` vert.
- Depuis v4.16.220, le branding tenant mobile passe par `leopardo_core` (`TenantBranding`, `TenantTheme`, `TenantBrandMark`). Employee/manager peuvent lire `/company/branding` apres auth et appliquer un theme tolerant; `leopardo_platform_admin` ne doit pas appliquer de theme tenant global. Garder `validate-mobile-tenant-branding.ps1` vert.
- Depuis v4.16.207, `StartupGate` auto-libere l'overlay apres un timeout/echec critique court. Ne pas revenir a un overlay qui attend seulement un bouton manuel : sur Firebase App Distribution, cela ressemble a une app bloquee sur logo/page noire pour les testeurs.
- Depuis v4.16.198, les tests widget `front/mobile_apps/leopardo_core/test/core/widgets/startup_gate_test.dart` sont le garde anti-regression pour le demarrage mobile et sont executes par `mobile-apps-ci.yml` sur `leopardo_core`. Toute refonte startup doit conserver l'affichage immediat du garde et le mode degrade actionnable apres timeout.
- Depuis v4.16.201, les repositories mobiles qui lisent des listes critiques doivent utiliser `extractDataList()` / `extractDataMap()` depuis `leopardo_core/lib/core/api/api_payload.dart` au lieu de caster directement `response.data['data'] as List`. Cela vaut notamment pour pointage, absences, avances, equipes, taches du jour, corrections, soldes conges, projets, paie, depenses, contrats, formations, evaluations, onboarding, vehicules, approvals, horaires et tokens push afin d'absorber les payloads Laravel directs, enveloppes ou pagines sans spinner infini. Les avances salaire mobiles suivent le workflow double validation : manager `PUT /salary-advances/{id}/manager-approve`, manager/RH `PUT /salary-advances/{id}/mark-paid`, employe `PUT /salary-advances/{id}/confirm-received`; garder OpenAPI, `FrontendApiContractTest`, `mobile-workflow-contracts.json` et la matrice frontend/API synchronises.
- Depuis v4.16.202, `extractDataList()` accepte aussi les listes `data.items` et `extractDataMap()` accepte les objets `data.item`. Ne pas reintroduire de parsing local different dans `leopardo_platform_admin` : les plans, entreprises, demandes client et metriques doivent passer par ces helpers partages.
- Depuis v4.16.203, les repositories cabinet employee/manager passent par `requestWithRetry` et les helpers `extractDataList()` / `extractDataMap()`. Garder un timeout upload distinct plus long pour `MultipartFile`, mais ne pas revenir a des appels `apiClient.dio.*` directs pour dossiers, documents, partages ou statistiques du placard numerique.
- Depuis v4.16.204, le mobile manager ne doit plus caster directement les listes modules/organigramme depuis `response.data['data']`. Les repositories `modules`, `organigramme` et le digest d'accueil doivent rester sur `requestWithRetry` + helpers payload pour eviter les ecrans vides ou spinners quand Laravel renvoie un payload pagine/enveloppe.
- Depuis v4.16.205, les flux auth/settings employee/manager utilisent `requestWithRetry` avec timeouts courts et `extractDataMap()`. Conserver `/auth/me` avec timeout court et `maxRetriesOverride: 0` pour ne jamais bloquer le demarrage mobile, et garder un timeout upload separe pour l'enrolement biometrie.
- Le Plan 20 readiness lancement vit dans `docs/PLAN_ACTION/20_PLAN_READINESS_LANCEMENT_PRODUCTION.md`. Le cockpit API `GET /api/v1/launch-readiness` est la source de verite pour le score go-live tenant ; toute extension dashboard/support doit garder ce contrat tenant-scope et RBAC P/RH.
- Depuis v4.16.127, la racine API Render expose aussi `/tester-guide` et `/api-explorer`. Garder ces pages publiques, legeres et coherentes avec `DemoCompanySeeder` pour que QA/dev puissent valider web client, mobile, admin plateforme et API sans preparer de payloads a la main.
- Le login demo doit rester robuste meme si `public.user_lookups` est incomplet : `AuthService` peut retrouver l'employe dans les schemas tenants connus puis regenerer le lookup. Ne pas supprimer ce fallback sans fournir une commande ops de reparation demo.
- Depuis v4.16.234, le compte super-admin expose par `/api/v1/demo-users` doit rester réellement utilisable sur les environnements demo : `DemoCompanyOnceSeeder` resynchronise `admin@leopardo-rh.com` / `password123` et retire le 2FA demo si necessaire. Si l'app `leopardo_platform_admin` retourne `INVALID_CREDENTIALS` avec les credentials publics, verifier d'abord ce seeder/deploy avant de toucher au client mobile.
- Depuis v4.16.249, le bouton demo de `leopardo_platform_admin` doit remplir `admin@leopardo-rh.com` / `password123`. `validate-mobile-plan29.ps1` garde ce contrat; ne pas remettre l'ancien mot de passe `admin`.
- Le contexte rapide pour nouveaux agents vit dans `docs/CONTEXT/`. Avant un audit large, lire ce dossier avec `AGENTS.md`, `CHANGELOG.md` et les rapports `docs/validation/*`.
- Le positionnement marche courant vit dans `docs/GOTO_MARKET/2026_MARKET_LAUNCH_COMPANY_OS/`. Le produit doit etre vendu comme `Mobile-First Company OS` pour PME terrain, pas comme simple SIRH generaliste.
- Le smoke auth marche exige toujours `POST /api/v1/auth/login` puis `GET /api/v1/auth/me` avec le token. En mode shared, `TenantMiddleware` doit pouvoir rehydrater l'employe Sanctum via `public.user_lookups` avant de poser le tenant.
- En auth shared PostgreSQL, ne pas supposer que `Company` se resout via le `search_path` courant : si un schema tenant contient une table `companies`, il peut masquer `public.companies`. `AuthService` et `TenantMiddleware` doivent recharger l'entreprise depuis `public.companies` avant de conclure `COMPANY_NOT_FOUND`.
- Depuis v4.16.128, `/api/v1/demo-users` est un contrat public de documentation QA, pas un endpoint secret : ne pas le rebloquer via `DEMO_MODE_ENABLED=false`. Pour desactiver l'auto-seed Render, utiliser `DISABLE_DEMO_SEEDING=true`.
- `DemoCompanyOnceSeeder` doit verifier les slugs demo (`techcorp-algerie`, `pharmaplus-casablanca`, `digitalflow-tunis`) et non la simple presence d'une entreprise en `shared_tenants`; en mode shared, les vrais clients utilisent aussi ce schema.
- Si le lock `demo_company_seed_v2` existe mais que ces slugs demo manquent, il doit etre considere stale et supprime par le seeder avant de relancer le seed. Ne pas reparer ce cas par SQL manuel tant que le seeder sait le faire.
- Depuis v4.16.246, `DemoCompanyOnceSeeder` doit aussi backfiller les signaux readiness des demos existantes quand le lock est deja pose : salaire actif minimal, geofence, kiosque actif et client event recent. Si `/api/v1/launch-readiness` reste sous 70 sur TechCorp apres deploy, verifier ce backfill avant de modifier le controleur.
- Depuis v4.16.247, ces backfills readiness doivent rester actifs meme si `DISABLE_DEMO_SEEDING=true` : ce flag bloque la creation/recreation des demos dans tous les environnements, pas la reparation non destructive des demos existantes. `notifications:backfill-preferences` doit aussi garder `SET search_path TO shared_tenants, public` pour ecrire dans le schema tenant reel lu par `/api/v1/launch-readiness`.
- Les notifications web/mobile doivent rester vivantes : polling/refresh visible, badge non lu et action de lecture immediate. Toute refonte SSE/WebSocket doit conserver un fallback polling pour Render/proxy/mobile.
- Dans `tests.yml`, ne pas faire porter la dette mobile historique a des PR backend/web en declenchant `mobile-tests` uniquement parce que le workflow lui-meme change. Le job mobile doit rester cale sur `mobile/**` tant que la base n'est pas completement assainie.
- Pour `Backend Quality`, garder Pint et PHPStan en gates diff-aware sur les fichiers PHP backend modifies. Cela bloque les nouvelles regressions sans faire porter la dette historique hors perimetre au PR courant. Garder les artefacts et la visibilite du baseline.
- Le workflow `OWASP ZAP Baseline` scanne la cible staging apres un deploy `main` reussi ou via `workflow_dispatch`. Il produit des artefacts HTML/Markdown/JSON et utilise `-I` pour ne pas bloquer sur les warnings informatifs ; traiter les alertes en lots dedies plutot que desactiver le scan.
- La matrice operationnelle routes/roles vit dans `docs/security/RBAC_ROUTE_MATRIX.md`. Toute PR qui ajoute/deplace une route protegee ou change un guard doit mettre cette matrice a jour avec le scenario/test associe.
- L'audit SQL injection courant vit dans `docs/security/SQL_INJECTION_AUDIT.md`. Toute introduction de tri/groupe/selection dynamique ou de raw SQL doit passer par allowlist, test de regression securite, et mise a jour de cet audit.
- L'audit CSRF/XSS admin vit dans `docs/security/ADMIN_CSRF_XSS_AUDIT.md`. Garder ESLint bloquant sur `vue/no-v-html`, `no-eval`, `no-implied-eval`, `no-new-func` et `no-script-url`; toute exception doit documenter le sanitizer et le test associe.
- Le workflow `Database Backup & Restore Drill` porte le backup PostgreSQL quotidien et le drill restore mensuel. Il saute proprement si les secrets ne sont pas configures ; ne pas supprimer ce skip, il evite les faux rouges sur forks/PR.
- Le code splitting par route est deja actif dans `front/admin-dashboard/src/router/index.js` via `component: () => import(...)`. Ne pas reouvrir cet item Plan 14 sauf si une route redevient importee en eager.
- La vue recrutement admin consomme les endpoints backend reels `/v1/recruitment/jobs`, `/v1/recruitment/jobs/{id}/applicants` et `/v1/recruitment/applicants/{id}/status`; ne pas revenir aux anciens chemins inexistants `/v1/job-postings` ou `/v1/applicants`.
- La documentation API publique est servie par le backend sur `/docs` et lit directement la spec canonique `/docs/openapi.yaml` depuis `api/openapi.yaml`; eviter toute copie divergente de la specification.
- Depuis v4.16.190, `/api-explorer` est aussi une surface developpeur : garder la base API configurable, les rappels Bearer/erreurs standard/webhooks, et les tests `OpenApiDocsTest` alignes avec `api/openapi.yaml` et le guide partenaire.
- Le guide de deploiement canonique des workers est `DEPLOYMENT_GUIDE.md` a la racine. Toute evolution queue/scheduler/Render Worker doit y etre reportee, surtout pour les jobs paie PDF et commandes planifiees.
- La matrice frontend/API canonique vit dans `docs/validation/FRONTEND_API_CONTRACT_MATRIX.md`. Toute nouvelle dependance admin/mobile/kiosk a une route Laravel doit ajouter la ligne matrice et, au minimum, un garde dans `FrontendApiContractTest`.
- Depuis v4.16.154, le Plan 30 API hardening vit dans `docs/PLAN_ACTION/30_PLAN_API_WORKFLOW_HARDENING.md`. `POST /api/v1/platform/companies` doit rester compatible avec le payload minimal de `leopardo_platform_admin` (name, email, country, city, manager names/email) en resolvant cote serveur `sector`, `plan_id`, `language`, `currency` et `timezone`. Ne pas rendre `sector` ou `plan_id` obligatoires sans adapter l'app mobile et les tests.
- Depuis v4.16.250, la creation platform admin multi-pays passe par `App\Support\CountryDefaults`. Ne plus coder `DZD`, `Africa/Algiers` ou `fr` comme defaults universels dans `POST /api/v1/platform/companies` ou dans l'app `leopardo_platform_admin`; si un pays est ajoute cote mobile, ajouter aussi son mapping backend et le couvrir par test ou audit.
- Depuis v4.16.250, `GET /api/v1/platform/country-defaults` est la source frontend super-admin pour les pays supportes. `leopardo_platform_admin` peut garder un fallback local pour la resilience, mais toute extension durable pays/devise/timezone doit d'abord passer par `CountryDefaults` et OpenAPI.
- Depuis v4.16.250, la fiche `leopardo_platform_admin` doit garder l'action directe `Activer client` pour les entreprises en `trial`. Cette action reutilise le `plan.id` de `/platform/companies/{company}/subscription`, appelle le `PATCH` abonnement existant et rafraichit fiche, liste entreprises et metriques.
- Depuis v4.16.250, les horaires manager sont aussi la surface "regles entreprise" pour heures de travail, pauses, repos, conges et notes internes. Toute evolution mobile manager doit garder `POST /api/v1/schedules/{schedule}/assign-employees`, `ScheduleControllerTest`, OpenAPI et la matrice frontend/API alignes, avec assignation strictement limitee aux employes du tenant courant.
- Depuis v4.16.250, les surfaces mobiles employee/manager ne doivent plus afficher `DZD` en dur dans les montants runtime. Utiliser la devise API (`summary.currency`, `SalaryAdvance.currency`, `Payroll.currency`) ou la devise de l'employe/tenant, avec `DZD` uniquement comme fallback technique de dernier recours.
- Depuis v4.16.250, le travail traducteur Jules doit suivre `docs/GUIDES/GUIDE_JULES_TRADUCTION_MULTILINGUE.md`. Les traducteurs modifient les catalogues (`shared/i18n/locales`, ARB mobile core, JSON admin), pas les composants/controllers. Utiliser `dev-hub/tools/validate-i18n-debt.ps1` pour generer le rapport de dette hardcodee par surface.
- Depuis v4.16.250, la recette API lancement par profil passe par `dev-hub/tools/launch-api-profile-smoke.ps1` et le workflow manuel GitHub `Launch API Profile Smoke`. Fournir les tokens via variables d'environnement/secrets (`LEOPARDO_MANAGER_TOKEN`, `LEOPARDO_EMPLOYEE_TOKEN`, `LEOPARDO_PLATFORM_ADMIN_TOKEN`, `LEOPARDO_KIOSK_DEVICE_CODE`, `LEOPARDO_KIOSK_TOKEN`) et ne jamais les commiter. La creation entreprise de test est volontairement gardee par `-IncludePlatformProvisioning` / input `include_platform_provisioning`.
- Si `Launch API Profile Smoke` echoue avec `Name or service not known`, verifier d'abord que le workflow passe `$env:LEOPARDO_API_BASE_URL` et non une interpolation litterale. Si un profil sans secret echoue au lieu de `SKIP`, verifier que l'appel PowerShell utilise des parametres nommes pour eviter le decalage des arguments quand un token vaut `$null`.
- Dans `launch-api-profile-smoke.ps1`, preferer le splat hashtable pour appeler les helpers avec des valeurs potentiellement vides. En PowerShell, un argument variable vide apres un parametre nomme peut etre avale et faire glisser le parametre suivant.
- Dans `.github/workflows/launch-api-profile-smoke.yml`, le script doit etre appele avec un splat hashtable (`@{ BaseUrl = ... }`) et non un array (`@("-BaseUrl", "...")`) : l'array splat devient positionnel et casse le mapping des parametres.
- Le smoke `launch-api-profile-smoke.ps1` auto-login par defaut via `/demo-users` pour couvrir employee, manager et platform admin si les tokens secrets manquent. Utiliser `-DisableDemoLogin` / `disable_demo_login=true` uniquement pour tester le mode strict secrets.
- La creation entreprise du smoke platform admin reste volontairement gardee par `-IncludePlatformProvisioning`. Utiliser `-PlatformProvisioningStatus active` seulement pour une recette controlee d'activation immediate, car cela cree une entreprise smoke reelle.
- Depuis v4.16.250, `api/composer.json` cible `laravel/framework:^12.60` afin de rester hors advisory `CVE-2026-48019`. Ne pas redescendre sur Laravel 11 sans verifier `composer audit --locked --no-dev` et les compatibilites Sanctum/Pest/Sentry.
- Depuis v4.16.250, la migration publique `2026_05_02_100001_create_users_and_company_requests_tables.php` doit reconciler explicitement `public.company_requests` sur PostgreSQL avec `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`. Ne pas remplacer ce garde par de simples `Schema::hasColumn()` : l'ancienne table `employee_id` peut deja exister et les flux user/company-request modernes ont besoin de `user_id`.
- Depuis v4.16.189, les retours testeurs produit 31-44 sont formalises dans `docs/PLAN_ACTION/57_PLAN_API_DOCS_ECOSYSTEME_DEVELOPPEUR.md` a `65_PLAN_PAIEMENT_MASSE_SIGNATURE_NUMERIQUE.md`. Avant de coder ces chantiers, choisir le plan concerne, livrer ses lots dans l'ordre, mettre a jour `00_SOMMAIRE.md` si un nouveau plan est ajoute, puis pousser via PR pour garder la mission lisible.
- Depuis v4.16.216, les plans 01-66 sont consideres comme historiques executes ou cartographies. Avant de rouvrir un ancien sujet, consulter `docs/validation/PLAN_ACTION_COVERAGE_MATRIX_2026_06_01.md`; les derniers lots de lancement doivent etre rattaches a `docs/PLAN_ACTION/67_PLAN_AUDIT_FINAL_QUALITE_PRODUIT.md` sauf besoin clairement hors perimetre.
- Les decisions d'architecture structurantes vivent dans `docs/architecture/adr/`, le diagramme C4 dans `docs/architecture/C4_ARCHITECTURE.md`, et le point d'entree operations dans `docs/GESTION_PROJET/RUNBOOK_OPERATIONS.md`.
- Le guide partenaire canonique est `docs/GUIDES/GUIDE_INTEGRATION_PARTENAIRES.md`; l'actualiser avec tout changement API/webhook/SSO expose aux integrateurs.
- Le controle generalise de release vit dans `docs/validation/RELEASE_READINESS_GATE.md` avec le script `dev-hub/tools/release-readiness.ps1`; produire ou mettre a jour un rapport `docs/validation/RELEASE_READINESS_REPORT_*.md` avant de declarer un score production-ready.
- Depuis v4.16.223, `release-readiness.ps1 -Strict` doit passer a `23/23` et couvrir aussi `front/mobile_apps/leopardo_core`, `leopardo_employee`, `leopardo_manager`, `leopardo_platform_admin`, les gardes Plan 67, `mobile-distribute.yml`, `front/web`, `front/zkteco-kiosk` et le cadrage open core/marketplace. Ne plus declarer une readiness mobile uniquement sur l'ancien `front/mobile`.
- Depuis v4.16.223, l'ouverture open core/marketplace est cadree par `docs/architecture/adr/0004-open-core-marketplace-boundaries.md` et `docs/GUIDES/GUIDE_OPEN_CORE_MARKETPLACE.md`. Ne pas publier de depot/package public sans secret scan, license scan, nettoyage demo data, validation juridique et scopes/webhooks documentes. Les plugins partenaires doivent passer par API publique et webhooks signes, jamais par import direct du backend Laravel ou des apps mobiles.
- Depuis v4.16.224, l'audit post-Plan 67 vit dans `docs/PLAN_ACTION/68_PLAN_AUDIT_POST_67_QUALITE_CODE_LANCEMENT.md`. Utiliser `dev-hub/tools/repository-hygiene-report.ps1` pour verifier l'hygiene depot avant de supprimer des branches. Ne pas supprimer la branche locale historique `codex/plan57-api-docs-ecosystem` sans audit explicite de son contenu/stash.
- Depuis v4.16.225, la gouvernance contrats front/API est verifiee par `dev-hub/tools/validate-frontend-api-contract-governance.ps1`. Toute nouvelle route critique consommee par mobile, admin, web ou kiosk doit rester alignee entre `docs/validation/FRONTEND_API_CONTRACT_MATRIX.md`, `api/tests/Feature/FrontendApiContractTest.php`, `api/openapi.yaml` et, si mobile, `dev-hub/tools/mobile-workflow-contracts.json`.
- Depuis v4.16.226, `dev-hub/tools/validate-code-quality-governance.ps1` bloque le retour des chemins OpenAPI obsoletes dans les docs canoniques et verifie les artefacts qualite post-67. Les docs publiques doivent pointer vers `/docs` et `/docs/openapi.yaml`, pas vers l'ancien `/openapi/v1.yaml`.
- Depuis v4.16.227, l'audit ops production est garde par `dev-hub/tools/validate-production-ops-readiness.ps1`. Avant lancement, verifier `DEPLOYMENT_GUIDE.md`, Render deploy/rollback hooks, Redis/queues, scheduler, Firebase App Distribution, FCM backend et backup/restore. `FIREBASE_READBACK_REQUIRED=true` ne doit etre active qu'apres rotation et test du service account.
- Depuis v4.16.228, le Plan 68 est cloture et la suite canonique est `docs/PLAN_ACTION/69_PLAN_EXECUTION_LANCEMENT_MOBILE_FIRST_COMPANY_OS.md`. Commencer par le lot 69.1 : recette mobile release sur vrais appareils/Firebase pour employee, manager et platform admin avant d'elargir marketing.
- Depuis v4.16.229, la distribution Firebase staging Plan 69.1 run `26750677529` a reussi pour employee, manager et platform admin depuis `main` SHA `2fc2ca97058365ed468430c2c3323df0690d607e`. Le go final mobile reste conditionne au test sur appareils physiques documente dans `docs/validation/MOBILE_RELEASE_DEVICE_QA_2026_06_01.md`.
- Depuis v4.16.243, `GET /api/v1/launch-readiness` doit reutiliser `currentCompany()` quand le middleware tenant l'a resolue et rester schema-aware sur les colonnes paie employees. Ne pas recharger `Company` via le `search_path` tenant dans ce cockpit de lancement.
- Depuis v4.16.230, les apps employee/manager doivent lire les soldes conges personnels via `GET /api/v1/me/leave-balances`, jamais via `/leave-balances` qui reste manager-only. Les demos Render doivent aussi avoir des lignes `leave_balances` backfillees par `DemoCompanyOnceSeeder`; si le formulaire absence indique "aucun type disponible", verifier d'abord ce seed/backfill avant de toucher aux permissions.
- Depuis v4.16.232, `EmployeeController@index` doit garder son `select` compatible avec `EmployeeResource` sans supposer que toutes les colonnes optionnelles existent deja sur tous les environnements. Utiliser `employeeIndexColumns()` + `relationColumns()` + `Schema::hasColumn`, y compris pour `company`, `schedule`, la recherche et les tris attendance. Garder `EmployeeResource::employeeAttribute()` tolerant aux attributs absents. Si `GET /api/v1/employees` retourne 500 en production, verifier d'abord les champs partiellement charges/colonnes optionnelles avant de toucher aux policies.
- Depuis v4.16.250, `EmployeeController@index` ne doit plus eager-loader la relation `company` sous le `search_path` tenant. Attacher `currentCompany()` a la collection avant `EmployeeResource` pour que la liste mobile manager garde `company`, `currency` et `features` non nuls sur Render/shared PostgreSQL sans recharger `public.companies` depuis `shared_tenants`.
- Apres un lot qui touche auth/web/admin/mobile, attendre les checks GitHub Actions du PR et verifier aussi les workflows `main` post-merge quand ils partent en cascade (deploy, staging E2E, OWASP, mobile) avant d'annoncer la preuve finale.
- Le workflow manuel `Deploy - Leopardo RH` doit rester utilisable sur `main` sans dependance au lookup `workflow_run`; c'est le bouton ops pour relancer Render et reexecuter l'entrypoint (migrations/seeders) apres une correction de donnees demo.
- Le smoke k6 `dev-hub/load/k6/api-core-smoke.js` doit rester read-only et suivre les endpoints reels du dashboard client (`auth/me`, `dashboard/summary`, `dashboard/recent-activity`, `dashboard/kpi`) avant d'ajouter des scenarios plus agressifs.
- Le workflow manuel `k6 Load Smoke - Leopardo RH` lance ce smoke via Docker contre staging. Configurer `K6_MANAGER_TOKEN` et `K6_EMPLOYEE_TOKEN` pour mesurer les parcours authentifies ; sans tokens, le workflow reste health-only et publie quand meme un artefact JSON.
- Le depot contient deja beaucoup de tests backend critiques (auth, guardrails, RBAC, absences, attendance, contrats mobile). Avant d'ajouter de nouveaux tests, verifier d'abord si le manque reel n'est pas plutot la visibilite CI (coverage, artifacts, reporting).
- Les tests locaux Windows peuvent echouer avant PHPUnit si l'extension PHP `mbstring` manque (`mb_split()` introuvable dans Laravel). Dans ce cas, ne pas conclure a un rouge applicatif ; verifier la syntaxe et laisser GitHub Actions executer la suite complete.
- Pour `front/admin-dashboard`, `VITE_API_URL` doit pointer vers la base versionnee (`.../api/v1`). Le client Axios normalise les anciens appels `/v1/*`; ne pas revenir a des `window.open('/api/v1/...')` pour les exports, car cela casse sur Cloudflare Pages et perd le bearer token.
- Pour `front/mobile`, le flux auth canonique est `POST /auth/login` puis hydration `GET /auth/me` avec le token Bearer recu. Les roles tenant (`manager_role`), capabilities, modules et preference langue/RTL doivent venir de `/auth/me`; garder `login_screen_test.dart` comme garde anti-regression.
- Depuis v4.16.133, `front/mobile/lib/features/attendance/screens/attendance_screen.dart` porte le design pointage v3 final. Ne pas ajouter de `BottomNavigationBar` dans cet ecran : la navigation reste dans le shell `go_router`. Garder l'horloge `Timer.periodic(1s)`, le menu `...` pour correction, le blocage des heures futures et les couleurs via `AppColors`.
- Depuis v4.16.135, les couleurs secondaires du mobile sombre ne doivent plus descendre sous les tons lisibles (`MobileSurface.muted #8EA9C8`, `disabled #6F86A5`). Le pointage doit rester utilisable pendant le chargement historique : afficher une synchronisation inline et conserver les lignes semaine/resume, sans spinner pleine section. Le libelle menu reste `Modifier`; seule la soumission distingue employe (`Demander une modification` via `POST /attendance/corrections`) et manager principal/RH (`Modifier` avec `PUT /attendance/{id}`).
- Depuis v4.16.136, le pointage mobile utilise `AttendanceState.isPunching` pour le spinner du bouton, pas `isLoading` qui concerne l'hydratation ecran. Le bouton central reste une icone empreinte sans texte interne. Par defaut, le mobile pointe vers Render ; utiliser `--dart-define=API_BASE_URL=...` ou `--dart-define=USE_LOCAL_API=true` pour developper contre une API locale.
- Depuis v4.16.146, toute evolution mobile app-store doit garder `dev-hub/tools/mobile-workflow-contracts.json` et `validate-mobile-workflow-contracts.ps1` alignes. Le garde verifie les routes statiques, les routes exposees par `MobileExperienceService`, les endpoints API et les tokens d'action critiques ; une navigation `context.push/go(...)` vers une route absente doit etre corrigee avant merge.
- Depuis v4.16.147, la distribution Firebase mobile est multi-app. Utiliser `FIREBASE_EMPLOYEE_ANDROID_APP_ID`, `FIREBASE_MANAGER_ANDROID_APP_ID` et `FIREBASE_TOKEN`; ne pas reutiliser l'ancien `FIREBASE_APP_ID` de l'app unique. Les fichiers `google-services.json` / `GoogleService-Info.plist` doivent matcher exactement `com.leopardo.employee` et `com.leopardo.manager`; `install-mobile-firebase-configs.ps1` refuse les fichiers mal nommes et choisit le fichier Android le plus specifique disponible quand plusieurs exports contiennent le meme package.
- Depuis v4.16.151, les workflows Firebase App Distribution retentent le read-after-write via `firebase appdistribution:releases:list` apres l'upload. Si la commande de listing fonctionne, l'absence du build courant reste bloquante apres retries. Si `firebase-tools` ne peut pas lister les releases alors que l'action d'upload a deja retourne success, le workflow emet un warning non bloquant ; passer a un readback service-account strict avant d'en faire une preuve de conformite release.
- Depuis v4.16.150, le Plan 28 mobile multi-app est le garde d'excellence pour `leopardo_employee` / `leopardo_manager`. Garder `dev-hub/tools/validate-mobile-plan28.ps1` vert : l'app employee ne doit pas contenir de methodes/endpoints de decision manager (`approve*`, `reject*`, `/team`, `/approvals`), les configs Firebase Android/iOS doivent matcher les IDs natifs, et les workflows doivent conserver le read-after-write App Distribution.
- Depuis v4.16.152, `front/mobile_apps/leopardo_platform_admin` est la troisieme app mobile reservee au super-admin plateforme. Elle doit consommer exclusivement les routes `/api/v1/platform/*`, garder l'identite native `com.leopardo.platformadmin`, et rester separee des workflows tenant employee/manager. Toute evolution doit garder `dev-hub/tools/validate-mobile-plan29.ps1` vert.
- Depuis v4.16.171, `dev-hub/tools/mobile-workflow-contracts.json` couvre aussi `leopardo_platform_admin`. Toute nouvelle action mobile super-admin doit declarer sa route `/platform/*`, son endpoint `/platform/*` et ses tokens d'ecran dans ce contrat, en plus de garder le Plan 29 vert.
- Depuis v4.16.184, le Plan 56 durcit l'app mobile `leopardo_platform_admin` : pas d'hydratation `/platform/auth/me` sans token local, gestion explicite du `202 TWO_FA_REQUIRED`, bouton compte demo super-admin et validation email/code pays sur la creation client.
- Depuis v4.16.172, la fiche client mobile platform admin vit sur `/platform/companies/:companyId` et consomme `/platform/companies/{company}/health`, `/subscription` et `/features`. `PlatformCompany.id` doit rester une string pour supporter les UUID de `public.companies`; ne pas le recaster en int.
- Depuis v4.16.236, les endpoints detail platform admin (`/platform/companies/{company}/health`, `/subscription`, `/features`) doivent charger `Company` via `PlatformCompanyLookup`, qui force `SET search_path TO public` puis lit la table qualifiee `public.companies`. Sans ce garde, une creation client ou une requete tenant precedente peut laisser PostgreSQL chercher l'UUID dans le mauvais schema et produire un faux `404/500`.
- Depuis v4.16.218, `leopardo_platform_admin` doit garder `PlatformRepository.createCompany()` avec retour `PlatformCompany` et redirection vers `/platform/companies/{companyId}` apres creation. Ne pas revenir a un `void` silencieux : le super-admin doit voir la fiche du client cree sans attendre un refresh manuel.
- Depuis v4.16.173, cette fiche client est actionnable : edition abonnement via `GET /platform/plans` + `PATCH /platform/companies/{company}/subscription`, et edition modules via `PATCH /platform/companies/{company}/features`. Le module `rh` reste verrouille actif cote UI comme cote API.
- Depuis v4.16.174, `shared/i18n/sync/sync-mobile.js` ecrit les catalogues ARB a la fois dans `front/mobile/lib/l10n` et `front/mobile_apps/leopardo_core/lib/l10n`. Ne pas laisser Jules traduire seulement l'ancien mobile : les apps employee/manager/platform admin lisent le core.
- Depuis v4.16.153, `mobile-distribute.yml` se declenche aussi sur `main` quand `front/mobile_apps/**` change et distribue les trois apps Android `employee`, `manager` et `platform_admin`. Garder `FIREBASE_EMPLOYEE_ANDROID_APP_ID`, `FIREBASE_MANAGER_ANDROID_APP_ID`, `FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID` et les fichiers `google-services.json` synchronises ; `deploy-main.yml` peut sauter une app non prete, mais le workflow mobile manuel doit echouer si l'app demandee n'est pas correctement configuree.
- Depuis v4.16.181, les blocs `node <<'NODE'` dans `deploy-main.yml` et `mobile-distribute.yml` doivent garder le contenu JavaScript et le delimiteur `NODE` sans indentation residuelle dans le script Bash rendu. Sinon GitHub Actions echoue avec `here-document ... wanted NODE` puis `syntax error: unexpected end of file`.
- Depuis v4.16.175, `front/mobile_apps/` est la source canonique des apps mobiles de lancement. `front/mobile/` reste une app historique de maintenance : ne pas y ajouter de nouvelle fonctionnalite produit employee/manager/platform admin. Le workflow `Legacy Mobile CI - Flutter`, le check protege `Mobile Flutter (Stable Channel)` et les assets de release `leopardo-rh-legacy-*` ne valident que ce perimetre historique ; les apps store passent par `mobile-apps-ci.yml` et `mobile-distribute.yml`.
- Le secret GitHub `FIREBASE_SERVICE_ACCOUNT_JSON` est optionnel mais recommande pour le readback Firebase strict. Il doit contenir le JSON complet d'un compte de service Google/Firebase du projet `leopardo-rh`, idealement limite a Firebase App Distribution. Quand il est absent, les workflows gardent le fallback `FIREBASE_TOKEN` et n'echouent pas si le listing Firebase CLI est indisponible apres upload reussi.
- Depuis v4.16.155, garder un `type:` explicite sur chaque input `workflow_dispatch` de `mobile-distribute.yml`. GitHub Actions peut refuser le dispatch avec une erreur de schema interne si un input n'a pas de type. Si une cle service account Firebase est exposee dans un chat, ticket ou log, la revoquer dans Google Cloud et regenerer une nouvelle cle avant de la remettre dans GitHub Secrets.
- Depuis v4.16.156, `FIREBASE_SERVICE_ACCOUNT_JSON` utilise le compte de service pour le readback App Distribution, mais ne rend pas le listing bloquant par defaut apres upload reussi. Activer `FIREBASE_READBACK_REQUIRED=true` uniquement quand la nouvelle cle est rote, non exposee et permissionnee avec App Distribution ; sinon les testeurs doivent continuer a recevoir les APK meme si le readback Firebase CLI echoue.
- Depuis v4.16.157, les workflows mobile comparent chaque secret `FIREBASE_*_ANDROID_APP_ID` au `mobilesdk_app_id` du `google-services.json` pour le package attendu avant upload. Ne pas retirer ce garde : il detecte immediatement un secret qui pointe vers l'ancienne app Firebase ou vers le mauvais projet. En readback non strict, le workflow retente aussi `FIREBASE_TOKEN` si le service account echoue.
- Depuis v4.16.158, le pointage employee supporte plusieurs sessions par jour via `attendance_logs.session_number` et le contexte `work_type` (`normal`, `overtime`, `break`, `resume`, `mission`, `travel`, `training`, `other`). Ne pas remettre de filtre dur `session_number = 1` dans `AttendanceService` ou `/attendance/today`; l'ecran mobile attend `sessions` et `summary`. Les taches terrain du jour passent par `GET /api/v1/tasks/today` et les champs `estimated_minutes`, `completed_minutes`, `completion_note`, `performance_score`, `recurrence_rule`, `template_key`.
- Depuis v4.16.209, le Plan 61 solde paie mobile est branche sur `PayrollCycleService`, `GET /api/v1/me/balance` et `GET /api/v1/payroll/mobile-summary`. Ne pas reutiliser `Company::$settings` : les parametres cycle paie viennent de `companies.metadata.payroll` ou `company_settings` avec fallback mensuel. Toute evolution du solde doit garder `PayrollCycleIntegrationTest`, `FrontendApiContractTest`, OpenAPI, la matrice frontend/API et les apps employee/manager synchronises.
- Depuis v4.16.210, les documents de paiement asynchrones passent par `payment_documents` et `GeneratePaymentDocumentJob` sur queue `documents`. `mark-paid` avance salaire doit creer un `advance_receipt` pending sans generer le PDF dans la requete, puis `GET /api/v1/me/payment-documents` et `/download` exposent le statut et le fichier a l'employe. Toute extension mobile doit conserver les statuts `pending/generating/available/failed` et l'isolation tenant/employee.
- Depuis v4.16.211, les apps employee/manager consomment les documents paiement depuis les ecrans paie. Le modele partage est `leopardo_core/lib/models/payment_document.dart`; employee utilise `/me/payment-documents`, manager utilise `/payments/{payrollRun}/documents`, et les deux telechargent via `/me/payment-documents/{document}/download`. Garder `dev-hub/tools/mobile-workflow-contracts.json` synchronise avec ces routes.
- Depuis v4.16.212, le worker production doit ecouter `documents,pdf,payroll,notifications,webhooks,default` et `REDIS_CLIENT=predis` reste le defaut compatible Upstash. `queue:health-check` doit couvrir toutes ces queues, `failed_jobs` et la connexion active ; ne pas revenir a un worker limite a `payroll,notifications,default`, sinon les documents Plan 62 resteront en attente.
- Depuis v4.16.213, `attendance:auto-close` ne doit pas utiliser le statut `auto_closed` car l'ancien schema tenant limite les statuts de pointage. Tracer l'auto-cloture via `correction_note=auto_close` et `punch_meta.auto_close`. Les payloads pointage doivent garder `device_timezone`, UTC, local entreprise et `geofence` doux ; hors zone ne bloque pas le pointage par defaut.
- Depuis v4.16.214, le paiement en masse canonique passe par `payment_batches`, `payment_items` et `payment_confirmations`. Garder l'ancien `/payroll-runs/{id}/bulk-pay` compatible, mais les nouveaux frontends doivent privilegier `/payment-batches` puis `/payment-batches/{id}/mark-paid` et la confirmation employee `/payment-confirmations/{paymentItem}/confirm` pour l'audit financier/signature future-ready.
- Depuis v4.16.215, le branding entreprise canonique passe par `GET/PATCH /api/v1/company/branding` et se stocke dans `companies.metadata.branding`. Les managers `principal`/`rh` peuvent modifier, tous les utilisateurs authentifies peuvent lire. Toute UI mobile/web qui applique ces couleurs doit garder un fallback `AppColors` et verifier le contraste avant application globale.
- Depuis v4.16.178, l'UX de pointage employee est progressive : premier pointage = arrivee normale directe, premier depart = depart direct, puis les choix avances (`pause`, `reprise`, `overtime`, `mission`, `travel`) apparaissent seulement quand la journee contient deja des sessions. Ne pas remettre une bottom sheet obligatoire au premier clic.
- Depuis v4.16.176, dans `leopardo_employee`, les trois points d'une ligne de semaine ouvrent d'abord un menu `Details de la journee` / correction. Garder cette separation : les details servent a lire sessions multiples, pauses, heures supp et gains ; la correction reste l'action RH de modification ou demande.
- Depuis v4.16.177, dans `leopardo_manager`, la creation de taches terrain doit garder les templates metier et les champs API `category`, `template_key`, `recurrence_rule`, `estimated_minutes`. Les nouveaux templates doivent rester des presets UI legers, pas une logique metier dupliquee cote mobile.
- Depuis v4.16.179, les demandes RH visibles par manager mobile doivent afficher le contexte complet avant decision : demandeur, montant/duree, date, motif, statut et remboursement si applicable. Les repositories manager critiques (`absences`, `salary_advances`, `team`) doivent passer par `ApiClient.requestWithRetry` avec timeouts courts pour eviter les spinners infinis.
- Depuis v4.16.169, `EstimationService` et les dashboards doivent agreger toutes les sessions d'une journee. Les vues peuvent choisir une session courante pour l'affichage, mais les totaux heures/gains/retards doivent venir de `dailySummaryFromLogs()` ou `dailySummary()`, jamais d'un filtre dur `session_number = 1`.
- Depuis v4.16.170, le menu haut du pointage employee ne porte pas l'action `Modifier`. Il doit garder `Taches du jour` (bottom sheet connectee a `todayTasksProvider`), `Historique` (`/history`), `Preferences` et `Parametres` (`/settings`). Les demandes/modifications de pointage restent sur les menus `...` des lignes jour.
- Depuis v4.16.168, les trois nouvelles apps mobiles ont un branding natif distinct. Ne pas modifier `leopardo_mobile_legacy`, ne pas restaurer les icones Flutter par defaut, et garder synchronises launcher Android, adaptive icon, splash Android/iOS, AppIcon iOS et `drawable/ic_notification` pour employee, manager et platform admin. Les previews vivent dans `docs/assets/mobile-branding/`.
- Depuis v4.16.159, le compte employee est durable : `PATCH /api/v1/auth/profile` accepte `personal_email`, `recovery_email` et `personal_phone`, et `GET /api/v1/me/career` expose le parcours professionnel pour l'ecran mobile Compte. Le placard numerique est un espace personnel scope par `employee_id` ; ne pas rebrancher les modeles cabinet sur le global scope `BelongsToCompany`, car leurs tables historiques ont un `company_id` numerique alors que les tenants utilisent des UUID.
- Depuis v4.16.167, le mobile manager remplace les placeholders presence/anomalies/corrections par des ecrans connectes. Garder `GET /api/v1/attendance/corrections` et `PUT /api/v1/attendance/corrections/{id}/approve|reject` tenant-scopes, reserves aux managers `principal`/`rh`, et alignes avec OpenAPI + `FrontendApiContractTest` + `dev-hub/tools/mobile-workflow-contracts.json`.
- Depuis v4.16.160, l'onboarding QR mobile passe par des jetons signes (`OnboardingQrService`) et les routes `GET /api/v1/me/qr-profile`, `GET /api/v1/company/qr-onboarding`, `POST /api/v1/company/qr-onboarding/scan-employee`, `POST /api/v1/company/qr-onboarding/create-employee`, `POST /api/v1/me/company-qr/scan`. Le QR pre-remplit ou cree une demande, mais ne doit pas valider automatiquement une embauche. Le formulaire classique manager doit rester disponible et ne doit pas attendre un refresh reseau bloquant avant de fermer.
- Depuis v4.16.183, les apps `leopardo_employee` et `leopardo_manager` initialisent `PushNotificationService` apres authentification et synchronisent automatiquement le token FCM vers `/api/v1/device-tokens`. Garder les routes device-token dans `routes/modules/integrations.php` pour eviter un doublon dans `api.php`, et ne pas exposer le client API du service push comme champ mutable public.
- Depuis v4.16.184, `PushNotificationService.unregisterCurrentToken()` doit etre appele avant logout employee/manager pour executer `DELETE /api/v1/device-tokens` et eviter des notifications vers une session fermee. Ne pas supprimer ce nettoyage silencieux : une erreur reseau ne doit pas bloquer la deconnexion.
- Depuis v4.16.182, les QR mobile doivent utiliser le composant partage `LeopardoQrCard` dans `leopardo_core` au lieu d'afficher le jeton brut seul. Garder le jeton copiable comme fallback, mais l'experience principale doit rester un QR visuel scannable.
- Depuis v4.16.161, la home manager mobile lit ses signaux via `GET /api/v1/dashboard/manager-digest`. Ce contrat doit rester tenant-scope et limiter les managers non `principal/rh` a eux-memes + leurs directs `manager_id`; ne pas remettre de chiffres hardcodes dans `front/mobile_apps/leopardo_manager/lib/features/home/screens/home_screen.dart`.
- Depuis v4.16.162, le mobile manager expose `/schedules` pour gerer les horaires via l'API canonique `GET/POST/PUT/DELETE /api/v1/schedules`. Toute evolution des horaires doit garder `ScheduleControllerTest`, `FrontendApiContractTest`, OpenAPI et la matrice frontend/API alignes; ne pas creer de route mobile parallele pour les memes regles.
- Depuis v4.16.163, `schedule_id` fait partie du contrat employee create/update et QR create. La validation doit toujours etre limitee au `company_id` courant, et le formulaire mobile manager doit continuer a charger les horaires via `/schedules` avant d'envoyer `schedule_id`.
- Depuis v4.16.164, la fiche collaborateur mobile manager est actionnable : `PATCH /api/v1/employees/{employee}` porte aussi `contract_start`, `salary_type`, `salary_base` et `hourly_rate`, et le mobile affiche/modifie telephone, poste, departement, lieu, salaire et horaire. Garder ces champs synchronises entre `EmployeeResource`, `Employee` Dart et la matrice frontend/API.
- Depuis v4.16.165, le workflow taches du jour relie manager et pointage employe : le manager utilise `/tasks` pour assigner une tache, l'employe voit `/tasks/today` dans le pointage et cloture via `PATCH /tasks/{task}` avec `completed_minutes` et `completion_note`. Toute assignation `assigned_to.*` doit rester validee sur le `company_id` courant. Les tenants ayant une ancienne table `tasks` doivent aussi recevoir `category`, `checklist` et `visibility` via migration additive avant toute creation de tache. Le fixture PostgreSQL `mvp_schema.pgsql.sql` doit garder `tasks.assigned_to` en JSONB, pas en entier historique.
- Depuis v4.16.166, l'ecran employee `/me/monthly` doit toujours afficher un resultat utile : chargement explicite, etat vide `Aucun pointage sur ce mois`, et action vers `/history`. `GET /api/v1/me/monthly-summary` doit rester valide pour un mois sans pointage avec `breakdown=[]` et totaux a zero.
- Depuis v4.16.238, `GET /api/v1/payroll/mobile-summary` doit rester schema-aware sur les colonnes employees optionnelles (`manager_id`, `salary_type`, `salary_base`, `hourly_rate`, noms). Les tenants Render historiques peuvent ne pas avoir tous les backfills au moment du smoke ; ne pas remettre une selection brute de colonnes optionnelles dans `PayrollCycleService`.
- Depuis v4.16.239, `PayrollCycleService` doit reutiliser `currentCompany()` quand le middleware tenant l'a deja resolue. Ne pas recharger aveuglement `$employee->company` dans les endpoints tenant shared PostgreSQL : le `search_path` peut masquer `public.companies` et provoquer un 500 sur les soldes paie mobiles.
- Depuis v4.16.240, la route `GET /api/v1/employees/{employee}/balance` doit garder une signature controleur alignee sur `{employee}` et le resume `GET /api/v1/payroll/mobile-summary` doit degrader un solde individuel en `warning=partial_balance_fallback` plutot que retourner 500 pour tout l'ecran manager.
- Depuis v4.16.208, le workflow avances salaire Plan 60 doit rester teste de bout en bout : `manager-approve`, `mark-paid`, puis `confirm-received`. Toute evolution des statuts `validation_status` doit mettre a jour `SalaryAdvanceSecurityTest`, `CreatesMvpSchema.php`, `mvp_schema.pgsql.sql`, OpenAPI et les repositories mobiles employee/manager.
- Pour `front/zkteco-kiosk`, `apiBaseUrl` peut etre configure avec ou sans `/api/v1`; `app.js` normalise la base. Garder ce contrat pour eviter les URLs double-versionnees sur site client.
- Les extensions kiosque `/api/v1/kiosks/{deviceCode}/employee-info`, `/announcements`, `/leave-balance` et `/qr-punch` sont des endpoints device token-only : elles doivent rester sous `throttle:api` avec `X-Kiosk-Token`, sans `auth:sanctum` utilisateur.
- Le smoke E2E staging API doit tester les routes reelles du backend. Pour un check auth sans token, utiliser `/api/v1/auth/me`, pas `/api/v1/me`.
- Les curls de smoke API doivent envoyer `Accept: application/json`; sinon Laravel peut retourner une redirection HTML `302 /login` au lieu du statut JSON attendu (`401`, `403`, etc.).
- Le smoke staging des vrais comptes demo vit dans `dev-hub/tools/staging-demo-auth-smoke.sh` et reste desactive par defaut. L'activer uniquement avec `demo_auth_smoke=true` ou `STAGING_DEMO_AUTH_SMOKE=true` + secrets `STAGING_*`; `/api/v1/demo-users` doit rester accessible publiquement pour alimenter les frontends demo et l'API Explorer.
- Pour l'E2E staging `front/web`, `BASE_URL` indique une cible distante : la config Playwright ne doit pas demarrer `npm run dev` dans ce cas, et le workflow doit rester aligne avec les navigateurs installes (`--project=chromium` si seul Chromium est installe).
- Le workflow `e2e-staging.yml` distingue l'URL API/admin Render (`DEFAULT_STAGING_URL`) de l'URL vitrine Vercel (`DEFAULT_WEB_STAGING_URL`). Ne pas pointer les tests landing `front/web` vers le backend API.
- Sur la vitrine, garder le gate staging limite a `front/web/e2e/staging-smoke.spec.ts`. Les tests de conversion complets manipulent des formulaires et des CTA plus profonds ; ils sont utiles en local/preview, mais trop fragiles et trop larges pour une cible publique de production.
- Depuis v4.16.100, le workflow vitrine execute aussi `front/web/e2e/marketing-funnel.spec.ts` en preview local production-like via `PLAYWRIGHT_WEB_SERVER_COMMAND="npm run build && npm run start"`. Garder ce test pour signup, demo, newsletter et contrat erreur API ; le staging public reste limite au smoke.
- Depuis v4.16.110, le workflow vitrine preview execute aussi `front/web/e2e/manager-workday-smoke.spec.ts` pour le parcours RH connecte dashboard -> equipe -> pointage -> absences -> logout. Garder ce test mocke cote API pour eviter la dependance aux donnees staging volatiles. Lancer le funnel marketing public dans une commande separee avant les smokes connectes, car le formulaire signup historique est fragile quand il passe apres des tests authentifies.
- Les endpoints vitrine `/api/forms/*` passent par `front/web/src/app/api/forms/_lib/lead-capture.ts`. Ne pas remettre de logique email/CRM dupliquee dans chaque route ; configurer plutot `MARKETING_CRM_WEBHOOK_URL`, `MARKETING_EMAIL_WEBHOOK_URL` et `MARKETING_LEAD_WEBHOOK_TOKEN`.
- Les composants vitrine utilises avec `react-hook-form` doivent forwarder leur `ref` vers le vrai champ HTML. Le bug corrige sur `Input` provoquait des submissions sans valeur reelle malgre des champs visuellement remplis.
- Les pages vitrine conversion `/pricing`, `/demo` et `/integrations` utilisent `useVitrineLocale()` avec contenu FR/EN/TR/AR et `dir=rtl` pour l'arabe. Toute evolution marketing sur ces pages doit ajouter les 4 locales dans le meme changement.
- Le blog vitrine utilise `getBlogPosts(locale)` / `getBlogPost(slug, locale)` et le rail `?lang=` pour les alternates sitemap/hreflang. Toute nouvelle entree blog doit conserver slug stable et champs localises FR/EN/TR/AR.
- Les listes API critiques consommees par mobile/admin (`employees`, `absences`, `attendance`, `me/pay-slips`, `notifications`) doivent garder des filtres/tris allowlistes et couverts par `ApiListQueryContractTest`. Ne pas accepter de `sort_by` libre ou de champ SQL arbitraire.
- Le workflow `Launch Observability Smoke` sonde API/docs/vitrine/admin toutes les 30 minutes et publie `launch-observability-smoke-report`. Les probes retry les timeouts, 5xx et latences transitoires pour absorber les reveils Render ; si le rouge persiste apres toutes les tentatives, suivre `docs/GESTION_PROJET/RUNBOOK_MARKETING_ROLLBACK.md` avant de toucher au code.
- Le Plan 18 demarre la phase experience client : login reel vitrine -> espace manager, features par plan/role, pages de connexion modernes et smoke E2E staging. Toute evolution auth/client doit garder ce plan a jour.
- Sur Next 16 / React 19, les routes dynamiques sous `front/web/src/app/**/[slug]` recoivent `params` sous forme de Promise. Dans les Server Components, `await params`; dans les Client Components, utiliser `use(params)`.
- Sur la vitrine `front/web`, les CTA d'acquisition publics doivent envoyer vers `/signup`; `/auth/login` reste reserve aux utilisateurs qui ont deja un compte. Garder `/blog`, `/guides/rh-startup`, `/demo` et `/pricing` visibles dans le funnel marketing.
- Dans `database-backup.yml`, ne pas installer `awscli` via `apt-get` sur `ubuntu-latest`; le paquet peut disparaitre. Installer `postgresql-client age`, puis utiliser l'AWS CLI preinstallee ou fallback `pip --user`.
- Depuis Plan 18, le login web client `/auth/login` doit afficher les erreurs `POST /api/v1/auth/login` localement. Dans `front/web/src/lib/api-client.ts`, ne pas rediriger automatiquement sur les `401` des endpoints de login, sinon les mauvais identifiants deviennent une session expiree confuse.
- Le parcours client web critique est documente dans `docs/validation/CLIENT_LOGIN_READINESS.md` et couvert par `front/web/e2e/auth-client-smoke.spec.ts` : login manager, mauvais identifiants, session expiree, dashboard non vide et toggle mot de passe.
- `NEXT_PUBLIC_ADMIN_URL` est la cible recommandee pour rediriger un `super_admin` qui arriverait sur le login client ; sans variable, le fallback reste `/dashboard` pour eviter un lien mort en preview.
- Les feature gates du portail client vivent dans `front/web/src/lib/client-features.ts` et sont appliquees dans `front/web/src/app/(dashboard)/layout.tsx`. Ajouter un nouveau module client implique de declarer sa route, ses cles `capabilities`/`features`, ses roles autorises et un test Playwright dans `front/web/e2e/client-feature-gates.spec.ts`.
- Le dashboard client `front/web/src/app/(dashboard)/dashboard/page.tsx` est role-aware : manager charge les KPI tenant, employe evite les endpoints manager et affiche son espace personnel, super-admin est oriente vers la plateforme. Garder ce comportement dans les prochains lots Plan 18.
- Depuis v4.16.119, l observabilite UX client est contractuelle : `trackClientEvent` emet `login_success`, `login_failed`, `dashboard_loaded`, `feature_blocked`, `demo_user_selected` et les tests Playwright les verifient. Ne pas brancher un outil tiers directement dans les composants ; ajouter d abord un endpoint/backend stable ou adapter `front/web/src/lib/client-analytics.ts`.
- Depuis v4.16.120, les evenements UX authentifies se persistent via `POST /api/v1/client-events` dans `client_events`. Garder `login_failed` hors persistance tant que le tenant n est pas fiable, conserver la minimisation PII dans `ClientEventController`, et etendre `ClientEventControllerTest` pour tout nouvel evenement allowliste.
- Le kiosque ZKTeco emet `leopardo:kiosk-status` et affiche la derniere synchronisation. Garder cet etat offline-first lisible lors des evolutions kiosk/bridge, car c est le signal terrain principal pour les clients.
- Depuis v4.16.134, le mobile utilise un socle visuel sombre inspire du mockup pointage v3 via `front/mobile/lib/core/widgets/mobile_surface.dart`. Pour tout nouvel ecran mobile, privilegier `MobileTopBar`, `MobilePanel`, `MobileStatusPill`, `MobileIconBubble` et les couleurs `MobileSurface.*` afin de garder une experience 2026 coherente sans dupliquer des `BoxDecoration`.

## Pieges connus

### 2026-05-14 - Integration branche Devin Plan 14

- La branche distante `devin/1778717175-plan14-phase1-tests` apportait les suites Plan 14 Phase 1 : E2E admin-dashboard, integration API et tests de modeles Flutter. Elle doit etre integree depuis un `origin/main` recent, pas mergee telle quelle si les checks GitHub Actions sont rouges.
- Les vues admin-dashboard ne doivent pas contenir de `catch {}` vide : `Web Lint` bloque avec `no-empty`. Ajouter au minimum un `console.warn(...)` explicite ou un etat d'erreur utilisateur selon le contexte.
- Les tests Feature qui declenchent `AbsenceRequested`, `AbsenceApproved`, `AbsenceRejected`, `PayrollValidated` ou d'autres evenements metier peuvent passer par `WebhookListener`. Le schema de test MVP doit donc creer `webhook_endpoints` et `webhook_deliveries`, sinon PostgreSQL echoue avec `relation "webhook_endpoints" does not exist` avant meme les assertions.
- Les contrats plateforme recents doivent rester dans `api/openapi.yaml`. Le workflow `OpenAPI CI` lance Redocly uniquement quand la spec ou son workflow changent ; corriger la spec plutot que laisser les frontends deviner les shapes `data` / `meta`.
- Depuis v4.16.63, les contrats tracking/flotte sont aussi dans `api/openapi.yaml`. Pour toute evolution de `routes/modules/tracking.php`, garder la spec alignee sur les vrais champs Eloquent (`plate_number`, `traccar_*`, `assigned_driver_id`) et non sur les anciens noms generiques (`registration_number`, `tracker_id`).
- Les predictions IA doivent rester defensives face aux donnees RH incompletes : `department_id` peut etre nul dans les groupements Eloquent, et les soldes conges historiques peuvent exposer `remaining`, `remaining_days` ou `balance` selon la migration/fixture. Utiliser des allowlists de colonnes et des `whereNull` explicites plutot que caster une cle vide.

### Audit 2026-05-13 - IA, RBAC et tenant runtime

- Les routes IA doivent importer `App\AI\Orchestrator`. Ne pas recreer `App\AI\AIOrchestrator` : cette classe n'existe pas et provoque un boot fatal sur les routes IA.
- Les analytics IA (`/api/v1/ai/analytics/*`) sont reservees aux managers `principal` et `rh`. Ne pas les remettre derriere le seul `AIFeatureCheck`, sinon un manager departement/superviseur peut lire des couts LLM.
- `AdminMiddleware` ne doit pas traiter tout `role=manager` comme admin. Le sous-role attendu est `manager_role=principal`, sauf vrais roles globaux `admin` / `super_admin`.
- `TenantMiddleware` doit conserver son `try/finally` autour de `TenantManager::resetToPrevious()`. L'hypothese operationnelle actuelle reste une requete active par worker PHP-FPM ; si des workers persistants/interleavings sont introduits, evaluer `SET LOCAL search_path` ou une gestion strictement connexion/transaction plutot que l'etat d'instance.
- Front mobile : la stack reelle est Flutter 3.x + `flutter_riverpod` 3.3. Ne pas documenter Bloc comme architecture active.
- PHPStan reste en diff-gate avec baseline historique. Ne jamais elargir `api/phpstan-baseline.neon`; reduire par campagne module par module (AI, middleware, routes, payroll, attendance) et garder le scope visible dans les artefacts CI.

### Audit 2026-05-13 - Policies explicites et isolation FK

- Les policies Laravel sont enregistrees explicitement dans `AppServiceProvider`. Si une nouvelle policy est ajoutee, l'ajouter au boot provider ou a un `Gate::define` dedie dans le meme PR.
- Les modeles sans `company_id` direct (`WebhookDelivery`, `PaySlipLine`, `ApprovalDecision`, `ExpenseItem`) doivent rester isoles via leur relation parent (`endpoint`, `paySlip`, `request`, `claim`). Toute requete metier sur ces modeles doit filtrer avec `whereHas(...)` ou charger depuis le parent deja scope.
- La suite `FkChainTenantIsolationTest` couvre ce contrat ; l'etendre si un nouveau modele sans `company_id` est introduit.

### 2026-05-13 - Plan 13 et couverture Feature billing

- Avant d'ajouter un test liste comme manquant dans `docs/PLAN_ACTION/13_RESTANT_POST_SPRINTS.md`, verifier d'abord `api/tests/Feature/` : plusieurs suites post-sprints existent deja meme si le plan historique les affichait encore en non cochees.
- `BillingControllerTest` couvre maintenant abonnement, upgrade/cancel/renew, RBAC employe et isolation tenant liste/detail/PDF facture ; etendre cette suite plutot que creer un doublon.
- `PaymentWebhookControllerTest` couvre les webhooks Stripe/Chargily valides et invalides. Les payloads inconnus doivent rester idempotents cote donnees : acquittement HTTP OK, mais aucune creation de paiement ni mutation facture/abonnement.
- `PUT /api/v1/feature-flags/matrix` ne doit pas etre ouvert aux utilisateurs tenant. Les ecritures matrice feature flags passent par les contrats plateforme super-admin ; garder `FeatureFlagControllerTest` comme garde anti-regression.
- `StructuredLoggingMiddlewareTest` verifie que les requetes API non-health ecrivent sur le channel JSON `structured`, tandis que `/api/v1/health/*` reste exclu pour eviter le bruit des sondes.
- `OnboardingStepControllerTest` couvre `/api/v1/onboarding-setup/*` : auto-seed checklist, progression, complete/skip et isolation tenant. Ne pas confondre avec `/api/v1/onboarding/checklist`, qui mesure le go-live client.

### Frontieres routes modules

- `routes/modules/rh.php` porte le socle RH transverse (employes, contrats, absences, rapports courants) alors que `routes/modules/hr_extended.php` porte les extensions post-MVP. Avant de deplacer une route, verifier le controller et le scenario de test associe.
- Les routes IA experimentales voice/agent restent sous feature AI + rate limit ; toute exposition plus large doit passer par une feature flag explicite et une couverture RBAC.
- Dans les extensions RH (`RecruitmentController`, `TrainingController`, `EmployeeLoanController`, `ExpenseClaimController`), les index doivent toujours demarrer par `where('company_id', $actor->company_id)` et les references employees/departments/positions/trainers/interviewers doivent etre validees dans le tenant courant.

### Paie multi-pays et exports bancaires

- Les tables `tax_slabs` et `social_contributions` sont creees par les migrations tenant. Le seeder `PayrollCountryConfigSeeder` doit etre lance dans le schema tenant courant, pas depuis un contexte public qui n'a pas ces tables.
- Les exports bancaires doivent utiliser les colonnes reelles de `employees` : `iban` et `bank_account`. Ne pas reintroduire `rib` ou `bank_name` sans migration correspondante.
- Les declarations sociales CNAS/CNSS/DSN doivent lire les salaries via le modele `Employee`, pas via `DB::table('employees')`, afin de respecter les casts `encrypted` (`national_id`). Les identifiants entreprise viennent de `companies.metadata` (`nis`, `affiliate_number`, `siret`, `tax_id`) ; ne pas reintroduire `companies.tax_id` ni `employees.hire_date`.
- Pour les barèmes fiscaux de paie, les tranches documentees sont inclusives (`0-5000`, `5001-20000`). Utiliser le helper progressif de `AbstractCountryRules` pour eviter les erreurs d'unite aux bornes.
- Pour tester `PayrollRunController` sans rendre la suite fragile face aux baremes/salary structures, binder un faux `PayrollCalculator` dans le container et verifier le contrat controller : run calcule, pay slip cree, validation/cancel et isolation tenant.

### Render et migrations PostgreSQL

Render peut rejouer des migrations dans un environnement ou certaines tables existent deja. Les migrations publiques doivent donc etre idempotentes.

Exemples resolus le 2026-05-06 :

- `2026_05_02_000003_create_company_requests_table.php` doit verifier `Schema::hasTable('company_requests')` avant `Schema::create`.
- `2026_05_02_100001_create_users_and_company_requests_tables.php` doit verifier l'existence de `users`, `company_requests` et `user_employee_links`.
- Si une migration touche une table tenant comme `employees`, verifier le `search_path` PostgreSQL et proteger avec `Schema::hasTable`.

### Vercel

Le statut externe `Vercel` peut echouer immediatement vers une page de configuration projet. Lors du PR #268 et du hotfix #299, tous les GitHub Actions etaient verts et le merge restait possible malgre ce statut externe. Ne pas perdre du temps a corriger le code si Vercel echoue sans logs de build applicatif.

Le workflow GitHub `Build & Deploiement` a aussi porte une integration `vercel/action@v4` introuvable cote Actions. Si ce workflow redevient rouge pour `Unable to resolve action vercel/action`, conserver seulement le job de build jusqu'a ce qu'une integration Vercel valide soit configuree.

Dans `web/vercel.json`, ne declarer un bloc `functions` que si le pattern correspond vraiment aux fonctions Vercel generees par le projet. Le pattern historique `api/**` casse les deploys du frontend Next.js avec `The pattern "api/**" defined in functions doesn't match any Serverless Functions`, car les route handlers reels vivent sous `web/src/app/api/**`.

Pour le frontend `web/`, ne pas declarer dans `vercel.json` un bloc `env` avec des objets de description. Vercel attend des chaines de caracteres si `env` est present. Si les variables sont deja gerees dans le dashboard Vercel, supprimer completement ce bloc du fichier pour eviter l'erreur `env.<VAR> should be string`.

### Main local divergent

Le poste local peut avoir un `main` divergent (`ahead`/`behind`). Dans ce cas :

- Ne pas tenter de fast-forward aveugle.
- Travailler depuis `origin/main` via une branche propre.
- Une fois les travaux merges, remettre le local en phase avec `origin/main` seulement apres avoir confirme qu'aucun changement local utile ne sera perdu.

## Procedure PR et merge

1. Creer une branche courte depuis `origin/main`.
2. Faire le changement minimal.
3. Ajouter `CHANGELOG.md` et `AGENTS.md` si une connaissance doit etre conservee.
4. Push la branche et creer un PR.
5. Observer avec `gh pr checks <numero>`.
6. Corriger uniquement les rouges.
7. Quand les GitHub Actions requis sont verts, merger avec `gh pr merge <numero> --merge --delete-branch`.
8. Verifier que le PR est `MERGED` avec `gh pr view <numero> --json state,mergedAt,mergeCommit`.
9. Verifier que la branche distante est supprimee avec `git ls-remote --heads origin <branche>`.

## Nettoyage branches

Objectif demande le 2026-05-06 : en local, ne garder que `main` aligne sur `origin/main`.

Procedure recommandee :

- Verifier `git status --short --branch`.
- Verifier les stashes avec `git stash list`.
- Supprimer les branches locales non `main` apres merge ou abandon explicite.
- Pour les branches distantes, commencer par les PR ouverts. Merger uniquement si les changements apportent une nouveaute utile a `main`, puis supprimer la branche.
- Ne pas supprimer une branche distante non analysee si elle contient du travail non merge ou non remplace.

## Federation de branches

- Pour les vieilles branches mobiles ou mixtes tres en retard sur `main`, ne pas merger la branche complete si le diff embarque des centaines de suppressions hors sujet.
- Preferer recuperer uniquement les fichiers utiles avec `git checkout <branche> -- <fichier>` dans une branche federatrice propre creee depuis `origin/main`.
- Cette approche a ete confirmee utile le 2026-05-06 pour reutiliser seulement les apports de `#269`, `#275` et `#298` sans reintroduire le bruit historique de branches anciennes.

## Historique utile

### 2026-05-24 - Vitrine proxy API, pricing et plan Jules i18n

- Pour `front/web`, le login navigateur doit passer par le proxy Next same-origin `/api/v1/[...path]` en production. Ne pas forcer `NEXT_PUBLIC_API_URL` en direct cote browser sauf avec `NEXT_PUBLIC_API_DIRECT=true`, sinon les erreurs CORS Render/Vercel reviennent.
- Le pricing vitrine n'est plus un simple tarif par employe tres bas : garder une structure SaaS credible avec forfait mensuel, employes inclus, surcout par employe, essai 30 jours et Enterprise sur devis.
- Les traductions gerees par Jules doivent rester dans les fichiers dedies de `shared/i18n`, `api/lang`, `front/admin-dashboard/src/i18n/locales`, `front/mobile/lib/l10n` et `front/mobile_apps/leopardo_core/lib/l10n`; ne pas lui faire modifier les composants metier pour de la traduction simple.
- Le plan multilingue Jules canonique vit dans `docs/PLAN_ACTION/24_PLAN_MULTILINGUE_JULES_TRANSLATION.md` avec les prompts anglais, arabe et turc.
- La navigation vitrine garde les docs sous Ressources, le blog sous un libelle plus vendeur type Insights RH, et les liens desktop/mobile sous "Installer Leopardo".

### 2026-05-22 - Plan 21 readiness fonctionnelle profils

- `/api/v1/demo-users` est le contrat canonique pour les personas de demonstration. Le garder aligne avec `DemoCompanySeeder` quand un nouveau profil, pays ou surface est ajoute.
- `DemoCompanySeeder` seed maintenant aussi preferences de notification, communication events, client events, device tokens, kiosks et demandes biometrie de facon defensive. Toute nouvelle table demo optionnelle doit etre inseree via une detection `Schema::hasTable` / colonnes existantes pour rester compatible avec les environnements partiellement migres.
- Les tests `DemoUserControllerTest` et `ProfileFunctionalReadinessTest` couvrent le minimum attendu avant demo commerciale : principal/RH accedent aux analytics/readiness, les autres roles restent bloques, les pages web sensibles respectent les sous-roles.
- Pour un nouveau parcours profil, ne pas se contenter d'ajouter un email au seeder : ajouter aussi la surface cible, la route conseillee, les donnees de readiness et une assertion de role.

### 2026-05-21 - Auth readiness marche : client, mobile, plateforme

- Le bouton demo de `front/admin-dashboard/` doit rester limite aux comptes super-admin plateforme : ce frontend appelle `/api/v1/platform/auth/login`, donc les comptes RH/employes tenant doivent rester sur `front/web` ou mobile.
- Pour prouver un login pret marche, tester le contrat complet `login -> token -> /auth/me` et `platform/login -> token -> /platform/auth/me`, pas seulement la presence d'un token dans la reponse.
- Sur `front/web`, `npm audit fix --force` peut proposer des regressions majeures incoherentes (ex. downgrade Next). Preferer un patch mineur cible, relancer lint/build, puis documenter les advisories restantes si elles dependent d'un upstream non corrige.
- Ne pas utiliser `useSyncExternalStore` avec un getter qui parse `localStorage` et retourne un nouvel objet a chaque snapshot : React peut boucler en erreur #185. Hydrater l'utilisateur stocke via `useState` + `useEffect`, puis tester le parcours login -> dashboard avec Playwright.

### 2026-05-18 - Nettoyage depot distant Devin/GTM/mobile

- Nettoyage realise via PRs vertes une par une : #491 vitrine, #488 integrations API, #495 GTM/vitrine, #489 mobile. Apres chaque merge dans `main`, refaire `git fetch origin main --prune` puis verifier si les PR restantes passent en `BEHIND`; si oui, merger `origin/main` dans la branche restante, pousser, et attendre les nouveaux checks.
- `gh pr merge --merge --delete-branch` peut supprimer correctement la branche distante tout en laissant des refs locales `origin/devin/*` visibles. Verifier la verite distante avec `git ls-remote --heads origin`, puis nettoyer les refs locales via `git remote prune origin`.
- Les jobs mobile `Build Debug APK` et `Mobile Flutter (Stable Channel)` peuvent rester plusieurs minutes en `pending/in_progress` apres analyse/tests verts. Ne pas merger tant que ces jobs ne sont pas explicitement `pass`, meme si `gh pr checks` retourne parfois un code de sortie 0.
- Sur Windows local, PHP/Flutter peuvent etre absents ou non representatifs. Pour ces branches, GitHub Actions a servi de source de verite ; localement, seuls les builds Next.js cibles ont ete lances quand le rouge etait frontend.
- Avant de nettoyer les branches locales, conserver les stashes et les branches non mergees (`git stash list`, `git branch --no-merged main`). Supprimer seulement les branches dont `git branch --merged main` confirme l'integration.

### 2026-05-16 - Plan 15 : parallel merge #468 et iteration 6

- **Iteration 5** monitoring : code backend deja dans `main` (`CHANGELOG` [4.16.55]) ; reste **ops** sondes externes (UptimeRobot / Better Stack) + runbook `docs/GESTION_PROJET/RUNBOOK_OBSERVABILITY.md`.
- **Iteration 4** perf/paie : PR **#468** en attente de merge ; preparation **iteration 6** admin-dashboard (`front/admin-dashboard/`, routes `/payroll`, `/leaves`) peut progresser sur une branche separee depuis `origin/main`, puis reconciliation `main` apres merge.
### 2026-05-17 - Consolidation PR #487 SSO / IA workflows

- Les routes publiques SSO doivent accepter les UUID `companies.id`; ne pas remettre `whereNumber('companyId')` sur `/api/v1/sso/saml/{companyId}/callback` ni `/oidc/{companyId}/callback`.
- Pour `company_sso_configs`, eviter `created_at => DB::raw('COALESCE(created_at, NOW())')` dans `updateOrInsert` : PostgreSQL ne permet pas de referencer la colonne cible dans `VALUES`. Faire update puis insert explicites.
- Les workflows IA doivent rester compatibles avec les schemas historiques et fixtures MVP : verifier `Schema::hasColumn('employees', 'salary_structure_id')` avant de filtrer dessus, et grouper les absences via `absence_type_id` / `absence_types`, jamais via une colonne fantome `absences.type`.
- Dans les tests PostgreSQL partages, ne pas construire de `search_path` `company_{uuid}` non quote/sanitise ; la factory `Company` est shared par defaut et `shared_tenants,public` suffit.
- Les modules inclus depuis `routes/api.php` sont deja sous `Route::prefix('v1')`; les fichiers `routes/modules/*.php` ne doivent pas repeter `prefix('v1/...')`, sinon les endpoints deviennent `/api/v1/v1/...`.
- La fixture `CreatesMvpSchema` doit refleter le modele `Contract` moderne (`contract_type`, `base_salary`, `department_id`, `probation_end_date`, `contract_amendments`) pour eviter les faux rouges sur contrats, rapports RH, planning et predictions.
### 2026-05-16 - Plan 15 iteration 4 (performance / paie async)

- Iteration 4 cloture fonctionnelle : cache tenant `GET /api/v1/reports/headcount` (`HR_REPORT_HEADCOUNT_CACHE_TTL`), job `WarmPaySlipPdfPathsForPayrollRunJob` apres validation paie (`PAYROLL_QUEUE_PDF_WARMUP`), PDF bulletins via `pdf_path` sur disque `local`.
- **D4 JWT refresh** hors scope pour l’auth Sanctum metier ; JWT dans le depot = flux camera (`CameraStreamTokenService`, TTL `config/cameras.php`).
- **D5 chiffrement** : casts Laravel `encrypted` deja sur Employee (`iban`, `bank_account`, `national_id`) ; extension = chantier inventaire dedie.

### 2026-05-08 - Render race sur `company_requests`

- Le hotfix idempotent base uniquement sur `Schema::hasTable()` ne suffit pas sur Render quand plusieurs processus de migration courent en parallele.
- Symptome observe sur le deploy du commit `fed92d684274e9bbf52b6b4d81785b8e851ac221` : `2026_05_02_000003_create_company_requests_table.php` echoue avec `SQLSTATE[42P07] Duplicate table` alors que la table vient juste d'etre creee par un autre processus.
- Pour une migration publique sensible, entourer `Schema::create(...)` d'un `try/catch QueryException` et traiter `42P07` comme un no-op de course concurrente, sans relancer de requete SQL dans le `catch`.
- Appliquer la meme protection aux autres tables publiques exposees a la course (`users`, `company_requests`, `user_employee_links`) afin que le rattrapage Render et les retries du point d'entree restent vraiment idempotents.

### 2026-05-08 - Admin plateforme + vitrine multilingue

- Le dashboard plateforme ne doit plus inventer ses routes d'auth. Le backend expose `/api/v1/platform/auth/login`, `/me`, `/logout`; il n'existe pas de refresh token `/admin/auth/refresh`.
- `PlatformAuthController` retourne maintenant aussi `role=super_admin` et `two_fa_enabled` pour eviter les hypothese cote frontend.
- Si un compte super-admin a le 2FA active, l'API renvoie `202 TWO_FA_REQUIRED`; le frontend doit traiter ce cas comme une etape de login et non comme un succes silencieux.
- Le login admin supporte maintenant un toggle afficher / masquer le mot de passe. Si cette zone evolue encore, garder les labels ARIA synchronises avec l'etat visible/cache et couvrir la regression dans Playwright.
- La vitrine publique `web/` a maintenant un vrai rail de locale client (`FR/EN/TR/AR`) sur la landing page. Pour les prochaines evolutions, reutiliser ce socle au lieu de rehardcoder des textes dans chaque composant.
- Les pages legales de la vitrine vivent dans `front/web/src/app/privacy` et `front/web/src/app/terms`. Garder les liens footer reels vers ces routes et reutiliser `useVitrineLocale()` pour FR/EN/TR/AR + RTL au lieu de creer une logique locale separee.
- Desormais, quand `web/**` change, les checks de lint/build doivent partir via `web-marketing-ci.yml`; ne pas se reposer uniquement sur le workflow admin.

### 2026-05-14 - Registre traitements RH

- Le registre interne des traitements vit dans `docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md`. Le maintenir a jour a chaque nouveau module collectant une nouvelle categorie de donnees, integration externe, traitement IA ou usage biometrique.
- Les points privacy publics (`/privacy`, `/terms`) et API (`/api/v1/privacy/export`, `/deletion-request`, `/biometric-consent`) doivent rester coherents avec ce registre.

### 2026-05-14 - Rate limiting endpoints sensibles

- Les surfaces sensibles utilisent des limiters nommes dans `AppServiceProvider` et configures via `api/config/security.php` : `auth-sensitive`, `privacy-sensitive`, `payroll-sensitive`, `platform-sensitive`, `ai-sensitive`.
- Pour les prochains endpoints auth, paie, privacy, IA ou super-admin, reutiliser ces limiters au lieu d'ajouter des `throttle:10,1` isoles.

### 2026-05-15 - Versioning API et quotas par plan

- Le middleware `ApiVersionMiddleware` est dans le groupe API global et ajoute `X-API-Version` / `X-API-Supported-Versions`; si un frontend ou integrateur force `X-API-Version: v2` avant ouverture officielle de v2, l'API doit continuer a retourner `400 UNSUPPORTED_API_VERSION`.
- Le limiter `api-plan` doit rester applique apres `auth:sanctum` + `tenant` sur les routes tenant authentifiees. Avant cet ordre, le plan commercial et le contexte tenant ne sont pas fiables.
- Les quotas par plan vivent dans `api/config/security.php` sous `plan_rate_limits`; garder `enterprise_per_minute=0` pour illimite et abaisser les seuils uniquement via config dans les tests.

### 2026-05-14 - Load testing k6

- Le socle de charge vit dans `dev-hub/load/k6/api-core-smoke.js` et reste read-only par defaut. Ne pas ajouter de mutations de pointage, paie ou exports dans ce script sans flag explicite.
- Les benchmarks Plan 14 doivent etre consignes avec p50/p95, taux d'erreur et endpoints lents avant d'annoncer un SLA.

### 2026-05-08 - Render et transaction PostgreSQL abort

- Sur PostgreSQL, une migration Laravel executee dans la transaction du migrateur ne doit pas lancer de requete de verification apres une erreur SQL, sinon on tombe sur `SQLSTATE[25P02] current transaction is aborted`.
- Concretement, apres un `42P07 Duplicate table`, ne pas appeler `Schema::hasTable(...)` dans le `catch`. Il faut considerer le code SQLSTATE et sortir directement, sinon le correctif de course reintroduit un echec.
- Si une migration publique peut enchainer plusieurs `Schema::hasTable(...)` / `Schema::create(...)` sur Render, desactiver aussi la transaction du migrateur avec `public bool $withinTransaction = false;`, sinon une premiere course gagnée par un autre processus empoisonne tout le reste de la migration.

### 2026-05-12 - Tests modules post-sprints

- Les tests qui utilisent `Tests\Support\CreatesMvpSchema` ne voient que le schema fixture, pas automatiquement toutes les migrations post-sprints. Si un test couvre billing, paie, recrutement, formation, prets, frais ou vehicules, verifier que le fixture cree aussi la table minimale correspondante.
- Attention aux tables historiques homonymes dans `public` et `shared_tenants` (`invoices`, notamment) : en PostgreSQL, `Schema::hasTable()` peut donner un faux positif si le `search_path` inclut `public`. Pour un fixture ou une migration tenant, preferer une table qualifiee ou un rattrapage idempotent.
- L'ancien `audit_logs` tenant utilise `employee_id`, `target_type`, `target_id`, `changes`, `ip`; le code actuel ecrit `user_id`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `ip_address`, `user_agent`. Toute migration de compatibilite doit ajouter le contrat moderne sans relancer de SQL apres erreur PostgreSQL.
- Pour tester les endpoints flotte, injecter un faux `TraccarService` dans le container plutot que de laisser les tests appeler Traccar/HTTP. Le contrat utile est `vehicle_id` + `position`, pas la disponibilite du serveur Traccar externe.
- Les tests calendrier doivent garder `CreatesMvpSchema` et `tests/Support/sql/mvp_schema.pgsql.sql` alignes sur la migration tenant `2026_05_18_000002_create_calendar_sync_table.php` : `calendar_connections` porte `employee_id/provider/access_token/...`, et `calendar_events` porte `employee_id/provider/starts_at/ends_at/sync_status` (pas l'ancien couple `connection_id/start_at/end_at`).
- Les routes `routes/modules/tracking.php` doivent rester dans un groupe `auth:sanctum` + `tenant`. Sans ce garde, un appel anonyme peut atteindre `FleetController` avec `$request->user() === null` et produire un 500.
- Les tests de bulletins de paie ont besoin de `pay_slip_lines` dans `CreatesMvpSchema`; `PaySlipController` charge toujours la relation `lines`, donc un fixture sans cette table casse les endpoints meme si le test ne cree pas explicitement de lignes.
- Pour les conges avances, ne pas se fier uniquement au middleware `tenant` : les listes `leave_policies`, `leave_balances` et `leave_accruals` doivent filtrer explicitement par `company_id`, et les creations d'accrual doivent verifier que l'employe et la policy appartiennent au tenant courant.
- Sur Windows local, PHPStan peut etre non representatif si `phpstan.ci.neon` genere par CI reference Larastan absent/incomplet dans `vendor`. Dans ce cas, verifier au minimum `php -l` et laisser GitHub Actions Linux servir de source de verite.
- Les commandes Artisan qui lisent `$this->argument()` / `$this->option()` doivent normaliser les valeurs avant de les passer aux services (`string|null` attendu), sinon PHPStan voit `array|bool|string|null` et la dette revient vite.
- Si le job backend principal echoue sur `composer validate` avec `github oauth token contains invalid characters`, verifier que le setup PHP force bien `tools: composer:v2`, comme les jobs backend-quality et coverage.
- Pour les commandes console de detection/registre de features, preferer des helpers locaux `stringValue`, `stringList`, `optionString` et `optionBool` plutot que caster inline des tableaux `mixed`; cela garde PHPStan exploitable et les sorties Artisan previsibles.
- Les notifications API ne sont pas les `DatabaseNotification` Laravel natives : le modele interne doit exposer `markAsRead()` et `Employee` doit declarer explicitement `notifications()` / `unreadNotifications()`.
- Les analytics IA doivent rester alignees sur le schema reel `ai_audit_logs` : `input_tokens`, `output_tokens`, `cost_cents`, `duration_ms`, `error`, `tools_called`. Ne pas reintroduire les colonnes fantomes `total_tokens`, `cost`, `tool_called`, `response_time_ms`, `status`, `error_message`.

### 2026-05-07 - I18N enterprise partage

- Ne pas repartir d'abord d'un framework i18n web ou mobile. Pour Leopardo RH, la vraie source de verite doit vivre dans shared/i18n/locales/*.json, puis etre synchronisee vers backend, web et mobile.
- Les variantes de locale (fr-CA, fr-BE, ar-SA, en-GB) doivent etre normalisees vers une langue canonique tant que le contenu reste mutualise. Cela donne tout de suite une meilleure compatibilite sans dupliquer les catalogues.
- Pour le mobile Flutter, garder les ARB comme artefacts generes tant que l'UI depend de gen-l10n; ne pas essayer d'imposer un JSON runtime partout d'un coup.
- Pour le backend Laravel, migrer progressivement les domaines communs vers des fichiers generes (shared.php, emails.enterprise.php) au lieu de casser tout lang/ en big bang.
- Le cache mobile de traductions distantes doit rester non bloquant: en cas d'echec reseau, toujours revenir au catalogue embarque ou au dernier cache valide.
- La validation i18n doit verifier au minimum: cles manquantes, placeholders, mojibake/RTL, longueurs critiques et checksum de catalogue.

### 2026-05-07 - Mobile i18n

- Avant d'estimer un chantier i18n mobile, verifier l'etat reel sur `origin/main` : `flutter_localizations`, `intl`, locale et RTL peuvent etre branches sans que `gen-l10n`, `l10n.yaml`, les `ARB` et `context.l10n` existent deja.
- Ne pas migrer 500+ cles d'un coup. La sequence la plus sure est : fondation `gen-l10n`, un ecran prioritaire, CI mobile, puis extension par lots verticaux.
- Pour l'arabe, tester explicitement les petits viewports : les textes traduits peuvent casser les `Column` avec `Spacer`; preferer des zones scrollables bornees ou des layouts qui degradent proprement.
- Les plans i18n doivent distinguer les cles reellement utilisees dans le code des cles "catalogue" prevues plus tard, sinon la progression annoncee devient trompeuse.

### 2026-05-06 - PR #268 Feature/vitrine restructure

- Le PR #268 a ete merge dans `main` avec le commit `08d4316a2b9baaf2e95b2d40ffa8dd69bdc40af5`.
- Approche gagnante : boucle rapide GitHub Actions, pas de tatonnement local.
- Corrections majeures : TypeScript vitrine, exports ambigus, Zod v4, Playwright hors Jest, migrations PostgreSQL, mobile pubspec, gates CI instables.

### 2026-05-06 - PR #299 Hotfix Render company_requests

- Render echouait avec `SQLSTATE[42P07]: Duplicate table: relation "company_requests" already exists`.
- Hotfix merge dans `main` avec le commit `53f1d20892353e7012612822ff43eb0709e56202`.
- Le correctif rend la migration `company_requests` idempotente.

### 2026-05-07 - CI/CD incremental

- Les anciens workflows web pointaient encore vers `web/**` alors que le frontend reel du depot est `admin-dashboard/`.
- La bonne simplification n'est pas de fusionner des YAML a l'aveugle, mais de realigner d'abord la CI sur l'arborescence reelle puis d'ajouter un smoke E2E Playwright dedie.
- La coverage backend doit etre publiee en artifact et resume CI avant de devenir une gate stricte ; un seuil configurable via variable GitHub est preferable a une valeur codee en dur trop ambitieuse.
- Attention au schema public `company_requests` : la migration historique `2026_05_02_000003_*` cree l'ancienne forme basee sur `employee_id`, alors que les controllers et le modele `User` attendent la forme moderne basee sur `user_id`. La migration `2026_05_02_100001_*` doit donc aussi mettre a niveau une table existante, pas seulement la creer.
- Dans `tests.yml`, un `continue-on-error` sur Unit/Feature ou coverage peut masquer un vrai rouge applicatif si aucun step final ne re-propage l'echec. Toujours ajouter un step final explicite qui fait echouer le job quand la suite de tests a casse.
- Quand des assertions FR cassent avec `EmployÃƒÂ©` ou `RÃƒÂ©cupÃƒÂ¨re`, verifier tout de suite un probleme d'encodage UTF-8/mojibake dans les tests ou les messages de validation avant de soupconner la logique metier.

### 2026-05-07 - Cap 10 clients payants

- Le produit a maintenant ses 10 premiers clients payants.
- Priorite produit immediate : prouver la valeur mesurable du pointage et du controle terrain avant d'ajouter des modules RH generiques.
- Premier chantier lance : `GET /api/v1/attendance/anomalies` pour exposer aux managers les retards, sorties manquantes, corrections manuelles, heures supplementaires elevees et pointages rapproches sur un meme appareil.
- Meme lot backend : `GET /api/v1/attendance/monthly-report` fournit le rapport mensuel en JSON/CSV/PDF ; `GET /api/v1/onboarding/checklist` donne la progression d'installation client ; `GET/PATCH /api/v1/platform/companies/{company}/features` rend les feature flags exploitables par API super-admin.
- Les anomalies avancees utilisent `company.metadata.attendance_geofence` avec `{lat,lng,radius_meters}` pour detecter les pointages hors zone, et signalent aussi les pointages a heure trop repetitive.
- Pour les prochaines PR, privilegier les features qui donnent un ROI client visible : reduction fraude/erreurs, temps admin economise, exports comptables, alertes manager simples.

### 2026-05-08 - Valeur terrain attendance

- Les endpoints attendance doivent parler en actions manager, pas seulement en donnees brutes : `attendance/anomalies` expose `business_impact`, `requires_manager_action` et `recommended_action` pour prioriser les corrections avant paie.
- Le rapport mensuel est le support de vente le plus concret : conserver les champs d'estimation paie (`estimated_gross_payroll`, `estimated_overtime_pay`, montants par employe) et les baser sur `hourly_rate` ou, a defaut, sur `salary_base / 173.33`.
- La checklist onboarding doit mesurer le go-live client : equipe active, paie renseignee, geofence, biometrie/kiosque. Eviter d'ajouter une etape si elle n'aide pas un client a pointer et preparer sa paie plus vite.

### 2026-05-08 - Plateforme health client

- Pour aller vers v5.0 commercial, chaque nouvelle brique plateforme doit aider a piloter adoption, retention ou upsell. `GET /api/v1/platform/companies/{company}/health` est le contrat de reference pour lire plan/MRR, usage pointage 30 jours, onboarding, anomalies et next actions.
- Le score health doit rester explicable et conservateur : ne pas le remplacer par une logique opaque tant qu'on n'a pas de donnees churn reelles.
- Les dashboards super-admin doivent consommer ce contrat avant d'ajouter un billing complet ; il donne deja les signaux minimaux pour relancer un client, aider l'onboarding ou proposer un module Business.
- La vue portefeuille `GET /api/v1/platform/companies/health` doit rester compacte : resume MRR/risques en haut, puis une action prioritaire par client. Eviter d'en faire un export lourd ; le detail appartient au health d'une seule company.
- Le contrat abonnement `GET/PATCH /api/v1/platform/companies/{company}/subscription` est volontairement fournisseur-agnostique : plan/statut/dates/notes seulement. Brancher Stripe/PayPal/local PSP plus tard derriere ce contrat, pas dans le premier lot.
- `GET /api/v1/platform/plans` fournit le catalogue a utiliser par l'admin-dashboard pour les formulaires d'abonnement ; ne pas hardcoder les `plan_id` cote frontend.
- Le cockpit `admin-dashboard` doit consommer les contrats plateforme reels (`/platform/companies/health`, `/platform/companies/{id}/health`, `/subscription`, `/plans`) avant toute nouvelle statistique mockee.
- `GET /api/v1/platform/metrics/overview` est le contrat d'agregats pour le cockpit super-admin : MRR/ARR, encaissements, impayes, companies, subscriptions, billing et systeme. Il doit rester sous `super_admin_api`, non nominatif, et tolerant aux tables billing absentes pendant les migrations progressives.
- Le dashboard admin doit consommer `/platform/metrics/overview` pour les chiffres financiers globaux. Ne pas recalculer ARR, impayes ou encaissements cote frontend a partir de listes partielles.
- Quand un contrat plateforme est consomme par le dashboard ou la future IA, l'ajouter aussi dans `api/openapi.yaml` avec schemas `data.*` explicites afin d'eviter les integrations a l'aveugle.
- La page Support admin sert maintenant d'intake demandes clients via `/platform/company-requests`; ne pas y remettre de tickets mockes tant qu'un vrai module support n'a pas son API dediee.
- Le dashboard d'accueil admin doit rester une synthese des contrats plateforme existants. Eviter les endpoints `/admin/dashboard/*` tant qu'ils n'existent pas cote API.
- Approuver une `company_request` doit declencher le provisioning partage via `CompanyProvisioningService` et remplir `approved_company_id`; ne pas se limiter a changer le statut.

### 2026-05-07 - Dossier Go-To-Market racine

- Le dossier racine de strategie commerciale s'appelle `docs/GOTO_MARKET/`, pas `marketing/`, sur demande explicite.
- Le PDF inspirant `Leopardo_RH_Production_Creative.pdf` doit etre conserve dans `docs/GOTO_MARKET/00_inspiration/` et sert de base creative IA-first.
- Le fichier `docs/PLAN_ACTION/14_ROADMAP_EXECUTION_POST_LOTS.md` sert maintenant de roadmap actualisee apres execution des lots plateforme metrics/backend/admin. Le fichier 13 reste l'inventaire brut ; le 14 doit porter la sequence priorisee et les retours d'experience.
- Les prochaines actions GTM doivent rester connectees au wedge produit prioritaire : pointage, anomalies, rapport mensuel, onboarding et ROI client mesurable.
- `docs/GOTO_MARKET/` est aussi le centre de reflexion sur la viabilite globale : utiliser la tech pour repondre a un besoin actuel, gagner de l'argent, et ne pas hesiter a repositionner ou moderniser le produit/offre quand le marche l'exige.
- Les fichiers destines a presenter Leopardo RH au public doivent aller dans `docs/GOTO_MARKET/public/` avec un sous-dossier par canal : `social/`, `landing/`, `video/`, `press/`, `partners/`, `ads/`, `content_calendar/`, `metrics/`.
- Le pack de lancement acquisition vit dans `docs/GOTO_MARKET/12_PACK_LANCEMENT_ACQUISITION.md` et les supports associes (`public/email`, `public/lead_magnets`, `public/owned_channels`, etc.).
- La vision "Leopardo RH peut aussi aider une entreprise a gerer et automatiser son marketing" est documentee dans `docs/GOTO_MARKET/product_marketing_automation/`, mais elle n'est pas implementee dans le depot et ne doit pas distraire du wedge pointage tant qu'il n'est pas solidement vendu.

### 2026-05-07 - Federation de PR ouvertes

- Quand plusieurs PR ouvertes sont propres mais en retard sur `main`, il est souvent plus rapide de creer une branche federatrice depuis `origin/main`, puis de `cherry-pick` uniquement les commits utiles des PR au lieu de tenter des merges historiques un par un.
- Les PR purement documentaires ou de synchronisation de version (`docs/AUDITS/*`, simple bump `PILOTAGE.md` / `api/config/app.php`) ne doivent pas bloquer un lot fonctionnel plus utile ; elles peuvent etre fermees comme supersedees apres integration du vrai contenu produit.
- Pour les lots mixtes backend/mobile, les conflits les plus probables sont `CHANGELOG.md`, `PILOTAGE.md` et `api/config/app.php`. Les absorber une seule fois en fin de federation fait gagner beaucoup de temps.
- Avant de conserver un fichier cache bot ou journal interne (`.jules/*`), verifier qu'il apporte une connaissance de projet exploitable par les humains ; sinon le retirer du lot merge.

### 2026-05-07 - Gouvernance de scenarios et deploiement

- Toute nouvelle feature sur `api/`, `mobile/` ou `admin-dashboard/` doit maintenant mettre a jour la base de scenarios correspondante (`SCENARIOS_TEST_API_GITHUB_ACTIONS.md`, `SCENARIOS_TEST_MOBILE_FLUTTER.md`, `SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md`) ou le `REGISTRE_SCENARIOS_TESTS.md`.
- Le script `tools/check-governance.ps1` doit echouer si une surface fonctionnelle change sans mise a jour de cette base de scenarios. Cela evite qu'une feature apparaisse sans etre rattachee a une couverture attendue.
- Le deploiement auto doit raisonner par SHA et non seulement par nom de workflow : pour un commit `main`, on ne deploie que si les workflows requis pour ce SHA sont conclus avec succes.
- Pour le web admin, Playwright doit continuer a fournir des artefacts exploitables en cas d'echec: HTML, JUnit, traces et videos retenues sur echec.

### 2026-05-10 - Sprint 1-2 completion

- Les 8 domain events existaient mais n'etaient cables a aucun listener. Il faut toujours verifier que les events ont un `EventServiceProvider` et des listeners actifs, pas seulement des classes event.
- Les services (`EmployeeService`, `AbsenceService`, etc.) sont le bon endroit pour dispatcher les events, pas les controllers.
- La commande `php artisan make:module {Name}` est disponible pour scaffolder la structure DDD.
- Les endpoints `/api/v1/health/live` et `/api/v1/health/ready` sont maintenant disponibles pour les sondes Kubernetes/Render.
- `DEVELOPMENT.md` a la racine contient le guide de setup rapide. Le maintenir a jour a chaque ajout de dependance.
- `config/sentry.php` configure le traces_sample_rate via `SENTRY_TRACES_SAMPLE_RATE` (defaut 0.2 en prod).
- En Laravel 11, `EventServiceProvider` doit etre enregistre explicitement dans `bootstrap/providers.php` pour que les listeners soient actifs. L'auto-discovery ne fonctionne plus pour les providers custom.
- Les listeners `ShouldQueue` s'executent en mode sync pendant les tests (queue=sync). Toujours proteger les ecritures DB dans les listeners avec un try-catch pour ne pas casser l'operation metier parente.
- Pour tester les endpoints IA voice/agent sans reseau, injecter un fake `LLMClient` dans le container et configurer les providers voice sans cle. Les contrats doivent rester testables meme quand Whisper, ElevenLabs ou Edge TTS ne sont pas disponibles localement.
- La governance gate CI exige que `SCENARIOS_TEST_API_GITHUB_ACTIONS.md` soit mis a jour quand de nouveaux endpoints API sont ajoutes. Ne pas oublier cette etape avant de push.
- Le repo a ete renomme de `gestionemployerBackend` a `leopardo-hr` sur GitHub. Utiliser `kitokoh/leopardo-hr` pour les operations PR/CI.

### 2026-05-10 - Reorganisation arborescence repo

- Les dossiers `.jules/` et `.kiro/` sont des artefacts d'agents IA. Ils doivent rester dans `.gitignore` et ne pas etre commites.
- `docs/GOTO_MARKET/` a ete deplace dans `docs/GOTO_MARKET/` pour centraliser toute la documentation.
- Les frontends (`mobile/`, `web/`, `admin-dashboard/`, `zkteco-kiosk/`) sont regroupes dans `front/`.
- Quand on deplace des dossiers references par les workflows CI (`.github/workflows/*.yml`), il faut systematiquement mettre a jour les filtres `paths:` et les chemins `working-directory:` dans chaque workflow concerne.
- ATTENTION: Le token OAuth Devin n'a PAS le scope `workflow`. Les fichiers `.github/workflows/` ne peuvent pas etre pushes par l'agent. Le proprietaire du repo doit mettre a jour les workflows manuellement ou accorder le scope.
- `PILOTAGE.md` est un fichier de gouvernance obligatoire a la racine. Ne PAS le deplacer dans `docs/`.
- Les fichiers `.md` techniques (DEPLOYMENT, MONITORING, etc.) vont dans `docs/` ; ne garder a la racine que README, CHANGELOG, AGENTS, SECURITY, SUPPORT, LICENSE, PILOTAGE. Les fichiers liés au développement (CONTRIBUTING, CODE_OF_CONDUCT, DEVELOPMENT) et les dossiers techniques (scripts, sdk, openapi, tools, demo, examples) sont regroupés dans `dev-hub/`.

### 2026-05-10 - Sprint 5-6 conges avances + contrats

- Les modeles LeavePolicy, LeaveBalance, LeaveAccrual, Contract, ContractAmendment, ApprovalWorkflow/Request/Decision existaient deja en tant que modeles. Verifier les routes et controllers avant de creer du code duplique.
- `hr_extended.php` centralise toutes les routes des modules etendus (conges, contrats, recrutement, formation, loans, frais, webhooks, audit).
- Le trait `Approvable` est un pattern reutilisable pour brancher le workflow d'approbation sur n'importe quel modele (Absence, ExpenseClaim, etc.).
- Les contrats doivent rester explicitement scopes par `company_id` dans `index`, `expiring`, `myContracts` et les endpoints self-service. Ne pas compter uniquement sur les IDs de route : la creation doit refuser un `employee_id` hors tenant, et PDF/amendments doivent verifier proprietaire ou manager.

### 2026-05-14 - Privacy / RGPD self-service

- Les droits employes RGPD sont servis par `GET /api/v1/privacy/export`, `POST /api/v1/privacy/deletion-request` et `PATCH /api/v1/privacy/biometric-consent`, tous sous `auth:sanctum` + `tenant`.
- Une demande de suppression doit rester non destructive par defaut : creer une `privacy_requests` pour revue RH/juridique, ne pas supprimer directement l'employe ni ses donnees paie/attendance.
- Le retrait du consentement biometrique doit desactiver les flags visage/empreinte et effacer les chemins de references de templates. Ne pas reactiver des templates simplement parce que `consented=true`; l'enrolement reste le role du workflow biometrie.
- Les acces RH sensibles doivent passer par `DataAccessAuditLogger` quand c'est possible. Le logger doit rester non bloquant et enregistrer `category=hr_data_access` dans `audit_logs` pour que le dashboard audit et la future couche IA puissent tracer les consultations.

### 2026-05-14 - SDK OpenAPI generes

- Les SDK JavaScript et Python officiels vivent dans `dev-hub/sdk/` et sont generes depuis `api/openapi.yaml` par `node dev-hub/tools/generate-openapi-sdk.mjs`.
- Ne pas modifier `dev-hub/sdk/javascript/leopardoClient.js`, `dev-hub/sdk/python/leopardo_client.py` ou `dev-hub/sdk/MANIFEST.json` a la main. Mettre a jour OpenAPI puis lancer le generateur.
- Avant de livrer une modification du contrat OpenAPI ou des SDK, executer `node dev-hub/tools/generate-openapi-sdk.mjs --check`.

### 2026-05-14 - Benchmarks performance Plan 14

- Les benchmarks k6 cibles vivent dans `dev-hub/load/k6/` : `employee-100-attendance-payroll.js`, `payroll-500-batch.js` et `admin-dashboard-10k.js`.
- Les scripts de benchmark destructifs restent proteges par flags explicites (`ALLOW_ATTENDANCE_MUTATIONS=true`, `ALLOW_PAYROLL_MUTATIONS=true`) et doivent viser staging/preproduction, jamais la production client sans fenetre de test.
- Pour les endpoints list/rapport, verifier les scans repetes autant que les N+1 Eloquent : le rapport mensuel attendance doit conserver le groupement par `employee_id`, et l'organigramme le groupement par `manager_id`.

### 2026-05-14 - Coverage backend ratchet

- La derniere mesure GitHub Actions connue est `60.01%` de statement coverage backend (`9748/16243`) sur PR #515.
- Le seuil par defaut `DEFAULT_BACKEND_COVERAGE_MIN` est remonte a `60%` apres PR #515. Ne pas redescendre le seuil sauf incident CI documente.
- Le workflow dedie `coverage-gate.yml` doit parser `clover.xml` pour eviter les faux `0%` issus d'une sortie texte PHPUnit variable.

### 2026-05-14 - Tests mobiles Plan 14

- Les tests mobiles ajoutes pour Plan 14 vivent dans `front/mobile/test/navigation`, `front/mobile/test/features/mobile_surface_smoke_test.dart`, `front/mobile/test/repositories` et `front/mobile/test/golden`.
- Le harnais `front/mobile/test/helpers/mobile_test_harness.dart` remplace auth, preferences et storage par des fakes Riverpod afin de tester les ecrans sans Hive, secure storage ni reseau.
- Les goldens actuels sont des baselines structurelles, pas encore des captures PNG. Ne les presenter comme goldens image qu'apres ajout de fixtures generees et validees par Flutter.
- La derniere mesure coverage mobile connue est `21.85%` (`1469/6723`) sur PR #460. Le seuil par defaut est `21%`; prochaine cible `25%`.

### 2026-05-17 - Iterations 7-11 Plan 15

- Les services IA predictions (`App\AI\Predictions\*`) utilisent des requetes SQL directes (`DB::table(...)`) pour la performance sur grands volumes. Ne pas migrer vers Eloquent sans benchmark comparatif.
- Le `ProactiveNotificationService` est extensible : ajouter un type = ajouter une methode `check*()` privee dans la classe.
- Le `PredictionController` restreint l'acces aux managers `principal` et `rh` via `hasManagerRole()`. Ne pas elargir sans revue RBAC.
- La route `/predictions` est lazy-importee dans `front/admin-dashboard/src/router/index.js`. Garder le code splitting actif.
- Le SSO est un stub (K2) : `SSOService` + `SSOController` logguent les callbacks SAML/OIDC mais ne valident pas les assertions. L'implementation complete necessite `onelogin/php-saml` ou `lightSAML`.
- La table `company_sso_configs` est publique (pas tenant-schema) car la config SSO doit etre lisible avant l'authentification tenant.
- Les routes SSO callbacks (`/sso/saml/{id}/callback`, `/sso/oidc/{id}/callback`) sont publiques (recues de l'IdP). Les routes de gestion sont authentifiees `auth:sanctum` + `tenant`.
- L'audit WCAG 2.1 AA (K4) est documente dans `docs/security/WCAG_ACCESSIBILITY_AUDIT.md`. Score actuel 68% (23/34 conformes, 11 partiels). Plan de remediation 8 items.
- Le lien "Aller au contenu principal" (WCAG 2.4.1) est ajoute dans `DashboardLayout.vue` et `web/src/app/layout.tsx`. Ne pas le supprimer.
- Les items mobile G2-G9 sont deja implementes dans Flutter (absences, contrats, formations, frais, chat IA, voice IA, carte vehicule). Avant d'ajouter un ecran mobile, verifier `front/mobile/lib/features/`.
- Le plan 15 consolide (`docs/PLAN_ACTION/15_PLAN_EXECUTION_CONSOLIDE.md`) est le document de reference pour l'avancement. Les iterations 1-11 sont documentees avec PRs, contenus et statuts.
- Backlog restant apres iteration 11 : C14 (optimisation planning), H (kiosk), J (GTM non-code), L5 (ZKTeco), L6 (calendrier sync), G8 (push Firebase), G10 (organigramme mobile).

### 2026-05-25 - Mobile pointage, equipe et avances

- L'ecran pointage mobile ne doit plus afficher un spinner de synchronisation semaine bloquant. `AttendanceRepository` limite les lectures `today/history` a des delais courts et l'historique indisponible doit rester un avertissement non bloquant.
- Les actions check-in/check-out/correction doivent rester bornees et afficher un SnackBar succes/echec clair ; ne pas relancer de retry long cote mobile qui donne l'impression que le bouton tourne sans fin.
- La creation mobile d'employe doit conserver les champs RH minimum : `contract_start`, `salary_type`, `salary_base` ou `hourly_rate`, `matricule` et `extra_data.department/job_title/work_location`. Le backend `StoreEmployeeRequest`, `EmployeeController@index` et `EmployeeResource` doivent rester alignes avec ce contrat.
- Le module mobile Avances doit proposer une vraie demande employee-side via `POST /salary-advances` avec `amount`, `reason` et `repayment_months`, puis rafraichir la liste locale.
- Depuis v4.16.138, l'ecran pointage doit utiliser `attendanceHistoryMonthKey(_now)` pour `historyProvider` ; ne jamais repasser `_now` complet comme cle provider, sinon l'historique se recharge chaque seconde avec l'horloge live.
- Les actions pointage doivent rester protegees contre doubles taps et timeout provider : un succes API ou une erreur reseau ne doit jamais laisser `isPunching=true` indefiniment.
- Depuis v4.16.139, les ecrans RH mobiles a fort impact demo (`Absences`, `Avances`, `Equipe`) doivent utiliser les composants de `front/mobile/lib/core/widgets/mobile_surface.dart` pour les listes, chargements et erreurs. Eviter de revenir a des `ListTile`/`AppBar` Material bruts sur ces parcours marketing-ready.
- Depuis v4.16.139, la demande d'absence mobile doit resoudre un vrai `absence_type_id` via `leaveBalancesProvider` avant `POST /absences`; ne pas hardcoder de type d'absence cote Flutter.
- Depuis v4.16.140, les annulations self-service mobile passent par les endpoints existants `DELETE /absences/{id}` et `DELETE /salary-advances/{id}` uniquement pour les demandes en attente. Garder les confirmations utilisateur, le refresh provider et les tests repository de route.
- Depuis v4.16.141, les decisions manager/RH mobiles pour absences et avances utilisent les endpoints existants `PUT /absences/{id}/approve|reject` et `PUT /salary-advances/{id}/approve|reject`. Les boutons doivent rester role/capability-aware (`principal`, `rh`, `*.manage`, `*.approve`) et ne pas apparaitre sur les demandes personnelles du manager/RH, qui restent annulables en self-service. Les refus doivent demander un commentaire, et les tests repository doivent verrouiller les routes.
- Depuis v4.16.142, la checklist mobile marketing-ready vit dans `docs/validation/MOBILE_MARKETING_READINESS.md`. Toute evolution des parcours demo mobile (login, pointage, absence, avance, equipe, decisions RH) doit mettre a jour ce guide, la matrice `FRONTEND_API_CONTRACT_MATRIX.md` si une route change, et le smoke `front/mobile/test/features/mobile_marketing_readiness_test.dart` si un comportement visible change.
- Depuis v4.16.143, la fondation multi-app mobile vit dans `front/mobile_apps/` : `leopardo_mobile_legacy` est une archive intouchable du mobile historique, `leopardo_core` porte le partage, `leopardo_employee` exclut les parcours equipe/approvals/organigramme/manager, et `leopardo_manager` conserve le perimetre complet. Toute modification partagee doit aller dans `leopardo_core`; toute modification d'ecran specifique va dans l'app concernee. La CI dediee est `.github/workflows/mobile-apps-ci.yml`.
- Depuis v4.16.144, le Plan 26 ajoute le garde canonique `dev-hub/tools/validate-mobile-apps-split.ps1`. Le lancer avant toute PR touchant `front/mobile_apps/` avec `pwsh` ou, sur Windows sans PowerShell 7, `powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-apps-split.ps1`. Ce garde bloque le retour de marqueurs manager dans `leopardo_employee`, les imports app-specifiques dans `leopardo_core`, les imports `package:leopardo_rh` dans les nouvelles apps et les modifications de `leopardo_mobile_legacy` en PR.
- Depuis v4.16.145, les deux apps mobiles ont des identites store distinctes : employee = `com.leopardo.employee`, manager = `com.leopardo.manager`. Avant de declarer les mobiles publiables, lancer aussi `dev-hub/tools/validate-mobile-release-readiness.ps1`; son mode `-StrictStores` doit rester rouge tant que les signatures release Android/iOS ne sont pas configurees.
- Depuis v4.16.180, `GET /api/v1/employees` expose `work_state` / `work_state_label` pour la vue operationnelle manager mobile. Les etats doivent rester derives du tenant courant (attendance du jour, absences approuvees, statut employe) et ne jamais afficher les donnees d'un autre tenant. Les modifications `role` / `manager_role` via `PATCH /employees/{employee}` sont reservees au manager principal; repasser `role=employee` doit nettoyer `manager_role`.
