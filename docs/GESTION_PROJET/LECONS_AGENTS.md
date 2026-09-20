# Leçons historiques des agents — Leopardo

> Extrait de `AGENTS.md` (assainissement gouvernance racine, issue #7843).
> Ce fichier regroupe les leçons datées, post-mortems et historiques d'incidents
> qui alourdissaient le guide. Les **règles actives** restent dans `AGENTS.md`
> (à la racine du dépôt), qui fait foi en cas de conflit.
> Voir aussi : `docs/GESTION_PROJET/BIBLIOTHEQUE_ERREURS.md` (vue rapide des pièges)
> et `docs/archive/AGENTS_HISTORIQUE_UTILE.md` (archive plus ancienne, issue #6698).

## Lecon 2026-08-16 — Famine du pipeline de deploiement (issue #3545)

- **`cancel-in-progress: false` ne protege PAS les runs `pending`** : GitHub ne
  conserve qu'UN run pending par groupe de concurrence. Sous rafale de merges
  (~1/2 min), les runs Tests de main etaient annules en pending (48/50
  cancelled) et `deploy-main.yml` (dependance `workflow_run.conclusion ==
  success`) skipait le deploy a 100 % — prod figee sans signal rouge.
- **Garde : tout workflow de deploiement déclenché sur main doit écouter
  `push: main` et poller les runs/checks du SHA (timeout borne, ~30 min) au
  lieu de lire la conclusion d'un parent `workflow_run` potentiellement
  annulé.** Toujours re-verifier que le SHA est encore la tete de main avant
  de deployer (garde anti-stale, audit #1705).
- Tout skip de deploiement doit emettre `::warning::` + `$GITHUB_STEP_SUMMARY`
  (raison, SHA, conclusions) — un skip silencieux ressemble a un success.

## Lecon 2026-09-08 — Gate deploy dev aveugle (issues #6834/#6973)

Le tier dev/continu (`gestionemployerbackend`) n'a PAS d'`APP_VERSION` :
`/api/v1/health` y expose `version` = SHA Render tronqué (`RENDER_GIT_COMMIT`,
config `app.version` « honnête » #6835). Un healthcheck de deploy qui compare
`version` à un APP_VERSION (PILOTAGE/prod) est donc **aveugle sur le tier dev**
— et s'il poll un healthcheck partagé via secret, il peut valider la PROD :
`deploy-main` « success » pendant ~24 h avec dev figé sur un vieux SHA. Règle :
le healthcheck d'un workflow de déploiement doit cibler l'instance QU'IL
déploie (URL dev explicite, pas de secret partagé) et prouver qu'une NOUVELLE
instance répond (baseline avant hook → version ≠ baseline), jamais comparer à
un APP_VERSION sur un tier qui n'en porte pas. Rattrapage : `deploy-main-catchup.yml`.

### 2026-09-16 — Drain de crise : les pièges qui coûtent une journée (#7562)

- **`git merge-file --union` casse les JSON/ARB** (13 à 17 fichiers invalides, synchronisateurs en échec) → merge **profond** puis régénération par `shared/i18n/sync/*.js` + `validators/validate.js` ; `versions.json` doit être **valide avant** de relancer les syncs. `--union` reste bon pour les fichiers texte additifs.
- **Les annotations PHPDoc peuvent être désarmées** : en PHP, seul le **dernier** docblock avant la déclaration compte. Un `/** @extends Factory<Employee> */` isolé au-dessus d'un second docblock était ignoré → `Employee::factory()->create()` typé `Model` → ≈20 erreurs PHPStan strict dans les tests.
- **Un handler qui ferme puis bascule le même état ne ferme rien** : `closePanels(); setOpen(v => !v)` rouvre dans le même lot → calculer la cible **avant** : `const next = !open; closePanels(); setOpen(next)`.
- **`Core → Modules` est interdit** : pour un enum partagé, déplacer l'enum **vers `Core`** ; ne jamais élargir l'allowlist d'isolation (#5584).
- **Un rouge non requis n'est pas forcément le vôtre** : le mesurer sur `main` (worktree détaché) avant de corriger — `event-catalogue`, `route-owner-guard` et `application-layer-placement` sont rouges **sur main** depuis des semaines.
- **GitHub peut ne créer AUCUN run** pour un push (constaté 2 fois de suite) : `commit --allow-empty "ci: nudge"`, sinon merger `origin/main` et pousser ; ne jamais merger en supposant la CI.
- **Mergeability « dirty » stale** sur gros diffs → `git merge origin/main` **dans la branche** + push (jamais de force-push).
- **Un correctif de gate/CI se vérifie dans les LOGS DE PRODUCTION** : #7559 passait tous les tests locaux et échouait sur un run réel (`gate_outcome=tests-null`, statut `requested` non modélisé). Corriger, puis relire un run réel.

### 2026-09-08 - Merge lane, garde PA2-OPS-008, pureté des couches Application, mergeability GitHub

- **Garde PA2-OPS-008 BLoquante (nouvelle) : toute PR de code (hors `docs:`/`chore:`) DOIT porter `Closes #N` / `Fixes #N` / `Resolves #N` dans le titre ou le body** (`dev-hub/tools/check-pr-closes-issue.sh`). Une PR « Part of #N » seule est rouge → pour une tranche d'une campagne (ex. #6569, #6968) : **créer une sous-issue de tranche** et mettre `Closes #<sous-issue>` dans le body (précédent : sous-issue #7004 pour la tranche PlatformUsersController de #6569, PR #7002). Les PRs payroll « Part of #6968 » (#6976, #6980…) sont bloquées par cette garde tant qu'elles ne closent rien.
- **Pureté des couches : les facades Laravel (`use Illuminate\Support\Facades\DB`) sont INTERDITES dans `Application/`** (`check-layer-purity.sh`, issue #6568) — les `Generate*ReportAction` historiques de Platform sont allowlistés, les nouveaux fichiers ne le sont pas. Pour de l'accès données depuis une Action Application : **déléguer à un service `Infrastructure/Services`** (pattern `ProvisionCompany` → `CompanyProvisioningService`, ADR-0020 ; précédent PR #7002) ou utiliser les modèles Eloquent directement (précédents Delivery/Recruitment).
- **Mergeability GitHub stale sur gros diffs** : une PR peut afficher « merge conflicts » / « not mergeable » alors que `git merge` local est propre (constaté #6983, #6998, #7003, #6955 — diffs énormes ou CHANGELOG) → **fusionner `origin/main` dans la branche et pousser** force le recalcul. Après CHAQUE merge dans main, toute PR ouverte touchant le haut de `CHANGELOG.md` devient `dirty` — la réaligner avant de merger.
- **Garde « Check unique issue claim per PR » cassée** (`dev-hub/tools/check-issue-claim-unique.sh`) : `gh: Resource not accessible by integration (HTTP 403)` puis `AttributeError: 'str' object has no attribute 'get'` — échoue sur TOUTES les PRs. Non bloquante pour le merge (les 4 checks requis sont PHPStan Strict, Module Structure Validator, Frontend ESLint+TS, actionlint) mais rend « PR Issue Guard » rouge : à corriger dans l'outillage (permissions GITHUB_TOKEN + parsing).
- **Protection main en `strict`** : 4 checks requis + branche à jour exigée → après chaque merge dans main, les PRs `behind` doivent être mises à jour (update-branch API parfois 404 → fusion locale + push) puis repassent un cycle CI complet. Un « merge sweep » périodique (merge auto des PRs clean + 4 checks verts + inactives ≥ 5 min) évite les heures d'attente.

### 2026-09-15 - Dérive dev Render : un déploiement « vert » ne déploie pas (#7304)

- `deploy-main.yml` peut sortir **`success` sans déployer** : le job
  `Deploy API + Web to Render` est *skipped* quand le run `Tests - Leopardo`
  du SHA est absent (`Tests=missing` — runs `synchronize` non créés sous charge,
  leçon #3545). Mesuré le 2026-09-14 : **12 runs successifs, 0 déploiement**, dev
  figé sur `fe2ab9f` à ~60 merges derrière `main` → recettes menées sur du code
  périmé (faux bugs) et worker de queue de l'image ancienne donc absent.
- **Pré-vol obligatoire avant toute recette** :
  `dev-hub/tools/check-deploy-drift.sh --url "$DEV_API_BASE_URL" --expect origin/main`
  (garde CI `deploy-drift-guard.yml`, toutes les 30 min). Un environnement dont
  `/health.version` ≠ SHA de `main` n'est pas un environnement de recette —
  runbook `docs/ops/RENDER_DEV_ALIGNMENT.md`.
- **Ne pas activer `autoDeploy`** sur le service dev Render
  (`gestionemployerbackend`, `srv-d7dro8u7r5hc73a395pg`) : décision #6700 —
  un auto-deploy rebâtit à chaque push `main` (docs/web-only compris) et
  consomme les build hours. Le déploiement passe par `deploy-main.yml`, dont le
  gate peut **sauter** (`Tests=missing`, suivi par #7457) : d'où le pré-vol de
  recette ci-dessus + un redéploiement manuel à la demande. Contrainte API : un
  `POST /deploys` Render déploie le **HEAD de la branche**, jamais un SHA
  arbitraire.
- Le dev est en **mono-conteneur** : le worker de queue vit dans le conteneur web
  (`api/docker-entrypoint.sh`, respawn loop #7041). Une queue qui s'accumule est
  un symptôme d'**image périmée**, pas d'un « worker manquant » — le compte dev
  Render refuse toute création de service payant (`new paid services not allowed`).

### 2026-05-14 - Integration branche Devin Plan 14

- La branche distante `devin/1778717175-plan14-phase1-tests` apportait les suites Plan 14 Phase 1 : E2E admin-dashboard, integration API et tests de modeles Flutter. Elle doit etre integree depuis un `origin/main` recent, pas mergee telle quelle si les checks GitHub Actions sont rouges.
- Les vues admin-dashboard ne doivent pas contenir de `catch {}` vide : `Web Lint` bloque avec `no-empty`. Ajouter au minimum un `console.warn(...)` explicite ou un etat d'erreur utilisateur selon le contexte.
- Les tests Feature qui declenchent `AbsenceRequested`, `AbsenceApproved`, `AbsenceRejected`, `PayrollValidated` ou d'autres evenements metier peuvent passer par `WebhookListener`. Le schema de test MVP doit donc creer `webhook_endpoints` et `webhook_deliveries`, sinon PostgreSQL echoue avec `relation "webhook_endpoints" does not exist` avant meme les assertions.
- Les contrats plateforme recents doivent rester dans `api/openapi.yaml`. Depuis #5280 (2026-08-23), le workflow `OpenAPI CI` (lint Redocly + couverture routes→spec + sync miroir/SDK `dev-hub`) s'execute sur CHAQUE pull request (`pull_request` sans filtre `paths:`) — une PR qui ajoute une route, casse la spec ou laisse le miroir/SDK perime est bloquee immediatement ; le filtre `paths:` ne reste que sur `push: main` (lecon #3545). Corriger la spec plutot que laisser les frontends deviner les shapes `data` / `meta`. Ne jamais introduire de cle de chemin dupliquee ni de `type: [x, "null"]` (OAS 3.0 → `nullable: true`) ; apres toute modif de la spec, rejouer `node dev-hub/tools/generate-openapi-sdk.mjs` et committer le miroir + SDK. Redocly : 0 erreur exigee, les warnings preexistants (~762) ne bloquent pas.
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

- Avant d'ajouter un test liste comme manquant dans `docs/archive/PLAN_ACTION/13_RESTANT_POST_SPRINTS.md`, verifier d'abord `api/tests/Feature/` : plusieurs suites post-sprints existent deja meme si le plan historique les affichait encore en non cochees.
- `BillingControllerTest` couvre maintenant abonnement, upgrade/cancel/renew, RBAC employe et isolation tenant liste/detail/PDF facture ; etendre cette suite plutot que creer un doublon.
- `PaymentWebhookControllerTest` couvre les webhooks Stripe/Chargily valides et invalides. Les payloads inconnus doivent rester idempotents cote donnees : acquittement HTTP OK, mais aucune creation de paiement ni mutation facture/abonnement.
- `PUT /api/v1/feature-flags/matrix` ne doit pas etre ouvert aux utilisateurs tenant. Les ecritures matrice feature flags passent par les contrats plateforme super-admin ; garder `FeatureFlagControllerTest` comme garde anti-regression.
- `StructuredLoggingMiddlewareTest` verifie que les requetes API non-health ecrivent sur le channel JSON `structured`, tandis que `/api/v1/health/*` reste exclu pour eviter le bruit des sondes.
- `OnboardingStepControllerTest` couvre `/api/v1/onboarding-setup/*` : auto-seed checklist, progression, complete/skip et isolation tenant. Ne pas confondre avec `/api/v1/onboarding/checklist`, qui mesure le go-live client.

## Lecon 2026-08-14 — Vague QA hardening : endpoints reels, mocks cockpit, contrats

- **Les vues admin appellent parfois des chemins `/v1/...` alors que le backend sert le cockpit sous `/admin/...`** (auth `super_admin_api`). Avant de déclarer une vue cassée, vérifier `php artisan route:list` et le mapping du client (`normalizeApiPath` ne touche que `/v1/`).
- **Cockpit admin : ne jamais afficher de données fabriquées.** Users/Analytics/System ont été réécrits sur des endpoints réels (`/admin/users`, `/admin/dashboard/stats|activities|alerts`, `/health/live`, `/health/ready`). Les sections sans backend affichent un état « non disponible » explicite. Toute nouvelle vue cockpit doit consommer un endpoint réel ou un état vide honnête.
- **Mobile employee : les écrans Formation et Véhicules appelaient `/me/training-enrollments` et `/me/vehicles` inexistants (404).** La règle : tout repository mobile doit être cross-checké contre `php artisan route:list` (extraire les chaînes `'/...'` des repositories Dart et vérifier chaque endpoint — pattern réutilisable, cf. `check-openapi-route-coverage.py`).
- **`TrainingEnrollmentResource`** expose désormais `course_title`, `session_date`, `progress` (additif, charge `session.course`) — l'écran Formation employee attend cette shape.
- **`/me/vehicles`** renvoie les véhicules `assigned_driver_id` = employé courant avec position Traccar best-effort (null-safe — ne jamais faire échouer la liste si le traqueur est hors ligne).
- **Webhooks : `POST /webhooks/{webhookEndpoint}/test`** dispatche `webhook.test` (tracé dans `webhook_deliveries`), 403 hors `principal`, 404 cross-tenant.
- **`legal_reference`** est maintenant une colonne nullable sur `tax_slabs` et `social_contributions` (migration additive) — le champ du formulaire TaxRates est réellement persisté.
- **`.env.example`** : garder la parité avec `config/` (`check-env-example-parity.sh`), sinon le check CI rouge.

## Compléments déplacés depuis les règles actives (2026-09-20, #7843)

### Garde « une table, une migration » — historique de résorption (#7452)

- **Tranche Travel (2026-09-15)** : la consolidation forward-only (ajout des
  colonnes attendues par le code + `DROP NOT NULL` sur les colonnes « zombies »
  que le code ne renseigne jamais, jamais de second `Schema::create`) est dans
  `2026_09_15_001600_7452_consolidate_travel_duplicate_schema.php`. Mesure :
  `tests/Feature/Travel` **223 → 188 échecs**, 0 régression. **Ne pas chercher
  la cause des échecs restants dans les migrations** : ils sont fonctionnels
  (contrats d'API d'une autre génération, `QuizStatus::ACTIVE` et
  `TravelQuiz::STATUS_*` absents du code, closures de fixtures sans
  `use ($company)`, helpers de test inexistants). Le compteur
  `223 failed` de #7452 est donc **majoritairement non-schéma** : la
  consolidation ne peut pas, à elle seule, fermer l'issue.

### Recette de consolidation d'un module (appliquée à Travel le 2026-09-15, #7452)

1. **Deux formes de garde coexistent** — ne pas n'en voir qu'une :
   `if (! schemaTableExists('t')) { Schema::create(...); }` **et**
   `if (schemaTableExists('t')) { return; }` suivi du `Schema::create`. Travel en
   portait 3 de la seconde forme (`travel_outbox_events`, `travel_advert_prices`,
   `travel_tourist_sites`) : un outil qui ne cherche que la forme négative les
   laisse intactes, et la colonne manquante le reste aussi (`image_asset_id`).
   Vérifier après coup : `grep -l "Schema::create('travel_" <fichiers touchés>`.
2. **Le gagnant garde la déclaration, les perdants deviennent des `ALTER`** :
   remplacer le bloc du perdant par
   `if (schemaTableExists('t')) { Schema::table('t', function (Blueprint $table) { if (! schemaHasColumn('t','col')) { $table->…; } }); }`
   — le schéma réel devient l'**union** des générations, résultat identique sur
   base neuve et base déjà migrée. Ne pas « inverser le gagnant » (retirer la 1ʳᵉ
   déclaration) : les bases existantes garderaient des colonnes que les neuves
   n'auraient pas → divergence d'environnements.
3. **Colonnes NOT NULL « legacy »** que seule la 1ʳᵉ génération déclare et que le
   code ne renseigne jamais (`contact_identifier`, `base_currency`,
   `passenger_count`, `name` des référentiels d'annonces…) : les rattraper par une
   migration dédiée `ALTER COLUMN … DROP NOT NULL` (idempotent, sans perte de
   données, les lecteurs legacy continuent de marcher). Un `->nullable()` dans un
   `Schema::table` ne suffit pas : la colonne existe déjà, la contrainte reste.
4. **Mesurer avant/après, sur base neuve** — le test runner met en cache le schéma
   canonique (`canonicalSchemaReady()`, #6754) : sans `DROP DATABASE` (ou
   `migrate:fresh`), une exécution peut valider l'ANCIEN schéma et faire croire à
   un correctif sans effet.
5. **État après Travel** : dette **68 tables dupliquées / 36 divergentes → 40 / 17**
   (plus aucune `travel_*`). `tests/Feature/Travel` : 223 échecs → 181 (les
   restants sont d'autres natures — policies, fixtures, constantes — et masqués
   jusque-là par la cascade `25P02` ; voir #7420, #7417, #7445).


### Leçons datées extraites de « Regles obligatoires »

- **Lecon 2026-08-16 (#4164)** : le garde `validate-mobile-workflow-contracts.ps1`
  (scan forbidden-route) ne doit matcher que des ROUTES DE NAVIGATION, pas les
  chemins d'endpoints portes par les mocks API (`leopardo_core/lib/core/api/mock_interceptor.dart`
  contient `/attendance`, `/attendance/check-in`, ...). Depuis #4102 (leopardo_core
  inclus dans le scan), ces chaines produisaient un faux positif permanent
  (« platform_admin app must not expose forbidden route /attendance ») → Mobile
  Apps CI rouge sur main. Garde : `Get-DartContent $root @('*mock*.dart')`.
  Tout nouveau fichier de mock doit suivre le pattern `*mock*.dart`.
- **Lecon 2026-08-17 (audit #4868)** : le check externe « Vercel » echoue sur TOUTES les PRs web quand le quota gratuit de deploiements est epuise (`api-deployments-free-per-day`, ~100/jour, famille #3765/#3766). C'est un echec de QUOTA, pas de build — et le check n'est PAS requis (protection de branche : 5 checks requis ; aucun workflow du repo n'attend le status Vercel). Ne pas traiter le rouge Vercel comme bloquant : merger sur la base des checks requis (meme regle que « Workers Builds: gestionemploye », #4216).
- **Lecon 2026-08-16 (swe-qa-360)** : sous rafale de pushes concurrents (300+
  runs queued), GitHub Actions peut ne PAS creer de runs pour certains
  evenements `synchronize` — zero check suite `github-actions` sur le nouveau
  head alors que les integrations tierces (Vercel/Render/...) reçoivent bien
  l'evenement. Observe : certains pushs declenchent, d'autres non, sans
  explication par les path filters (actionlint/architecture-check/coverage-gate
  n'ont pas de filtre). Correctifs partiels : annuler les runs queued
  supersedes de la meme (branche, workflow) pour liberer les groupes de
  concurrency (#3545) ; `git commit --allow-empty -m "ci: nudge"` + push aide
  parfois (fonctionnait en debut de session, plus du tout en fin). Fermer/
  rouvrir la PR ne force rien. Si une PR reste sans runs apres 15 min, la
  laisser ouverte avec un commentaire (un autre agent a la file qui fonctionne
  pourra merger) — ne pas merger sans checks. Le check « Workers Builds:
  gestionemploye » echoue sur TOUTES les PR (deploy Cloudflare hors PR) — ce
  n'est pas un check requis, ne pas le traiter comme rouge.

### Pieges connus — Vercel (incidents historiques)


Le statut externe `Vercel` peut echouer immediatement vers une page de configuration projet. Lors du PR #268 et du hotfix #299, tous les GitHub Actions etaient verts et le merge restait possible malgre ce statut externe. Ne pas perdre du temps a corriger le code si Vercel echoue sans logs de build applicatif.

Le workflow GitHub `Build & Deploiement` a aussi porte une integration `vercel/action@v4` introuvable cote Actions. Si ce workflow redevient rouge pour `Unable to resolve action vercel/action`, conserver seulement le job de build jusqu'a ce qu'une integration Vercel valide soit configuree.

Dans `web/vercel.json`, ne declarer un bloc `functions` que si le pattern correspond vraiment aux fonctions Vercel generees par le projet. Le pattern historique `api/**` casse les deploys du frontend Next.js avec `The pattern "api/**" defined in functions doesn't match any Serverless Functions`, car les route handlers reels vivent sous `web/src/app/api/**`.

Pour le frontend `web/`, ne pas declarer dans `vercel.json` un bloc `env` avec des objets de description. Vercel attend des chaines de caracteres si `env` est present. Si les variables sont deja gerees dans le dashboard Vercel, supprimer completement ce bloc du fichier pour eviter l'erreur `env.<VAR> should be string`.

