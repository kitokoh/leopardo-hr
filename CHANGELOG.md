# CHANGELOG - LEOPARDO RH 
# Format : Keep a Changelog (keepachangelog.com)
# Versioning : Semantic Versioning (semver.org)

## [4.16.19] - 2026-05-13

### Tests — Fleet dashboard contracts

- Tests : ajout de `FleetControllerTest` couvrant overview tenant-scope, live-map avec `TraccarService` fake, rapports carburant/kilometrage, maintenances dues et refus non authentifie.
- Securite : les routes tracking/flotte sont maintenant protegees par `auth:sanctum`, `tenant` et `throttle:api`, alignant le comportement reel avec les scenarios API.
- Documentation : plan post-sprints et scenarios API synchronises avec la couverture tracking flotte reelle.

## [4.16.18] - 2026-05-13

### Tests — AI voice, agents and analytics coverage

- Tests : extension de `AIGatewayAndAnalyticsTest` pour couvrir `voice/transcribe`, `voice/synthesize`, `voice/command`, `agent/workflows`, `agent/run` et la validation `max_steps`.
- Tests : ajout d'une couverture `ai/analytics/costs` avec groupement mensuel, tokens et couts scopes au tenant.
- IA : les tests utilisent un `LLMClient` fake afin de valider les contrats sans appel reseau ni cle provider externe.
- Documentation : plan post-sprints et scenarios API synchronises avec la couverture IA avancee reelle.

## [4.16.17] - 2026-05-13

### Tests — Onboarding setup workflow

- Tests : ajout de `OnboardingStepControllerTest` couvrant auto-seed checklist, progression, completion, skip optionnel, refus skip obligatoire et isolation inter-tenant.
- Documentation : plan post-sprints et scenarios API synchronises avec la couverture onboarding setup.

## [4.16.16] - 2026-05-13

### Tests — Structured logging observability

- Tests : ajout de `StructuredLoggingMiddlewareTest` pour verifier l'ecriture `http_request` sur le channel JSON `structured` avec request id, status et duree.
- Tests : verification que les sondes `/api/v1/health/*` restent exclues du logging structure pour eviter le bruit de supervision.
- Documentation : plan post-sprints synchronise pour health enrichi et logging JSON deja couverts sur `main`.

## [4.16.15] - 2026-05-13

### Security tests — Feature flag matrix writes

- Securite : `PUT /api/v1/feature-flags/matrix` refuse maintenant les utilisateurs tenant ; l'administration de matrice reste reservee aux contrats plateforme super-admin.
- Tests : ajout de `FeatureFlagControllerTest` pour matrix, check par plan actif, fallback trial, feature inconnue et refus RBAC en ecriture.
- Documentation : plan post-sprints et scenarios API synchronises avec la couverture feature flags reelle.

## [4.16.14] - 2026-05-13

### Tests — Payment webhook negative coverage

- Tests : ajout des cas negatifs `PaymentWebhookControllerTest` pour payload Stripe facture inconnue, evenement Stripe inconnu et payload Chargily facture inconnue sans mutation billing.
- Documentation : plan post-sprints et scenarios API synchronises avec la couverture webhook paiement valide/invalide.

## [4.16.13] - 2026-05-13

### Tests — Billing tenant and RBAC coverage

- Tests : durcissement de `BillingControllerTest` avec couverture renouvellement, refus cancel/renew employe, isolation inter-tenant des factures et telechargement PDF facture.
- Documentation : synchronisation du plan d'action post-sprints et du registre de scenarios API avec la couverture billing reelle.

## [4.16.12] - 2026-05-13

### Architecture security — Policies and FK tenant chains

- Securite : enregistrement explicite des policies dans `AppServiceProvider` pour eviter les echecs silencieux d'auto-discovery Laravel.
- Tests : ajout d'une suite `FkChainTenantIsolationTest` couvrant `WebhookDelivery`, `PaySlipLine`, `ApprovalDecision` et `ExpenseItem`, qui sont isoles via leur chaine FK parent portant `company_id`.
- CI : conservation des gates Pint/PHPStan diff-aware pour bloquer les nouvelles regressions sans faire porter la dette historique aux PR urgents.

## [4.16.11] - 2026-05-13

### Audit readiness — P0/P1 security fixes

- IA : correction des imports `AIOrchestrator` vers `App\AI\Orchestrator` sur les routes Agent/Voice et le runner agent.
- IA : alignement Agent/Voice sur le contrat `AIRequest` de l'orchestrateur pour eviter les erreurs de signature au runtime.
- Migrations : resolution de la collision de timestamp tenant `2026_04_24_000110` en renommant la migration cameras en `000111`.
- Securite : restriction des analytics IA aux managers `principal` et `rh`, avec test de refus pour manager departement.
- Securite : durcissement `AdminMiddleware` pour refuser les managers `dept` / `superviseur` et accepter seulement principal/admin/super_admin.
- IA : format `tool_result` natif Claude ajoute dans l'orchestrateur tout en conservant le chemin OpenAI existant.
- CI : Pint passe en mode gate bloquant diff-aware via `./vendor/bin/pint --test` sur les fichiers PHP modifies, pour eviter que la dette historique hors PR bloque les correctifs urgents.
- CD : le workflow deploiement resout `APP_VERSION` depuis `PILOTAGE.md` pour eviter la derive manuelle.
- Documentation : stack mobile corrigee vers Flutter + Riverpod 3.3 et notes runtime TenantManager/PHP-FPM ajoutees.

## [4.16.10] - 2026-05-13

### OpenAPI — Platform Metrics Contract (Lot 6)

- OpenAPI : documentation de `GET /api/v1/platform/metrics/overview` avec securite `SuperAdminBearerAuth`.
- OpenAPI : ajout des schemas d'agregats plateforme pour revenue, companies, subscriptions, billing, system et `generated_at`.

## [4.16.9] - 2026-05-13

### Documentation — Roadmap Execution Post Lots

- Plan d'action : ajout du fichier `docs/PLAN_ACTION/14_ROADMAP_EXECUTION_POST_LOTS.md` avec retour d'experience, risques, recommandations architecture et prochaine sequence de lots.
- Sommaire : index du dossier `docs/PLAN_ACTION` mis a jour pour exposer le fichier 14.

## [4.16.8] - 2026-05-13

### Admin Dashboard — Platform Metrics Wiring (Lot 5)

- Cockpit plateforme : branchement de `/platform/metrics/overview` pour afficher MRR, ARR, encaissements 30 jours, impayes et clients actifs depuis le nouveau contrat backend.
- Abonnements : ajout des agregats subscriptions, past due, trials, ARR et impayes afin de piloter retention et recouvrement sans widgets mockes.
- Gouvernance : scenarios web admin mis a jour pour couvrir le nouveau contrat metrics overview consomme par le dashboard.

## [4.16.7] - 2026-05-13

### Backend — Platform Metrics Overview (Lot 4)

- API plateforme : ajout de `GET /api/v1/platform/metrics/overview` reserve au guard `super_admin_api`.
- Super admin : exposition d'agregats business stables pour MRR/ARR, encaissements 30 jours, impayes, companies, abonnements, facturation et contexte systeme.
- Billing : lecture resiliente des tables tenant `subscriptions`, `invoices` et `payments` afin de ne pas casser les environnements partiellement migres.
- Tests : couverture Feature du contrat super-admin et du refus unauthenticated pour proteger le futur cockpit plateforme.

## [4.16.6] - 2026-05-13

### Backend — Feature Registry Console Typing (Lot 3)

- Feature Registry : reecriture type-safe des commandes `features:demo`, `features:detect` et `features:test-detector`.
- PHPStan : retrait des ignores baseline residuels lies aux commandes console du registre de fonctionnalites.
- Console : normalisation des options, listes et tableaux de manifeste pour eviter les erreurs `mixed` dans les sorties Artisan.

## [4.16.5] - 2026-05-13

### Backend — Service Layer Typing (Lot 2)

- Feature registry : typage generique des contrats `FeatureRegistryInterface` et `FeatureDetectorInterface` pour fiabiliser les manifestes API consommes par web, mobile, admin et future couche IA.
- Feature registry : normalisation des arguments/options de la commande `features:registry` afin d'eviter les chaines `mixed` et les sorties JSON `false`.
- PHPStan : retrait des ignores baseline resolus sur les attributs API et les contrats du registre de fonctionnalites.
- CI : alignement du job backend principal sur `composer:v2`, comme les jobs quality/coverage, afin d'eviter les echecs `composer validate` lies au token GitHub runtime.

## [4.16.4] - 2026-05-12

### Backend — API Contract Hardening (Lot 1)

- Tests Feature : ajout de couvertures ciblees pour webhooks Stripe/Chargily, dashboard tenant, notifications et contrats IA.
- Notifications : ajout des relations `Employee::notifications()` / `unreadNotifications()` et d'un marquage lu compatible avec le modele Notification interne.
- Dashboard : KPI mensuels rendus compatibles SQLite/PostgreSQL via fenetre de dates, sans `to_char()` specifique PostgreSQL.
- IA : analytics branchees sur les colonnes reelles (`input_tokens`, `output_tokens`, `cost_cents`, `duration_ms`, `error`, `tools_called`) et scopees au tenant authentifie.
- IA : clients OpenAI/Claude tolerent une cle API absente afin que les endpoints metadata/history ne tombent pas a l'instanciation de l'orchestrateur.
- Tests : fixture `CreatesMvpSchema` completee pour les tables critiques post-sprints (`payments`, `audit_logs`, `ai_*`, notifications modernes, `archived_at`).

## [4.16.3] - 2026-05-12

### Backend — Migration Safety (Partie 4 stabilisation)

- Migrations tenant : ajout de `Schema::hasTable()` guards sur 8 migrations (22 tables protegees)
- Migrations public : ajout `$withinTransaction = false` sur `2026_05_02_000002`
- Contrainte CHECK `chk_absence_dates` rendue idempotente via `pg_constraint` lookup
- Elimination des erreurs `42P07 Duplicate table` sur Render lors de deploys concurrents

## [4.16.2] - 2026-05-12

### Backend — Controller Stabilization (Partie 3 stabilisation)

- Controllers : extraction des chaines `->fresh()->method()` nullables vers des variables typees (EvaluationController, ContractController, ApprovalController, LeavePolicyController, AuthController, UserAuthController)
- Controllers : ajout de null checks sur les acces aux relations (employee, evaluator) dans les methodes serialize
- Controllers : remplacement de `optional()` par operateur nullsafe dans KioskController

## [4.16.1] - 2026-05-12

### Backend — Controller Type Safety (Partie 2 stabilisation)

- Controllers : extraction des appels inline `$request->user()->` vers des variables typees dans 8 controllers (DepartmentController, PositionController, SiteController, ScheduleController, HrReportController, AttendanceController, LeavePolicyController, TrainingController)
- Elimination des erreurs `method.nonObject` restantes sur les appels `isManager()` et `company_id` non types

## [4.16.0] - 2026-05-12

### Backend — Type Safety & Architecture (Partie 1 stabilisation)

- Models : annotations `@property` PHPDoc sur les 71 modeles Eloquent (1167 lignes ajoutees)
- Models : annotations `@return` generics sur 82+ methodes de relation (HasMany, BelongsTo, etc.)
- Services : helper type `currentCompany()` remplace les 31 appels `app('current_company')` non types
- Services : `PayrollCalculator` accepte les country rules via injection de dependances
- Services : `Orchestrator` accepte `LLMClient` via DI, binding enregistre dans AppServiceProvider
- Controllers : 207 annotations `@var Employee` sur les `$request->user()` dans 47 controllers
- Architecture : nouveau `app/helpers.php` enregistre dans `composer.json` autoload files

## [4.15.0] - 2026-05-12
 
### Paie avancee — Premier lot urgent

- Payroll : factorisation du calcul progressif des tranches fiscales pour DZ, TN, FR, TR et SN afin d'eviter les erreurs de bornes inclusives.
- Payroll : ajout de tests unitaires sur les cotisations sociales et les calculs d'impot multi-pays.
- Payroll : ajout du seeder `PayrollCountryConfigSeeder` pour initialiser `TaxSlab` et `SocialContribution` dans le schema tenant courant.
- Bank Export : support du format `virement_ma` comme export CSV bancaire standard.
- Bank Export : correction des exports pour utiliser les colonnes employees reelles (`iban`, `bank_account`) et eviter les colonnes inexistantes `rib` / `bank_name`.
- Bank Export : correction de la date d'execution SEPA via `addWeekdays`.

## [4.14.0] - 2026-05-12

### Backend — Stabilisation tests modules post-sprints

- Tests Feature : correction des tests Devin billing, prets, frais, exports, formation pour utiliser les routes et contrats API actuels.
- Tests Feature : `CreatesMvpSchema` reconstruit maintenant le schema minimal des modules post-sprints testes (billing, paie, recrutement, formation, prets, frais, vehicules).
- Migrations : rattrapage idempotent du contrat `audit_logs` moderne quand l'ancienne table tenant existe deja.
- Migrations : ajout d'un rattrapage `updated_at` pour les anciennes factures publiques.
- Exports : correction des exports employes/attendance pour ne plus selectionner des colonnes absentes du schema courant.

