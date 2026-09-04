# AGENTS.md — Historique utile & leçons (archive)

> Archivé depuis AGENTS.md le 2026-09-02 (issue #6698 — politique de placement : racine = canonique vivant, docs/archive/ = daté). Les règles actives d'AGENTS.md sont la référence ; ce fichier conserve le journal des leçons pour traçabilité.

## Historique utile

### 2026-05-24 - Vitrine proxy API, pricing et plan Jules i18n

- Pour `front/web`, le login navigateur doit passer par le proxy Next same-origin `/api/v1/[...path]` en production. Ne pas forcer `NEXT_PUBLIC_API_URL` en direct cote browser sauf avec `NEXT_PUBLIC_API_DIRECT=true`, sinon les erreurs CORS Render/Vercel reviennent.
- Le pricing vitrine n'est plus un simple tarif par employe tres bas : garder une structure SaaS credible avec forfait mensuel, employes inclus, surcout par employe, essai 30 jours et Enterprise sur devis.
- Les traductions gerees par Jules doivent rester dans les fichiers dedies de `shared/i18n`, `api/lang`, `front/admin-dashboard/src/i18n/locales` et `front/mobile_apps/leopardo_core/lib/l10n`; ne pas lui faire modifier les composants metier pour de la traduction simple.
- Le plan multilingue Jules canonique vit dans `docs/archive/PLAN_ACTION/24_PLAN_MULTILINGUE_JULES_TRANSLATION.md` avec les prompts anglais, arabe et turc.
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
- Le fichier `docs/archive/PLAN_ACTION/14_ROADMAP_EXECUTION_POST_LOTS.md` sert maintenant de roadmap actualisee apres execution des lots plateforme metrics/backend/admin. Le fichier 13 reste l'inventaire brut ; le 14 doit porter la sequence priorisee et les retours d'experience.
- Les prochaines actions GTM doivent rester connectees au wedge produit prioritaire : pointage, anomalies, rapport mensuel, onboarding et ROI client mesurable.
- `docs/GOTO_MARKET/` est aussi le centre de reflexion sur la viabilite globale : utiliser la tech pour repondre a un besoin actuel, gagner de l'argent, et ne pas hesiter a repositionner ou moderniser le produit/offre quand le marche l'exige.
- ⚠️ (Audit doc 2026-07-19) Les chemins `docs/GOTO_MARKET/public/`, `docs/GOTO_MARKET/12_PACK_LANCEMENT_ACQUISITION.md` et `docs/GOTO_MARKET/product_marketing_automation/` mentionnes historiquement dans les entrees precedentes n'existent plus dans le depot. La structure GTM reelle actuelle vit sous `docs/GOTO_MARKET/01_PRODUCT/`, `docs/GOTO_MARKET/2026_MARKET_LAUNCH_COMPANY_OS/`, `docs/GOTO_MARKET/ASSETS_PRODUCTION/` et `docs/GOTO_MARKET/GOTO_MARKET_AUDIT.md` (voir `docs/GOTO_MARKET/README.md`). Verifier l'arborescence reelle avant de referencer un sous-dossier GTM.

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
- Les services (`EmployeeService`, `AbsenceService`, etc.) sont le bon endroit pour dispatcher les events, pas les controllers. _(Ces services vivent maintenant dans `App\Modules\*/Infrastructure/Services/` — plus dans `App\Services\*` supprimé en PR #824)_
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

- Les tests mobiles ajoutes pour Plan 14 vivent dans les dossiers `test/navigation`, `test/features/mobile_surface_smoke_test.dart`, `test/repositories` et `test/golden` de chaque app mobile (ex. `front/mobile_apps/leopardo_manager/test/...`).
- Le harnais `mobile_test_harness.dart` (ex. `front/mobile_apps/leopardo_manager/test/helpers/mobile_test_harness.dart`) remplace auth, preferences et storage par des fakes Riverpod afin de tester les ecrans sans Hive, secure storage ni reseau.
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
- Les items mobile G2-G9 sont deja implementes dans Flutter (absences, contrats, formations, frais, chat IA, voice IA, carte vehicule). Avant d'ajouter un ecran mobile, verifier le dossier `lib/features/` de l'app mobile concernee sous `front/mobile_apps/`.
- Le plan 15 consolide (`docs/archive/PLAN_ACTION/15_PLAN_EXECUTION_CONSOLIDE.md`) est le document de reference pour l'avancement. Les iterations 1-11 sont documentees avec PRs, contenus et statuts.
- Backlog restant apres iteration 11 : C14 (optimisation planning), H (kiosk), J (GTM non-code), L5 (ZKTeco), L6 (calendrier sync), G8 (push Firebase), G10 (organigramme mobile).

### 2026-05-25 - Mobile pointage, equipe et avances

- L'ecran pointage mobile ne doit plus afficher un spinner de synchronisation semaine bloquant. `AttendanceRepository` limite les lectures `today/history` a des delais courts et l'historique indisponible doit rester un avertissement non bloquant.
- Les actions check-in/check-out/correction doivent rester bornees et afficher un SnackBar succes/echec clair ; ne pas relancer de retry long cote mobile qui donne l'impression que le bouton tourne sans fin.
- La creation mobile d'employe doit conserver les champs RH minimum : `contract_start`, `salary_type`, `salary_base` ou `hourly_rate`, `matricule` et `extra_data.department/job_title/work_location`. Le backend `StoreEmployeeRequest`, `EmployeeController@index` et `EmployeeResource` doivent rester alignes avec ce contrat.
- Le module mobile Avances doit proposer une vraie demande employee-side via `POST /salary-advances` avec `amount`, `reason` et `repayment_months`, puis rafraichir la liste locale.
- Depuis v4.16.138, l'ecran pointage doit utiliser `attendanceHistoryMonthKey(_now)` pour `historyProvider` ; ne jamais repasser `_now` complet comme cle provider, sinon l'historique se recharge chaque seconde avec l'horloge live.
- Les actions pointage doivent rester protegees contre doubles taps et timeout provider : un succes API ou une erreur reseau ne doit jamais laisser `isPunching=true` indefiniment.
- Depuis v4.16.139, les ecrans RH mobiles a fort impact demo (`Absences`, `Avances`, `Equipe`) doivent utiliser les composants de `front/mobile_apps/leopardo_core/lib/core/widgets/mobile_surface.dart` pour les listes, chargements et erreurs. Eviter de revenir a des `ListTile`/`AppBar` Material bruts sur ces parcours marketing-ready.
- Depuis v4.16.139, la demande d'absence mobile doit resoudre un vrai `absence_type_id` via `leaveBalancesProvider` avant `POST /absences`; ne pas hardcoder de type d'absence cote Flutter.
- Depuis v4.16.140, les annulations self-service mobile passent par les endpoints existants `DELETE /absences/{id}` et `DELETE /salary-advances/{id}` uniquement pour les demandes en attente. Garder les confirmations utilisateur, le refresh provider et les tests repository de route.
- Depuis v4.16.141, les decisions manager/RH mobiles pour absences et avances utilisent les endpoints existants `PUT /absences/{id}/approve|reject` et `PUT /salary-advances/{id}/approve|reject`. Les boutons doivent rester role/capability-aware (`principal`, `rh`, `*.manage`, `*.approve`) et ne pas apparaitre sur les demandes personnelles du manager/RH, qui restent annulables en self-service. Les refus doivent demander un commentaire, et les tests repository doivent verrouiller les routes.
- Depuis v4.16.142, la checklist mobile marketing-ready vit dans `docs/validation/MOBILE_MARKETING_READINESS.md`. Toute evolution des parcours demo mobile (login, pointage, absence, avance, equipe, decisions RH) doit mettre a jour ce guide, la matrice `docs/validation/FRONTEND_API_CONTRACT_MATRIX.md` si une route change, et le smoke `test/features/mobile_marketing_readiness_test.dart` (present dans chaque app mobile concernee sous `front/mobile_apps/`) si un comportement visible change.
- Depuis v4.16.143, la fondation multi-app mobile vit dans `front/mobile_apps/` : `leopardo_mobile_legacy` est une archive intouchable du mobile historique, `leopardo_core` porte le partage, `leopardo_employee` exclut les parcours equipe/approvals/organigramme/manager, et `leopardo_manager` conserve le perimetre complet. Toute modification partagee doit aller dans `leopardo_core`; toute modification d'ecran specifique va dans l'app concernee. La CI dediee est `.github/workflows/mobile-apps-ci.yml`.
- Depuis v4.16.144, le Plan 26 ajoute le garde canonique `dev-hub/tools/validate-mobile-apps-split.ps1`. Le lancer avant toute PR touchant `front/mobile_apps/` avec `pwsh` ou, sur Windows sans PowerShell 7, `powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-apps-split.ps1`. Ce garde bloque le retour de marqueurs manager dans `leopardo_employee`, les imports app-specifiques dans `leopardo_core`, les imports `package:leopardo_rh` dans les nouvelles apps et les modifications de `leopardo_mobile_legacy` en PR.
- Depuis v4.16.145, les deux apps mobiles ont des identites store distinctes : employee = `com.leopardo.employee`, manager = `com.leopardo.manager`. Avant de declarer les mobiles publiables, lancer aussi `dev-hub/tools/validate-mobile-release-readiness.ps1`; son mode `-StrictStores` doit rester rouge tant que les signatures release Android/iOS ne sont pas configurees.
- Depuis v4.16.180, `GET /api/v1/employees` expose `work_state` / `work_state_label` pour la vue operationnelle manager mobile. Les etats doivent rester derives du tenant courant (attendance du jour, absences approuvees, statut employe) et ne jamais afficher les donnees d'un autre tenant. Les modifications `role` / `manager_role` via `PATCH /employees/{employee}` sont reservees au manager principal; repasser `role=employee` doit nettoyer `manager_role`.

### 2026-07-19 - Audit consolide technico-commercial + TaxSlab branche dans PayrollCalculator

- Un ecran CRUD/API expose (ex. `TaxSlabController`) ne garantit pas que la donnee qu'il gere est reellement lue ailleurs dans le systeme. Avant de considerer un audit "fait" sur un point donne, verifier le site d'appel reel (grep du modele/methode dans le code de calcul/business logic), pas seulement l'existence du controller/route/seeder. `TaxSlab` avait toute la chaine admin (migration, modele, seeder, controller, routes) sans jamais etre lu par `PayrollCalculator` — invisible sans grep cible.
- `AbstractCountryRules::taxSlabs()` lit desormais la table `tax_slabs` (override `company_id` puis override global `company_id IS NULL` puis fallback code en dur `defaultTaxSlabs()`, abstract sur chaque `*PayrollRules`). `PayrollCalculator::calculateRun()` appelle `forCompany($companyId)` sur les rules avant de calculer les bulletins. Toute nouvelle regle pays doit implementer `defaultTaxSlabs()` (pas `taxSlabs()`, reserve a `AbstractCountryRules`).
- Avant de dupliquer un audit ou de relancer un futur audit "technico-commercial", lire `docs/archive/PLAN_ACTION2/27_RECONCILIATION_BACKLOG_2026-07-26.md` en premier (bilan consolidé des correctifs livrés vs ouverts) : il documente precisement quels findings des audits precedents (secu API, architecture, i18n) sont deja corriges dans le code vs encore reellement ouverts, evite de re-auditer du travail deja fait.
- Sans runtime PHP/Composer disponible dans l'environnement d'audit, tout changement touchant `PayrollCalculator`/`CountryRules` doit etre revalide par l'equipe via `php artisan test --filter=Payroll` avant merge; les tests unitaires ajoutes ici (`PayrollCountryRulesTest`) couvrent seulement le fallback sans app bootstrappee et le contrat `forCompany()`, pas un vrai override DB end-to-end.
- Main rouge 2026-08-09 (43 tests) : les causes racines étaient (1) `employees.national_id` varchar(50) vs cast `encrypted` (~230 chars) → élargi varchar(500) ; (2) `languages.updated_at` manquant (0003 créé sans, 00015 early-return) → réconcilié ; (3) tests encore calés sur le schéma manuel permissif (plan_id/first_name NOT NULL, statuts attendance_logs, PDF non compressés, PendingCommand lazy). Toute nouvelle migration touchant une table existante doit réconcilier (pattern 00015) et jamais early-return sans ALTER additif.
- Spec S-1 (#1661) : la rétention biométrique vit dans `POLITIQUE_RETENTION_DOCUMENTS.md` (v2) et la purge dans `biometric:purge-expired` (hebdo, `--company`/`--dry-run`). Règle de purge : contrat terminé depuis > N mois, OU consentement datant de > N mois quand aucune fin de contrat n'est renseignée. Ne pas purger un employé encore en poste même si son consentement est ancien.

## Lecon 2026-08-14 — QA pass plateforme : les gates locales ne mentent pas quand CI est saturee

Pendant la saturation de la file GitHub Actions (#2131), des merges/poussées directes successives ont laisse `main` **rouge latent** : les checks requis restaient pending/queued et ne s'exprimaient pas. Le QA pass local (issues #2172/#2173/#2174) a retrouve sur main : un test unitaire CI perime post-#1913 (`AbstractCountryRulesCapTest::test_ivory_coast_cnss_capped_at_1647315_xof` — la vague #1913/573c1f05 avait realise les goldens SN/CI mais oublie ce test), 43 erreurs PHPStan Strict level 8 (7 app + 36 tests, dont le docblock `compliance` du `PayrollCalculationPresenter` sans `warning_key`), la dette Pint `tests/Unit` (14 fichiers), le doublon perime `tests/Unit/Payroll/CedeaoRulesUnitTest.php` (valeurs pre-#1918 — la « suppression » annoncee par 573c1f05 n'avait jamais ete commitee), et 5 tests unitaires pays perimes (BF/ML/G A passes pilot — les loops placeholder attendaient encore `placeholder`).

Conduite a tenir quand la CI est saturee : ne jamais declarer « main vert » sur un merge recent sans relancer localement les gates (suite Unit, `phpstan-strict.neon`, `phpstan-modules.neon`, `pint --test`) ; verifier aussi les tests unitaires pays quand une vague change `confidenceLevel()` (les loops `other_*_members_unaffected` sont les premiers a casser) ; verifier qu'une « suppression de doublon » annoncee dans un CHANGELOG est reellement commitee (`git show --stat <sha>`). Les corrections type `fix/main-*` en poussée directe ne suffisent pas : sans run local, on ne voit pas les rouges qui attendent dans la file.

## Lecon 2026-08-14 — Triage des branches distantes apres une vague multi-agents

Apres une vague ou plusieurs agents mergent en parallele, la plupart des branches
distantes restantes sont des **doublons** de commits deja squash-merges dans `main`
(le diff 2-points `git diff origin/main origin/<branche>` est pollue par
l'avancement de main et ne prouve rien). Pour trier sans rien perdre :

```bash
git fetch origin --prune
git diff --name-only origin/main...origin/<branche>   # travail propre de la branche (3-points)
# Pour chaque fichier divergent, verifier si main couvre deja le contenu :
git diff origin/main:<fichier> origin/<branche>:<fichier>   # vide = duplique
```

- `DIVERGE=0` ou seul le `CHANGELOG.md` diverge → doublon → supprimer la branche.
- Contenu de branche PLUS ANCIEN que main (main a evolué) → doublon → supprimer.
- Fichier present sur la branche et ABSENT de main → travail potentiellement non merge
  → analyser avant de merger ou de capturer dans une issue (ne jamais merger une base
  perimee qui REVERTIRAIT main : verifier la direction du diff).
- Ne jamais merger une branche dont l'approche a ete remplacee sur main (ex. barème
  ITSAS CI annuel remplace par l'ITS 2024 unifie, art. 119 bis).

## Lecon 2026-08-14 — Campagne QA fonctionnelle multi-agents (convergence sur les memes correctifs)

- **Plusieurs agents convergent sur les MEMES fixes** (ex. PHPStan 43 erreurs, golden CI SMIG 8 800,00) : avant de pousser un correctif, faire `git fetch origin` et comparer `git log origin/main` — si un commit recouvre deja le changement, NE PAS dupliquer : rebaser et ne garder que le reliquat. Les doubles fixes creent des conflits de merge inutiles (observé : golden CI SMIG corrigé 2× dans la meme session).
- **Ne jamais lancer deux processus `artisan test` en parallele sur la meme base `leopardo_test`** : deadlocks PostgreSQL (`40P01`), courses de migration (`23505 pg_type_typname_nsp_index`), faux rouges en cascade. Recréer la base (`DROP/CREATE leopardo_test`) avant un run propre.
- **Les goldens paie sont la source de verite des montants** : quand un test unitaire contredit un golden, c'est le test unitaire qui est perime (aligner sur le golden + la doc `docs/payroll/{CC}_COMPLIANCE.md`), jamais l'inverse. Exemple : CNSS CI famille/AT plafonnees a 70 000 (guide CNPS, #1913) — les goldens CI/SN portaient encore les valeurs non plafonnees.
- **Le preavis CI est au niveau employe/technicien** (CI_COMPLIANCE.md §8) : `< 5 ans 30 j / ≥ 5 ans 60 j`, cadres 90 j, ouvriers 8/15 j — le palier 90 j par anciennete (≥ 10 ans) n'est PAS documente et a ete retire du moteur (issue #2289). La categorie vient de `employees.ipres_category` via `EndOfContractService`.
- **Le login admin ne doit plus contenir de `href="#"`** : « Mot de passe oublie » n'existe pas en self-service super-admin (ops : `php artisan super-admin:reset-password`) ; « Support » = `mailto:support@leopardo-rh.com` (email canonique vitrine).

### 2026-08-16 - Migrations PostgreSQL concurrentes
- `Schema::hasColumn()` suivi de `Schema::table()` n'est pas atomique sous Render/Neon : plusieurs processus peuvent franchir le test puis provoquer `SQLSTATE[42701] Duplicate column`. Pour les réconciliations additives publiques, utiliser `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` avec un schéma explicitement résolu et ne pas avaler les autres erreurs (issue #4123).

### 2026-08-16 - Contraintes PostgreSQL tenant concurrentes
- PostgreSQL ne propose pas `ADD CONSTRAINT IF NOT EXISTS`. Pour les migrations Render exécutées en concurrence, utiliser un advisory lock transactionnel, inspecter `pg_constraint`, traiter une définition équivalente comme no-op et refuser une définition incompatible; ne pas faire `DROP` puis `ADD` sans vérification (issue #4156).

### 2026-08-16 - Défauts de config vs défauts de seeder (racine #2646)
- Un défaut de config peut diverger silencieusement du défaut d'un seeder qui crée le même objet (`config/demo.php` fixait `super_admin_email` à `admin@example.com` alors que `SuperAdminSeeder` crée `admin@leopardo-rh.com` → `syncDemoSuperAdmin` ciblait un compte inexistant → no-op silencieux → INVALID_CREDENTIALS pour le parcours démo, issue #3775). Règle : dès qu'un seeder lit `env('X', default)`, la config correspondante DOIT porter le même défaut, et tout sync « démo » sur un compte absent DOIT émettre un warning (pas de no-op silencieux).

### 2026-08-16 — Tests Payroll déterministes et précision overtime (#4266)
Les tests Golden ne doivent jamais dépendre des dates aléatoires d’`EmployeeFactory` : lorsqu’une période complète est attendue, fixer explicitement `contract_start` avant `period_start` et `contract_end` à `null`. Pour `computeOvertimePay`, la précision complète est conservée jusqu’à l’arrondi final; les attentes doivent donc refléter `4327.01` et `6923.21` pour la formule de référence 60 000 / 173.33, et non des valeurs obtenues après arrondi prématuré.


## Leçon 2026-08-16 — Déduplication des déploiements post-merge (#4359)

Le workflow `deploy-main.yml` doit utiliser le `push` sur `main` comme source automatique unique. Ajouter également `workflow_run` pour les mêmes parents crée plusieurs runs pour un même SHA, empile E2E/ZAP et peut affamer le déploiement Render. Le groupe de concurrence doit rester indexé directement sur `github.sha`, avec une garde anti-stale et un résumé explicite en cas d’annulation.

Référence : issue Spec Kit #4359.

## Leçon 2026-08-16 — Matrice Android Flutter homogène (#4378)

- Les cinq apps sous `front/mobile_apps/` partagent la même chaîne Flutter/Android CI : Gradle 8.14.3, AGP 8.9.2 et Kotlin 2.1.20. Une app restée sur une version antérieure peut échouer sur les builds release même si les autres apps passent.
- Toute mise à niveau doit comparer les cinq fichiers `android/settings.gradle.kts`, les wrappers Gradle et les variantes Firebase avant de modifier un seul projet. Ne pas migrer vers AGP 9 sans validation explicite de la version Flutter utilisée en CI et du nouveau DSL supporté.
- Le workflow `Mobile Distribute - Main` doit rester atomique : aucun artefact Firebase ne doit être publié si le build de l’app correspondante échoue.


## Leçon 2026-08-16 — PHPStan Strict #4642 : factories et setups de tests

- Dans les setups PHPUnit, une propriété déclarée (`private Employee $employee`) doit être utilisée dès sa création avec `$this->employee`; une référence locale `$employee` inexistante est une erreur statique réelle et ferait également échouer le test à l’exécution.
- Les appels `Employee::factory()->create()` et `Company::factory()->create()` peuvent être inférés comme `Illuminate\Database\Eloquent\Model` par PHPStan. Ajouter une annotation locale `/** @var Employee $employee */` ou `/** @var Company $company */` au point d’assignation permet de conserver le contrôle strict sur `id`, `company_id` et `Sanctum::actingAs()` sans augmenter la baseline.
- Un test qui vérifie volontairement le rejet runtime d’un type interdit peut garder son appel invalide avec un commentaire explicite `// @phpstan-ignore argument.type` immédiatement avant l’appel. Cette suppression doit rester locale et ne doit jamais devenir une règle globale.
- Avant toute mise à jour de `phpstan-strict-baseline.neon`, corriger les types et réexécuter PHPStan. Pour #4642, la baseline n’a pas eu besoin d’un nouveau pattern : la commande termine avec `[OK] No errors`.
- La suite PHPUnit locale de cette session a été tentée avec `php artisan test --parallel`, mais le sandbox ne possède pas le pilote `pdo_pgsql` et échoue sur `could not find driver`; l’exécution complète doit être reprise par un runner CI équipé de PostgreSQL.

## Lecon 2026-08-25 — `.claim-marker` jamais commité (issue #5447)

- **Convention (PLAN_100PCT.md §6.4)** : le marqueur de claim est un LOCK LOCAL.
  Il se crée côté agent (fichier non tracké) ou via un commit vide
  « claim marker #N » (protocole #2400) — **jamais** un fichier `.claim-marker`
  commité. Constat du 2026-08-25 : 52 branches + main portaient le marqueur
  (#5261) → conflits de merge en cascade et bruit de revue.
- **Garde CI** : `dev-hub/tools/check-no-claim-marker.sh` (branché dans
  `architecture-check.yml` hygiene-guards) échoue si un `.claim-marker` est
  présent dans l'arbre de travail d'une PR. Avant tout push, vérifier
  `git ls-files | grep claim-marker` et retirer le fichier (`git rm`) le cas échéant.
- **Purge** : marqueurs retirés de main et des branches actives le 2026-08-25
  (PR dédiée issue #5447).

## 🗄️ Protocole migrations — OBLIGATOIRE avant de créer une migration (issue #5634)

Deux collisions de préfixe le même jour (2026-08-24) ont bloqué toutes les PR. Règle :

1. **AVANT de créer un fichier de migration**, vérifier l'unicité du préfixe `YYYY_MM_DD_0000NN` :
   ```bash
   ls api/database/migrations/ | grep 2026_08_26_000001 || echo "préfixe libre"
   ```
2. Nommer la migration avec la **référence d'issue dans le nom** :
   `2026_08_26_000123_<issue>_<slug>.php` (ex. `2026_08_26_000123_5634_add_merge_quota_log.php`).
3. La CI vérifie tout : `check-migration-basename-collisions.sh` (basename + issue-ref) et
   `check-migration-prefixes.mjs` (préfixes inter-PR) — une PR qui viole le protocole est rouge.
4. Ne jamais renommer une migration mergée (les fichiers `*.php` du schéma tenant sont figés).

## 🔒 Discipline merge (issue #5634 — rétro pilotes J6)

- **Quota merges quotidien** : le gate `merge-quota-guard.yml` compte les merges de main sur 24 h
  et devient rouge au-delà de `vars.MERGE_DAILY_QUOTA` (défaut 25). Ralentir le rythme : les
  merges en rafale (132 en 6 jours) génèrent des rebasages constants et du re-travail.
- **main cassé = alerte immédiate** : ne pas empiler un merge sur un main rouge ; signaler et
  corriger d'abord (`fix/main-green-backend` est le lot dédié).
- **Ticket créé = ticket assigné** : pas de création d'issue orpheline.
- **Ratio fix/feat** : suivi hebdo dans `dev-hub/tools/fix-feat-ratio-report.sh` (alerte > 3,
  cible <= 2.5).
