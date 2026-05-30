# PLAN D'ACTION COMPLET — LEOPARDO RH

**Version :** 2.0  
**Date :** 2026-05-11 (mise a jour post-sprints 1-18)  
**Objectif :** Amener Leopardo RH au niveau enterprise-grade, capable de rivaliser avec ERPNext/Frappe HR sur le domaine RH, tout en conservant l'avantage terrain (pointage, biometrie, geofence, mobile natif).

**Public cible :** Developpeurs (juniors inclus), agents IA, contributeurs open-source.

---

## Index du dossier

| # | Fichier | Contenu |
|---|---------|---------|
| 00 | `00_SOMMAIRE.md` | Ce fichier — index et vision globale |
| 01 | `01_ARCHITECTURE_FONDATIONS.md` | Fondations techniques : DDD, event sourcing, API versioning, scalabilite, conventions code |
| 02 | `02_MODULES_API_MANQUANTS.md` | Tous les modules API manquants : endpoints, modeles, migrations, policies — prets a coder |
| 03 | `03_PAIE_COMPLETE.md` | Paie legale multi-pays (DZ, MA, TN, FR, TR, SN), conformite, exports bancaires, bulletins |
| 04 | `04_COUCHE_IA.md` | Architecture IA parallele : AI Gateway, Orchestrator, Tool Registry, Voice, Memory, phases |
| 05 | `05_TRACKING_VEHICULES.md` | Integration Traccar : architecture, endpoints API, modeles, sync utilisateurs |
| 06 | `06_INTERFACES.md` | Interfaces web (dashboard admin, blog vitrine), mobile Flutter, kiosk — ecrans a creer |
| 07 | `07_MONITORING_LOGGING_OBSERVABILITE.md` | Monitoring, logging, alerting, health checks, APM, error tracking |
| 08 | `08_TESTS_CI_CD.md` | Strategie tests (unit, feature, E2E, Playwright), CI/CD pipelines, deploy automatique |
| 09 | `09_ONBOARDING_WORKFLOWS_ABONNEMENTS.md` | Onboarding clients, workflows approbation, billing, abonnements, feature flags |
| 10 | `10_OPEN_SOURCE_GITHUB.md` | Ameliorer le depot GitHub pour attirer des devs : templates, labels, good first issues, docs |
| 11 | `11_GTM_EXECUTION.md` | Plan marketing execution etape par etape : Maghreb, Afrique francophone, contenu, SEO, vente |
| 12 | `12_PRIORITES_ROADMAP.md` | Ordre d'execution, dependances entre modules, timeline realiste, criteres de validation |
| 13 | `13_RESTANT_POST_SPRINTS.md` | **NOUVEAU** — Consolidation de tout ce qui reste a developper apres les sprints 1-18 |
| 14 | `14_ROADMAP_EXECUTION_POST_LOTS.md` | **NOUVEAU** - Roadmap d'execution actualisee apres les lots plateforme metrics backend/admin |
| 14b | `14_PLAN_SOLIDIFICATION_MARCHE.md` | Plan de solidification 7 phases (fiabilite, securite, performance, integrations, UX, docs, GTM) |
| 15 | `15_PLAN_EXECUTION_CONSOLIDE.md` | **NOUVEAU 2026-05-14** — Plan d'execution consolide : audit code vs plans, taches restantes, iterations |
| 16 | `16_PLAN_CONSOLIDATION_LANCEMENT.md` | **NOUVEAU 2026-05-19** — Consolidation lancement : contrats API/frontends, release readiness, design vendeur, GTM |
| 17 | `17_PLAN_COVERAGE_LANCEMENT.md` | **NOUVEAU 2026-05-20** - Coverage lancement et priorites tests |
| 18 | `18_PLAN_EXPERIENCE_CLIENT_CONNEXION.md` | **NOUVEAU 2026-05-21** - Connexion client, features par role/plan, UX login et observabilite |
| 19 | `19_PLAN_COMMUNICATION_INTERNE.md` | **NOUVEAU 2026-05-22** - Notifications web/mobile, emails, SMS, WhatsApp et orchestration communication |
| 20 | `20_PLAN_READINESS_LANCEMENT_PRODUCTION.md` | **NOUVEAU 2026-05-22** - Readiness lancement, support client et controle go-live tenant |
| 21 | `21_PLAN_PROFILS_READINESS_FONCTIONNELLE.md` | **NOUVEAU 2026-05-22** - Tests multi-profils, seeders demo, parcours API/web/mobile/kiosk |
| 22 | `22_PLAN_DOC_TESTEUR_API_NOTIFICATIONS.md` | **NOUVEAU 2026-05-23** - Documentation testeur, API explorer, demo login et notifications vivantes |
| 23 | `23_PLAN_API_PRODUCTION_GRADE.md` | **NOUVEAU 2026-05-24** - Durcissement API production-grade |
| 24 | `24_PLAN_MULTILINGUE_JULES_TRANSLATION.md` | **NOUVEAU 2026-05-24** - Plan multilingue centralise pour traduction Jules |
| 25 | `25_PLAN_MODERNISATION_MOBILE_MARKETING_READY.md` | **NOUVEAU 2026-05-25** - Modernisation mobile marketing-ready |
| 26 | `26_PLAN_MOBILE_MULTI_APP_PRODUCTION.md` | **NOUVEAU 2026-05-26** - Durcissement production de la structure mobile employee/manager |
| 27 | `27_PLAN_MOBILE_RELEASE_READINESS.md` | **NOUVEAU 2026-05-26** - Readiness App Store / Play Store et verification workflows mobiles |