## [4.13.0] - 2026-05-11

### Open Source — Attractivite communautaire

- CONTRIBUTING.md : guide complet pour les nouveaux contributeurs (prereqs, workflow, conventions)
- CODE_OF_CONDUCT.md : Contributor Covenant v2.1
- Good First Issue template : .github/ISSUE_TEMPLATE/good_first_issue.md
- RELEASE_PROCESS.md : documentation du processus de release et versioning
## [4.12.0] - 2026-05-11

### DevOps — Health enrichi, Metrics, Structured Logging

- Health : checks queue (driver + size) et memory (usage_mb, peak_mb, limit_mb)
- Health : ajout environment et uptime_seconds dans la reponse
- Metrics : GET /api/v1/metrics — companies, employees, system info
- Logging : middleware StructuredLogging enregistre chaque requete API en JSON
- Logging : channels `structured` (daily JSON, 14j) et `audit` (daily JSON, 90j)
- Logging : exclusion automatique des endpoints /health du logging
## [4.11.0] - 2026-05-11

### Paie avancee — PDF, exports bancaires, billing, carry-forward

- PaySlip PDF :   Bulletin de paie PDF via DomPDF avec template adapte par pays (6 pays)
- PaySlip PDF : Endpoint GET /pay-slips/{id}/pdf + self-service /me/pay-slips/{id}/pdf
- PaySlip : Endpoint POST /payroll-runs/{id}/send-slips pour envoi bulletins
- Bank Export : BankExportGenerator avec 3 formats reels (SEPA XML, CCP DZ, CSV generique)
- Bank Export : SEPA XML pain.001.001.03 pour virements europeens
- Bank Export : CCP Algerie Poste format texte fixe
- Invoice PDF : Template Blade avec numero auto-incremente LEO-YYYY-XXXX
- Invoice PDF : Endpoint GET /billing/invoices/{id}/pdf genere le PDF a la volee
- Billing : Commande billing:check-trials (daily, trials expirant dans 3j)
- Billing : Commande billing:check-overdue (daily, factures en retard)
- Billing : Commande billing:generate-invoices (monthly, generation automatique)
- Leave : Commande leave:carry-forward (annuel, report soldes + expiration)
- Scheduler : 4 nouvelles commandes enregistrees dans le scheduler
- SCENARIOS_TEST : Documentation de tous les nouveaux endpoints

## [4.10.0] - 2026-05-11
 
### Mise a jour PLAN_ACTION — Bilan post sprints 1-18

- PLAN_ACTION : Mise a jour 00_SOMMAIRE.md v2.0 avec etat reel de chaque module (fait/reste)
- PLAN_ACTION : Mise a jour 12_PRIORITES_ROADMAP.md avec statut par sprint et metriques actuelles
- PLAN_ACTION : Nouveau fichier 13_RESTANT_POST_SPRINTS.md — consolidation de ~79 taches restantes
- PLAN_ACTION : Categorisation par priorite (critique/haute/moyenne/basse) et effort estime (~105 jours-dev)
- PLAN_ACTION : Ordre d'execution recommande en 4 phases (A-D) sur 90+ jours

## [4.7.0] - 2026-05-11

### Sprint 13-14 — Billing, Onboarding & Feature Flags

- Billing : Migration pour 3 tables (subscriptions, invoices, payments)
- Billing : Modeles Subscription, Invoice, Payment
- Billing : BillingController (subscription, upgrade, cancel, renew, invoices, PDF)
- Billing : PaymentWebhookController (Stripe + Chargily webhooks)
- Onboarding : Migration table onboarding_steps + modele OnboardingStep
- Onboarding : OnboardingStepController (checklist dynamique, progress, complete, skip)
- Onboarding : Auto-seed 10 etapes par defaut lors du premier acces
- Features : Migration table feature_plan_matrix + modele FeaturePlanMatrix
- Features : FeatureFlagController (matrix CRUD, check par feature/plan)
- Features : FeatureService avec cache (active/limit par company)
- Features : FeaturePlanMatrixSeeder avec 17 features x 4 plans
- Routes : ~15 nouveaux endpoints dans modules/billing.php

## [4.6.0] - 2026-05-11

### Sprint 11-12 — Modules RH avances

- Recrutement : Actions publish, close, delete sur JobPosting avec workflow statut
- Recrutement : Detail candidat avec entretiens, changement statut pipeline, suppression
- Recrutement : Feedback entretien avec notation, annulation entretien
- Self-service : GET /me/trainings (mes formations avec sessions et cours)
- Self-service : POST /me/trainings/{sessionId}/enroll (auto-inscription formation)
- Self-service : GET /me/loans (mes prets avec compteur echeances)
- Self-service : GET /me/loans/{id}/repayments (echeancier de mon pret)
- Rapports avances : Pipeline recrutement (candidats par statut)
- Rapports avances : Completion formations (inscriptions par statut)
- Rapports avances : Resume prets (montants par statut)
- Rapports avances : Demographics (effectifs par departement et type contrat)
- Rapports avances : Analyse couts (prets actifs + inscriptions formations par annee)
- Routes : ~17 nouveaux endpoints dans hr_extended.php

## [4.9.0] - 2026-05-11

### Sprint 17-18 — IA avancee (Voice, Agents, Analytics)

- Voice IA : VoiceController (transcribe, synthesize, command pipeline complet)
- Voice IA : Support 4 langues (FR/AR/TR/EN) avec Whisper STT + Edge TTS
- Voice IA : Integration Deepgram (STT) et ElevenLabs (TTS) en alternative
- Voice IA : Pipeline audio-in -> transcription -> IA -> synthese -> audio-out
- Agents : AgentRunner (multi-step tool calling autonome, max 10 etapes)
- Agents : AgentController (run task, list workflows predefinis)
- Agents : 3 workflows predefinis (paie, rapport hebdo, onboarding employe)
- Analytics IA : AIAnalyticsController (usage, costs, tools, errors)
- Analytics IA : Usage par tenant, couts par periode/provider, outils top, taux erreur
- Config : config/ai.php enrichi avec voice providers et agent settings
- Routes : ~9 nouveaux endpoints dans routes/ai.php
## [4.8.0] - 2026-05-11

### Sprint 15-16 — Dashboard API, Notifications & Exports

- Dashboard : DashboardController (summary, recent-activity, KPI mensuel)
- Dashboard : NotificationController (liste paginee, unread, mark read, mark all)
- Dashboard : ExportController (export employes JSON/CSV, export attendance JSON/CSV)
- Routes : ~9 nouveaux endpoints dans modules/dashboard.php

## [4.5.0] - 2026-05-11

### Sprint 9-10 — Tracking vehicules (Integration Traccar)

- Tracking : Migration idempotente pour 5 tables (vehicles, vehicle_assignments, vehicle_trips, vehicle_alerts, vehicle_maintenances)
- Tracking : 5 modeles (Vehicle, VehicleAssignment, VehicleTrip, VehicleAlert, VehicleMaintenance)
- Tracking : TraccarService complet (devices, positions, trips, geofences, events)
- Tracking : VehicleController CRUD complet avec position GPS, trips, alerts, maintenance sub-resources
- Tracking : Affectation/desaffectation chauffeurs avec historique
- Tracking : VehicleTripController (liste paginee, detail)
- Tracking : VehicleAlertController (liste filtrable, acquittement)
- Tracking : VehicleMaintenanceController CRUD (enregistrement, suivi, prochaine maintenance)
- Tracking : FleetController (overview, live-map, rapports fuel/mileage/maintenance-due)
- Tracking : TrackingSyncController (sync devices, positions, trips depuis Traccar)
- Tracking : Config config/tracking.php avec feature flag et parametres Traccar
- Routes : nouveau fichier routes/modules/tracking.php (~25 endpoints)
## [4.4.0] - 2026-05-11

### Sprint 7-8 — Couche IA Phase 1

- IA : Architecture complète avec Orchestrator, IntentEngine, ToolRegistry, MemoryManager, AIAuditLogger
- IA : LLMClient abstrait avec implémentations OpenAI (GPT-4o) et Claude (Sonnet)
- IA : 3 middlewares (AIRateLimiter, AITenantInjector, AIFeatureCheck) pour quotas/tenant/feature flag
- IA : AIGatewayController avec endpoints POST /api/ai/chat, GET /api/ai/chat/history, DELETE /api/ai/chat/{id}, GET /api/ai/tools
- IA : Migration idempotente pour 3 tables (ai_conversations, ai_audit_logs, ai_tool_registry)
- IA : 3 modèles (AIConversation, AIAuditLog, AIToolRegistryEntry)
- IA : 4 DTOs (AIRequest, AIResponse, ToolCall, ToolResult)
- IA : Seeder avec 15 outils IA (get_employees, search, attendance, absences, payroll, etc.)
- IA : System prompt multilingue dans resources/ai/system_prompt.md
- IA : Config config/ai.php avec quotas par plan SaaS et support multi-provider
- Routes : nouveau fichier routes/ai.php

## [4.3.0]  - 2026-05-10

### Reorganisation structure depot

- Structure : Suppression `.jules/` et `.kiro/` (artifacts agents IA), ajout au `.gitignore`
- Structure : `GOTO_MARKET/` deplace dans `docs/GOTO_MARKET/`
- Structure : Frontends (`mobile/`, `web/`, `admin-dashboard/`, `zkteco-kiosk/`) regroupes dans `front/`
- Structure : 19 fichiers `.md` techniques deplaces de la racine vers `docs/`
- CI : Tous les workflows GitHub Actions mis a jour pour les nouveaux chemins `front/`
- Docs : Toutes les references internes mises a jour (AGENTS.md, README.md, DEVELOPMENT.md, PLAN_ACTION, etc.)
- README : URLs corrigees (`your-org/leopardo-rh` → `kitokoh/leopardo-hr`)

### Open Source (Plan 10)

- Docker : `docker-compose.yml` a la racine (api + postgres + redis + dashboard + web)
- DevContainer : `.devcontainer/devcontainer.json` pour GitHub Codespaces
- Scripts : `scripts/setup-labels.sh` pour creer les 23 labels GitHub organises

### Sprint 5-6 — Conges avances + Contrats

- Leave : DELETE /leave-policies (desactivation douce)
- Leave : GET /me/leave-balances (self-service employe)
- Leave : GET/POST /leave-accruals (historique + cumul manuel)
- Leave : Commande `leave:accrue` (scheduleur quotidien, accumulation le 1er du mois)
- Contracts : POST /contracts/{id}/activate, /suspend, /terminate, /renew
- Contracts : GET /me/contracts (self-service)
- Contracts : GET /contracts/{id}/generate-pdf
- Contracts : Commande `contracts:alert-expiring` (alertes a 30/15/7 jours)
- Approvals : ApprovalController complet (CRUD workflows, pending/approve/reject/history)
- Approvals : Trait `Approvable` pour integration polymorphique
- Tests : LeavePolicyApiTest, ContractWorkflowTest, AccrueLeaveBalancesTest
- Scheduler : Commandes enregistrees dans `bootstrap/app.php`

### Paie Complete Multi-Pays (Plan 03)

- Paie : moteur de calcul configurable par pays avec interface `CountryRulesInterface`
- Paie : 6 implementations pays — Algerie (DZ), Maroc (MA), Tunisie (TN), France (FR), Turquie (TR), Senegal (SN)
- Paie : `PayrollCalculator` service avec calcul automatique des cotisations sociales et impots
- Paie : modeles `SalaryStructure`, `SalaryComponent`, `PayrollRun`, `PaySlip`, `PaySlipLine`, `TaxSlab`, `SocialContribution`, `BankExport`
- Paie : migration idempotente pour 8 nouvelles tables (salary_structures, salary_components, tax_slabs, social_contributions, payroll_runs, pay_slips, pay_slip_lines, bank_exports)
- Paie : controllers CRUD pour structures salariales, composants, tranches impots, cotisations sociales
- Paie : workflow payroll run complet (draft -> calculating -> calculated -> validated -> paid/cancelled)
- Paie : generation bulletins de paie avec lignes detaillees (gains, deductions, cotisations patronales)
- Paie : self-service `/me/pay-slips` pour les employes
- Paie : generation export bancaire (CSV generique, SEPA, CCP Algerie, virement Maroc)
- Routes : nouveau fichier `routes/modules/payroll_engine.php` (~30 endpoints)
## [4.2.1] - 2026-05-10

### Sprint 1-2 Completion — Fondations manquantes

