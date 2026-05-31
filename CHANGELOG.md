#  CHANGELOG - LEOPARDO RH 
# Format : Keep a Changelog (keepachangelog.com)
# Versioning : Semantic Versioning (semver.org)

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

### Added
- Plan 60: Double validation des avances salaire (migration, contrôleur, routes)
- Plan 61: Service cycles de paie et solde employé (PayrollCycleService, PayrollCycleController)
- Plan 62: Génération PDF bulletins de paie async (GeneratePaySlipPdfJob, queue `pdf`)
- Plan 63: Architecture Redis Upstash — queues nommées (pdf, notifications, payroll, webhooks), QueueHealthCheck
- Plan 64: Clôture automatique présences (AutoCloseAttendanceCommand, scheduler horaire)
- Plan 65: Paiement en masse (ProcessBulkPaymentJob, BulkPaymentController avec progression Redis)
- Redis Upstash TLS configuré dans database.php et queue.php
- README: section architecture complète (Render, Vercel, Cloudflare, Upstash, Firebase)

### Changed
- SalaryAdvance: nouveaux champs double validation (manager_approved_at, payment_declared_at, employee_confirmed_at, validation_status)
- TenantCacheService: helpers TTL Upstash-compatibles (rememberEmployees, rememberAttendanceReport)
## [4.16.194] - 2026-05-31

### Fixed

- Mobile employee/manager/platform admin : ajout d'une barriere anti-logo infini avec timeout court du bootstrap critique, Firebase platform admin sorti du chemin bloquant et timeout explicite de l'hydratation auth avant redirection vers l'ecran de connexion.

## [4.16.193] - 2026-05-31

### Added

- Mobile employee / manager / platform admin : ajout d'un `SplashScreen` natif Flutter (logo Leopardo + glow émeraude + barre de progression) affiché pendant `checkAuth()`. L'app ne reste plus sur un écran vide pendant le cold start Render.

### Changed

- Mobile employee / manager / platform admin : le router démarre sur `/splash` (ou `/platform/splash`) et redirige automatiquement vers `/welcome` (non connecté) ou `/` (connecté) dès que le bootstrap auth est terminé. Plus de cas où `isLoading=true` laisse l'utilisateur sur un écran blanc/logo figé.

## [4.16.192] - 2026-05-31

### Changed

- Mobile startup : `StartupGate` ne montre plus l'écran "Preparation de votre espace" pendant le bootstrap. L'app démarre directement sur fond neutre (< 300ms sur device normal) ; seule une erreur critique affiche un panneau de récupération.
- Mobile employee / manager : refonte des écrans d'accueil (`WelcomeScreen`) — suppression du carousel storytelling ; accès direct aux CTA principaux (Se connecter / Demo) avec logo, tagline et grille de modules visibles d'emblée.
- Mobile employee / manager : refonte des écrans de connexion (`LoginScreen`) — formulaire direct sans bloc hero verbeux, snackbar d'erreur floating, boutons aux bonnes tailles (52px principal), disposition compacte sur petits écrans (< 700px).

## [4.16.191] - 2026-05-31

### Fixed

- Mobile employee/manager/platform admin : correction du blocage logo infini introduit par le timeout global de 8s dans `StartupGate`. Les ops critiques (`_openOfflineCache`, `_initializeLocales`) sont désormais exécutées sans timeout via `criticalInitializer` ; seul Google Sign-In (optionnel) est soumis à `optionalTimeout`. L'app ne peut plus rester bloquée sur l'écran de chargement à cause d'un timeout silencieux sur Hive ou les locales.

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

- API notifications : compatibilite mobile renforcee avec le filtre `unread_only`, suppression scoppée utilisateur via `DELETE /api/v1/notifications/{notification}` et audit `communication_events`.
- Mobile employee/manager : les listes notifications utilisent `requestWithRetry`, timeouts courts et parsing robuste des collections paginees Laravel pour eviter les spinners vides apres reveil Render.
- Mobile manager : le module notifications consomme le filtre canonique `unread` et garde les actions lire/tout lire/supprimer sur les endpoints mobiles versionnes.

## [4.16.184] - 2026-05-29