## Plans operationnels ajoutes apres retours testeurs 2026-05-29

Ces plans regroupent les points 31 a 44 remontes apres les tests produit. Ils sont volontairement separes en lots coherents pour eviter les implementations inachevees : chaque agent doit choisir un plan, livrer ses lots dans l'ordre, mettre a jour `CHANGELOG.md`, `AGENTS.md` si une lecon durable apparait, puis pousser via PR avant de passer au plan suivant.

| # | Fichier | Objectif |
|---|---------|----------|
| 57 | `57_PLAN_API_DOCS_ECOSYSTEME_DEVELOPPEUR.md` | Documentation API professionnelle, OpenAPI, API Explorer, erreurs standard et ecosysteme developpeur |
| 58 | `58_PLAN_TENANT_BRANDING_PREMIUM.md` | Personnalisation entreprise : logo, couleurs, identite visuelle et experience premium tenant-scope |
| 59 | `59_PLAN_POSITIONNEMENT_WORKFORCE_OS.md` | Requalification strategique du produit vers une proposition Workforce OS / Company OS mobile-first |
| 60 | `60_PLAN_AVANCES_DOUBLE_VALIDATION.md` | Workflow avance salaire securise : validation manager, paiement declare, confirmation employee |
| 61 | `61_PLAN_SOLDE_EMPLOYE_CYCLE_PAIE.md` | Solde employe, cycles de paie et paie simplifiee mobile-first |
| 62 | `62_PLAN_PDF_BORDEREAUX_ASYNC.md` | Bordereaux, recus et justificatifs PDF generes en arriere-plan |
| 63 | `63_PLAN_ARCHITECTURE_HEURES_POINTE.md` | Architecture pics de charge : Redis, cache, queues, batch jobs, k6 |
| 64 | `64_PLAN_CLOTURE_TIMEZONE_GPS.md` | Cloture automatique des journees, timezones correctes et geofence GPS douce |
| 65 | `65_PLAN_PAIEMENT_MASSE_SIGNATURE_NUMERIQUE.md` | Paiements en masse, confirmations employees, audit et preparation signature numerique |
| 66 | `66_PLAN_CONSOLIDATION_MOBILE_FIRST_COMPANY_OS.md` | Plan maitre A-J : cartographie des 44 idees consolidees, anti-oubli et ordre de livraison |

### Ordre d'execution recommande