- Architecture : `AuditLogger` listener implementé — écoute les 8 domain events et écrit automatiquement dans `audit_logs`
- Architecture : `WebhookListener` implementé — dispatche automatiquement les domain events vers les webhook endpoints configurés par tenant
- Architecture : Appels `event()` ajoutés dans `EmployeeService`, `AbsenceService`, `AttendanceService`, `PayrollService`
- Architecture : `EventServiceProvider` créé pour câbler listeners aux events
- Architecture : Template module DDD dans `stubs/module-template/` avec structure complète
- Architecture : Commande Artisan `php artisan make:module {Name}` pour scaffolding DDD
- Monitoring : Endpoints `/api/v1/health/live` (liveness) et `/api/v1/health/ready` (readiness) ajoutés
- Monitoring : Config Sentry performance (`config/sentry.php`) avec traces et profiling
- Docs : `DEVELOPMENT.md` créé — guide setup rapide (Docker + local), structure projet, commandes utiles
- Tests : `HealthLiveReadyTest`, `RequestIdMiddlewareTest`, `AuditLoggerListenerTest`, `MakeModuleCommandTest`

## [4.2.0] - 2026-05-10

### Architecture & Fondations (Plan 01)

- Architecture : 8 domain events (EmployeeCreated, AttendanceCheckedIn/Out, AbsenceRequested/Approved/Rejected, PayrollValidated, EmployeeArchived)
- Architecture : systeme AuditLog avec migration `audit_logs` et trait `Auditable` pour auto-logging CRUD
- Architecture : systeme Webhook complet (migration `webhook_endpoints`/`webhook_deliveries`, service `WebhookDispatcher`, job `DispatchWebhook` avec retry 3x)
- Architecture : middleware `RequestIdMiddleware` pour tracabilite des requetes API
- Architecture : migration indexes de performance (composites sur employees, absences, attendance_logs, payrolls)

### Modules API manquants (Plan 02) — ~50 nouveaux endpoints, 21 modeles

- Module A : Conges avances — `LeavePolicy`, `LeaveBalance`, `LeaveAccrual`, `ApprovalWorkflow`, `ApprovalRequest`, `ApprovalDecision`
- Module B : Contrats de travail — `Contract`, `ContractAmendment` avec endpoint contrats expirants
- Module C : Recrutement/ATS — `JobPosting`, `Applicant`, `Interview` avec workflow complet
- Module D : Formation/LMS — `TrainingCourse`, `TrainingSession`, `TrainingEnrollment`
- Module E : Prets employes — `EmployeeLoan`, `LoanRepayment` avec echeancier auto-genere
- Module F : Notes de frais — `ExpenseClaim`, `ExpenseItem` avec soumission/approbation
- Module G : Organigramme — endpoints arbre hierarchique, subordonnes, chaine managers
- Module H : Rapports RH — effectifs, turnover, absenteisme, masse salariale, heures supplementaires
- Module I : Webhooks API — CRUD endpoints, liste evenements disponibles, historique livraisons
- Module J : Audit Trail — liste filtrable paginee avec detail par entree
- Routes : nouveau fichier `routes/modules/hr_extended.php` enregistre dans `api.php`
- PHPStan : baseline regeneree pour inclure les nouveaux fichiers

### Plan d'action documentaire

- Docs : 13 fichiers dans `docs/PLAN_ACTION/` couvrant architecture, modules, paie, IA, tracking, interfaces, monitoring, tests, onboarding, open source, GTM, roadmap

## [4.1.120] - 2026-05-10

### DocKeeper - Alignement documentaire

- Docs : alignement de `docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md` avec la gouvernance actuelle (remplacement des références à `JOURNAL_DE_BORD.md` par `JOURNAL_RACINE.md`).
- Gouvernance : synchronisation de la version globale du projet à `4.1.120` dans `PILOTAGE.md`, `api/config/app.php` et `CHANGELOG.md`.

## [4.1.119] - 2026-05-09

### Branding - Professionnalisation Enterprise-grade du dépôt

- Docs : refonte complète du `README.md` racine en vitrine SaaS moderne avec badges dynamiques, diagrammes d'architecture et hub documentaire.
- Docs : création de `ARCHITECTURE.md`, `SECURITY.md`, `AI_ARCHITECTURE.md`, `TESTING.md`, `DEPLOYMENT_GUIDE.md`, `ROADMAP.md` et `QUICKSTART.md` à la racine.
- Docs : organisation du hub documentaire dans `/docs/` (Architecture, API, Mobile, Web, AI, Sécurité, Contributing, Deployment, Testing).
- Gouvernance : implémentation des templates standards GitHub (Bug Report, Feature Request, Pull Request) et des fichiers communautaires (`CODE_OF_CONDUCT.md`, `SUPPORT.md`).
- DevExp : ajout du script `scripts/bootstrap.sh` pour l'initialisation automatique des environnements API et Web.
- API : initialisation de la spécification OpenAPI v1 dans `openapi/v1.yaml`.
- Branding : ajout de diagrammes Mermaid illustrant l'authentification multi-tenant et la topologie de la base de données.

### Janitor - Hygiène du dépôt