### Changed

- Mobile platform admin : durcissement de la session super-admin, gestion explicite du `TWO_FA_REQUIRED`, bouton compte demo et validation du formulaire de creation client.
- Mobile employee/manager : les tokens FCM sont retires via `DELETE /api/v1/device-tokens` avant la deconnexion pour eviter les pushes vers des sessions fermees.
- Documentation : ajout du Plan 56 platform admin mobile auth hardening.

## [4.16.183] - 2026-05-29

### Added

- API : création de `SendPushNotificationJob` pour asynchroniser l'envoi de push sans bloquer la requête utilisateur.
- API : intégration de Firebase HTTP v1 en natif dans `PushNotificationService` avec cache de 50 minutes pour le JWT OAuth 2.0.
- Documentation : mise à jour des guides et du walkthrough pour l'intégration mobile HTTP v1.

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
- Mobile : tests repository ajoutés pour verrouiller les routes de decision manager/RH.

## [4.16.140] - 2026-05-25

### Added

- Mobile : workflows employe Plan 25.3 enrichis avec annulation des demandes d absence en attente via `DELETE /absences/{id}`.
- Mobile : annulation des demandes d avance en attente via `DELETE /salary-advances/{id}`.
- Mobile : tests repository ajoutés pour verrouiller les routes self-service d'annulation absence/avance.

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
- RBAC Route Matrix : section « Model Policies » ajoutee avec matrice complete viewAny/view/create/update/delete/approve par role.
## [4.16.130] - 2026-05-24

### Added

- API Resources : 11 nouvelles classes Resource (AbsenceResource, DepartmentResource, PositionResource, ScheduleResource, SiteResource, NotificationResource, ApprovalRequestResource, InvoiceResource, AuditLogResource, WebhookEndpointResource, PayrollResource) normalisent les contrats JSON API.
- FormRequests : 10 classes extraites (StoreDepartment, UpdateDepartment, StorePosition, UpdatePosition, StoreSchedule, UpdateSchedule, StoreSite, UpdateSite, StoreWebhookEndpoint, UpdateWebhookEndpoint) avec validation et authorize gates.
- ApiError enum : catalogue centralise de ~40 codes erreur API avec traductions FR/EN/AR/TR, codes HTTP semantiques et methode `->response()`.
- Traductions api_errors : fichiers i18n `lang/{en,fr,ar,tr}/api_errors.php` pour les messages erreur API.
- Plan 23 : document `docs/PLAN_ACTION/23_PLAN_API_PRODUCTION_GRADE.md` — audit architecture + plan 8 iterations production-grade.

### Changed

- Controllers refactorises : AbsenceController, DepartmentController, PositionController, ScheduleController, SiteController, NotificationController, WebhookController, ApprovalController, ContractController utilisent desormais les API Resources au lieu de serialisations manuelles.
- DB::transaction ajoutees : ContractController::renew, ApprovalController::approve/reject, NotificationController::markRead/markAllRead protegent les ecritures multi-tables.
- FormRequests injectees dans les signatures store/update des controllers Department, Position, Schedule, Site, Webhook — la validation et l'autorisation quittent le corps du controller.
## [4.16.129] - 2026-05-24

### Added

- API : `EnsureApiManagerMiddleware` — RBAC paramétré par rôle (`api.manager`, `api.manager:principal,rh`) pour protéger les routes sensibles.
- Routes : dashboard (managers only), exports (P/RH/FIN), billing (principal), payroll engine (P/FIN), hr_extended 3-tier RBAC.
- Seeder : `DemoCompanySeeder` enrichi avec contrats, formations, recrutement, prêts et notes de frais pour faciliter les tests API.
- API Explorer : boutons endpoints groupés par catégorie (auth, dashboard, self-service, paie, billing, plateforme).
- Sécurité : matrice RBAC mise à jour dans `docs/security/RBAC_ROUTE_MATRIX.md` avec documentation `api.manager`.

### Tests

- Backend : `ApiManagerMiddlewareTest` couvre 5 scénarios (allow any manager, reject employee, allow specific roles, reject wrong role, reject unauthenticated).

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