1. **Plan 57** : stabiliser la documentation API, les contrats JSON et la vision developpeur avant d'exposer davantage de workflows.
2. **Plan 60** : securiser les avances, car le workflow financier actuel est deja utilise par les testeurs.
3. **Plan 61 + Plan 62** : construire le solde employe et les documents de paiement sans bloquer l'application.
4. **Plan 63** : deplacer les traitements lourds vers queues/cache/batch avant d'augmenter la charge marketing.
5. **Plan 64** : rendre le pointage fiable en conditions reelles multi-pays, GPS et oublis de depart.
6. **Plan 65** : ajouter paiement en masse, confirmations employees et preparation signature.
7. **Plan 58** : personnalisation premium tenant une fois le socle operationnel stable.
8. **Plan 59** : aligner la vitrine, le pricing et le storytelling sur ce qui est reellement livre.
9. **Plan 66** : maintenir la cartographie A-J a jour a chaque nouveau retour testeur ou changement de positionnement.

Chaque plan doit sortir avec son changement code/documentation, ses tests ou preuves CI, une entree `CHANGELOG.md` et, si une lecon durable apparait, une entree `AGENTS.md`.

---

## Etat actuel du projet (audit 2026-05-11 — post sprints 1-18)

### Ce qui EXISTE et FONCTIONNE