- Gouvernance : correction du Mojibake (corruption d'encodage UTF-8) dans `CHANGELOG.md` et `PILOTAGE.md` pour restaurer la lisibilité des accents français et des emojis.

## [4.1.118] - 2026-05-08

### Plateforme - Provisioning depuis demandes clients

- API : l'approbation `PATCH /api/v1/platform/company-requests/{id}` provisionne maintenant la company, cree le manager principal et lie `approved_company_id`.
- API : l'approbation accepte un `plan_id` optionnel et utilise le premier plan actif par defaut pour eviter une validation commerciale bloquee.
- Tests : ajout d'un scenario feature couvrant approbation, creation company, manager principal et invitation.

## [4.1.117] - 2026-05-08

### Admin dashboard - Accueil cockpit

- Admin : remplacement du dashboard d'accueil base sur endpoints inexistants/mockes par une synthese branchee sur `companies/health`, `plans` et `company-requests`.
- Admin : ajout des priorites clients, demandes entrantes, adoption terrain, ARPA et raccourcis cockpit depuis la page d'accueil.
- Gouvernance : documentation des scenarios dashboard v5.0 relies aux contrats plateforme reels.

## [4.1.116] - 2026-05-08

### Admin dashboard - Intake clients

- Admin : remplacement de la page Support mockee par une file de demandes clients branchee sur `GET /api/v1/platform/company-requests`.
- Admin : ajout des filtres statut, compteurs pending/approved/rejected, notes internes et actions approuver/rejeter via `PATCH /api/v1/platform/company-requests/{id}`.
- Gouvernance : les scenarios web admin incluent maintenant la qualification des demandes entrantes sans donnees mockees.

## [4.1.115] - 2026-05-08

### Admin dashboard - Cockpit plateforme

- Admin : remplacement des placeholders Entreprises par un cockpit portefeuille branche sur `GET /api/v1/platform/companies/health`.
- Admin : ajout du detail entreprise avec health client, actions prioritaires et formulaire abonnement branche sur les endpoints `health`, `subscription` et `plans`.
- Admin : enrichissement de la page Abonnements avec catalogue plans API, MRR portefeuille et clients prioritaires a traiter.
- Gouvernance : les scenarios web admin couvrent maintenant le cockpit plateforme et ses contrats backend v5.0.

## [4.1.114] - 2026-05-08

### Plateforme - Catalogue plans API

- API : ajout de `GET /api/v1/platform/plans` pour exposer au super-admin le catalogue des plans disponibles.
- API : le catalogue plans retourne prix mensuel/annuel, plafond employes, features, jours d'essai et statut actif afin d'alimenter les formulaires d'abonnement.
- Tests : ajout d'une couverture Feature verifiant l'ordre par prix et le decodage des features de plan.

## [4.1.113] - 2026-05-08

### Plateforme - Contrat abonnement client

- API : ajout de `GET/PATCH /api/v1/platform/companies/{company}/subscription` pour lire et mettre a jour plan, statut, dates d'abonnement et notes client.
- API : le contrat abonnement expose les informations plan utiles au billing futur (`price_monthly`, `price_yearly`, `max_employees`) sans integrer de fournisseur de paiement.
- Tests : ajout d'une couverture Feature super-admin pour lecture, upgrade de plan et validations de statut/dates.

## [4.1.112] - 2026-05-08

### Plateforme - Portefeuille clients

- API : ajout de `GET /api/v1/platform/companies/health` pour donner au super-admin une vue portefeuille des clients.
- API : le portefeuille agrège nombre de clients, clients actifs, MRR, repartition des risques et prochaine action prioritaire par client.
- Tests : ajout d'un scenario Feature couvrant un client sain upsellable et un client suspendu dans la vue portefeuille.

## [4.1.111] - 2026-05-08

### Plateforme - Pilotage adoption client

- API : ajout de `GET /api/v1/platform/companies/{company}/health` pour donner au super-admin une vue adoption/risque par client.
- API : le health client expose plan, MRR, features actives, usage pointage 30 jours, progression onboarding, anomalies et prochaines actions prioritaires.
- Tests : ajout d'une couverture Feature pour un client sain upsellable et un client suspendu a risque eleve.
- Gouvernance : ajout du nouveau contrat health plateforme dans les scenarios API critiques.

## [4.1.110] - 2026-05-08

### Backend - Valeur terrain attendance

- API : `GET /api/v1/attendance/anomalies` expose maintenant un bloc `business_impact` et une `recommended_action` par anomalie pour transformer les signaux de pointage en actions manager avant la paie.
- API : `GET /api/v1/attendance/monthly-report` ajoute les jours travailles et une estimation paie terrain (`estimated_gross_payroll`, `estimated_overtime_pay`, montants par employe) basee sur `hourly_rate` ou `salary_base`.
- API : l'export CSV et le PDF du rapport mensuel incluent les nouveaux indicateurs de jours travailles et d'estimation paie.
- Onboarding : `GET /api/v1/onboarding/checklist` ajoute les etapes `payroll_ready` et `geofence_configured`, un statut `go_live_ready` et les prochaines actions prioritaires.
- Tests : couverture Feature ciblee pour figer les nouveaux champs business des anomalies, rapports mensuels et checklist go-live.

## [4.1.109] - 2026-05-08

### Plateforme admin - Confort de login

- Admin : ajout d'un toggle afficher / masquer le mot de passe sur l'ecran de login, avec labels ARIA dynamiques et conservation du flux 2FA existant.
- Tests : ajout d'un scenario Playwright dedie pour verifier le changement de type `password` <-> `text` sans casser les selecteurs du login admin.

## [4.1.108] - 2026-05-08

### CI - Gates proportionnees au perimetre modifie

- CI : le job mobile du workflow `Tests - Leopardo RH` ne se declenche plus sur un simple changement de `.github/workflows/tests.yml`; il reste reserve aux vraies modifications `mobile/**`.
- CI : `Backend Quality` garde maintenant une gate PHPStan/Larastan bloquante, mais son scope est limite aux fichiers PHP backend modifies par le diff afin d'eviter qu'une dette historique hors perimetre empeche un lot propre d'etre merge.
- Gouvernance : mise a jour des scenarios backend/admin et du registre canonique pour couvrir explicitement le contrat d'auth plateforme, le cas `TWO_FA_REQUIRED` et la place de la vitrine multilingue dans la CI.

## [4.1.107] - 2026-05-08

### Plateforme admin - Contrat API aligne

- Admin : `admin-dashboard` utilise maintenant les vraies routes `/platform/auth/*` au lieu de l'ancien contrat fantome `/admin/auth/*`.
- Admin : alignement de la shape de session avec `PlatformAuthController` (`data`, `token`, `token_type`) et ajout du role `super_admin` dans `login` / `me`.
- Admin : suppression de la tentative de refresh token inexistante et nettoyage direct de session sur `401`.
- Admin : le login plateforme gere maintenant le cas `TWO_FA_REQUIRED` avec saisie de code 2FA cote interface.

### CI - Gates plus veridiques

- CI : `tests.yml` ne laisse plus `PHPStan`, `flutter analyze`, `flutter test`, le gate de coverage mobile et le smoke build Android passer en faux vert.
- CI : ajout d'un workflow dedie `web-marketing-ci.yml` pour lint/build de `web/**`, afin que la vitrine publique soit testee comme surface produit a part entiere.

### Vitrine web - Rail multilingue public

- Web : ajout d'un socle de locale client pour la vitrine (`FR/EN/TR/AR`) avec persistance, synchro document `lang/dir` et selecteur visible dans la navbar.
- Web : traduction des sections landing principales (hero, features, demo, pricing, testimonials, FAQ, CTA, footer) et des jeux de donnees associes.
- Web : mise a jour des metadata globales pour mieux refleter la vitrine multilingue et le positionnement RH terrain.

## [4.1.106] - 2026-05-08

### Web - Pattern Vercel functions corrige

- Web : suppression du bloc `functions.api/**` de `web/vercel.json`, qui ne correspondait plus a la structure Next.js actuelle et faisait echouer Vercel avec `The pattern "api/**" defined in functions doesn't match any Serverless Functions`.
- Ops : dans ce projet, les route handlers Next sont sous `web/src/app/api/**`; ne pas garder de pattern `functions` Vercel s'il n'est pas aligne sur les vraies fonctions generees.

## [4.1.105] - 2026-05-08

### Web - Compatibilite Vercel

- Web : suppression du bloc `env` invalide de `web/vercel.json` qui cassait les builds Vercel avec l'erreur `env.NEXT_PUBLIC_API_URL should be string`.
- Ops : les variables `NEXT_PUBLIC_*`, `NEXTAUTH_*` et secrets web doivent rester configurees dans le dashboard Vercel, pas decrites comme objets metadata dans `vercel.json`.

## [4.1.104] - 2026-05-08

### Backend - Migrations publiques Render hors transaction

- API : les migrations publiques `2026_05_02_000003_create_company_requests_table.php` et `2026_05_02_100001_create_users_and_company_requests_tables.php` desactivent maintenant l'encapsulation transactionnelle Laravel via `withinTransaction = false`.
- API : ce choix evite qu'une course concurrente `42P07` sur Render laisse toute la migration suivante dans un etat PostgreSQL `25P02 current transaction is aborted`.
- Ops : pour les migrations publiques idempotentes exposees aux demarrages concurrents, preferer des operations non transactionnelles plus des gardes/catches explicites plutot qu'une transaction globale du migrateur.

## [4.1.103] - 2026-05-08

### Backend - Hotfix transaction PostgreSQL Render

- API : correction du hotfix Render sur `company_requests` pour ne plus executer de `Schema::hasTable(...)` dans une transaction PostgreSQL deja invalidee.
- API : en cas de `SQLSTATE[42P07]`, les migrations publiques traitent maintenant le conflit comme une course gagnee par un autre processus sans relancer une requete qui provoquerait `SQLSTATE[25P02]`.
- Ops : le correctif vise explicitement les deploys Render concurrents ou plusieurs processus de migration passent sur la meme table publique au meme instant.

## [4.1.102] - 2026-05-08

### Backend - Hotfix Render migration race

- API : durcissement des migrations publiques `2026_05_02_000003_create_company_requests_table.php` et `2026_05_02_100001_create_users_and_company_requests_tables.php` pour absorber les courses concurrentes PostgreSQL sur Render.
- API : gestion explicite des erreurs SQLSTATE `42P07` pendant les `Schema::create(...)` ; si la table apparait entre le test et la creation, la migration devient un no-op au lieu de faire echouer le deploiement.
- Ops : documentation ajoutee dans `AGENTS.md` pour rappeler que `Schema::hasTable()` n'est pas atomique sur Render et qu'une migration publique sensible doit traiter la duplication concurrente comme un cas idempotent.

## [4.1.101] - 2026-05-07

### Internationalisation - Socle enterprise centralise

- I18N : ajout d'une source de verite partagee shared/i18n/ avec catalogues versionnes FR/AR/TR/EN, glossaire metier verrouille, schema et checksums.
- I18N : ajout des scripts validate.js, sync-mobile.js, sync-web.js et sync-backend.js pour valider puis generer les sorties Flutter ARB, web JSON et Laravel lang/ depuis une base unique.
- API : ajout d'un endpoint public GET /api/v1/i18n/catalog{/{locale}} avec normalisation de variantes (fr-CA, en-GB, ar-SA), ETag, checksum, metadata et fallback distant.
- Mobile : preparation du cache local de catalogues distants et extension du support de locales variantes/RTL dans l'application Flutter.
- CI : ajout du workflow GitHub Actions i18n-enterprise.yml pour rendre la validation et la synchronisation i18n bloquantes.
- Docs : ajout de docs/GESTION_PROJET/ARCHITECTURE_I18N_ENTERPRISE_2026-05-07.md pour cadrer l'architecture cible, la migration et les risques.

## [4.1.100] - 2026-05-07

### CI/CD - Gouvernance de scenarios et deploiement bloque par preuves

- CI : ajout d'un registre canonique `docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md` pour lier domaines, workflows, artefacts et gates de deploiement.
- CI : ajout de `docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md` pour formaliser la couverture Playwright du dashboard admin.
- Gouvernance : `tools/check-governance.ps1` bloque maintenant une PR si une surface fonctionnelle API, mobile ou web evolue sans mise a jour de sa base de scenarios ou du registre central.
- Web CI : enrichissement des artefacts Playwright avec `junit.xml`, rapport HTML, traces et videos retenues en echec.
- Deploiement : `deploy-main.yml` ne part plus simplement parce qu'un workflow s'est termine ; il verifie maintenant que les workflows requis pour le meme SHA de `main` sont bien conclus avec succes avant de deployer.

## [4.1.99] - 2026-05-07

### Go-To-Market - Viabilite globale

- Docs : ajout du dossier racine `GOTO_MARKET/` pour centraliser la strategie commerciale, les segments ICP, le packaging, le playbook vente, les canaux d'acquisition, le calendrier 90 jours, les KPI, les templates operationnels et les assets d'inspiration.
- Docs : integration du document inspirant `Leopardo_RH_Production_Creative.pdf` comme source de production creative IA-first, sans creer de dossier nomme `marketing`.
- Docs : ajout d'une boussole de viabilite et repositionnement pour rappeler que le projet doit utiliser la tech afin de repondre a des besoins actuels, generer du revenu et accepter les modernisations necessaires.
- Docs : ajout de `GOTO_MARKET/public/` pour structurer les contenus publics de presentation sur LinkedIn, WhatsApp, Instagram/Facebook, landing pages, videos, presse, partenaires, ads, calendrier editorial et metriques.
- Docs : ajout d'un pack lancement acquisition avec scripts video, sequence email pilote, lead magnet checklist, messages WhatsApp, posts LinkedIn, copies ads et playbook owned channels.
- Produit : documentation d'une future brique `product_marketing_automation/`, non implementee, pour cadrer l'idee d'aider les entreprises clientes a gerer et automatiser leur marketing.

## [4.1.98] - 2026-05-07

### Federation - PR ouvertes utiles

- Securite : durcissement du module Evaluations avec `StoreEvaluationRequest`, `UpdateEvaluationRequest`, `EvaluationPolicy` et une couverture de regression dediee pour l'isolation tenant.
- Securite : correction de la recherche employe lors du login Google pour eviter les collisions inter-tenant dues aux global scopes.
- Securite/Tests : ajout de `AuthenticatedGuardrailsTest` pour verrouiller la revocation immediate des sessions quand le statut employe ou entreprise devient invalide, avec compatibilite SQLite amelioree dans `Company`.
- Performance : optimisation de `CabinetDocumentController@index` pour limiter les colonnes chargees, et extension du schema SQLite de test au module Cabinet.
- Mobile : amelioration de l'UX auth avec retour haptique leger et labels d'accessibilite sur les ecrans de connexion/inscription.
- Mobile : amelioration de l'ecran des avances sur salaire avec pull-to-refresh fiable, etats vides scrollables, semantics plus coherentes et navigation retour plus claire.
- Hygiene : suppression des logs CI accidentellement suivis a la racine et ajout de garde-fous `.gitignore` pour eviter leur retour.

## [4.1.97] - 2026-05-08

### Sentinel - Renforcement de la sécurité Evaluation

- API : Introduction de `StoreEvaluationRequest` et `UpdateEvaluationRequest` pour durcir la validation des évaluations, incluant une vérification tenant-scoped sur `employee_id`.
- API : Création de `EvaluationPolicy` pour centraliser l'isolation tenant et les règles RBAC sur le module évaluations.
- API : Refactorisation de `EvaluationController` pour utiliser les nouveaux FormRequests et la Policy, éliminant les vulnérabilités IDOR potentielles.
- Tests : Ajout de `api/tests/Feature/Security/EvaluationSecurityTest.php` pour verrouiller l'isolation inter-tenant et le RBAC du module évaluations.
## [4.1.97] - 2026-05-07

### ⚡ Bolt - Performance et optimisation Cabinet

- API : optimisation de `CabinetDocumentController@index` par l'ajout de `select()`, évitant ainsi la récupération de colonnes non utilisées (`path`, `disk`) lors de la liste des documents.
- Tests : mise à jour de `CreatesMvpSchema` pour inclure les tables du module Cabinet (`cabinet_folders`, `cabinet_documents`) dans l'environnement de test SQLite.

## [4.1.96] - 2026-05-07

### Tests - Guardrails d'authentification

- Tests : ajout de `api/tests/Feature/Security/AuthenticatedGuardrailsTest.php` pour verrouiller le blocage immédiat des sessions actives lorsque le statut de l'employé ou de l'entreprise devient invalide (archivé, suspendu, expiré).
- API : correction de `Company::booted` pour assurer la compatibilité avec SQLite dans les environnements de test lors de la mise à jour du statut de l'entreprise.
### Mobile - Ameliorations UX et accessibilite

- Mobile : amelioration de l'accessibilite de la liste des avances de salaire avec labels semantiques unifies (montant, motif, statut) et tooltip de retour.
- Mobile : ajout d'un rafraichissement manuel (`RefreshIndicator`) et d'etats vides/erreur scrollables sur l'ecran des avances.

### Mobile - Fondation i18n

- Mobile : ajout de la configuration `gen-l10n`, des premiers catalogues ARB FR/EN/TR/AR et du helper `context.l10n`.
- Mobile : raccord de `AppLocalizations.delegate` dans l'application Flutter existante.
- Mobile : migration initiale de l'ecran Welcome vers les cles localisees, en gardant le support RTL deja present.
- Docs : ajout dans `AGENTS.md` d'une lecon operationnelle sur le demarrage progressif des chantiers i18n mobile.

## [4.1.95] - 2026-05-07

### Backend - Intelligence pointage

- API : ajout de `GET /api/v1/attendance/anomalies` pour donner aux managers un resume actionnable des retards, sorties manquantes, corrections manuelles, heures supplementaires elevees et pointages rapproches sur un meme appareil.
- API : ajout de la detection avancee des pointages hors zone autorisee (`company.metadata.attendance_geofence`) et des pointages a heure trop repetitive.
- API : ajout de `GET /api/v1/attendance/monthly-report` en JSON, CSV et PDF pour produire le rapport mensuel comptable/manager.
- API : ajout de `GET /api/v1/onboarding/checklist` pour exposer la progression d'installation client (societe, manager, equipe, biometrie, kiosque).
- Platform : ajout de `GET/PATCH /api/v1/platform/companies/{company}/features` pour piloter les modules actifs par entreprise via `companies.features`.
- Tests : couverture de l'acces manager, du refus employe et de l'isolation tenant sur les anomalies de pointage.

## [4.1.95] - 2026-05-07

### CI/CD - Realignement executable

- CI : remplacement des anciens workflows web cibles sur `web/**` par un workflow unique `web-ci.yml` aligne sur `admin-dashboard/**`.
- CI : ajout d'un smoke test Playwright dedie au dashboard d'administration avec artifacts de debug en cas d'echec.
- CI : ajout d'une couverture backend visible dans `tests.yml` avec artifact Clover, rapport HTML et seuil progressif configurable via `BACKEND_COVERAGE_MIN`.
- CI : ajout d'un cache Composer base sur `api/composer.lock` pour les jobs backend lourds.
- CI : le job backend remonte maintenant un echec reel si les suites Unit/Feature cassent au lieu de laisser `continue-on-error` masquer le probleme.
- API : la migration publique `2026_05_02_100001_create_users_and_company_requests_tables.php` met maintenant a niveau une table `company_requests` legacy pour aligner le schema attendu (`user_id`, `email`, `phone`, `description`, `admin_notes`, `reviewed_at`) avec les controllers actuels.
- API/Tests : normalisation UTF-8 des messages FR et des assertions backend pour eviter les faux rouges lies au mojibake (`Employé`, `Récupère`, etc.).
- Securite : ajout d'un workflow TruffleHog pour le scan automatique des secrets.
- Docs : simplification du runbook backup/restore autour d'une procedure minimale explicite (backup hebdomadaire, restore mensuel).

## [4.1.94] - 2026-05-07

### Mobile - Mise a jour dependances Flutter

- Mobile : mise a jour ciblee de `flutter_secure_storage` de `10.0.0` vers `10.1.0`.
- Mobile : mise a jour ciblee de `google_sign_in` de `6.3.0` vers `7.2.0`.
## [4.1.93] - 2026-05-07

### Mobile - Durcissement du contrat attendance

- Mobile : durcissement du parsing des modeles `Employee` et `AttendanceLog` pour mieux tolerer les ecarts de types dans les payloads (`int.tryParse`, champs employe imbriques, `photo_url`/`photo_path`).
- Mobile : enrichissement du mapping `AttendanceRepository` pour recuperer proprement les metadonnees employe exposees dans les reponses attendance.
- Tests : ajout d'une couverture explicite du payload `/api/v1/attendance` dans `MobilePayloadContractTest` pour verrouiller le contrat mobile de l'historique.

## [4.1.92] - 2026-05-06

### Mobile - Federation UX et dependances

- Mobile : amelioration de l'ecran des fiches de paie avec retour `go_router`, rafraichissement manuel et etats vides/chargement plus robustes.
- Mobile : amelioration de l'accessibilite de l'historique de pointage avec labels semantiques plus explicites pour les lecteurs d'ecran.
- Mobile : mise a jour des dependances Flutter du lot `#298` sans reintroduire `flutter_haptic`.

## [4.1.91] - 2026-05-06

### CI - Ciblage web par chemins

- CI : limitation des workflows web `build.yml`, `lint.yml` et `test.yml` aux modifications de `web/**` ou de leur propre fichier YAML pour eviter les executions inutiles sur des PR backend, mobile ou documentation.

## [4.1.90] - 2026-05-06

### CI - Workflows GitHub

- CI : retrait des jobs GitHub Vercel bases sur `vercel/action@v4`, introuvable cote Actions, pour garder le workflow `Build & Deploiement` limite au build verifie.
- CI : passage de `lighthouse.yml` en declenchement manuel afin d'eviter les echecs immediats lies a un probleme de definition du workflow.

## [4.1.89] - 2026-05-06

### Mobile - Android release build

- Mobile : suppression de la dependance `flutter_haptic`, inutilisee dans le code et incompatible avec le build Android release CI faute de `namespace` declare.

## [4.1.88] - 2026-05-06

### Render - Release integration hardening

- API : rendre idempotentes les migrations publiques `company_requests`, `users` et `user_employee_links` pour eviter les echecs PostgreSQL `relation already exists` lors des redeploiements Render.
## [4.1.87] - 2026-05-06

### Agents - Guide operationnel racine

- Docs : ajout de `AGENTS.md` a la racine pour transmettre aux prochaines sessions les procedures CI rapides, les pieges Render/Vercel/migrations et les regles de nettoyage branches/main.

## [4.1.86] - 2026-05-06

### Render - Hotfix migration company_requests

- API : rend la migration publique `2026_05_02_000003_create_company_requests_table` idempotente afin d'eviter l'erreur PostgreSQL `Duplicate table` lorsque `company_requests` existe deja en production.

## [4.1.85] - 2026-05-02

### CI/CD - Résolution des problèmes de pipeline et tests

- API : Correction de la compatibilité SQLite dans `api/tests/TestCase.php` — gestion du driver PostgreSQL vs SQLite pour `SET search_path`
- Mobile : Formatage complet des fichiers Dart (7 fichiers) avec `dart format`
- Docs : Ajout de `MOBILE_API_SYNC_CI_CD_FIXES.md` avec documentation complète des fixes et instructions Docker
- Tests : Safeguards ajoutés dans `FeatureDetector` pour éviter les boucles infinies lors du scan de routes

### Admin Dashboard - Implémentation complète Phase 1, 2, 3

- Frontend : Création du dashboard d'administration interne avec Vue.js 3, Pinia, Tailwind CSS
- Phase 1 (Foundation) : Architecture de base, authentification, WebSocket, layout responsive
- Phase 2 (Intelligence) : Analytics avancées, prédictions de churn, revenue forecasting, gestion utilisateurs
- Phase 3 (Automation) : Administration système, tâches automatisées, backups, monitoring sécurité, auto-scaling
- Composants : 47 fichiers, 9981 insertions — tous les composants fonctionnels avec données mock

### Web - Modules et dépendances

- Web : Ajout de modules vitrine (landing page) avec composants réutilisables
- Web : Mise à jour des dépendances (package.json, package-lock.json)
- Web : Sections Hero, Features, Pricing, Testimonials, FAQ, CTA avec animations
## [4.1.85] - 2026-05-03

### Auth - Self-registration, Google Sign-In & Company Requests

- API : nouveau modele `User` (schema public) pour les comptes ordinaires sans entreprise, avec support Sanctum et Google ID.
- API : `UserAuthService` — inscription email/mot de passe, connexion, Google Sign-In avec emission de tokens et verrouillage de compte.
- API : `UserAuthController` — register, login, googleSignIn, me, updateProfile, changePassword, logout.
- API : `CompanyRequestController` — soumission et consultation de demandes de creation d'entreprise (scope user).
- API : `UserEmployeeLinkController` — liaison d'un compte ordinaire a un employe par le manager.
- API : `PlatformCompanyRequestController` — validation/rejet des demandes par le super-admin.
- API : migration `2026_05_02_100001` — tables `users`, `company_requests`, `user_employee_links`.
- API : guard `user_api` (Sanctum + users provider) dans `config/auth.php`.
- API : routes `/v1/user/*` et `/v1/platform/company-requests/*`.
- API : i18n fr, en, tr, ar pour le module user.
- Mobile : packages `flutter_animate`, `google_sign_in`, `cached_network_image`, `flutter_haptic`.
- Mobile : `UserRegisterScreen` — inscription avec email/mot de passe + Google Sign-In, design moderne avec animations.
- Mobile : `UserLoginScreen` — connexion compte personnel avec Google Sign-In.
- Mobile : `UserHomeScreen` — espace personnel avec acces Placard, creation d'entreprise, liens employe.
- Mobile : `CompanyRequestScreen` — formulaire de soumission de creation d'entreprise.
- Mobile : modele `AppUser`, `UserAuthRepository`, `UserAuthProvider` (Riverpod StateNotifier).
- Mobile : `WelcomeScreen` mis a jour avec bouton "Creer un compte personnel".
- Mobile : `LoginScreen` mis a jour avec lien "Connexion compte personnel".
## [4.1.87] - 2026-05-05

### Janitor - Hygiène du dépôt

- Dépôt : Normalisation des marqueurs historiques dans `docs/notes/archive/` pour assurer la conformité avec le protocole d'archivage (usage systématique du préfixe 📦).
## [4.1.86] - 2026-05-03

### DocKeeper - Alignement documentation

- Ops : Mise à jour du `docs/GESTION_PROJET/RUNBOOK_BETA_ENV_SETUP.md` (v1.1) pour l'aligner sur l'infrastructure Render + Neon (PaaS), remplaçant les instructions VPS obsolètes.
- Gouvernance : Synchronisation de `PROGRAM_VERSION` à `4.1.86` dans `PILOTAGE.md`, `CHANGELOG.md`, `docs/README.md` et `api/config/app.php`.
- Gouvernance : Mise à jour de la date de dernière mise à jour dans `PILOTAGE.md` (2026-05-03).

## [4.1.84] - 2026-04-30 
### Mobile-API Synchronization - Système de synchronisation automatique des fonctionnalités

- API : Implémentation complète du système de synchronisation mobile-API avec détection automatique des nouvelles fonctionnalités
- API : Nouveau modèle `Feature` avec table `features` pour l'inventaire centralisé des fonctionnalités API
- API : Service `FeatureRegistry` pour la gestion du registre des fonctionnalités avec cache intelligent
- API : Service `FeatureDetector` utilisant la réflexion PHP pour détecter automatiquement les nouvelles routes API
- API : Contrôleur `FeatureManifestController` avec endpoints `/api/v1/features/manifest`, `/api/v1/features/compatible/{version}`, `/api/v1/features/{key}`
- API : Endpoints d'administration `/api/v1/features/admin/statistics` et `/api/v1/features/admin/synchronize` pour les super-admins
- API : Attributs PHP `#[ApiFeature]`, `#[MobileCompatible]`, `#[RequiresPermission]` pour l'annotation des contrôleurs
- API : Services `AnnotationReader` et `ReflectionService` pour l'analyse des métadonnées des contrôleurs
- Mobile : Modèles `Feature`, `FeatureManifest`, `FormSchema`, `ListSchema` pour la synchronisation
- Mobile : Service `SynchronizationEngine` avec synchronisation intelligente et gestion des versions
- Mobile : Générateur d'interface `DynamicUIGenerator` pour créer automatiquement les écrans mobiles
- Mobile : Cache local avec `Hive` et signatures cryptographiques pour l'intégrité des données
- Mobile : Support complet des formulaires dynamiques, listes et actions avec validation
- Tests : Suite complète de tests unitaires et d'intégration pour tous les composants
- Tests : Tests de propriétés (Property-Based Testing) pour la validation des invariants
- Docs : Documentation technique complète du système de synchronisation
- Sécurité : Signatures cryptographiques des manifestes et gestion des permissions par fonctionnalité
- Performance : Cache intelligent avec invalidation automatique et synchronisation < 5 secondes
- Compatibilité : Support des 3 dernières versions mobiles majeures avec migration automatique

## [4.1.84] - 2026-04-30


### API / Mobile / Web - Experience client alignee et modernisee

- API : ajout de `mobile_experience` dans le payload `/api/v1/auth/me` via `MobileExperienceService`, avec modules exposes, quick actions et stade d'usage pour les clients mobiles.
- API : renforcement de `api/tests/Feature/Contracts/MobilePayloadContractTest.php` pour verrouiller ce nouveau contrat mobile.
- Mobile : realignement de `welcome`, `login`, `home` et `modules hub` sur l'experience mobile-first documentee, avec consommation directe du payload backend (`features` + `mobile_experience`).
- Mobile : ajout des modeles `MobileExperience`, `MobileModule`, `MobileQuickAction` et du mapping d'icones associe pour garder une navigation coherente avec les routes reelles de l'application.
- Web : suppression de la dependance au telechargement Google Fonts au build, correction des effets React penalises par linter, et fallback API aligne sur `https://gestionemployerbackend.onrender.com/api/v1`.
- Web : remplacement des liens dashboard cassés par de vraies pages RH branchees a l'API pour `employees`, `attendance` et `absences`.

### API - Performance et durcissement multi-tenant

- API : optimisation de `PayrollController@index`, `AbsenceController@index` et `EvaluationController@index` avec des `select()` explicites et des relations chargees sur des colonnes limitees pour reduire la sur-recuperation.
- API : elimination des concatenations directes de `schema_name` dans plusieurs `SET search_path` sensibles au profit de `Company::getSafeSearchPath()`.
- API : protection des creations d'absences et de bulletins contre les references inter-tenant via des validations `exists` scopees au `company_id` courant.
- Tests : ajout de `api/tests/Feature/Security/CrossTenantValidationTest.php` et enrichissement de `CreatesMvpSchema` pour couvrir correctement `payrolls`, `payment_method` et `leave_balance`.

### Sentinel - Sécurisation des index et tests de régression Salary Advance

- API : Durcissement des requêtes de liste (IndexRequest) pour les modules `Absences`, `Payroll`, `Attendance` et `SalaryAdvances` via l'ajout d'une validation `exists` systématiquement scopée au tenant de l'utilisateur pour le champ `employee_id`.
- Tests : Création de `SalaryAdvanceSecurityTest.php` pour verrouiller l'isolation inter-tenant et le RBAC du module des avances sur salaire.
- Tests : Ajout de tests de régression dans `AbsenceIndexTest` et `TodayAndHistoryTest` pour vérifier la protection contre le filtrage par `employee_id` hors-tenant.

### Mobile - Contrat attendance et UX absences

- Mobile : parsing de `photo_url`, `hire_date`, `overtime_hours` et `late_minutes` aligne sur les payloads backend actuels.
- Mobile : `AbsenceListScreen` gagne le pull-to-refresh, un meilleur etat vide scrollable et de petites ameliorations d'accessibilite.

### Depot - Hygiene

- Depot : suppression du gitlink fantome `.codex-pr-140` reste d'une branche bot obsolete.
## [4.1.84] - 2026-05-02

### DocKeeper - Alignement documentation

- Gouvernance : Mise à jour du `PULL_REQUEST_TEMPLATE.md` pour refléter la structure canonique post-MVP (substitution de `INDEX_CANONIQUE.md` par `PILOTAGE.md`).
- Gouvernance : Archivage définitif des documents de pilotage historiques à la racine (`08_FEUILLE_DE_ROUTE.md`, `CU-01_ET_AGENTS.md`, `ARBORESCENCE_PROJET_COMPLET.md`) vers `docs/notes/archive/`.
- Gouvernance : Synchronisation de `PROGRAM_VERSION` à `4.1.84` dans `PILOTAGE.md`, `CHANGELOG.md` et `api/config/app.php`.
- Gouvernance : Ajout de `GO_NO_GO_MVP.md` comme source de vérité canonique dans `PILOTAGE.md` et `docs/README.md`.
- Gouvernance : Mise à jour de `CODE_VERSION` à `0.1.0` dans `PILOTAGE.md` (release MVP prête).
- Gouvernance : Correction d'une anomalie chronologique pour la version `[4.1.71]` (date corrigée en 2026-04-24).
- Dépôt : Synchronisation des dates et versions dans `docs/README.md` (v4.1.84 | Mai 2026).

## [4.1.84] - 2026-05-01

### Performance - Optimisation des index Absence et Evaluation

- API : Optimisation de `AbsenceController@index` via l'ajout de `select()` et du chargement lié (`with`) limité en colonnes pour `absenceType`, évitant ainsi le sur-récupération de données.
- API : Optimisation de `EvaluationController@index` via l'ajout de `select()` et du chargement lié (`with`) limité en colonnes pour `employee` et `evaluator`.
## [4.1.84] - 2026-05-02 module placard 

### Sentinel - Sécurisation des validations et tests d'isolation

- API : Renforcement de la validation dans `StoreAbsenceRequest` et `StorePayrollRequest` pour empêcher les attaques par IDOR (Insecure Direct Object Reference) en vérifiant systématiquement l'appartenance des IDs (absence_type_id, employee_id) au tenant de l'utilisateur authentifié.
- Tests : Extension massive de `TenantModelIsolationTest` pour couvrir 10 modèles supplémentaires (Employee, Absence, Payroll, Task, etc.), garantissant une isolation stricte entre les entreprises.
- Tests : Ajout de `CrossTenantValidationTest` pour verrouiller les nouvelles protections contre les fuites de données inter-tenant lors de la création de ressources.
- Gouvernance : Synchronisation de `PROGRAM_VERSION` à `4.1.84` dans `PILOTAGE.md`, `CHANGELOG.md` et `api/config/app.php`.

## [4.1.87] - 2026-05-05

### ⚡ Bolt - Performance et optimisation HR Referentials

- API : optimisation de `DepartmentController@index` par l'ajout de `select()` et du chargement lié (`with`) limité en colonnes pour `manager`, évitant ainsi la sur-récupération de données.
- API : optimisation de `PositionController@index` par l'ajout de `select()` et du chargement lié (`with`) limité en colonnes pour `department`, évitant ainsi la sur-récupération de données.

## [4.1.86] - 2026-05-03


### Auth - Auto-inscription, Google Sign-In, espace personnel et demandes d'entreprise

- API : ajout du rôle `ordinary` pour les utilisateurs sans entreprise immédiate (Espace Personnel).
- API : migration pour rendre `company_id` nullable dans `employees` et `user_lookups`.
- API : implémentation de `POST /api/v1/auth/register` pour l'auto-inscription en tant que compte ordinaire.
- API : intégration de Laravel Socialite pour Google Sign-In avec endpoints `redirectToGoogle`, `handleGoogleCallback` et `handleGoogleToken` (mobile).
- API : création du modèle `CompanyRequest` et des endpoints associés pour permettre aux comptes ordinaires de demander la création d'une entreprise (incluant les détails du manager).
- API : mise à jour de `TenantMiddleware` pour autoriser l'accès à l'API aux utilisateurs `ordinary` sans contexte d'entreprise.
- Mobile : ajout des dépendances `google_sign_in` et `url_launcher`.
- Mobile : implémentation de `PersonalSpaceScreen` (accueil pour comptes sans entreprise) et `CompanyRequestScreen` (formulaire de demande enrichi).
- Mobile : ajout du bouton "Continuer avec Google" sur `LoginScreen` et activation du formulaire sur `RegisterScreen`.
- Mobile : mise à jour du routeur pour gérer les redirections vers l'espace personnel pour les utilisateurs `ordinary`.
- CI : résolution d'un bug de `flutter pub get` via un fallback `--offline` dans le workflow de test.
## [4.1.87] - 2026-05-03

### Sentinel - Durcissement de l'isolation tenant sur les filtres d'index

- API : Renforcement de la validation dans `SalaryAdvanceIndexRequest`, `AbsenceIndexRequest` et `EvaluationIndexRequest` pour empêcher les fuites de données inter-tenant (ID Enumeration) en vérifiant systématiquement l'appartenance de `employee_id` et `evaluator_id` au tenant de l'utilisateur.
- Tests : Ajout de `SalaryAdvanceSecurityTest` et `IndexCrossTenantValidationTest` pour verrouiller l'impossibilité de probe ou filtrer des ressources d'un autre tenant via les paramètres de requête.
### ðŸ›¡ï¸ Sentinel - Renforcement de la sécurité multi-tenant (SalaryAdvance)

- API : Durcissement de `SalaryAdvanceIndexRequest` par l'ajout d'une validation tenant-scoped sur `employee_id`, empêchant l'énumération d'identifiants inter-tenant.
- Tests : Ajout de `api/tests/Feature/Security/SalaryAdvanceSecurityTest.php` pour verrouiller l'isolation des avances sur salaire et la validation des filtres.
- Gouvernance : Synchronisation de `PROGRAM_VERSION` à `4.1.87` dans `PILOTAGE.md`, `CHANGELOG.md` et `api/config/app.php`.

## [4.1.86] - 2026-05-03
### Janitor - Hygiène du dépôt

- Dépôt : Archivage de la version obsolète de la stratégie CI/CD (o2switch/VPS) `docs/dossierdeConception/10_deploiement_cicd/19_CICD_ET_GIT.md` vers `docs/notes/archive/19_CICD_ET_GIT_O2SWITCH.md` pour éliminer la confusion avec la version Render active.

### ⚡ Bolt - Performance et optimisation Employee

- API : optimisation de `EmployeeController@index` et `EmployeeController@show` par l'ajout de `with('company')` pour eliminer les requetes N+1 lors de la resolution de la ressource.
- API : ajout explicite de `preferred_language` et `extra_data` dans le `select()` de `EmployeeController@index` pour garantir l'integrite du payload `EmployeeResource` et des services associes.

## [4.1.85] - 2026-05-02
## [4.1.82] - 2026-04-29

### Janitor - Hygiène du dépôt et sécurité

- Mobile : Retrait des fichiers d'environnement (`.env.local`, `.env.production`, `.env.staging`) du suivi Git pour éviter la fuite de configurations locales ou sensibles.
- Mobile : Création de `mobile/.env.example` comme modèle de configuration.
- Dépôt : Mise à jour du `.gitignore` racine pour ignorer systématiquement les fichiers d'environnement mobile tout en autorisant le modèle `.env.example`.
- Gouvernance : Synchronisation de `PROGRAM_VERSION` à `4.1.82` dans `PILOTAGE.md`, `CHANGELOG.md` et `api/config/app.php`.

### API - Maintenance dependances frontend

- API : mise a jour du groupe de dependances frontend dans `api/` via Dependabot (`laravel-vite-plugin`, `tailwindcss` et `vite`) pour rester aligne avec la toolchain front embarquee.


### Mobile - Maintenance dependances

- Mobile : mise a jour du groupe de dependances Flutter dans `mobile/` via Dependabot (`cupertino_icons`, `flutter_riverpod`, `go_router`, `image_picker`, `local_auth`, `flutter_lints` et mises a jour transitives associees).

## [4.1.81] - 2026-04-28

### Janitor - Hygiène du dépôt et synchronisation

- Dépôt : Suppression du fichier généré accidentellement `api/test-results.xml` du suivi Git.
- Dépôt : Ajout de `test-results.xml` au fichier `api/.gitignore`.
- Gouvernance : Synchronisation de `PROGRAM_VERSION` à `4.1.81` dans `PILOTAGE.md`, `CHANGELOG.md` et `api/config/app.php`.
- Gouvernance : Correction d'une anomalie de date dans l'entrée `4.1.79` du CHANGELOG (2026-05-22 → 2026-04-27).

### API - Super-Admin 2FA & Hardening Cameras

- API : Implémentation du second facteur d'authentification (2FA) pour les Super-Admins (`setup`, `enable`, `disable`) avec génération de secret TOTP et QR Code.
- API : Renforcement de `CameraPolicy` avec vérification systématique de l'expiration des permissions via le helper `activePermission`.
- API : Alignement des routes `platform/auth` et correction du binding `PublicCameraViewerController`.
- Docs : Mise à jour majeure de `api/openapi.yaml` pour refléter les modules `Tasks`, `Evaluations` et `Notifications` (82+ endpoints synchronisés).
- Tests : Ajout de `PlatformAuthTest` couvrant le workflow 2FA complet.

## [4.1.80] - 2026-04-27

### API - Module Evaluations (module 7 complément)

- API : ajout du modèle `Evaluation` avec trait `BelongsToCompany`, scopes (`draft`, `submitted`, `forEmployee`) et relations `employee`/`evaluator`.
- API : ajout de `EvaluationController` avec 8 endpoints REST couvrant le workflow complet `draft → submitted → acknowledged`.
- API : `GET /api/v1/evaluations` — manager voit toutes les évaluations, employé voit uniquement les siennes.
- API : `POST /api/v1/evaluations` — manager crée une évaluation en statut `draft` (validation doublon employee+evaluator+period).
- API : `PUT /api/v1/evaluations/{id}/submit` — manager soumet une évaluation (`draft → submitted`).
- API : `PUT /api/v1/evaluations/{id}/acknowledge` — employé accuse réception (`submitted → acknowledged`).
- API : `DELETE /api/v1/evaluations/{id}` — manager supprime uniquement les évaluations `draft`.
- API : protection contre modification après `acknowledged` et contre suppression si non-`draft`.

## [4.1.79] - 2026-04-27

### Documentation & Outils - Standardisation des Mocks API

- Docs : Création de `docs/api-mock-data/` contenant des exemples de réponses JSON pour tous les endpoints de l'API, facilitant le développement offline.
- Outils : Ajout de `tools/generate_api_examples.py`, un utilitaire Python permettant de régénérer les fichiers de mock à partir de la spécification OpenAPI (`api/openapi.yaml`).
- Mobile : Synchronisation des assets de mock (`mobile/assets/mock/`) avec les nouveaux schémas standardisés.
- Mobile : Refactorisation de `MockInterceptor` pour charger dynamiquement les fichiers JSON standardisés au lieu d'utiliser des données codées en dur.
- Docs : Mise à jour du `mobile/README.md` et création d'un `README.md` dans `docs/api-mock-data/` pour documenter la source de vérité des mocks.

## [4.1.78] - 2026-04-27

### API - Public onboarding par invitation

- API : ajout de `GET /api/v1/onboarding/invitation/{token}` pour verifier un token d'invitation public et retourner les informations minimales d'activation (`email`, `role`, `manager_role`, `employee_name`, `expires_at`).
- API : ajout de `POST /api/v1/onboarding/invitation/{token}/activate` pour activer un compte employe invite, valider le mot de passe et emettre un token Sanctum de connexion immediate.
- API : ajout du controleur `OnboardingController` et des routes publiques throttlees (`10 req/min`) pour l'onboarding sans authentification prealable.

### Contractor - Alignement contract API/mobile (attendance)

- API : Mise à jour de `AttendanceTodayResource` pour inclure le `matricule` et les champs d'estimation (`base_gain`, `overtime_gain`, `total_estimated`, `currency`) résolus via `EstimationService`.
- API : Mise à jour de `AttendanceLogResource` pour inclure un objet `employee` imbriqué (id, name, matricule, photo_url) dans l'historique des pointages.
- API : Optimisation de `AttendanceController@index` par le chargement lié (`with`) de la relation `employee` pour éviter les requêtes N+1.
- API : Standardisation des types de retour pour `hours_worked` et `overtime_hours` (float) dans les ressources de présence.
- Mobile : Mise à jour du modèle `DailySummary` pour inclure et parser `hoursWorked` et `overtimeHours`.
- Tests : Renforcement de `MobilePayloadContractTest` avec une couverture explicite de `/api/v1/me/daily-summary` et verrouillage des nouveaux champs du contrat.
- Tests : Sécurisation de `CreatesMvpSchema` pour la compatibilité SQLite (garde `SET search_path`).

## [4.1.77] - 2026-04-27

### Documentation - Gouvernance premium et gestion projet
### Sécurité & Performance - Stabilisation multi-tenant et standardisation API

- API : Sécurisation des commandes `SET search_path` PostgreSQL via l'introduction de `Company::getSafeSearchPath()` pour prévenir les injections SQL.
- API : Refactorisation de `MeController` pour utiliser les `JsonResource` standardisées de Laravel (`AttendanceLogResource`, `AttendanceTodayResource`).
- API : Optimisation des requêtes dans `EstimationService` par l'ajout de clauses `select()` limitant les colonnes récupérées sur `AttendanceLog`.

- Docs : `docs/README.md` est realigne avec la structure canonique actuelle, complete avec `GUIDES/` et `notes/`, et enrichi d'un bloc de standards documentaires.
- Docs : creation des points d'entree `docs/GESTION_PROJET/README.md`, `docs/notes/README.md` et `docs/PROMPTS_EXECUTION/README.md` pour rendre la navigation plus senior et explicite.
- Docs : correction des chemins de validation et enrichissement du cadrage QA dans `docs/validation/README.md` et `docs/validation/01_pointage/README.md`.
- Vision : nettoyage des references PDF archivees, renommage des suffixes `+1` en noms d'archive lisibles, et realignement des chemins dans `docs/vision/README.md`, `docs/REFERENTIEL_PRODUIT/ROADMAP.md` et `docs/REFERENTIEL_PRODUIT/AUDIT_v2_v3_COMPLIANCE.md`.
- Pilotage : `PILOTAGE.md`, `PLAN_ACTION_AMELIORATION.md` et `api/config/app.php` sont remis en coherence sur `PROGRAM_VERSION 4.1.77`.

## [4.1.76] - 2026-04-27

### Qualité & Robustesse - Plan d'Action Amélioration Phase 2, 3 & 4

- API : Centralisation de la gestion multi-tenant via `TenantManager` service (isolation `search_path` robuste avec `withinTenant`).
- API : Introduction des DTOs (`CreateEmployeeDTO`, `UpdateEmployeeDTO`, `CheckInDTO`) pour typer les échanges entre contrôleurs et services.
- API : Refactorisation complète des contrôleurs vers `JsonResource` (`EmployeeResource`, `AttendanceLogResource`, etc.) pour une sérialisation standardisée.
- API : Configuration du Rate Limiting dynamique par entreprise (300 req/min) et par IP (60 req/min) dans `AppServiceProvider`.
- API : Gel du mode "schema" Enterprise via un observer `creating` sur le modèle `Company` pour sécuriser le MVP.
- API : Internationalisation complète du Dashboard Blade et création des fichiers `lang/{fr,en}/dashboard.php`.
- Web : Pagination des employés sur le dashboard manager pour améliorer les performances sur les gros comptes.
- Ops : Ajout d'un hook de **rollback automatique** dans le workflow GitHub Action `deploy-main.yml` en cas d'échec du smoke test post-déploiement sur Render.
- Docs : Mise à jour de `PLAN_ACTION_AMELIORATION.md` (Actions 6, 7, 8, 10, 11, 12, 13, 14, 15 marquées terminées).

## [4.1.75] - 2026-04-27

### Sécurité - Plan d'Action Amélioration Phase 1 (P0)

- API : Implémentation du chiffrement `EncryptedCast` sur les colonnes sensibles (`iban`, `bank_account`, `national_id`) dans le modèle `Employee`.
- API : Migration `encrypt_existing_sensitive_data` pour sécuriser les données existantes en base.
- API : Configuration explicite du middleware CORS dans `config/cors.php` pour autoriser le frontend web et l'application mobile.
- API : Système de lockout anti-brute-force — verrouillage du compte après 5 tentatives échouées pendant 15 minutes (`failed_login_attempts`, `locked_until`).
- API : Nouvelle exception `AccountLockedException` (HTTP 423) et mise à jour d' `AuthService` pour gérer le verrouillage.
- Mobile/API : Retrait de `google-services.json` du suivi Git et mise à jour du `.gitignore`.
- Docs : Mise à jour de `PILOTAGE.md` et `PLAN_ACTION_AMELIORATION.md` pour refléter la complétion de la Phase 1.

## [4.1.75] - 2026-04-27

### RH - Salary advances et 2FA console super-admin

- API : ajout du module `salary_advances` avec son modele, son service, ses requetes de validation, son controleur REST et ses routes RH pour permettre la creation, la consultation, l'approbation, le rejet et l'annulation d'avances sur salaire.
- API : `PlatformAuthController` et `SuperAdminService` introduisent la verification 2FA pour la console super-admin, avec generation de secret, URL `otpauth://` et validation de code TOTP.
- Tests : `api/tests/Support/CreatesMvpSchema.php` couvre maintenant explicitement `salary_advances` dans le schema MVP afin d'aligner les tests backend avec le nouveau module RH.

### Cameras - Alignement DDD modulaire et autoload

- API : les classes du module Cameras sont maintenant alignees sur leur arborescence `app/Modules/Cameras/...` avec des namespaces PSR-4 coherents pour `Domain`, `Infrastructure`, `Interfaces/Api/V1/Controllers` et `Interfaces/Api/V1/Requests`, afin que Composer puisse les charger correctement apres la migration modulaire.
- API : `api/routes/modules/cameras.php`, `api/app/Providers/AuthServiceProvider.php` et `api/app/Policies/Cameras/CameraPolicy.php` sont realignes sur les nouveaux namespaces modulaires pour retablir le chargement des routes et des policies Cameras.

### RH - Payroll module

- API : ajout des endpoints RH `payrolls` dans `api/routes/modules/rh.php` pour exposer la consultation, la creation, la mise a jour, la validation et la suppression des bulletins via `PayrollController`.

### Documentation - Reorganisation des referentiels canoniques

- Docs : reorganisation des documents produit, vision, validation, strategie commerciale et infrastructure vers des sous-dossiers canoniques plus explicites afin de reduire les collisions de noms et clarifier les points d'entree documentaires.
- Docs : mise a jour des index et README associes (`docs/README.md`, `docs/GUIDES/README.md`, `docs/infra/README.md`, `docs/vision/README.md`, `docs/dossierdeConception/README.md`) pour reflecter les nouveaux emplacements et l'ordre de lecture attendu.
- Docs : requalification des references produit, strategie commerciale, infra, validation et vision vers leurs nouveaux emplacements canoniques.
- Pilotage : synchronisation des references de gouvernance et de pilotage pour que les chemins documentaires exposes au projet restent coherents avec l'arborescence reelle du repo.
- Docs : reorganisation des references produit, strategie commerciale, infra, validation et vision pour refleter la nouvelle structure documentaire sans s'appuyer sur les anciens emplacements.

## [4.1.74] - 2026-04-27

### Client - Alignement i18n mobile/web avec l'API

- API : `Language`, `SetLocale`, `AuthController`, `bootstrap/app.php` et les tests associes sont durcis pour que les langues actives en base gouvernent le runtime, que le contrat d'erreur localise reste coherent (`error` + `message` + `localized_message`) et que `is_rtl` soit expose aux clients.
- Mobile : `mobile/lib/core/api/api_client.dart` envoie maintenant `Accept-Language`, privilegie `localized_message` sur les erreurs et retombe sur la langue du telephone quand aucune preference compte n'est encore definie.
- Mobile : `mobile/lib/app.dart`, `mobile/lib/models/employee.dart`, `mobile/lib/features/auth/data/auth_repository.dart` et `mobile/lib/features/settings/screens/settings_screen.dart` appliquent la langue / direction (`is_rtl`) retournees par l'API et permettent a l'utilisateur de changer sa langue preferee depuis les parametres.
- Web : `web/src/lib/api-client.ts`, `web/src/lib/i18n.ts`, `web/src/components/locale-sync.tsx`, `web/src/app/auth/login/page.tsx` et `web/src/app/(dashboard)/*` utilisent la langue du navigateur en fallback, propagent `Accept-Language`, affichent les erreurs localisees et basculent le document en RTL quand necessaire.
- Tests : ajout de couverture mobile pour le parsing `language` / `is_rtl`, et extension des tests API sur les payloads auth/localisation.
- Pilotage : `PILOTAGE.md` realigne les liens de lecture recommande vers `docs/REFERENTIEL_PRODUIT/` et `docs/dossierdeConception/README.md` pour refleter la structure canonique actuelle de la documentation.

## [4.1.73] - 2026-04-26

### Multilinguisme - Support complet FR/AR(RTL)/TR/EN

- API : Migration `create_languages_table` — table `public.languages` (code CHAR(2) PK, name_fr, name_native, is_rtl, is_active) avec seeding des 4 langues supportees.
- API : Migration `add_preferred_language_to_employees` — champ `preferred_language CHAR(2)` nullable sur la table tenant `employees`, permettant un override personnel de la langue entreprise.
- API : Modele `Language` avec constantes `SUPPORTED` (fr/ar/tr/en) et `DEFAULT` (fr), methodes statiques `isSupported()` et `isRtl()`.
- API : Middleware `SetLocale` enregistre en prepend sur le stack API — resolution de la locale par priorite : preference utilisateur > langue entreprise > header `Accept-Language` > defaut `fr`. Configure `App::setLocale()` et `Carbon::setLocale()`.
- API : Integration `bootstrap/app.php` — les handlers DomainException, ValidationException, ModelNotFoundException, AuthorizationException et HttpException utilisent desormais `__('errors.CODE')` pour retourner des messages traduits selon la locale active.
- API : 36 fichiers de traduction (9 fichiers x 4 langues) dans `lang/{fr,ar,tr,en}/` : errors, auth, attendance, employees, finance, emails, pdf, cameras, validation.
- API : Endpoint `PATCH /api/v1/auth/language` — permet a l'employe authentifie de changer sa langue preferee (validation `in:fr,ar,tr,en`).
- API : Champ `language` ajoute au serializer employee (retourne dans login/me), resolu comme `preferred_language ?? company.language ?? 'fr'`.
- API : Remplacement des messages hardcodes dans AuthController, InvitationController et PlatformAuthController par des appels `__()`.
- Docs : `api/MULTILANG.md` — guide architecture i18n, usage `__()`, procedure d'ajout de nouvelles langues.
- Pilotage : `PILOTAGE.md` — statut langues mis a jour de « i18n prepare (1 langue seulement) » a « FR+AR+TR+EN production-ready ».
## [4.1.73] - 2026-04-27

### Web - Initialisation de l'application Next.js et site vitrine

- Web : Initialisation d'un projet Next.js 16 dans le dossier `web/` avec TypeScript, Tailwind CSS (v4) et App Router.
- Web : Mise en place d'un site vitrine (landing page) avec sections Hero, Fonctionnalités et Tarifs conformément à la stratégie marketing.
- Web : Implémentation d'un squelette d'authentification (page de connexion) et d'un tableau de bord (layout + page d'accueil) pour les futurs développements.
- Docs : Mise à jour de `ARBORESCENCE_PROJET_COMPLET.md`, `README.md` et `PILOTAGE.md` pour intégrer la nouvelle application web dans l'architecture du monorepo.
- Pilotage : Transition officielle du frontend web de Blade/Alpine vers Next.js pour le dashboard et la vitrine.

