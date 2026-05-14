# AGENTS.md - Guide de travail Leopardo RH

Derniere mise a jour : 2026-05-13

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
- Le workflow `coverage-gate.yml` doit creer `api/storage/coverage` avant tout `tee` vers `storage/coverage/summary.txt`, et son seuil par defaut doit rester non bloquant (`0`) tant que `BACKEND_COVERAGE_MIN` n'est pas configure explicitement.
- Le runbook backup existe deja dans `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` ; en cas de plan CI/CD, penser mise a jour/allegement avant creation d'une nouvelle doc.
- Le depot porte deux surfaces frontend distinctes : `admin-dashboard/` pour la plateforme interne et `web/` pour la vitrine / portail manager Next.js. Ne pas confondre les workflows ni les URLs de deploiement.
- Pour `admin-dashboard/`, garder `web-ci.yml` cible sur `admin-dashboard/**` avec lint/build/Playwright.
- Pour `web/`, utiliser un workflow dedie vitrine (`web-marketing-ci.yml`) sur `web/**` au lieu de recycler les checks admin.
- Dans `tests.yml`, ne pas faire porter la dette mobile historique a des PR backend/web en declenchant `mobile-tests` uniquement parce que le workflow lui-meme change. Le job mobile doit rester cale sur `mobile/**` tant que la base n'est pas completement assainie.
- Pour `Backend Quality`, garder Pint et PHPStan en gates diff-aware sur les fichiers PHP backend modifies. Cela bloque les nouvelles regressions sans faire porter la dette historique hors perimetre au PR courant. Garder les artefacts et la visibilite du baseline.
- Le depot contient deja beaucoup de tests backend critiques (auth, guardrails, RBAC, absences, attendance, contrats mobile). Avant d'ajouter de nouveaux tests, verifier d'abord si le manque reel n'est pas plutot la visibilite CI (coverage, artifacts, reporting).
- Les tests locaux Windows peuvent echouer avant PHPUnit si l'extension PHP `mbstring` manque (`mb_split()` introuvable dans Laravel). Dans ce cas, ne pas conclure a un rouge applicatif ; verifier la syntaxe et laisser GitHub Actions executer la suite complete.

## Pieges connus

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

### 2026-05-08 - Render et transaction PostgreSQL abort

- Sur PostgreSQL, une migration Laravel executee dans la transaction du migrateur ne doit pas lancer de requete de verification apres une erreur SQL, sinon on tombe sur `SQLSTATE[25P02] current transaction is aborted`.
- Concretement, apres un `42P07 Duplicate table`, ne pas appeler `Schema::hasTable(...)` dans le `catch`. Il faut considerer le code SQLSTATE et sortir directement, sinon le correctif de course reintroduit un echec.
- Si une migration publique peut enchainer plusieurs `Schema::hasTable(...)` / `Schema::create(...)` sur Render, desactiver aussi la transaction du migrateur avec `public bool $withinTransaction = false;`, sinon une premiere course gagnée par un autre processus empoisonne tout le reste de la migration.

### 2026-05-12 - Tests modules post-sprints

- Les tests qui utilisent `Tests\Support\CreatesMvpSchema` ne voient que le schema fixture, pas automatiquement toutes les migrations post-sprints. Si un test couvre billing, paie, recrutement, formation, prets, frais ou vehicules, verifier que le fixture cree aussi la table minimale correspondante.
- Attention aux tables historiques homonymes dans `public` et `shared_tenants` (`invoices`, notamment) : en PostgreSQL, `Schema::hasTable()` peut donner un faux positif si le `search_path` inclut `public`. Pour un fixture ou une migration tenant, preferer une table qualifiee ou un rattrapage idempotent.
- L'ancien `audit_logs` tenant utilise `employee_id`, `target_type`, `target_id`, `changes`, `ip`; le code actuel ecrit `user_id`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `ip_address`, `user_agent`. Toute migration de compatibilite doit ajouter le contrat moderne sans relancer de SQL apres erreur PostgreSQL.
- Pour tester les endpoints flotte, injecter un faux `TraccarService` dans le container plutot que de laisser les tests appeler Traccar/HTTP. Le contrat utile est `vehicle_id` + `position`, pas la disponibilite du serveur Traccar externe.
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