| Module | Endpoints | Modeles | Sprint | Etat |
|--------|-----------|---------|--------|------|
| Auth (login/register/SSO/2FA) | 10 | User, SuperAdmin | Pre-existant | Stable |
| Employees (CRUD + archive) | 6 | Employee | Pre-existant | Stable |
| Attendance (check-in/out, anomalies, monthly report) | 7 | AttendanceLog, AttendanceKiosk | Pre-existant | Stable |
| Absences (CRUD + approve/reject) | 5 | Absence, AbsenceType, LeaveBalanceLog | Pre-existant | Stable |
| Salary Advances (CRUD + approve/reject) | 5 | SalaryAdvance | Pre-existant | Stable |
| Departments/Positions/Sites/Schedules | 20 | Department, Position, Site, Schedule | Pre-existant | Stable |
| Notifications | 4 | Notification | Pre-existant | Stable |
| Projects & Tasks | 12 | Project, Task, TaskComment | Pre-existant | Stable |
| Evaluations | 5 | Evaluation | Pre-existant | Stable |
| Cabinet documentaire | 12 | CabinetFolder, CabinetDocument, CabinetShare | Pre-existant | Stable |
| Cameras/Surveillance | 12 | Module DDD complet | Pre-existant | Stable |
| Biometrie/Kiosks | 5 | BiometricEnrollmentRequest | Pre-existant | Stable |
| Invitations/Onboarding | 5 | UserInvitation | Pre-existant | Stable |
| Feature flags/manifest | 5 | Feature | Pre-existant | Stable |
| Plateforme super-admin | 8 | Company, CompanyRequest, CompanySetting | Pre-existant | Stable |
| i18n enterprise | 2 | Language | Pre-existant | Stable |
| Architecture & Fondations | ~50 | 8 events, AuditLog, Webhook, etc. | Sprint 1-2 | **FAIT** |
| Modules API (10 modules) | ~50 | 21 modeles (Leave, Contract, Recruitment, Training, Loan, Expense, OrgChart, Reports, Webhooks, Audit) | Sprint 1-2 | **FAIT** |
| Paie complete (DZ+MA) | ~30 | SalaryStructure, SalaryComponent, PayrollRun, PaySlip, PaySlipLine, TaxSlab, SocialContribution, BankExport | Sprint 3-4 | **FAIT** |
| IA Phase 1 (Chat) | ~5 | AIConversation, AIAuditLog, AIToolRegistry | Sprint 7-8 | **FAIT** |
| Tracking vehicules (Traccar) | ~25 | Vehicle, VehicleAssignment, VehicleTrip, VehicleAlert, VehicleMaintenance | Sprint 9-10 | **FAIT** |
| RH Avances (self-service, job actions, rapports) | ~17 | - (extensions controllers existants) | Sprint 11-12 | **FAIT** |
| Billing (Stripe/Chargily) + Onboarding + Feature Flags | ~15 | Subscription, Invoice, Payment, OnboardingStep, FeaturePlanMatrix | Sprint 13-14 | **FAIT** (PR #388) |
| Dashboard API + Notifications + Exports | ~9 | - (DashboardController, NotificationController, ExportController) | Sprint 15-16 | **FAIT** (PR #389) |
| IA Avancee (Voice STT/TTS, Agents, Analytics) | ~9 | AgentRunner | Sprint 17-18 | **FAIT** (PR #390) |

**Total actuel : ~280+ endpoints API, 70+ modeles, 263+ tests, 10 workflows CI.**

### Ce qui RESTE a developper

#### Priorite CRITIQUE (impacte directement les ventes)

1. **Paie 4 pays supplementaires** (TN, FR, TR, SN) — regles fiscales, cotisations, seeders
2. **Export SEPA XML + CCP Algerie** — generation fichiers bancaires reels
3. **PDF Bulletin de paie** — generation DomPDF/Snappy avec template par pays
4. **PDF Factures** — generation PDF pour les invoices billing
5. **Scheduled jobs billing** — `billing:check-trials`, `billing:check-overdue`, `billing:generate-invoices`
6. **Tests Feature complets** — chaque nouveau module doit atteindre 80%+ coverage

#### Priorite HAUTE (qualite enterprise-grade)

7. **Workflows d'approbation generiques** — systeme multi-niveaux (ApprovalWorkflow, ApprovalRequest, ApprovalDecision) + trait Approvable
8. **FormRequests + API Resources** — validation formelle et serialisation typee pour TOUS les nouveaux endpoints
9. **Policies RBAC completes** — chaque module a sa Policy Laravel
10. **Health check enrichi** — response JSON avec status/uptime/latency par service
11. **Logging structure JSON** — channel `production` avec Monolog JsonFormatter + RequestId
12. **Metriques plateforme** — `GET /platform/metrics/overview` (MRR, ARR, churn, uptime)

#### Priorite MOYENNE (interfaces + experience)

13. **Dashboard admin (Next.js)** — ecrans paie, conges, contrats, recrutement Kanban, tracking carte live, chat IA, audit, webhooks
14. **Blog vitrine MDX** — systeme articles, templates, SEO, newsletter
15. **Mobile Flutter** — ecrans bulletins, conges, contrat, formations, chat IA, voice IA, position vehicule
16. **Kiosk** — mode kiosk enrichi pour pointage biometrique
17. **Templates email i18n** — facturation, onboarding, notifications (FR/AR/EN)

#### Priorite BASSE (polish + long terme)

18. **IA Phase 2 — Tool calling write** — actions IA avec confirmation (create_employee, check_in, etc.)
19. **IA Phase 5 — Predictive** — prediction turnover, absenteisme, charge de travail
20. **E2E Playwright** — parcours critiques (auth, employee, attendance, payroll, leave, recruitment)
21. **Docker Compose dev** — setup en 1 commande pour contributeurs
22. **DevContainer / Gitpod** — configuration cloud IDE
23. **OpenAPI/Swagger** — documentation automatique de tous les endpoints
24. **GitHub Releases** — tags, changelog formates, release notes
25. **Good first issues** — 10+ issues taguees pour attirer contributeurs open-source

---

## Regles pour les developpeurs

1. **API first** — Chaque module commence par les endpoints API. Les interfaces suivent.
2. **Tests obligatoires** — Chaque endpoint a au minimum un test Feature Pest.
3. **Migrations idempotentes** — `Schema::hasTable()` + `try/catch 42P07` pour PostgreSQL/Render.
4. **i18n des le depart** — Messages de validation via `__()`, pas de texte en dur.
5. **RBAC** — Chaque action passe par une Policy Laravel. Utiliser les gates existantes.
6. **Multi-tenant** — Tout utilise le `company_id` Global Scope. Jamais d'acces cross-tenant.
7. **DDD pour nouveaux modules** — Structure : `Domain/`, `Application/`, `Infrastructure/`, `Interfaces/`.
8. **CHANGELOG.md** — Chaque PR met a jour le changelog.
9. **AGENTS.md** — Chaque lecon operationnelle y est ajoutee.
10. **OpenAPI** — Chaque nouvel endpoint est documente dans `openapi/v1.yaml`.