## [4.1.72] - 2026-04-25

### API - Durcissement attendance/auth et corrections review

- API : `api/app/Policies/AttendancePolicy.php` rebloque explicitement le pointage mobile pour les managers afin d'eviter la regression qui leur permettait de faire `check-in` / `check-out` comme un employe.
- API : `api/app/Http/Controllers/Api/V1/AttendanceController.php`, `api/app/Services/AttendanceService.php` et `api/app/Models/AttendanceLog.php` recalculent maintenant les champs derives (`status`, `late_minutes`, `hours_worked`, `overtime_hours`) lors d'une correction manuelle de pointage, en s'alignant sur les vraies colonnes `corrected_by` / `correction_note`.
- API : `api/app/Http/Controllers/Api/V1/EmployeeController.php` conserve le payload `data.id` / `data.status` sur l'archivage employe tout en ajoutant `message=EMPLOYEE_ARCHIVED`, pour rester compatible avec la suite existante.
- Tests : `api/tests/Feature/Attendance/CheckInTest.php`, `api/tests/Feature/Attendance/CheckOutTest.php`, `api/tests/Feature/Attendance/ManualUpdateTest.php` et `api/tests/Support/CreatesMvpSchema.php` couvrent la fermeture de la regression manager, la correction manuelle et le contrat d'erreur de pointage mis a jour.

