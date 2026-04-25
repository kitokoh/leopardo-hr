# CHANGELOG — LEOPARDO RH
# Format : Keep a Changelog (keepachangelog.com)
# Versioning : Semantic Versioning (semver.org)

## [4.1.71] - 2026-05-20
### Performance - Optimisation du dashboard manager

- `api/app/Http/Controllers/Web/DashboardController.php` : ajout de `select()` sur les requetes `Employee` et `AttendanceLog` pour ne recuperer que les colonnes necessaires, evitant ainsi le chargement des colonnes JSONB lourdes et reduisant la consommation memoire lors de l'hydratation des modeles Eloquent.

## [4.1.71] - 2026-04-24
### DocKeeper - Alignement documentation Sprint 0

- `PILOTAGE.md` : mise a jour du statut S0-2 en "termine" et correction du compte des contradictions (6 -> 7) pour correspondre a `docs/GESTION_PROJET/CORRECTIONS.md`.

## [4.1.71] - 2026-04-23
### Janitor: Archivage documentation historique et synchronisation

- Docs : archivage de 8 fichiers marques `📦 HISTORIQUE` dans `PILOTAGE.md` vers `docs/notes/archive/` (`ORCHESTRATION_MAITRE.md`, `INDEX_CANONIQUE.md`, `CONTEXTE_SESSION_IA.md`, `JOURNAL_DE_BORD.md`, `BACKLOG_PHASE1_UNIQUE.md`, `CONTINUE.md`, `SUIVI_PROMPTS.md`, `EXECUTION_BLOCKERS_AND_NEXT.md`)
- Governance : mise a jour de `tools/check-governance.ps1` pour refleter les nouveaux emplacements des fichiers requis
- Pilotage : mise a jour de `PILOTAGE.md` (bump version `4.1.71`, mise a jour de la table de statut documentaire, validation C-6)
- API : bump `APP_VERSION` default de `4.1.70` a `4.1.71` dans `api/config/app.php`

## [4.1.71] - 2026-04-24
### Palette - Accessibilite EmptyState mobile

- Mobile : Amelioration de l'accessibilite du widget `EmptyState` par l'ajout de labels `Semantics` regroupant le titre et la description, permettant aux lecteurs d'ecran d'annoncer clairement le contexte des listes vides.

## [4.1.71] - 2026-04-24
### Contractor - Alignement contrat API/mobile (auth/me)

- API : Mise a jour de `AuthController@serializeEmployee` pour inclure le `matricule` a la racine et un objet `company` imbrique (id, name, language, timezone, currency) conformement au contrat MVP.
- Mobile : Mise a jour du modele `Employee` pour inclure et parser le champ `matricule`.
- Tests : Renforcement de `MobilePayloadContractTest` pour verrouiller la presence de `matricule` et de la structure `company` dans la reponse `/api/v1/auth/me`.

## [4.1.70] - 2026-04-23
### Audit de coherence PILOTAGE / CORRECTIONS (aucun changement fonctionnel)

- `PILOTAGE.md` : en-tete re-aligne sur `PROGRAM_VERSION = 4.1.70 | 2026-04-23` (precedemment `4.1.58 | 14 Mai 2025`, date erronee), date MAJ corrigee, bloc "CONVENTION DE VERSIONING" precise que la version doit rester synchrone entre CHANGELOG.md, `api/config/app.php` et `/api/v1/health`
- `api/config/app.php` : bump `APP_VERSION` default de `4.1.68` a `4.1.70` pour respecter la regle de synchronisation introduite dans PILOTAGE (PROGRAM_VERSION == config('app.version') == champ `version` de `/api/v1/health`)
- `PILOTAGE.md` : avertissement explicite en tete du document sur la divergence entre la section "SCOPE MVP VERROUILLE" (schema mode interdit, 2 roles, 2 pages Blade, VPS) et le code livre sur main (schema mode actif, 6 sous-roles manager, plusieurs pages Blade, hebergement Render) — la decision produit reste a prendre, mais le document ne peut plus etre lu comme "source de verite" sans cet avertissement
- `docs/GESTION_PROJET/CORRECTIONS.md` : audit 2026-04-22 des 7 corrections Sprint 0. Toutes sont deja appliquees sur main (C-1 `/auth/refresh` supprime d openapi.yaml, C-2 `is_active` remplace par `status`, C-3 `user_lookups` PK email, C-4 prix Starter 29 EUR, C-5 trait unique `BelongsToCompany`, C-6 archive, C-7 `bon-fixed/`). Tableau STATUT coche avec preuves ligne par ligne. Version du doc passe a `5.1`
- Aucun changement fonctionnel, aucun schema DB, aucune migration. Rollback = `git revert` de la PR
### DocKeeper - Alignement documentation infra (GO MVP)

- Docs : alignement de `PILOTAGE.md` et `RUNBOOK_BETA_ACCEPTANCE.md` avec la decision GO MVP du 2026-04-21 ; remplacement des references "VPS" (obsoletes) par "Render" (canonique)
- Pilotage : mise a jour du statut Sprint 4 (S4-1 et S4-2 marques comme termines sur Render)

## [4.1.69] - 2026-04-22
### Sprint D - UI super-admin pour toggler les modules + guides utilisateurs

- Super-admin web : nouvelle page `GET /platform/companies/{company}/edit` (controller `PlatformCompanyController@edit/update`) qui permet de (a) cocher / decocher les modules actifs pour une societe (`companies.features` JSONB : `rh`, `finance`, `cameras`, `muhasebe`, `leo_ai`), (b) changer le statut (`active` / `suspended` / `expired`), (c) mettre a jour le plan et les notes internes ; le module `rh` est force a `true` (APV L.08) et les modules hors `Company::KNOWN_MODULES` soumis sont ignores (whitelist stricte)
- Super-admin web : bouton **Renvoyer l'invitation manager** (`POST /platform/companies/{company}/resend-invitation`) qui regenere le lien d activation du manager principal via `UserInvitationService::createAndSend` (l ancien token est invalide par le `updateOrCreate`)
- Super-admin web : page `/platform/companies` enrichie (colonne **Modules actifs** en badges + badge statut colore + bouton **Editer**)
- Fix : `Company::booted` etend le `search_path` au schema tenant avant de revoquer les tokens Sanctum lors d un passage en `suspended` / `expired` (precedemment, un update via super-admin web crashait `relation "employees" does not exist`)
- Tests : nouveau `PlatformCompanyEditTest` (6 tests) couvrant auth, affichage, update des features/status/notes/plan, force de `rh=true`, rejet des modules inconnus, rejet des statuts invalides, affichage de l index enrichi ; suite backend passe a **98 tests / 505 assertions**
- Docs utilisateurs : nouveau dossier `docs/GUIDES/` avec `README.md` + 4 guides (`GUIDE_SUPER_ADMIN.md`, `GUIDE_MANAGER.md`, `GUIDE_RH.md`, `GUIDE_EMPLOYEE.md`) pour couvrir les parcours cle par role sans plonger dans l implementation

## [4.1.68] - 2026-04-22
### Sprint C - Ops production-ready (observability + backup + multi-tenant)