- Tests : `api/tests/Support/CreatesMvpSchema.php` cree maintenant explicitement ses tables tenant dans `shared_tenants.*`, ses tables de support public dans `public.*`, et aligne aussi les fixtures `absence_types` / `absences` / `leave_balance_logs`, afin d'eviter les erreurs PostgreSQL `relation does not exist` pendant la suite backend.

### Migration - Robustesse creation user_invitations

- API : `api/database/migrations/public/2026_04_19_000012_create_user_invitations_table.php` utilise maintenant `DB::unprepared()` avec `CREATE TABLE IF NOT EXISTS` et des indexes `IF NOT EXISTS` PostgreSQL, afin qu'un rebuild/reset complet de la base de test sur Render ne casse pas si la creation de `user_invitations` est rejouee lors d'une relance ou d'une course de migration, sans tomber sur la limite PDO des multi-statements prepares.
- Tests : `api/tests/Support/CreatesMvpSchema.php` emploie maintenant aussi `CREATE TABLE IF NOT EXISTS` pour ses tables publiques creees en SQL brut (`user_lookups`, `super_admins`, `user_invitations`), afin de reduire les faux echecs backend lies a des recreations de schema pendant les tests PostgreSQL.

### Migration - Robustesse ajout colonnes JSONB company

- API : `api/database/migrations/public/2026_04_22_000014_add_metadata_and_features_jsonb.php` n'utilise plus `Schema::hasColumn()` pour ajouter `companies.features` et `companies.metadata`, mais un `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` PostgreSQL, afin de fiabiliser les rebuild/reset complets de base sur Render avant l'execution des seeders de demo.

### Seeder - Rejeu automatique et jeu de donnees demo enrichi

- API : `api/database/seeders/DemoCompanyOnceSeeder.php` versionne desormais le verrou de seed demo (`demo_company_seed_v2`) afin que le nouveau jeu de donnees de demonstration soit rejoue automatiquement au prochain deploiement avec `DEMO_SEED_ONCE=true`.
- API : `api/database/seeders/DemoCompanySeeder.php` cree desormais un jeu de donnees multi-company beaucoup plus riche pour les tests manuels et E2E : managers `principal` / `rh` / `dept` / `comptable` / `superviseur`, employes `active` / `suspended` / `archived`, plusieurs types d'absences, historiques de conges, paies et exports, projets, taches, commentaires, evaluations, notifications et audit logs.
- API : `api/database/seeders/DemoCompanySeeder.php` aligne maintenant toutes les insertions groupées dans `absences` sur le meme jeu de colonnes SQL, afin d'eviter l'erreur PostgreSQL `VALUES lists must all be the same length` pendant le seed de demonstration sur Render.
- API : `api/database/seeders/DemoCompanySeeder.php` cible maintenant explicitement `public.*` et `shared_tenants.*` pour ses lectures/ecritures critiques, afin d'eviter qu'une bascule ou une restauration imparfaite du `search_path` ne fasse echouer le seed avec des erreurs du type `relation "employees" does not exist`.
- API : `api/database/seeders/DemoCompanySeeder.php` force aussi desormais, au demarrage, la suppression de l'ancienne unicite globale sur `public.companies.schema_name` et la recreation de l'index unique partiel reserve au mode `schema`, afin que plusieurs societes de demo en `shared_tenants` puissent etre recreees meme si un ancien etat de contrainte persiste encore sur Render.
- Deploy : `api/docker-entrypoint.sh` accepte maintenant un reset complet one-shot de la base de test via `RESET_TEST_DB_ONCE=true`, memorise par un verrou `public.seed_locks` (configurable via `RESET_TEST_DB_LOCK_KEY`) pour qu'un redeploiement suivant ne repete pas le wipe.