- API : nouvel endpoint `GET /api/v1/health` servi par `App\Http\Controllers\Api\V1\HealthController` qui expose une matrice `checks` (`database`, `redis`, `storage`) avec latence et statut detaille ; 200 tant que la DB repond, 503 sinon (Redis/storage degrades = warning mais pas de 503) ; test `HealthEndpointTest` verifie le contrat
- Tests : nouveau `MultiTenantSharedIsolationTest` qui seed 5 compagnies dans `shared_tenants` et verifie strictement (a) qu'aucune compagnie ne voit les employes d'une autre, (b) que la bascule de contexte `current_company` ne fuit jamais de ligne, (c) qu'une compagnie suspendue reste correctement scopee (auth hors sujet) ; suite backend passe a 92 tests / 476 assertions
- Ops : nouveau script `scripts/backup_drill.sh` qui automatise le drill backup/restore (pg_dump format custom, chiffrement `age` optionnel, restore dans une base scratch, verification des row counts sur 6 tables critiques, nettoyage, log `last-drill.log`)
- Docs : `RUNBOOK_BACKUP_RESTORE.md` enrichi (secrets requis, drill automatise, tables verifiees, procedure manuelle fallback, retention, en cas d'echec)
- Docs : `RUNBOOK_ROLLBACK.md` enrichi (declencheurs chiffres, option A code rollback avec checks concrets sur `/health` + `auth/login` + `auth/me` + `me/monthly-summary`, option B code+DB avec maintenance mode + pg_restore + sanity checks SQL, regles de migration, temps cibles 10/45 min)
- Docs : nouveau `RUNBOOK_OBSERVABILITY.md` qui decrit l'activation Sentry backend (`sentry/sentry-laravel` + tags tenant dans `TenantMiddleware`) et mobile (`sentry_flutter` + `--dart-define=SENTRY_DSN_MOBILE`), scaffold d'alertes, regle RGPD `sendDefaultPii=false`

## [4.1.66] - 2026-04-22
### Sprint A - Cablage des tokens design (APV L.05/L.07)

- Mobile : nouvel ecran `HomeScreen` conversationnel (`mobile/lib/features/home/screens/home_screen.dart`) pose comme route `/` : salutation contextuelle, banniere Leo (placeholder tant que `leo_ai` n est pas active), grille d actions rapides (Pointer, Mon mois, Historique, Equipe si manager, Parametres) et barre de chat desactivee en pied ; l ancien ecran `AttendanceScreen` est desormais accessible via `/attendance`
- Mobile : refactor de `TeamScreen` pour utiliser `LeopardoBadge.forStatus` + `EmptyState` (finies les `Chip` locales avec couleurs codees en dur) et `LeopardoBadge` sur les invitations (avec libelles FR homogenes)
- Web : `dashboard.blade.php` bascule en layout 2 colonnes (1fr / 300px) avec nouveau composant `x-leo-sidebar` (placeholder sticky sur >=lg, rappel que Leo arrive bientot)
- Web : `hr/invitations/index.blade.php` et `me/dashboard.blade.php` utilisent `x-attendance-badge` pour statut pointage/invitation et `x-empty-state` pour les listes vides
- Web : `layouts/app.blade.php` ecrit les messages de session via `x-alert-banner level="success"` au lieu d une bande emerald ad hoc ; les boutons "Creer RH / employe" et "Renvoyer invitation" passent sur les tokens `bg-rh` / `bg-rh-dark` (APV L.05, RH = emerald)
- Design : plus aucun `#10B981` / `bg-emerald-500` hardcode dans ces ecrans, toute la chaine passe par `AppColors`, `tailwind.config.js` et les composants Blade
- Tests : `php artisan test` toujours vert a 89 tests / 408 assertions (aucun changement contrat API, aucun changement schema)

## [4.1.65] - 2026-04-22
### APV - Fondations design moderne + frontieres modules

- Vision : 6 PDF vision (APV v1/v2, Design System v2/v3, Finance, Cameras) archives sous `docs/vision/` avec un `README.md` qui etablit la hierarchie (v2/v3 = canonique, v1/v2 = historique, Finance/Cameras = Phase 2)
- Vision : creation des documents canoniques `docs/APV.md` (manifeste 1 page, 4 piliers, 12 Lois), `docs/ROADMAP.md` (Phase 0 MVP -> Phase 1 fondations -> Phase 2 modules a la demande), `docs/STATUTS.md` (catalogue exhaustif pointage/invitation/employe/features), `docs/COULEURS.md` (tokens couleur domaine + semantique + neutres), `docs/AUDIT_v2_v3_COMPLIANCE.md` (matrice conformite code vs vision)
- Module boundaries (APV L.08) : extraction des routes RH dans `api/routes/modules/rh.php` (contrat URL inchange), charge depuis `routes/api.php` avec directive de preparation Phase 2 pour finance/cameras/muhasebe
- Module boundaries (APV L.10) : nouvelle migration publique `2026_04_22_000014_add_metadata_and_features_jsonb.php` ajoute `companies.features` (JSONB, defaut `{}`) et `companies.metadata` (JSONB) + index GIN ; migration tenant `2026_04_22_000109_add_metadata_to_employees.php` ajoute `employees.metadata` (JSONB) + index GIN
- Module boundaries : nouveau service `App\Services\FeatureFlag` (`enabled($key, $company)` et `for($company)`), modele `Company` expose `hasFeature`, `setFeature`, constante `KNOWN_MODULES` (`rh`, `finance`, `cameras`, `muhasebe`, `leo_ai`)
- API : `/api/v1/auth/me` et `/auth/login` renvoient desormais `features` (carte resolue pour la company) pour que le client mobile/web puisse afficher ou cacher les modules sans redeployer
- Design (APV L.05/L.07) : cote mobile `mobile/lib/core/theme/app_colors.dart` centralise les tokens couleur (rh `#10B981`, finance `#F59E0B`, security `#3B82F6`, ia `#7C3AED` + variantes light/dark + semantique success/warning/danger/info + neutres) et `mobile/lib/core/theme/app_typography.dart` impose Inter 400/600 en 6 styles
- Design : nouveaux widgets Flutter `LeopardoBadge` (factories `present`, `late`, `absent`, `onLeave`, `forStatus`, `domain`), `EmptyState`, `AlertBanner` (niveaux success/warning/danger/info) sous `mobile/lib/core/widgets/`
- Design : cote web `api/tailwind.config.js` etendu avec les memes tokens (rh/finance/security/ia + semantique) et font `Inter` en premier ; nouveaux composants Blade `x-attendance-badge`, `x-alert-banner`, `x-empty-state` sous `resources/views/components/`
- Tests : nouveau `FeatureFlagTest` (5 tests, 18 assertions) couvre defaut `rh=true`, defaut `false` pour modules Phase 2, persistance `setFeature`, `for($company)` complet et company nulle. Suite verte a 89 tests / 408 assertions
- Compat : `app_theme.dart` reference desormais `AppColors` (aucune casse cote ecran existant)

## [4.1.65] - 2026-04-22
### Retours pilotes clients et audit post-MVP

- Ajout des retours pilotes Karim B., Amina T. et Sofiane M. avec priorisation produit post-GO MVP
- Ajout d un audit T01-T18 indiquant les points deja couverts, partiels ou encore a traiter
- Priorisation des prochains correctifs: `search_path`, pagination dashboard, lisibilite mobile, 401 mobile et recu/export paie

## [4.1.64] - 2026-04-22
### Accessibilite et navigation mobile

- Amelioration de la navigation mobile par l ajout de tooltips "Retour" sur les boutons de retour des ecrans Equipe et Mon mois
- Amelioration du feedback pour les lecteurs d ecran par l ajout de labels `Semantics` sur les indicateurs de chargement (connexion, presence, suivi equipe)

## [4.1.63] - 2026-04-22
### API self-service et parite CRUD mobile

- Ajout de 3 endpoints self-service sous `auth:sanctum` : `GET /api/v1/me/daily-summary`, `/me/quick-estimate`, `/me/monthly-summary` pour que l employe connecte consulte ses heures, heures sup et son du sans connaitre son identifiant technique
- `App\Http\Controllers\Api\V1\MeController` reutilise `EstimationService` pour garantir le meme contrat de reponse que les endpoints `/employees/{id}/*` existants (pas de policy viewAny requis cote employe)
- Couverture Pest : 6 nouveaux tests (`MeEndpointsTest`) couvrant employe sans ID, quick estimate par periode, monthly summary par defaut, acces manager, 401 non authentifie, validation du format de date
- Mobile : `Employee` expose `manager_role`, `suggested_home_route`, `capabilities`, `salary_type`, `hourly_rate`, `salary_base`, `currency` + getters `isPrincipal`, `isHr`, `canManageTeam`, `canManageInvitations`
- Mobile : nouveau modele `MonthlySummary` + `MonthlyBreakdownEntry` et methodes `AttendanceRepository::getMyDailySummary|getMyMonthlySummary|getMyQuickEstimate`
- Mobile : ecran `MonthlySummaryScreen` (route `/me/monthly`) affiche heures travaillees, heures sup, gain brut, deductions, du net et detail par jour, avec selecteur de mois
- Mobile : ecran `TeamScreen` (route `/team`, manager principal ou RH uniquement) avec onglets Employes et Invitations, creation d employe/manager avec envoi d invitation, archivage, renvoi d invitation
- Mobile : ecran d accueil pointage propose des boutons `Mon mois` (tous roles) et `Equipe` (managers autorises)

## [4.1.63] - 2026-04-22
### Onboarding API complet + RBAC + interfaces par role

- Correction bloquant: plusieurs societes en mode `shared_tenants` ne pouvaient plus etre creees a cause d un index UNIQUE global sur `companies.schema_name`. Nouvelle migration `2026_04_22_000013_relax_companies_schema_name_unique.php` qui remplace l index par un unique partiel `WHERE tenancy_type='schema'`
- Ajout de la commande Artisan `leopardo:migrate --fresh --seed` qui enchaine migrations publiques + tenant + seeders dans l ordre correct (fix de `php artisan migrate:fresh --seed` qui restait inutilisable)
- Correction expediteur mail: `config/mail.php` fixe `MAIL_FROM_ADDRESS=noreply@leopardo-rh.com` et `MAIL_FROM_NAME` par defaut
- RBAC serveur: ajout de `app/Policies/EmployeePolicy.php` (create/update/archive/manageInvitations) et des middlewares `EnsureManagerRoleMiddleware` / `EnsureEmployeeMiddleware`
- Interfaces dediees par role: redirection post-login web (employe -> `/me`, manager -> `/dashboard`), nouvelle page `/me` (fiche + pointages du mois) et `/hr/invitations` (Principal + RH, avec bouton resend)
- Nouveaux endpoints API: `GET /api/v1/invitations`, `POST /api/v1/invitations/{id}/resend`. `GET /api/v1/auth/me` retourne desormais `role`, `manager_role`, `suggested_home_route` et un bloc `capabilities`
- `TenantMiddleware` fixe le `search_path` Postgres au schema du tenant pour que les controllers trouvent `employees` quel que soit l etat anterieur de la connexion
- Nouveaux tests Pest: `OnboardingE2ETest` (flux complet admin -> manager email -> activation -> login -> creation RH/employe -> login web employe /me, 3 scenarios, 41+ assertions) + correctifs `WebAuthPagesTest` (employe redirige vers /me, pas 403) et `EmployeesRbacTest` (fixtures avec `manager_role='principal'`)
- Suite: 78 tests passent (355 assertions), Pint vert, aucune regression

## [4.1.62] - 2026-04-22
### Accessibilite mobile et retour haptique

- Ajout de tooltips sur les boutons icones des ecrans historique et parametres pour ameliorer la navigation lecteur d ecran
- Ajout de labels `Semantics` sur le bouton de pointage afin d expliciter l action arrivee / sortie
- Ajout d un retour haptique au tap du bouton de pointage pour renforcer la confirmation utilisateur

## [4.1.61] - 2026-04-21
### GO MVP officiel

- Decision GO MVP prononcee apres validation de `main`, Render, Neon/PostgreSQL, Firebase mobile et tests de connexion reels positifs
- `docs/GESTION_PROJET/GO_NO_GO_MVP.md` archive la decision, le perimetre valide et la checklist de passage
- Le projet passe de construction MVP a pilote client encadre, avec gel des nouvelles features hors corrections P0/P1
- La release MVP est preparee pour tag Git `v0.1.0-mvp`

## [4.1.60] - 2026-04-21
### Couverture onboarding invitations

- Ajout d assertions feature pour verrouiller la creation d une societe avec manager principal et invitation email depuis le super-admin
- Verification explicite des invitations RH et employe creees par un manager, avec `role`, `manager_role`, origine d invitation et lien `/activate`
- Ces tests securisent le parcours pilote: entreprise -> manager principal -> RH/employes -> activation de compte par email

## [4.1.59] - 2026-04-21
### Optimisations backend ciblees

- Optimisation du chemin de connexion API en chargeant la societe avec l employe pour eviter une requete relationnelle tardive
- Reduction des colonnes lues sur les endpoints de presence utilises par les tableaux de bord et historiques
- Reutilisation du fuseau horaire courant pendant la serialisation des statuts de presence manager
- Simplification de la resolution des horaires via les relations Eloquent existantes
- Correction du helper de schema de test pour eviter la syntaxe `CASCADE` hors PostgreSQL

## [4.1.58] - 2025-05-14
### Amelioration UX et accessibilite mobile

- Amelioration de l ecran de connexion mobile avec ajout d icones et d un basculeur de visibilite du mot de passe
- Ajout de tooltips d accessibilite sur les boutons d icone pour le support des lecteurs d ecran
- Amelioration du contraste visuel des indicateurs de chargement sur l application mobile

## [4.1.57] - 2026-04-19
### Borne ZKTeco offline-first et synchronisation differee

- Ajout d un mode de synchronisation offline-first pour les bornes d entree entreprise avec file locale, reprise automatique et synchronisation manuelle ou des le retour du reseau
- `zkteco-kiosk/desktop-bridge/bridge.py` fournit un pont local PC <-> API avec stockage SQLite des evenements, cache roster collaborateurs et endpoints `/local/*` pour une borne exploitable sans internet
- L API expose maintenant un roster borne et un endpoint de synchronisation batch pour reimporter les pointages visage / empreinte collectes hors ligne sans doublons grace aux `external_event_id`
- Les journaux de pointage conservent desormais la provenance borne, le type biometrie et le statut de synchronisation offline afin que le mobile et le back-office retrouvent les donnees apres sync
- Les interfaces web borne / biometrie manager couvrent maintenant la creation de la borne, l affichage des identifiants de synchronisation et le suivi des demandes biometrie approuvees avant activation effective
- `docs/GESTION_PROJET/RUNBOOK_ZKTECO_CLIENT.md` documente l installation client, le schema reseau, le mode offline, la synchronisation differee et la routine manager / RH
- `docs/GESTION_PROJET/SCHEMA_DEPLOIEMENT_ZKTECO_CLIENT.md` ajoute un schema visuel Mermaid du deploiement client ZKTeco, du flux metier et du mode offline / connecte
- `docs/GESTION_PROJET/SUPPORT_COMMERCIAL_ZKTECO_LEOPARDO_RH.md` fournit un support commercial simple pour presenter la solution a un prospect, un client ou un integrateur
- Le projet Flutter Android aligne maintenant Android Gradle Plugin, Kotlin et le wrapper Gradle sur des versions compatibles avec les dependances AndroidX recentes utilisees en CI GitHub Actions

## [4.1.56] - 2026-04-19
### Biometrie approuvee et borne d entree entreprise

- Ajout du workflow complet de demande biometrie employe avec validation manager / RH avant activation effective des donnees visage / empreinte
- Ajout d une borne de pointage entreprise configurable cote manager avec code appareil, interface web dediee et endpoint API de pointage a l entree
- L application mobile permet maintenant de soumettre une capture visage reelle et une demande d activation biometrie; toute modification ou premiere activation reste en attente d approbation
- Pour l empreinte mobile, le systeme s appuie sur une verification biometrie locale de l appareil puis laisse l activation effective etre approuvee et exploitee cote borne / lecteur entreprise
- Ajout d un dossier racine `zkteco-kiosk/` pour servir de socle au poste d entree ZKTeco / HID cote client

## [4.1.55] - 2026-04-19
### Onboarding plateforme, invitations et profils enrichis

- Le super admin plateforme peut maintenant creer une nouvelle societe depuis le web ou l API, provisionner son manager principal et declencher automatiquement une invitation email personnalisee avec lien d activation
- Les managers peuvent creer des comptes RH et employes sans saisir de mot de passe initial, le systeme rattachant automatiquement le nouveau compte a la bonne societe et a son manager createur
- Les invitations sont suivies dans `public.user_invitations`, avec expiration, acceptation, traces d envoi et activation du mot de passe via un ecran web dedie
- Les profils employes gagnent des donnees metier et RH plus riches: email perso, telephone, adresse, urgence, poste, departement, site, identite, biometrie visage / empreinte et consentement associe
- Le web dispose maintenant d un espace plateforme pour l onboarding des societes, d un formulaire manager pour creer RH / employes et d une fiche collaborateur enrichie pour preparer les prochaines etapes de pointage modernise

## [4.1.54] - 2026-04-19
### Stabilisation CodeQL GitHub Actions

- `.github/workflows/codeql.yml` bascule l analyse CodeQL vers le langage `actions`, qui est pris en charge par GitHub CodeQL, au lieu de `php` qui etait rejete
- Le workflow CodeQL passe de `github/codeql-action@v3` a `@v4` pour supprimer les avertissements de deprecation Node 20 / v3
- Les etapes PHP inutiles sont retirees du workflow CodeQL afin d eviter un echec d initialisation avant publication du statut

## [4.1.53] - 2026-04-19
### Parametres mobile, acces par role et preparation biometrie

- `api/routes/api.php` expose maintenant `/auth/profile` et `/auth/change-password` pour permettre au mobile de mettre a jour le profil et le mot de passe en self-service
- `api/tests/Feature/AuthProfileSettingsTest.php` couvre la mise a jour du profil et la rotation du mot de passe avec verification du mot de passe actuel
- `mobile/lib/features/attendance/screens/attendance_screen.dart` distingue desormais clairement les usages RH/manager et employe pour n afficher que les actions pertinentes
- `mobile/lib/features/settings/screens/settings_screen.dart` ajoute un ecran Parametres avec edition du profil, changement de mot de passe et preparation locale des preferences biometrie
- `mobile/lib/core/storage/app_preferences.dart` stocke localement les preferences de biometrie a reutiliser lors des prochaines etapes du pointage modernise

## [4.1.52] - 2026-04-19
### Dossier de decision GO / NO-GO MVP

- Ajout de `docs/GESTION_PROJET/GO_NO_GO_MVP.md`
- Formalisation du perimetre MVP, des criteres de passage, de la grille de decision et de la checklist de validation
- Ajout d'un cadre de decision reutilisable pour statuer entre `GO MVP`, `GO MVP sous reserve` et `NO-GO MVP`

## [4.1.51] - 2026-04-18
### Deblocage des checks GitHub Actions de PR

- `.github/workflows/tests.yml` corrige le job `notify` pour ne plus utiliser `secrets.*` directement dans le `if` de l'etape d'email
- Les secrets SMTP optionnels sont exposes via `env` au niveau du job puis reutilises dans l'action d'envoi de mail
- Ce correctif vise a eviter l'echec immediat du workflow `Tests - Leopardo RH` avant la creation effective des checks requis sur la PR
- Le job backend CI bootstrappe maintenant explicitement `public.migrations` et `shared_tenants.migrations`
- Les migrations CI sont desormais executees avec `DB_SEARCH_PATH=public` puis `DB_SEARCH_PATH=shared_tenants` pour eviter le conflit sur la table `migrations`
- `api/docker-entrypoint.sh` isole aussi Render avec `DB_SEARCH_PATH=public` puis `DB_SEARCH_PATH=shared_tenants` pendant le bootstrap de deploiement
- `api/database/migrations/public/2026_04_01_000001_create_plans_table.php` devient idempotente et sans transaction implicite pour eviter les courses PostgreSQL sur `plans`
- Les autres migrations publiques critiques (`companies`, tables de support, `personal_access_tokens`, `seed_locks`) tolerent maintenant les reruns partiels de Render apres succes incomplet
- `mobile/lib/core/storage/secure_storage.dart` ajoute un fallback memoire + Hive et des timeouts courts pour eviter le blocage du login mobile si `flutter_secure_storage` ralentit sur certains appareils/tests IA
- `mobile/android/app/src/main/AndroidManifest.xml` declare maintenant `INTERNET` et `ACCESS_NETWORK_STATE` aussi en release pour que Firebase/App Distribution puisse joindre l'API Render
- `mobile/lib/features/attendance/screens/attendance_screen.dart` n'attend plus un chargement complet avant d'afficher l'ecran employe apres connexion et propose un retry inline si `attendance/today` tarde

## [4.1.50] - 2026-04-18
### Correctif connexion mobile et validation login

- `mobile/lib/core/api/api_client.dart` pointe maintenant par defaut vers l'API Render au lieu de `10.0.2.2`
- Timeouts mobile reduits pour eviter l'impression de boucle infinie au login
- `mobile/lib/features/auth/screens/login_screen.dart` ajoute une validation simple et compatible avec les comptes de test existants
- `mobile/lib/features/auth/providers/auth_provider.dart` remonte proprement tous les messages `ApiException`
- `mobile/test/features/auth/login_screen_test.dart` couvre la resolution par defaut de `API_BASE_URL`

## [4.1.49] - 2026-04-18
### Scenarios mobile exhaustifs par role

- Refonte complete de `docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md`
- Couverture etendue a tous les profils: Super Admin, Owner/Admin, HR, Manager, Employee, Finance, compte bloque
- Ajout d'une matrice de test exhaustive par domaine fonctionnel (auth, permissions, employes, presence, conges, paie, notifications, resilience, securite)
- Ajout des parcours E2E minimaux obligatoires par role
- Clarification du mapping CI GitHub Actions et des criteres de validation `GO/NO GO`
- Ajout de `docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md` pour formaliser la couverture backend complete par role, domaine metier, securite et contrats API
- Mise a jour de `.github/workflows/tests.yml` et `docs/GESTION_PROJET/RAPPORT_QA_CI_2026-04-18.md` pour referencer explicitement les scenarios API et les gaps backend a fermer
- Ajout de tests backend reels pour les garde-fous auth, les contrats JSON critiques consommes par Flutter et l'isolation estimation inter-tenant
- Ajout de `docs/GESTION_PROJET/DOSSIER_REPONSE_AU_CAHIER_DES_CHARGES.md` comme reponse formelle au cahier des charges avec architecture, deploiement, modules valides et ecarts restants
- Normalisation des reponses API `404` JSON pour renvoyer `RESOURCE_NOT_FOUND` de maniere stable sur les chemins consommes par la CI et le mobile

## [4.1.48] - 2026-04-18
### CI report central + scenarios mobile Flutter

- .github/workflows/tests.yml genere maintenant un rapport CI central (ci-report.md) et l'uploade en artefact
- Ajout d'un envoi email optionnel du rapport CI via SMTP (secrets CI_SMTP_SERVER / CI_SMTP_USERNAME / CI_SMTP_PASSWORD)
- Ajout de docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md avec scenarios de test mobile Flutter (auth, presence, resilience, securite)

## [4.1.47] - 2026-04-18
### QA pro: CI scenario coverage + super admin reset flow

- Renforcement de .github/workflows/tests.yml avec execution distincte des tests backend Unit puis Feature
- Migrations CI executees explicitement par schemas (database/migrations/public puis database/migrations/tenant) avec --isolated
- Ajout d'artefacts de tests CI (rapports backend JUnit + logs, couverture mobile lcov.info)
- api/database/seeders/SuperAdminSeeder.php supporte la reinitialisation forcee du mot de passe (FORCE_SUPER_ADMIN_PASSWORD_RESET=true + SUPER_ADMIN_PASSWORD)
- Ajout de la commande artisan super-admin:reset-password dans api/routes/console.php
- Ajout du rapport QA dans docs/GESTION_PROJET/RAPPORT_QA_CI_2026-04-18.md

## [4.1.46] - 2026-04-18
### Silence duplicate plans migration race

- Migration api/database/migrations/public/2026_04_01_000001_create_plans_table.php rendue idempotente et tolerante a SQLSTATE 42P07
- Ajout d'un garde-fou Schema::hasTable('plans') + capture QueryException sur creation concurrente
- Objectif: supprimer le bruit d'erreur relation "plans" already exists pendant le boot multi-instance

## [4.1.45] - 2026-04-18
### Bootstrap DB_URL + retry readiness (Render)

- api/docker-entrypoint.sh parse maintenant DB_URL (host/port/database/user/password) pour le bootstrap SQL des tables migrations
- Ajout d'une boucle wait_for_db_bootstrap (30 tentatives, 2s) avant migrations pour absorber les delais de disponibilite PostgreSQL
- Gestion d'erreur non fatale (catch PDO) pour permettre les retries au lieu d'un crash immediat

## [4.1.43] - 2026-04-18
### Migration startup anti-race (Render)

- api/docker-entrypoint.sh passe les migrations avec --isolated pour eviter les executions concurrentes au boot multi-instance
- Ajout d'un fallback de rattrapage (php artisan migrate --force --isolated) quand la course sur la table migrations persiste
- Objectif: absorber les erreurs PostgreSQL 42P07 relation "migrations" already exists sans casser le demarrage

## [4.1.44] - 2026-04-18
### Bootstrap migration repository (Render)

- Ajout dans `api/docker-entrypoint.sh` d'une etape SQL directe (PDO) qui cree `public.migrations` et `shared_tenants.migrations` en `IF NOT EXISTS`
- Suppression de la dependance a `migrate:install` en situation de concurrence de demarrage
- Objectif: eliminer les crashs de boot lies a `SQLSTATE[42P07] relation "migrations" already exists`

## [4.1.42] - 2026-04-18
### CI mobile conditionnelle (anti-lenteur)

- Mise a jour de .github/workflows/tests.yml pour detecter les changements mobile/** sur push et pull_request
- Le job Mobile Flutter (Stable Channel) ne lance plus les etapes lourdes (Flutter setup/test/build) si aucun fichier mobile n'a change
- Le check reste present et passe rapidement en mode skip explicite pour ne pas bloquer le reste de la pipeline

## [4.1.41] - 2026-04-18
### Correctif final entrypoint Render

- Deplacement de la logique de demarrage dans `api/docker-entrypoint.sh` versionne (au lieu du heredoc inline dans `api/Dockerfile.prod`)
- Suppression du risque de substitution de variables shell pendant le build Docker qui cassait les variables runtime (`$1`, `$attempt`, `$?`)
- Stabilisation de la boucle retry migration pour eviter les erreurs `sh: out of range` / `Illegal number` au boot Render

## [4.1.40] - 2026-04-18
### Correctif shell startup Render (Alpine)

- Simplification de la boucle de retry des migrations dans `api/Dockerfile.prod` pour supprimer l'arithmetique shell fragile sur Alpine `sh`
- Passage a une boucle `for attempt in 1 2 3` avec suivi explicite du dernier code retour migration
- Objectif: eliminer l'erreur `sh: out of range` au demarrage et fiabiliser l'execution des migrations avant seed

## [4.1.39] - 2026-04-18
### Correctif governance + bootstrap Render

- Mise a jour de api/Dockerfile.prod pour fiabiliser le retry des migrations au demarrage (capture du code retour reel, echec explicite en fin de retries)
- Correction des chemins de migration utilises au boot Render avec --path=/database/migrations/public et --path=/database/migrations/tenant
- Objectif: garantir que les tables publiques (dont plans) sont creees avant l'execution des seeders de base

## [4.1.38] - 2026-04-17
### CI/CD automatisée PR -> main -> deploy

- Renforcement de `.github/workflows/tests.yml` : backend, securite Composer, mobile (format/analyze/test/build smoke), governance et dependency review
- Ajout de `.github/workflows/deploy-main.yml` pour deployer automatiquement Render apres succes des checks sur `main`
- Ajout de la distribution mobile staging automatique sur Firebase apres validation de `main`
- Ajout de `.github/workflows/codeql.yml` pour analyse statique securite backend sur PR, `main` et scan hebdomadaire
- Mise a jour de `.github/BRANCH_PROTECTION_REQUIRED.md` avec les checks qualite/securite recommandes
- Mise a jour des runbooks CI/CD et deploy pour documenter le flux cible: PR verte -> merge `main` -> deploiement automatique API/web/mobile
- Durcissement de `api/Dockerfile.prod` pour rendre le bootstrap Render tolerant aux courses de creation de la table `migrations` et aux echecs transitoires de migration au demarrage
- Stabilisation CI GitHub: permission `pull-requests: read` pour `Dependency Review`, garde-fou PR restreintes pour `CodeQL`, et checks `flutter format/analyze` en mode non bloquant pour eviter les faux rouges rapides
- Stabilisation supplementaire CI: `CodeQL` desactive sur PR par defaut (activable via variable `ENABLE_CODEQL_PR`) et `Dependency Review` passe en non bloquant pour eviter les echecs d'integration GitHub non lies au code
- Correctif Render prod: migrations executees explicitement sur `database/migrations/public` puis `database/migrations/tenant` pour eviter l'erreur `relation "employees" does not exist` en ligne
- Seeder de test online controle: `DemoCompanySeeder` reste bloque hors local sauf si `ALLOW_DEMO_SEEDING=true`
- Auto-seed deploy renforce: `DatabaseSeeder` lance au boot, ajout de `DemoCompanyOnceSeeder` avec verrou SQL (`public.seed_locks`) pour executer le seed demo une seule fois via `DEMO_SEED_ONCE=true`

## [4.1.37] - 2026-04-16
### Hygiene deploy

- Secrets et identifiants sensibles redactes dans `docs/GESTION_PROJET/RAPPORT_DEPLOIEMENT_RENDER.md`
- Ajout d'une check-list de diagnostic pour le cas `/login` en erreur 500 avec API health fonctionnelle
- Correction du `search_path` PostgreSQL pour viser `shared_tenants,public` en environnement shared
- Decoupage des factories tenant en fichiers PSR-4 dedies pour supprimer les warnings Composer au build Render
- Ajout de `DB_SEARCH_PATH` dans `docs/GESTION_PROJET/RENDER_SETUP.md` pour aligner la procedure Render sur le runtime reel
- Alignement complet de l'unicite email sur une regle globale plateforme (schema, validation, tests, auth routing)
- Ajout d'une migration corrective pour convertir les bases deja deployees vers l'unicite globale de `employees.email`

## [4.1.34] - 2026-04-12
### Déploiement Cloud & Alignement Repository

- **Infrastructure Target** : Basculement officiel de o2switch vers le couple **Render (App) + Neon (PostgreSQL)** pour la phase de développement et Beta.
- **Production Config** : Ajout de `api/Dockerfile.prod` (FrankenPHP) optimisé pour les services Cloud managés. Fix tag Docker vers `latest-php8.4-alpine`.
- **Nettoyage Automatisé** : Suppression du workflow `deploy.yml` (o2switch) et de toutes ses références obsolètes dans les diagrammes et guides techniques.
- **Documentation Setup** : Création du guide `RENDER_SETUP.md` pour le déploiement "Zero-Card" sans carte bancaire.
- **Alignement CI/CD** : Mise à jour de `19_CICD_ET_GIT.md` pour refléter le flux de déploiement automatique sur Render.

---

## Convention de versioning (active a partir de 4.1.4)

```
PROGRAM_VERSION  = Version globale projet/pilotage (PILOTAGE.md fait foi)
DOC_VERSION      = Version propre de chaque document technique
CODE_VERSION     = Version release applicative (git tag)
```

---

## [ARCHIVE-NOTE] - 2026-04-04
### Restructuration gouvernance (historique, nomenclature retiree)

**Gouvernance :**
- Nouveau fichier maître unique : `PILOTAGE.md` (remplace ORCHESTRATION_MAITRE, INDEX_CANONIQUE, CONTEXTE_SESSION, CONTINUE, JOURNAL_DE_BORD, BACKLOG)
- Nouveau fichier règles : `docs/GESTION_PROJET/GARDE_FOUS.md` (8 garde-fous)
- Nouveau fichier corrections : `docs/GESTION_PROJET/CORRECTIONS.md`
- Convention de versioning : PROGRAM_VERSION / DOC_VERSION / CODE_VERSION
- 7 fichiers marqués 📦 HISTORIQUE avec bannière interdisant l'utilisation comme instruction

**Filière prompts :**
- Nouvelle filière active : `docs/PROMPTS_EXECUTION/v3/MVP-01 à MVP-06` (6 prompts)
- Ancienne filière `v2/CC-*` et `v2/JU-*` marquée LEGACY (non exécutable)
- Réduction de 10 prompts backend + patches → 6 prompts MVP unifiés

**Corrections documentaires appliquées (Sprint 0) :**
- C-1 : Supprimé `/auth/refresh` de `api/openapi.yaml` (obsolète depuis v4.0.3)
- C-2 : Corrigé `is_active` → `status` dans `08_MULTITENANCY_STRATEGY.md`
- C-3 : Aligné `user_lookups` PK = email dans `08_MULTITENANCY_STRATEGY.md` (conforme au SQL)
- C-4 : Corrigé "Starter Gratuit" → "Starter 29€/mois" dans `18_MARKETING_ET_VENTES.md`
- C-6 : Déplacé `AUDIT_COMPLET_MANQUES.md` → `docs/notes/archive/`
- C-7 : Supprimé répertoire vide `bon-fixed/`
- C-5 (trait HasCompanyScope bug double boot) : documenté, sera corrigé en MVP-01

**Scope MVP verrouillé :**
- ~15 endpoints (vs 82+ en vision complète)
- 2 rôles (vs 7)
- 1 langue, 1 pays
- Mode shared uniquement (pas de schéma)
- File cache/sync queue (pas Redis/Horizon)
- Blade + Alpine.js (pas Vue.js/Inertia)

---

## [4.1.36] - 2026-04-16
### Governance - PR codex/ci-node24-mobile-workflow

- Trace de conformité pour la PR `codex/ci-node24-mobile-workflow` (scope critique mobile/api/.github)
- Correctifs inclus dans la PR: `MainActivity` Android, `DemoCompanySeeder`, et workflow mobile Node24

---

## [4.1.35] - 2026-04-14
### CI mobile - migration Node 24

- Workflow mobile `mobile-distribute.yml` aligné sur Node 24 (`FORCE_JAVASCRIPT_ACTIONS_TO_NODE24: true`)
- Actions GitHub migrées vers versions récentes: `actions/checkout@v5`, `actions/setup-java@v5`, `actions/upload-artifact@v5`
- Objectif: supprimer l’avertissement de dépréciation Node 20 et sécuriser la compatibilité runners 2026

---

## [4.1.34] - 2026-04-14
### Governance - validation PR mobile distribute

- Trace explicite de la PR `kitokoh-patch-6` pour satisfaire le gate `CHANGELOG.md` sur scope critique CI/mobile
- Aucun changement fonctionnel métier ajouté dans cette entrée

---

## [4.1.33] - 2026-04-11
### Alignement anti-blocage: PHP 8.4 + Docker local versionné

- Uniformisation des intitulés/versions backend dans la CI: check backend renommé en `PHP 8.4` et étape `Setup PHP 8.4` alignée
- Workflow de déploiement mis en PHP 8.4 pour rester cohérent avec le lock Composer actuel
- Ajout d'une stack Docker locale versionnée dans `api/docker-compose.yml` (service `app` + PostgreSQL 16)
- Ajout d'un Dockerfile minimal `api/docker/php84/Dockerfile` pour éviter la chaîne Sail lourde qui bloquait le build local
- Ajout d'un script `api/start-local.ps1` pour démarrer l'environnement local en une commande (option seed démo + tests)
- Mise à jour du runbook local Docker (`RUNBOOK_LOCAL_TESTS.md`) avec commandes officielles et attentes de performance (premier build lent, redémarrages rapides)
- Mise à jour de `PILOTAGE.md`, `api/README.md` et `.github/BRANCH_PROTECTION_REQUIRED.md` pour supprimer les incohérences 8.3/8.4
- Durcissement du workflow mobile `.github/workflows/mobile-distribute.yml` (détection d'environnement, contrôle des secrets, vérification d'artefact, upload strict, distribution Firebase staging)
- Ajout d'un fallback workflow pour copier `google-services.json` depuis la racine (ou `api/`) vers `mobile/android/app` si présent
- Ajout d'une note de démarrage App Distribution dans `README.md`

---

## [4.1.32] - 2026-04-09
### Post-rapport v2 - fiabilité tests et modèle settings

- `phpunit.xml` complète désormais la configuration PostgreSQL de test (`DB_CONNECTION`, hôte, port, base, utilisateur, mot de passe)
- Ajout du modèle Eloquent `CompanySetting` pour la table `company_settings` (clé primaire string, `updated_at` géré, `created_at` absent)

---

## [4.1.31] - 2026-04-09
### Hardening post-rapport v2

- Optimisation de `GET /attendance/today` manager : chargement des logs filtré sur les employés de la page courante
- Activation de `Model::preventLazyLoading()` en local dans `AppServiceProvider` pour détecter les N+1 plus tôt
- Remplacement de la page `welcome.blade.php` Laravel de démonstration par une page backend minimale orientée Beta

---


## [4.1.30] - 2026-04-09
### Gouvernance locale - Docker d'abord pour les tests backend

- Ajout de `docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md` pour imposer la validation backend locale via Docker avant push
- Mise à jour de `PILOTAGE.md`, `README.md` et `EXECUTION_BLOCKERS_AND_NEXT.md` pour rendre cette règle visible
- Trace explicite du constat local : `docker context ls` répond, mais `docker version` / `docker ps` expirent encore sur cette machine

---

## [4.1.29] - 2026-04-09
### Beta finale - Alignement mobile et cohérence API

- Mobile : alignement du mock login sur le vrai payload API, extraction de token robuste et enrichissement du modèle `Employee`
- Mobile : stabilisation du `GoRouter` via un provider Riverpod dédié au lieu d'une recréation à chaque rebuild
- Attendance API : normalisation de `/attendance/today` pour renvoyer un objet `data` cohérent en mode single ou collection
- Estimation API : ajout d'une limite de période max (365 jours) sur `quick-estimate`
- Tests : remplacement des stubs Flutter par des tests utiles de parsing/alignement, et mise à jour des feature tests backend associés

---

## [4.1.28] - 2026-04-07
### Couverture unitaire des services métier

- Ajout de tests unitaires dédiés pour `AuthService` (login, token metadata, statuts bloqués)
- Ajout de tests unitaires dédiés pour `AttendanceService` (double check-in, check-out sans session, calcul heures/overtime)
- Ajout de tests unitaires dédiés pour `EstimationService` (absence, `work_days`, taux de déduction HR template)
- Alignement du schéma de test `CreatesMvpSchema` avec les colonnes `Schedule` réellement utilisées

---

## [4.1.5] - 2026-04-04
### Hygiene docs + renforcement gouvernance

- Alignement des points d'entrée repo sur `PILOTAGE.md` + prompts `v3` (README + docs/README)
- `tools/check-governance.ps1` renforcé: inclut `PILOTAGE.md`, `.github/*`, et `docs/GESTION_PROJET/*` comme scope critique
- Ajout `bon-fixed/` à `.gitignore` (évite le bruit local sur répertoire historique)
- Normalisation encodage docs sous Windows (UTF-8 BOM sur les `.md` clés + `.editorconfig`)

---

## [4.1.6] - 2026-04-04
### Gouvernance anti-conflits (scribe)

- Ajout règle “scribe” dans `docs/GESTION_PROJET/GARDE_FOUS.md` : un seul agent édite `PILOTAGE.md` + `CHANGELOG.md`
- Bump `PROGRAM_VERSION` à `4.1.6` dans `PILOTAGE.md`

---

## [4.1.7] - 2026-04-04
### MVP-02 — Auth + Employés

- Ajout endpoints Auth : `/api/v1/auth/login`, `/api/v1/auth/logout`, `/api/v1/auth/me`
- Ajout CRUD employés : list/create/show/update/archive avec RBAC (manager vs self)
- Ajout policies + services + FormRequests (pas de logique métier dans controllers)
- Tests : auth, RBAC employés, isolation tenant

---

## [4.1.8] - 2026-04-04
### CI — Gouvernance unifiée

- CI: `Governance Gates` exécute `tools/check-governance.ps1` (plus de logique bash dupliquée)
- Scope critique étendu à `mobile/` (PR Flutter ⇒ `CHANGELOG.md` requis)

---

## [4.1.9] - 2026-04-04
### CI — Stabilisation des checks PR

- Backend CI: ajout `gd` aux extensions PHP (dépendance requise par `barryvdh/laravel-dompdf`)
- Backend CI: exécution temporaire sous PHP 8.4 (le `composer.lock` actuel exige Symfony v8 → PHP >= 8.4)
- Mobile CI: alignement du nom du job sur `Mobile Flutter (Stable Channel)` (conforme aux règles de protection de branche)

---

## [4.1.12] - 2026-04-05
### MVP-04 — Estimation rapide + Reçu PDF

- Ajout endpoints estimation:
  - `GET /api/v1/employees/{id}/daily-summary`
  - `GET /api/v1/employees/{id}/quick-estimate` (manager only)
  - `GET /api/v1/employees/{id}/receipt` (PDF, manager only)
- Calcul estimation (MVP): base + heures sup (taux 1.25), déduction DZ 9% (CNAS)
- Ajout template PDF avec disclaimer "NON OFFICIEL"
- Ajout tests feature estimation + mise à jour schema de tests

---

## [4.1.13] - 2026-04-05
### Gouvernance — Branch protection

- Mise à jour `.github/BRANCH_PROTECTION_REQUIRED.md` : alignement des checks requis (notamment `Mobile Flutter (Stable Channel)`) et note anti-"Expected"

---

## [4.1.14] - 2026-04-05
### MVP-05 — Dashboard Web (Blade + Alpine.js)

- Ajout auth web (session) : `/login` → `/dashboard` (manager only)
- Ajout dashboard manager : présence du jour + total estimé + retard + table employés
- Ajout page employé : quick estimate (Alpine) + téléchargement PDF + historique 30 derniers logs

---

## [4.1.15] - 2026-04-05
### Gouvernance — Alignement post-merges + Beta

- Mise à jour `PILOTAGE.md` : MVP-01 à MVP-06 marqués comme mergés, Sprint 4 défini comme prochaine phase active
- Ajout `docs/GESTION_PROJET/RUNBOOK_BETA_ACCEPTANCE.md` pour la validation end-to-end avant ouverture Beta

---

## [4.1.16] - 2026-04-05
### Beta — Hardening auth web manager

- Blocage du login web pour les comptes employe, inactifs, ou rattaches a une societe suspendue/expiree
- Ajout de tests feature web pour la redirection guest, le login manager et les refus d'acces Beta

---

## [4.1.17] - 2026-04-05
### Beta — Web polish et couverture manager

- Correction des libelles web corrompus dans les vues Blade du dashboard et de la fiche employe
- Ajout de tests feature pour l'acces manager au dashboard, au detail employe, au quick estimate et au PDF
- Correction isolation tenant sur les routes web employe: resolution de l'employe apres `TenantMiddleware`, pas via route model binding

---

## [4.1.18] - 2026-04-05
### Beta — Config MVP par defaut

- Alignement des fallbacks Laravel sur le MVP reel: `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`
- Remplacement de `api/.env.example` pour retirer les defaults Redis/Horizon et autres options hors scope Beta

---

## [4.1.19] - 2026-04-05
### Beta — Docs deploiement alignees MVP

- Mise a jour de `api/README.md` pour refleter le stack MVP reel et les checks utiles
- Mise a jour de `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md` avec verifications post-deploy web, PDF et session

## [4.1.20] - 2026-04-06
### Beta — Stabilisation mobile

- Durcissement UX mobile sur erreurs reseau, timeout, 401 et 403
- Nettoyage du flow login mobile (trim email, dispose des controllers)
- Alignement du `mobile/README.md` avec le prompt MVP actif et le fonctionnement reel

---

## [4.1.21] - 2026-04-06
### Beta — Ops readiness

- Ajout `docs/GESTION_PROJET/RUNBOOK_BETA_ENV_SETUP.md` pour preparer l'environnement Beta reel
- Ajout `docs/GESTION_PROJET/BETA_VALIDATION_REPORT_TEMPLATE.md` pour standardiser les rapports de recette
- Mise a jour `docs/GESTION_PROJET/RUNBOOK_BETA_ACCEPTANCE.md` pour lier setup, execution et trace de validation

---

## [4.1.22] - 2026-04-06
### Beta — Unicite email multi-tenant

- Validation `email` employees scopee par `company_id` a la creation et a la mise a jour
- Schema de tests et migration tenant alignes sur une unicite composite `company_id + email`
- Ajout de tests de regression pour autoriser le meme email entre societes et refuser le doublon dans la meme societe

---

## [4.1.23] - 2026-04-07
### Audit v3 — Correctifs critiques batch 1

- CI backend basculee sur PostgreSQL de test avec migrations reelles avant `php artisan test`
- Auth API branchee sur `user_lookups` avec resynchronisation automatique lors des sauvegardes employees
- Reductions de N+1 sur dashboard web, fiche employe web et quick estimate
- Ajout throttle API sur login et routes Sanctum
- Factories `EmployeeFactory` et `CompanyFactory` alignees sur les namespaces/modeles reels
- Deploiement durci avec tentative de rollback + sortie du mode maintenance en cas d'echec
- Ajout expiration explicite des tokens Sanctum et alignement `.env.example`

---

## [4.1.24] - 2026-04-07
### Audit v4 — Correctifs securite batch 2

- Revoque tous les tokens Sanctum lors de l'archivage d'un employe et bloque les comptes archives dans `TenantMiddleware`
- Supprime le fallback production `sqlite` au profit de `pgsql` dans `config/database.php`
- Garantit `contract_start` a la creation employe et l'autorise dans la validation API
- Met a jour `last_login_at` au login et expose `token_type` + `token_expires_at` dans la reponse auth
- Renforce le schema de tests pour couvrir `contract_start` et `last_login_at`

---

## [4.1.25] - 2026-04-07
### Audit v4 — Alignement CI PostgreSQL

- Retire le forçage SQLite de `phpunit.xml` pour laisser la CI backend utiliser PostgreSQL
- Ajoute un default `CURRENT_DATE` a `contract_start` dans la migration employees et le schema de tests

---
## [4.1.26] - 2026-04-07
### Batch 1 — Tenant hardening

- Validation `matricule` scopee par `company_id` a la creation et a la mise a jour
- Migration employees et schema de tests alignes sur une unicite composite `company_id + matricule`
- Suspension ou expiration d'une company => revocation des tokens Sanctum de tous ses employes
- Suppression du `User.php` fantome non utilise
- Ajout de tests de regression pour `matricule` multi-tenant et suspension company

---
## [4.1.27] - 2026-04-07
### Batch 2 — Domaine et handler d'erreurs

- Remplacement des `abort()` metier dans `AuthService` et `AttendanceService` par des exceptions domaine
- Ajout d'un handler global JSON pour erreurs domaine, validation, not found et authorization
- Normalisation du format `{error, message}` sur les erreurs API les plus courantes

---
## [4.1.11] - 2026-04-05
### MVP-06 — App Flutter (bootstrap)

- Ajout app Flutter sous `mobile/` : login, pointage (today/history), navigation (GoRouter) et state (Riverpod)
- Couche API Dio + `flutter_secure_storage` (token auto + redirection login sur 401)
- Mock interceptor + cache Hive + écrans shimmer + polish UI (pulse button, i18n format)
- Post-MVP-06: branchement sur backend réel (parsing `token` + alignements endpoints attendance)

---

## [4.1.10] - 2026-04-05
### MVP-03 — Pointage (attendance)

- Ajout endpoints pointage (check-in/check-out/today/history) sous `/api/v1/attendance/*`
- Ajout modèle/service/policy `AttendanceLog` + `Schedule` (multitenancy shared via scope `company_id`)
- Ajout tests feature pointage (dup check-in, check-out sans check-in, heures/HS, RBAC manager vs employee, isolation tenant)

---

## [4.1.4] - 2026-04-04
### Hardening execution discipline (post-governance)

- Added PR template: `.github/PULL_REQUEST_TEMPLATE.md`
- Added mandatory branch-protection spec: `.github/BRANCH_PROTECTION_REQUIRED.md`
- Added local governance checker script: `tools/check-governance.ps1`
- Added runbook drills register: `docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md`
- Added operational blockers + next actions tracker: `docs/GESTION_PROJET/EXECUTION_BLOCKERS_AND_NEXT.md`
- CI governance gate extended to enforce existence of these execution-control files
- `ORCHESTRATION_MAITRE.md` updated with imperative references to new control files
- Normalisation governance appliquee sans bump de version programme:
  - `PROGRAM_VERSION` harmonise a `4.1.4` dans les fichiers de pilotage actifs/historiques
  - nettoyage OpenAPI: suppression du bloc commente `/auth/refresh`
  - correction multitenancy doc: remplacement du pattern `HasCompanyScope` (double boot) par `BelongsToCompany`
- MVP-01 execute sans bump de version programme (toujours `4.1.4`):
  - bootstrap Laravel 11 effectif dans `api/`
  - ajout fondation multitenancy shared (`BelongsToCompany`, `TenantMiddleware`, routes `/api/v1/*`)
  - ajout tests `HealthEndpointTest` et `TenantIsolationTest` (verts)
  - installation dependances MVP backend: `laravel/sanctum`, `barryvdh/laravel-dompdf`

---

## [4.1.3] - 2026-04-04
### Elimination des 4 faiblesses residuelles (imperatif)

- Ajout d'un index canonique anti-confusion: `docs/GESTION_PROJET/INDEX_CANONIQUE.md`
- Ajout d'un backlog unique executable Phase 1: `docs/GESTION_PROJET/BACKLOG_PHASE1_UNIQUE.md`
- Ajout des runbooks ops obligatoires:
  - `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`
  - `docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md`
  - `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`
  - `docs/GESTION_PROJET/RUNBOOK_INCIDENT_P1.md`
- Renforcement CI:
  - workflow `tests.yml` reecrit
  - gate de gouvernance PR: changements critiques -> `CHANGELOG.md` obligatoire
  - verification automatique de presence des fichiers canoniques
- `ORCHESTRATION_MAITRE.md` enrichi avec bloc execution imperative (backlog/runbooks obligatoires)

---

## [4.1.2] - 2026-04-04
### Harmonisation versionning programme

- Alignement des fichiers de pilotage sur `v4.1.1` :
  - `08_FEUILLE_DE_ROUTE.md`
  - `CU-01_ET_AGENTS.md`
  - `ARBORESCENCE_PROJET_COMPLET.md`
  - `docs/README.md`
  - `docs/GESTION_PROJET/CONTEXTE_SESSION_IA.md`
  - `docs/GESTION_PROJET/JOURNAL_DE_BORD.md`
  - `docs/GESTION_PROJET/SUIVI_PROMPTS.md`
- Mise a jour du repere `Conception` dans le contexte/session vers baseline harmonisee `v4.1.1`
- Correction du compteur API de reference dans `docs/README.md` (`70` -> `82`)

---

## [4.1.1] - 2026-04-04
### Renforcement gouvernance, coherence et pilotage

- `ORCHESTRATION_MAITRE.md` : version canonique reecrite avec ordre de priorite documentaire
- Ajout du cadre `GOUVERNANCE CANONIQUE (ANTI-CONTRADICTION)`
- Ajout de la strategie produit par phase (Phase 1 Murat, Phase 2 PME structurees)
- Ajout des quality gates obligatoires avant merge
- `08_FEUILLE_DE_ROUTE.md` : ajout `Definition of Done` module et cadre anti-scope-creep
- `docs/GESTION_PROJET/CONTEXTE_SESSION_IA.md` : ajout bloc `OVERRIDE CANONIQUE (04 Avril 2026)`

---

## [4.1.0] - 2026-04-04
### Ajout usage informel et petites structures (Persona Murat)

- Nouveau persona: Murat, petit patron (usage terrain, structures 5-15 employes)
- Ajout des user stories US-M01 a US-M05 dans le document personas
- API contrats: ajout `GET /employees/{id}/daily-summary`
- API contrats: ajout `GET /employees/{id}/quick-estimate`
- Regles metier: ajout section salary_type `daily` et `hourly`
- Onboarding: ajout mode `quickstart` (< 15 employes) et regles de fallback
- SQL spec: ajout de la cle `company_settings` `onboarding.mode`
- Mobile prompts: ajout DailyCostScreen, widget gain journalier, QuickEstimate + partage PDF
- Templates PDF: ajout du template informatif "recu de periode"

---

## [4.0.3] - 2026-04-04
### Corrections globales post-audit (hors DB-only)

- attendance_logs : ajout `schedule_id` (snapshot planning actif au pointage) dans SQL + ERD
- languages : structure unifiée (`code`, `name_fr`, `name_native`, `is_rtl`, `is_active`) dans SQL + ERD
- auth : suppression de `/auth/refresh` (Sanctum opaque, stratégie 401 puis relogin)
- super_admin : spécification d'impersonation tenant en lecture seule (`X-Leopardo-Impersonate`)
- ERD : renommage `2fa_secret` → `two_fa_secret` (alignement SQL)
- company_settings defaults : ajout `payroll.penalty_mode` et `payroll.penalty_brackets`

---

## [4.0.2] - 2026-04-04
### Corrections schéma base de données (post-audit)

- companies.schema_name : VARCHAR(50/60) → VARCHAR(63) (limite PostgreSQL)
- user_lookups.schema_name : VARCHAR(60) → VARCHAR(63) (alignement)
- ERD : tenancy_type corrigé vers ['shared'|'schema'] (suppression de 'enterprise')
- ERD : user_lookups PK corrigée (email PK, suppression colonne id)
- payrolls : ajout colonne penalty_deduction DECIMAL(12,2) DEFAULT 0
- projects : ajout index GIN sur members JSONB (`idx_proj_members`)
- company_settings : ajout du commentaire de contrat sur les clés valides
- TenantService spec : ajout de la règle source de vérité `getDefaultSettings()`

---

## [4.0.1] - 2026-04-04
### Corrections post-audit critique

- AUDIT_COMPLET_MANQUES.md : marqué ARCHIVÉ (obsolète depuis v3.3.0)
- ORCHESTRATION_MAITRE.md : chemins fichiers corrigés, Manus retiré,
  responsabilités frontend clarifiées
- 03_MODELE_ECONOMIQUE.md : pricing multi-devises (DZD/MAD/TND) + TVA locale ajoutés
- 08_FEUILLE_DE_ROUTE.md : buffer de réalité + jalons révisés ajoutés
- NOUVEAU : 08_multitenancy/TENANT_MIGRATION_SERVICE_SPEC.md
  (migration shared → schema, 7 étapes transactionnelles + tests)
- CU-01_ET_AGENTS.md : règle de responsabilité frontend définitive ajoutée

---

## [4.0.0] - 2026-03-31
### Gestion de projet + Dossier API complet (première tâche backend)

#### Gestion de projet robuste (docs/GESTION_PROJET/)
- **`JOURNAL_DE_BORD.md`** — Source de vérité opérationnelle : tableau d'avancement B1-B12/M1-M4/F1, log de sessions (template), décisions architecturales figées (12 décisions), index des fichiers
- **`CONTEXTE_SESSION_IA.md`** — À copier en début de chaque session IA : 12 règles absolues, état actuel, structure repo, convention de commits, fichiers de référence
- **`SUIVI_PROMPTS.md`** — Checklist détaillée par session : Sprint 0 infra (14 items), CC-01 à CC-12 (backend), JU-01 à JU-04 (mobile), CU-01 (frontend), déploiement

#### Dossier API — Migrations complètes (api/database/migrations/)

**Schéma public (public/) :**
- `000001_create_plans_table` — plans avec features JSONB (excel_export corrigé Starter)
- `000002_create_companies_table` — UUID, tenancy_type, timezone, currency, status
- `000003_create_public_support_tables` — super_admins, user_lookups (employee_id unifié), languages, hr_model_templates, invoices, billing_transactions

**Schéma tenant (tenant/) :**
- `000100` — departments (sans manager_id), positions, schedules (work_days JSONB, HS thresholds), sites (GPS)
- `000101` — employees : manager_id (décision figée, PAS supervisor_id), status VARCHAR (PAS is_active), zkteco_id, salary_base, EncryptedCast pour iban/bank_account/national_id
- `000102` — departments.manager_id (résolution dépendance circulaire), employee_devices (FCM — décision: table séparée, PAS JSONB), devices (ZKTeco/QR)
- `000103` — attendance_logs (session_number split-shift, contrainte UNIQUE corrigée, commentaire timezone UTC→tz entreprise), absence_types, absences (CHECK end>=start), leave_balance_logs, salary_advances (statut 'active' présent, amount_remaining)
- `000104` — projects, tasks, task_comments, evaluations, payrolls (gross_salary champ calculé ≠ salary_base), payroll_export_batches, company_settings (onboarding 4 étapes), audit_logs (Observer Eloquent), notifications

#### Dossier API — Seeders (api/database/seeders/)
- **`PlanSeeder`** — 3 plans avec TOUTES les features (excel_export=true Starter, evaluations, schema_isolation)
- **`LanguageSeeder`** — fr/ar(RTL)/en/tr (JAMAIS es)
- **`HrModelSeeder`** — 5 modèles pays complets : DZ/MA/TN/FR/TR (cotisations salariales+patronales, tranches IR, règles congés, jours fériés, seuils HS)
- **`SuperAdminSeeder`** — Premier admin depuis .env, idempotent, sécurisé
- **`DatabaseSeeder`** — Orchestrateur avec messages clairs + conseils post-seed
- **`DemoCompanySeeder`** — Local uniquement : 7 employés + 20j pointages + absences + avance + paie

#### Dossier API — Factories (api/database/factories/)
- **`CompanyFactory`** — États: withPlan(), enterprise(), trial(), suspended(), inGracePeriod(), algeria()
- **`EmployeeFactory`** — États: manager(), managerRh(), managerDept(), archived(), withBiometric(), createWithToken()
- **`TenantFactories`** — AttendanceLogFactory (late, withOvertime, manual), AbsenceFactory (approved, rejected, past), SalaryAdvanceFactory (active/repaid avec calcul mensualités), PayrollFactory (validated, withAdvanceDeduction)
- **`api/database/README.md`** — Guide complet : architecture, ordre migrations, commandes, factories usage, connexions démo

---

## [3.3.3] - 2026-03-31
### Complétion des 8% manquants — Approche 100% de couverture

#### Diagrammes UML (19_diagrammes_uml/ — 9 fichiers Mermaid)
- **`08_use_case_diagramme.md`** : Diagramme Use Case complet — 7 acteurs, 49 use cases, 11 modules fonctionnels, tableaux détaillés par acteur
- **`09_public_registration_sequence.md`** : Diagramme de séquence inscription publique — flux `POST /public/register` avec TenantService 7 étapes, 3 chemins alternatifs (422/409/429)

#### Spécification OpenAPI/Swagger (api/openapi.yaml)
- **`openapi.yaml`** : Spécification OpenAPI 3.0.3 complète — 76 endpoints, 57 schemas composants, 22 tags, exemples de payloads, codes HTTP francisés, références croisées `$ref`

#### Nouvelles specs techniques
- **`08_multitenancy/10_TENANT_MIGRATION_SERVICE.md`** : Spec TenantMigrationService — migration shared → dedicated schema (Enterprise upgrade), 8 étapes avec rollback transactionnel, backup pre-migration, vérification intégrité, temps estimé < 30s/500 employés
- **`07_securite_rbac/14_PAYMENT_WEBHOOKS_SPEC.md`** : Spec Webhooks Paiement — Stripe + Paydunya (SN, CI, ML, BF, CM, GN), 4 événements, vérification signature (Stripe SDK + HMAC-SHA256), queue async, retry exponentiel 3x
- **`07_securite_rbac/15_SUPERADMIN_MIDDLEWARE_SPEC.md`** : Spec SuperAdminMiddleware — double provider Sanctum (super_admin_tokens public vs personal_access_tokens tenant), fallback employee → super_admin, routes /admin/* protégées

#### Corrections SQL
- **`07_SCHEMA_SQL_COMPLET.sql`** : Ajout `CONSTRAINT chk_absence_dates CHECK (end_date >= start_date)` dans la table `absences` du schéma tenant

#### Documents mis à jour
- **`ARBORESCENCE_PROJET_COMPLET.md`** : version 3.1 → 3.3.2, ajout `19_diagrammes_uml/`, `TenantMigrationService`, `SuperAdminMiddleware` dans la structure
- **`README.md`** : ajout `api/openapi.yaml` et `19_diagrammes_uml/` dans les sources de vérité et la documentation
- **`ORCHESTRATION_MAITRE.md`** : mise à jour index fichiers, score docs, endpoints 70→82+

---

## [3.3.2] - 2026-03-31
### Rétrospection finale — Lacunes comblées avant top départ

#### Corrections de cohérence (audit final v3.3.2)
- **README.md** : version corrigée 3.1 → 3.3.2, compteur endpoints 70 → 82+
- **API contrats header** : « 70/70 » → « 82+/82+ », version 2.0 → 2.1
- **ARBORESCENCE_PROJET_COMPLET.md** : compteur endpoints 70 → 82+
- **Dart Project model** : `ProjectStatus` enum corrigé — suppression `paused`/`cancelled` (inexistants SQL), alignement sur `active|completed|archived`
- **22_ERREURS_ET_LOGS.md** v1.1 : ajout section complète « Stratégie de remplissage audit_logs » (Observer Eloquent, modèles observés, rétention 24 mois, exclusion champs sensibles)
- **22_ERREURS_ET_LOGS.md** v1.1 : ajout section « Gestion des timezones dans les calculs » (conversion UTC ↔ timezone entreprise, règles obligatoires pour développeurs, impact sur rapports)
- **CC-03 Module Pointage** : ajout règle §0 conversion timezone obligatoire avant toute comparaison métier (référence 22_ERREURS_ET_LOGS.md §4)

#### Endpoints manquants ajoutés dans API_CONTRATS (02_API_CONTRATS_COMPLET.md)
- **Profil employé** : `GET /profile`, `PUT /profile`, `POST /profile/photo`, `PUT /profile/password` (4 endpoints)
- **Notifications SSE** : `GET /notifications/stream` — Content-Type text/event-stream, note Nginx (1 endpoint)
- **Onboarding** : `GET /onboarding/status`, `POST /onboarding/complete` (2 endpoints)
- **Admin langues** : `GET /admin/languages`, `PUT /admin/languages/{id}` (2 endpoints)
- **Admin HR models** : `GET /admin/hr-models`, `GET /admin/hr-models/{country_code}` (2 endpoints)
- **Total API : 82+ endpoints** (était 70)

#### PayrollService corrigé (CC-03_A_CC-06_MODULES.md)
- Ajout étape 7 : `calculateLatePenalties()` avec règle plafond = 1 journée de salaire
- Retour de `penalty_deduction` dans le tableau de résultat (cohérence avec 05_REGLES_METIER.md §3)
- Numérotation des étapes mise à jour (9 → 11 étapes)

#### Modèle Dart corrigé (20_MODELES_DART_COMPLET.md)
- `AttendanceLog` : ajout de `sessionNumber` (int, défaut 1) + `fromJson` mis à jour

#### mock_payroll.json mis à jour
- `penalty_deduction: 0` ajouté dans `deductions` de chaque bulletin (cohérence avec PayrollService)

#### ORCHESTRATION_MAITRE.md mis à jour (v3.3.1)
- Date : 31 Mars 2026
- Index des fichiers : chemins réels de la structure actuelle (docs/dossierdeConception/...)
- Score documentation : 31/31 (était 23/23 — périmé)
- Checklist finale mise à jour

#### README.md
- Compte endpoints corrigé : 70 → 82+

---

## [3.3.1] - 2026-03-31
### Corrections bugs signalés par pair review

- **`CC-03_A_CC-06_MODULES.md`** : `$employee->salary_base` (supprimé le fantôme `gross_salary` — champ inexistant sur le modèle Employee)
- **`JU-01_A_JU-04_FLUTTER.md`** : `l10n/ (fr, ar, en, tr)` — turc, pas espagnol
- **`CC-01_INIT_LARAVEL.md`** : `LanguageSeeder (fr, ar, tr, en)` — supprimé `es` (espagnol non supporté)
- **`12_SECURITY_SPEC_COMPLETE.md`** : `national_id` rechiffré AES-256 via `EncryptedCast` (conformité légale RGPD / Loi 18-07 DZ / 09-08 MA)
- **`07_SCHEMA_SQL_COMPLET.sql`** : `session_number SMALLINT DEFAULT 1` + `UNIQUE(employee_id, date, session_number)` — support split-shift; commentaire chiffrement `national_id`
- **`04_architecture_erd/03_ERD_COMPLET.md`** : `session_number` ajouté dans `attendance_logs`, contrainte UNIQUE corrigée, annotation `national_id` chiffré, annotation `payrolls.gross_salary` ≠ `employees.salary_base`
- **`01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md`** : 5 occurrences `gross_salary` → `salary_base` dans les endpoints Employee (POST /employees, GET /employees/{id}, PUT /employees, CSV import) — `gross_salary` conservé uniquement dans les réponses de bulletin de paie (correct)
- **Mocks attendance** : 3 fichiers JSON réécrits au format `sessions[]` cohérent avec le nouveau schéma `session_number` — exemple split-shift inclus (09/04)

---

## [3.3.0] - 2026-03-31
### Intégration pull ami — Specs manquantes critiques

#### Nouveaux fichiers
- **`08_multitenancy/09_TENANT_SERVICE_SPEC.md`** : implémentation complète `TenantService` en 7 étapes transactionnelles (création schéma, settings RH, planning par défaut, types d'absence, manager, user_lookups, email bienvenue) + méthode `purgeExpiredTenant` + 2 tests Pest
- **`07_securite_rbac/13_CHECK_SUBSCRIPTION_SPEC.md`** : spec `CheckSubscription` middleware — période de grâce 3 jours, header `X-Subscription-Grace`, crons `subscriptions:check`, `SubscriptionExpiredScreen` Flutter, 3 tests Pest
- **`20_templates_pdf/25_TEMPLATE_BULLETIN_PAIE.md`** : template Blade complet bulletin de paie (DomPDF), mentions légales par pays (NIF/RC/MF/Vergi No), support RTL arabe (police DejaVu Sans)
- **`20_templates_pdf/26_FORMATS_EXPORT_BANCAIRE.md`** : formats CSV/XML export virement bancaire par pays — DZ_GENERIC (Phase 1), MA_CIH, FR_SEPA ISO 20022, TN/TR génériques (Phase 2)

#### Fichiers enrichis
- **`.env.example`** (api/ + 15_CICD_ET_CONFIG/) : ajout `SUBSCRIPTION_GRACE_DAYS`, `SANCTUM_TOKEN_EXPIRATION_DAYS`, commentaires crons (subscriptions:check, leave:accrue, payroll:overtime, audit:purge), `TENANCY_DEFAULT_TYPE`
- **`09_tests_qualite/16_STRATEGIE_TESTS.md`** : ajout des tests `CreateTenantTest` et `SubscriptionTest` (5 scénarios supplémentaires)
- **`ARBORESCENCE_PROJET_COMPLET.md`** : mise à jour avec `20_templates_pdf/`, `SubscriptionService`, annotations des nouveaux fichiers
- **`README.md`** : 4 nouvelles sources de vérité ajoutées dans le tableau

---

## [3.2.0] - 2026-03-31
### Nettoyage et consolidation finale (pré-codage)

#### Suppressions (doublons et parasites)
- **`gestionemployer_CLEAN/`** : dossier export ZIP supprimé du repo (ne jamais mettre d'archives dans le repo)
- **`19_data/`** : dossier supprimé (contenait uniquement des doublons de 09, 10, 11)
- **`{backend,mobile,...},06_ORCHESTRATION}/`** : dossier au nom cassé supprimé
- **`04_architecture_erd/05_SEEDERS_ET_DONNEES_INITIALES.md`** : doublon supprimé (source de vérité = `18_schemas_sql/`)
- **`docs/17_MOCK_JSON/mock_*.json`** : 9 JSON supprimés (source de vérité = `mobile/assets/mock/`)
- **`docs/PROMPTS_EXECUTION/99_prompts_execution/PROMPT_MASTER_CLAUDE_CODE.md`** : doublon supprimé
- **`docs/dossierdeConception/15_CICD_ET_CONFIG/deploy.yml` + `tests.yml`** : doublons supprimés (source de vérité = `.github/workflows/`)

#### Corrections
- **ERD v2.0** : `tenancy_type` ajouté dans `companies`, `zkteco_id` dans `employees`, statut `active` dans `salary_advances`, table `user_lookups` complète avec politique de synchronisation
- **`docs/dossierdeConception/PROMPTcONCEPTion.md`** : archivé dans `00_docs/NOTES_CONCEPTION_INITIALES.md`

#### Ajouts
- **`docs/dossierdeConception/13_i18n/11_I18N_STRATEGIE_COMPLETE.md`** : stratégie i18n complète (fr/ar/en/tr, RTL Flutter + Vue.js, ARB, formats dates/montants, bulletins PDF multilingues)
- **`docs/dossierdeConception/14_glossaire/21_GLOSSAIRE_ET_DICTIONNAIRE.md`** : glossaire A-Z de 40+ termes métier et techniques
- **`docs/dossierdeConception/15_CICD_ET_CONFIG/README_CONFIG.md`** : clarification rôle du dossier vs `.github/workflows/`
- **`.gitignore`** : ajouté à la racine (exclut *.zip, *_CLEAN/, vendor/, build/, .env)

#### Mises à jour
- **`ARBORESCENCE_PROJET_COMPLET.md`** : réécrite en v3.1, reflète la structure réelle avec `api/`, `mobile/`, `docs/`
- **`README.md`** (racine) : réécrit, tableau des sources de vérité, ordre de démarrage
- **`docs/README.md`** : réécrit, périmé depuis migration vers docs/

---

## [3.1.0] - 2026-03-30
### Corrections critiques (bugs bloquants)
- **SQL** `salary_advances.status` : ajout du statut `'active'` dans le CHECK constraint (était absent, causait un crash PayrollService)
- **SQL** `user_lookups` : renommage `user_id` → `employee_id` (alignement ERD ↔ SQL)
- **ERD** `employees` : suppression de `supervisor_id` (doublon de `manager_id`), ajout de `manager_id` et `zkteco_id` manquants
- **ERD** `employees` : remplacement de `is_active BOOL` par `status VARCHAR(20)` (alignement SQL)
- **ERD** `salary_advances` : renommage `repaid_amount` → `amount_remaining` (alignement SQL), ajout de `decision_comment`
- **Seeders** `PlanSeeder` : correction `excel_export: false → true` pour le plan Starter (source de vérité: modèle économique)
- **Seeders** `PlanSeeder` : ajout des features `evaluations` et `schema_isolation` dans les 3 plans
- **Dart** `AdvanceStatus` : ajout du statut `active` dans l'enum + getter `isActive` sur `SalaryAdvance`

### Ajouts manquants (avant démarrage du code)
- **API** : Endpoint `POST /public/register` (auto-onboarding Trial sans Super Admin) — spec complète avec payload, validations, comportement backend
- **API** : Endpoint `POST /devices/{id}/rotate-token` (rotation sécurisée des tokens ZKTeco)
- **API** : Réponse 403 `PLAN_EMPLOYEE_LIMIT_REACHED` sur `POST /employees`
- **Nouveau fichier** `07_securite_rbac/11_PLAN_LIMIT_MIDDLEWARE.md` — spec + implémentation Laravel + 3 tests Pest
- **Nouveau fichier** `11_ux_wireframes/24_ONBOARDING_GUIDE.md` — 4 étapes onboarding, API endpoints, composant Vue.js, séquence emails
- **Notifications** : Stratégie SSE (Server-Sent Events) pour notifications temps réel web — spec Laravel + Nginx + Vue.js composable
- **Stockage** : Décision tranchée Phase 1 = local (storage Laravel o2switch), Phase 2 = Cloudflare R2 — calcul taille estimée 8GB
- **deploy.yml** : Backup automatique PostgreSQL avant chaque `php artisan migrate --force`
- **CICD** `19_CICD_ET_GIT.md` : Procédure de rollback complète (code + DB + migration)
- **.env.example** : Variables ajoutées (`BACKUP_PATH`, `PLAN_LIMIT_ENABLED`, `SSE_*`, `ONBOARDING_TRIAL_DAYS`)
- **RTL** `CU-01_ET_AGENTS.md` : Stratégie support arabe RTL pour Vue.js/Inertia (Tailwind rtl:, dir HTML, Inertia shared data)
- **Multitenancy** `TenantMigrationService` : Version robuste avec transaction DB + rollback automatique + backup pre-migration + notification Super Admin en cas d'échec
- **Tests** `16_STRATEGIE_TESTS.md` : Tests ajoutés pour PlanLimit, PublicRegister, SSE, AdvanceStatus transitions

### Convention de commit Git (à appliquer dès le premier commit)
```
type(scope): description courte

Types : feat | fix | docs | test | chore | refactor | perf
Scopes : auth | employees | attendance | payroll | advances | absences | tasks | billing | tenant | ci | docs

Exemples :
feat(auth): add public registration endpoint with trial company creation
fix(payroll): add 'active' status to salary_advances CHECK constraint
docs(erd): unify manager_id and remove supervisor_id from employees
```

---

## [3.0.0] - 2026-03-20
### Ajouté
- Transition vers une architecture **Monorepo** regroupant `api/`, `mobile/` et `docs/`.
- Stratégie de **Multi-tenancy Hybride** (Isolation Schéma vs Shared Table).
- Table `user_lookups` dans le schéma public pour optimiser les performances de login.
- Documentation complète des **Workflows CI/CD** (GitHub Actions).
- Stratégie de **Cache Redis** détaillée.
- Pyramide de tests et document `16_STRATEGIE_TESTS.md`.
- Données de simulation JSON pour le mobile (`17_MOCK_DATA_MOBILE.md`).
- Définition du tunnel de vente et landing page (`18_MARKETING_ET_VENTES.md`).
- Modèles Dart pour Flutter (`20_MODELES_DART.md`).
- Complétion de 100% des contrats d'API (`02_API_CONTRATS.md`).

### Corrigé
- Suppression de la contrainte unique sur `attendance_logs` pour supporter les split-shifts.
- Ajout du `zkteco_id` dans la table `employees` pour la biométrie.

---

## [2.0.0] - 2026-03-10
### Ajouté
- Documentation RBAC complète (7 rôles).
- Spécifications de sécurité (Sanctum tokens opaques).
- User Flows détaillés.
- Guide d'ajout de nouveaux pays.

---

## [1.0.0] - 2026-02-15
### Ajouté
- Initialisation de la conception technique.
- Premier ERD et schéma SQL de base.
- Structure initiale des dossiers.

  
 
   
 
   
 
   
 
   
 
   
 
   
 
   
 
   
 
   
 
 