### Seeder - Stabilisation demo multi-company

- API : `api/database/seeders/DemoCompanySeeder.php` stocke les identifiants legaux de demo dans `companies.metadata`, limite `company_settings` aux cles globales partagees du tenant commun, rend `absence_types.code` unique par company et complete `approved_by` sur les absences pending afin de fiabiliser le seed local multi-company en mode `shared_tenants`.

### Documentation - Infra actuelle officialisee en Markdown

- Docs : `docs/infra/01_etat_courant/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md` devient la reference principale de l'etat courant infra (Render / Neon / healthcheck / mobile Android actif / backup drill).
- Docs : `docs/infra/03_archives_pdf/Leopardo_RH_Architecture_Deploiement.pdf` est explicitement requalifie comme archive de vision / projection dans `docs/infra/README.md`.
- Docs : `docs/README.md` et `docs/infra/README.md` sont realignes pour faire pointer la lecture de l'infrastructure actuelle vers la reference Markdown avant le PDF historique.

### Cameras - Correctifs RBAC permissions expirees

- API : `api/app/Policies/Cameras/CameraPolicy.php` verifie desormais `expires_at` dans `update()` et `shareAccess()` afin qu'une permission interne expiree ne puisse plus autoriser la modification d'une camera ni l'emission/revocation d'acces tiers.
- Tests : `api/tests/Feature/Cameras/CamerasCrudTest.php` et `api/tests/Feature/Cameras/CameraAccessTokensTest.php` couvrent maintenant les cas de permissions `can_manage` / `can_share` expirees.

### Seeder - Alignement schema tenant

- API : `api/database/seeders/DemoCompanySeeder.php` n'envoie plus `updated_at` lors des insertions dans `departments`, `positions`, `schedules` et `sites`, en coherence avec la migration tenant qui ne declare que `created_at` sur ces tables.
- API : `api/database/seeders/DemoCompanySeeder.php` stocke maintenant les donnees legales de demo dans `companies.metadata`, limite `company_settings` aux cles globales partagees, genere un code `absence_types.code` unique par company et complete `approved_by` sur les insertions d'absences pour eviter les erreurs de seed multi-company.

### Documentation - Plan d'Action d'Amelioration

- `docs/GESTION_PROJET/PLAN_ACTION_AMELIORATION.md` : ajout du plan d'action detaillant les 15 ameliorations identifiees lors de l'audit technique, organise en 4 phases (Securite, Qualite, Robustesse, Scalabilite) avec instructions d'implementation, code d'exemple et criteres d'acceptation pour chaque action.
### Pointage - Corrections CRITIQUES (rapport Leopardo_RH_Pointage_Validation_Finale)

- API : `app/Exceptions/AlreadyCheckedInException.php` et `app/Exceptions/MissingCheckInException.php` renvoient désormais HTTP **422** (au lieu de 409) — alignement avec les règles R-PT-03 / R-PT-04 / PT-08 / PT-17.
- API : `app/Services/AttendanceService.php` (`checkOut`, `importExternalPunch`) — `hours_worked` soustrait désormais `schedule.break_minutes` (R-PT-06 / PT-13→PT-16). Pour 08:00→17:00 avec pause 60 min, on passe de 9.00 h à 8.00 h.
- API : `app/Services/AttendanceService.php` (`checkIn`, `checkOut`, `importExternalPunch`) — `late_minutes = max(0, in − start − tolerance)` (R-PT-08 / PT-02). Un check-in à 08:10 avec tolérance 15 min renvoie désormais `late_minutes=0`.
- API : `app/Policies/AttendancePolicy.php` — `checkIn`/`checkOut` exigent désormais `role='employee'` ; les managers reçoivent **403 FORBIDDEN** (PT-10).
- API : `app/Http/Controllers/Api/V1/AttendanceController.php` — `today()` (vue manager) filtre `where('status', 'active')` et n'expose plus les employés archivés/suspendus (PT-29 / PT-43).
- API : `app/Http/Middleware/TenantMiddleware.php` — bloque désormais les employés `suspended` en plus d'`archived` (`EMPLOYEE_SUSPENDED`, 403) — PT-68.
- Contrat : `openapi.yaml` mis à jour pour le statut 422 sur `/attendance/check-in` (consolidation `ALREADY_CHECKED_IN` + `GPS_OUTSIDE_ZONE`).
- Tests : `tests/Feature/Attendance/CheckInTest.php`, `tests/Feature/Attendance/CheckOutTest.php`, `tests/Unit/AttendanceServiceTest.php` mis à jour pour refléter les nouveaux statuts (422) et les nouvelles valeurs `hours_worked`/`overtime_hours`.
- Suite locale : 11/11 Unit + 87/87 Feature OK.

## [4.1.71] - 2026-04-24
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
### Mobile - Page d'accueil (WelcomeScreen) avant la connexion

- Mobile : nouvel ecran `/welcome` (`mobile/lib/features/auth/screens/welcome_screen.dart`) affiche par defaut aux utilisateurs non authentifies a la place du saut direct sur `/login`. L'ecran met en valeur les benefices employe-centres de l'app : pointage + total d'heures, parcours professionnel cumule (meme d'une entreprise a l'autre), coffre-fort de documents personnels (diplomes, contrats), et notifications des entreprises qui ont recrute l'employe. Deux CTA : "Se connecter" -> `/login` et "Creer un compte" -> `/register`
- Mobile : nouvel ecran `/register` (`mobile/lib/features/auth/screens/register_screen.dart`) qui explique le flow d'onboarding par invitation employeur (3 etapes : invitation RH -> email -> activation) et propose une capture d'email "me prevenir a l'ouverture de l'inscription libre" (UX placeholder, non branche au backend). L'inscription publique libre reste hors scope Phase 1 (pas de route API `/auth/register`, l'onboarding passe toujours par `user_invitations`)
- Mobile : `mobile/lib/app.dart` - redirection `GoRouter` mise a jour pour autoriser les routes publiques `/welcome`, `/login`, `/register` (les utilisateurs non authentifies sont maintenant rediriges vers `/welcome` au lieu de `/login`, les utilisateurs authentifies sont rediriges hors de ces routes publiques vers `/`)
- Mobile : `mobile/lib/features/auth/screens/login_screen.dart` - ajout d'un bouton "retour" (IconButton en top-left) pour revenir sur `/welcome` depuis l'ecran de connexion
- Tests : nouveau `mobile/test/features/auth/welcome_screen_test.dart` (smoke test : rendu de l'ecran, presence des CTA `Se connecter` / `Creer un compte`, presence de la marque `Leopardo RH`)
- Aucun changement backend, aucune migration. Rollback = `git revert` de la PR

### Audit de coherence PILOTAGE / CORRECTIONS (aucun changement fonctionnel)

- `PILOTAGE.md` : en-tete re-aligne sur `PROGRAM_VERSION = 4.1.70 | 2026-04-23` (precedemment `4.1.58 | 14 Mai 2025`, date erronee), date MAJ corrigee, bloc "CONVENTION DE VERSIONING" precise que la version doit rester synchrone entre CHANGELOG.md, `api/config/app.php` et `/api/v1/health`
- `api/config/app.php` : bump `APP_VERSION` default de `4.1.68` a `4.1.70` pour respecter la regle de synchronisation introduite dans PILOTAGE (PROGRAM_VERSION == config('app.version') == champ `version` de `/api/v1/health`)
- `PILOTAGE.md` : avertissement explicite en tete du document sur la divergence entre la section "SCOPE MVP VERROUILLE" (schema mode interdit, 2 roles, 2 pages Blade, VPS) et le code livre sur main (schema mode actif, 6 sous-roles manager, plusieurs pages Blade, hebergement Render) — la decision produit reste a prendre, mais le document ne peut plus etre lu comme "source de verite" sans cet avertissement
- `docs/GESTION_PROJET/CORRECTIONS.md` : audit 2026-04-22 des 7 corrections Sprint 0. Toutes sont deja appliquees sur main (C-1 `/auth/refresh` supprime d openapi.yaml, C-2 `is_active` remplace par `status`, C-3 `user_lookups` PK email, C-4 prix Starter 29 EUR, C-5 trait unique `BelongsToCompany`, C-6 archive, C-7 `bon-fixed/`). Tableau STATUT coche avec preuves ligne par ligne. Version du doc passe a `5.1`
- Aucun changement fonctionnel, aucun schema DB, aucune migration. Rollback = `git revert` de la PR
### DocKeeper - Alignement documentation infra (GO MVP)

- Docs : alignement de `PILOTAGE.md` et `RUNBOOK_BETA_ACCEPTANCE.md` avec la decision GO MVP du 2026-04-21 ; remplacement des references "VPS" (obsoletes) par "Render" (canonique)
- Pilotage : mise a jour du statut Sprint 4 (S4-1 et S4-2 marques comme termines sur Render)
### Durcissement CI backend + mobile

- CI backend : ajout du gate `Backend Quality (Pint + PHP Syntax + PHPStan/Larastan)` dans `.github/workflows/tests.yml` avec `composer validate`, `composer install`, `pint --test`, lint syntaxique PHP et analyse statique `PHPStan + Larastan`
- Backend quality : ajout des fichiers `api/phpstan.neon` et `api/phpstan-baseline.neon` pour versionner la configuration et preparer une baseline propre sans desactiver les futurs ecarts
- Backend quality : ajout du workflow manuel `.github/workflows/phpstan-baseline.yml` pour generer un candidat de baseline `phpstan-generated-baseline.neon` en artifact depuis GitHub Actions quand de nouveaux ecarts statiques apparaissent
- Gouvernance : `tools/check-governance.ps1` verifie maintenant la presence de la configuration PHPStan, des redirections historiques vers `PILOTAGE.md` et l alignement des noms de checks documentes
- Branche/protection : `.github/BRANCH_PROTECTION_REQUIRED.md` aligne les checks requis sur les vrais noms GitHub (`Backend Quality ...`, `CodeQL (Actions)`)
- Dependances : ajout de `.github/dependabot.yml` pour `composer`, `npm` et `pub`
- Mobile CI : les checks `dart format`, `flutter analyze` et `Dependency Review` sont desormais bloquants ; les tests Flutter produisent aussi un artifact machine-readable `test-results.json`, un resume de couverture `coverage/summary.txt` et un gate de couverture base sur `MOBILE_COVERAGE_MIN` (defaut 25%)
- Mobile linting : `mobile/analysis_options.yaml` active `avoid_print: true`
- Reporting CI : le job `Notify Result` telecharge maintenant les syntheses backend/mobile, les injecte dans `ci-report.md` et les publie aussi dans le Step Summary GitHub Actions pour accelerer le triage

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
- Vision : creation des documents canoniques `docs/REFERENTIEL_PRODUIT/APV.md` (manifeste 1 page, 4 piliers, 12 Lois), `docs/REFERENTIEL_PRODUIT/ROADMAP.md` (Phase 0 MVP -> Phase 1 fondations -> Phase 2 modules a la demande), `docs/REFERENTIEL_PRODUIT/STATUTS.md` (catalogue exhaustif pointage/invitation/employe/features), `docs/REFERENTIEL_PRODUIT/COULEURS.md` (tokens couleur domaine + semantique + neutres), `docs/REFERENTIEL_PRODUIT/AUDIT_v2_v3_COMPLIANCE.md` (matrice conformite code vs vision)
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

- Ajout règle “scribeâ€ dans `docs/GESTION_PROJET/GARDE_FOUS.md` : un seul agent édite `PILOTAGE.md` + `CHANGELOG.md`
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
