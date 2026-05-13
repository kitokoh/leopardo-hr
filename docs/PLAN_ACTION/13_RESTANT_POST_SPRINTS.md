# 13 — CE QUI RESTE A DEVELOPPER (Post sprints 1-18)

**Date :** 2026-05-11
**Contexte :** Les 18 sprints API backend sont termines. Ce document consolide TOUT ce qui reste a developper pour atteindre le niveau enterprise-grade.

---

## Vue d'ensemble

| Categorie | Taches restantes | Effort estime | Priorite |
|-----------|-----------------|---------------|----------|
| Backend — Paie avancee | 8 taches | ~12j | CRITIQUE |
| Backend — Services manquants | 9 taches | ~8j | HAUTE |
| Backend — Tests Feature | 10 taches | ~15j | HAUTE |
| Frontend — Dashboard admin | 12 ecrans | ~25j | MOYENNE |
| Frontend — Blog vitrine | 5 taches | ~5j | MOYENNE |
| Mobile — Flutter | 11 ecrans | ~20j | MOYENNE |
| DevOps — CI/CD + monitoring | 8 taches | ~6j | HAUTE |
| Open Source — Attractivite | 6 taches | ~4j | MOYENNE |
| Contenu — GTM | 10 taches | ~10j | MOYENNE |
| **TOTAL** | **~79 taches** | **~105 jours-dev** | |

---

## 1. BACKEND — Paie avancee (CRITIQUE)

Ces taches impactent directement les ventes. Un client ne peut pas utiliser la paie sans elles.

### 1.1 PayrollCalculator — Service de calcul complet

**Fichier :** `api/app/Services/PayrollCalculator.php` (squelette existant)

```
Taches :
- [x] Implementer le calcul brut -> net pour DZ (CNAS 9%, IRG par tranches avec abattement 40%)
- [x] Implementer le calcul brut -> net pour MA (CNSS 4.48%, AMO 2.26%, IR par tranches)
- [x] Implementer le calcul brut -> net pour TN (CNSS 9.18%, IRPP progressif)
- [x] Implementer le calcul brut -> net pour FR (CSG/CRDS/securite sociale, PAS)
- [x] Implementer le calcul brut -> net pour TR (SGK 14%, gelir vergisi progressif)
- [x] Implementer le calcul brut -> net pour SN (IPRES 5.6%, IR par tranches)
- [x] Creer les seeders pour TN, FR, TR, SN (TaxSlab + SocialContribution)
```

**Specification par pays :** Voir `docs/PLAN_ACTION/03_PAIE_COMPLETE.md` section 4.

### 1.2 PaySlip PDF Generator

**A creer :** `api/app/Services/PaySlipPdfGenerator.php`

```
- [x] Installer DomPDF ou Snappy : `composer require barryvdh/laravel-dompdf`
- [x] Creer le template Blade `resources/views/pdf/payslip.blade.php`
- [x] Template adaptable par pays (logo, format, mentions legales)
- [x] Supporter les mentions obligatoires : brut, cotisations, net imposable, net a payer
- [x] Ajouter endpoint GET /api/v1/pay-slips/{id}/pdf -> download
- [x] Ajouter endpoint POST /api/v1/payroll-runs/{id}/send-slips -> envoi par email
```

### 1.3 Export bancaire reel

**A creer :** `api/app/Services/BankExportGenerator.php`

```
- [x] Export SEPA XML (virement europeen standard) — pour FR et zone euro
- [x] Export CCP Algerie Poste (format texte fixe) — pour DZ
- [x] Export CSV banque standard — pour MA, TN, SN, TR
- [x] Chaque format genere un fichier telechargeable via GET /api/v1/bank-exports/{id}/download
```

### 1.4 Accrual automatique conges

```
- [x] Job schedule `leave:accrue` mensuel — calcule et credite les soldes
- [x] Carry forward annuel — reporte les soldes non utilises selon LeavePolicy
- [x] Expiration carry forward — expire les reports selon carry_forward_expiry_days
```

---

## 2. BACKEND — Services manquants (HAUTE)

### 2.1 Scheduled jobs billing

```
- [x] Artisan command `billing:check-trials` (daily) — notifier les trials expirant dans 3 jours
- [x] Artisan command `billing:check-overdue` (daily) — notifier les factures en retard
- [x] Artisan command `billing:generate-invoices` (monthly) — generer les factures du mois
- [x] Registrer dans `app/Console/Kernel.php` ou `routes/console.php`
```

### 2.2 Invoice PDF

```
- [x] Template Blade `resources/views/pdf/invoice.blade.php`
- [x] Numero auto-incremente LEO-2026-XXXX
- [x] Mentions legales (TVA, SIRET/RC selon pays)
- [x] Endpoint GET /api/v1/billing/invoices/{id}/pdf -> download
```

### 2.3 Templates email i18n

```
- [x] Template welcome/onboarding (FR, AR, EN)
- [x] Template invoice envoyee (FR, AR, EN)
- [x] Template trial expiring (FR, AR, EN)
- [x] Template payment failed (FR, AR, EN)
- [x] Template password reset (deja existant, verifier i18n)
```

### 2.4 FormRequests + API Resources pour tous les nouveaux modules

Chaque controller cree dans les sprints 1-18 utilise `$request->all()` ou `$request->validate()` inline.
Pour la qualite enterprise-grade :

```
Modules concernes :
- [x] BillingController -> BillingRequest + SubscriptionResource
- [x] OnboardingStepController -> OnboardingStepResource
- [x] FeatureFlagController -> FeatureFlagRequest + FeaturePlanMatrixResource
- [x] DashboardController -> DashboardSummaryResource
- [x] ExportController -> (pas de resource, mais FormRequest avec format validation)
- [x] VoiceController -> VoiceTranscribeRequest, VoiceSynthesizeRequest
- [x] AgentController -> AgentRunRequest
- [x] AIAnalyticsController -> (pas de resource, query params validation)
- [x] VehicleController -> VehicleRequest + VehicleResource
- [x] FleetController -> FleetRequest
- [x] PayrollRunController -> PayrollRunRequest + PayrollRunResource
- [x] PaySlipController -> PaySlipResource
- [x] LeavePolicyController -> LeavePolicyRequest + LeavePolicyResource
- [x] ContractController -> ContractRequest + ContractResource
- [x] RecruitmentController -> JobPostingRequest + ApplicantResource
- [x] TrainingController -> TrainingRequest + TrainingResource
- [x] EmployeeLoanController -> LoanRequest + LoanResource
- [x] ExpenseClaimController -> ExpenseClaimRequest + ExpenseClaimResource
```

### 2.5 Policies RBAC completes

```
- [x] BillingPolicy (admin + super_admin seulement)
- [x] OnboardingPolicy (admin du tenant)
- [x] FeatureFlagPolicy (super_admin seulement pour write, tous pour read)
- [x] ExportPolicy (admin + manager)
- [x] VehiclePolicy (admin + fleet_manager)
- [x] PayrollPolicy (admin + accountant)
- [x] RecruitmentPolicy (admin + recruiter)
- [x] TrainingPolicy (admin + hr_manager)
```

---

## 3. BACKEND — Tests Feature (HAUTE)

Chaque module a besoin de tests Pest Feature couvrant les cas positifs et RBAC.

```
Fichiers a creer dans tests/Feature/ :

- [x] BillingControllerTest.php (~10 tests : subscription CRUD, upgrade, cancel, renew, invoices, PDF, isolation tenant)
- [x] OnboardingStepControllerTest.php (~5 tests : checklist, progress, complete, skip, auto-seed, isolation tenant)
- [x] FeatureFlagControllerTest.php (~5 tests : matrix, check, fallback trial, feature inconnue, updateMatrix RBAC)
- [x] PaymentWebhookControllerTest.php (~7 tests : stripe valid/invalid, chargily valid/invalid, events inconnus sans effet)
- [ ] DashboardControllerTest.php (~5 tests : summary, recentActivity, kpi, RBAC)
- [ ] NotificationControllerTest.php (~6 tests : index, unread, markRead, markAllRead, pagination)
- [ ] ExportControllerTest.php (~4 tests : employees JSON, employees CSV, attendance JSON, attendance CSV)
- [x] VoiceControllerTest.php (~4 tests : transcribe, synthesize, command, RBAC)
- [x] AgentControllerTest.php (~4 tests : run, workflows, max steps, RBAC)
- [x] AIAnalyticsControllerTest.php (~4 tests : usage, costs, tools, errors)
- [ ] VehicleControllerTest.php (~8 tests : CRUD, assign, unassign, RBAC)
- [x] FleetControllerTest.php (~4 tests : overview, tracking, alerts, RBAC)
- [ ] PayrollRunControllerTest.php (~8 tests : create, calculate, validate, cancel, summary, RBAC)
- [x] PaySlipControllerTest.php (~4 tests : list, detail, PDF, self-service)
- [ ] LeavePolicyControllerTest.php (~6 tests : CRUD, accrual, balance, RBAC)
- [ ] ContractControllerTest.php (~6 tests : CRUD, amendment, expiring, RBAC)
- [ ] RecruitmentControllerTest.php (~8 tests : job CRUD, apply, pipeline, interview, RBAC)
- [ ] TrainingControllerTest.php (~6 tests : course CRUD, session, enrollment, RBAC)
- [ ] EmployeeLoanControllerTest.php (~6 tests : CRUD, repayment schedule, RBAC)
- [ ] ExpenseClaimControllerTest.php (~6 tests : CRUD, approve, reject, RBAC)

Total estime : ~110 nouveaux tests -> coverage backend > 70%
```

---

## 4. FRONTEND — Dashboard admin Next.js (MOYENNE)

Le backend API est pret. Les interfaces web doivent consommer ces APIs.

### Ecrans a creer

| # | Ecran | API Backend | Effort |
|---|-------|------------|--------|
| 1 | Dashboard principal (KPIs, graphiques) | GET /dashboard/summary, /dashboard/kpi | 3j |
| 2 | Gestion paie (runs, bulletins) | GET/POST /payroll-runs, /pay-slips | 3j |
| 3 | Gestion conges (demandes, soldes) | GET/POST /leave-policies, /absences | 2j |
| 4 | Gestion contrats (liste, alertes) | GET/POST /contracts | 2j |
| 5 | Recrutement Kanban (pipeline) | GET /job-postings, /applicants | 3j |
| 6 | Formation catalogue | GET /training/courses, /sessions | 2j |
| 7 | Tracking vehicules (carte live) | GET /vehicles, /fleet/tracking | 3j |
| 8 | Billing & abonnements | GET /billing/subscription, /invoices | 2j |
| 9 | Chat IA (widget) | POST /ai/chat | 2j |
| 10 | Audit trail (logs) | GET /audit-logs | 1j |
| 11 | Webhooks (configuration) | GET/POST /webhooks | 1j |
| 12 | Exports & rapports | GET /export/employees, /hr-reports | 1j |

### Composants partages a creer

```
- DataTable (tri, filtre, pagination, export CSV)
- MetricCard (KPI avec tendance)
- ChartWidget (Line, Bar, Pie via Chart.js ou Recharts)
- MapWidget (Leaflet pour tracking vehicules)
- KanbanBoard (drag & drop pour recrutement)
- ChatWidget (streaming, markdown, historique)
- PDFViewer (preview bulletins, contrats, factures)
- FileUpload (drag & drop, progress, validation)
- ApprovalWidget (approve/reject avec commentaire)
- NotificationCenter (dropdown avec badge unread)
```

---

## 5. FRONTEND — Blog vitrine MDX (MOYENNE)

```
- [ ] Setup MDX (Next.js + contentlayer ou next-mdx-remote)
- [ ] Template article : titre, date, auteur, tags, reading time
- [ ] Page /blog avec liste paginee et filtre par tag
- [ ] Page /blog/{slug} avec article complet + TOC + partage social
- [ ] Page /pricing avec tableau comparatif plans
- [ ] Page /demo avec formulaire de demande
- [ ] SEO : sitemap.xml dynamique, robots.txt, meta OG, schema.org JSON-LD
- [ ] Newsletter signup (Mailchimp ou Brevo integration)
```

---

## 6. MOBILE — Flutter (MOYENNE)

### Ecrans a creer

| # | Ecran | API Backend | Effort |
|---|-------|------------|--------|
| 1 | Mes bulletins de paie | GET /me/pay-slips | 2j |
| 2 | Mes conges (demande + solde) | GET/POST /absences, /leave-balances | 2j |
| 3 | Mon contrat | GET /me/contracts | 1j |
| 4 | Mes formations | GET /me/training-enrollments | 1j |
| 5 | Mes notes de frais | GET/POST /expense-claims | 2j |
| 6 | Chat IA | POST /ai/chat (streaming) | 2j |
| 7 | Voice IA (microphone) | POST /ai/voice/transcribe, /synthesize | 3j |
| 8 | Position vehicule (carte) | GET /vehicles/{id}/position | 2j |
| 9 | Approbations en attente | GET /approvals/pending, POST approve/reject | 2j |
| 10 | Notifications push | GET /notifications + Firebase Cloud Messaging | 2j |
| 11 | Onboarding wizard | GET /onboarding-setup/checklist | 1j |

---

## 7. DEVOPS — CI/CD + Monitoring (HAUTE)

### Workflows CI a ajouter

```
- [ ] Workflow Playwright E2E (tests/e2e/*.spec.ts) — execute apres deploy staging
- [ ] Workflow coverage gate — fail si coverage < seuil (commencer a 40%, monter 5%/mois)
- [ ] Workflow deploy staging automatique sur merge main
- [ ] Workflow mobile (flutter test + build APK)
```

### Monitoring production

```
- [x] Health check enrichi : GET /api/v1/health -> JSON {status, db, redis, queue, disk, uptime}
- [x] Logging JSON structure : channel production avec Monolog JsonFormatter
- [ ] Sentry APM : performance traces sur endpoints critiques (paie, IA)
- [ ] Alerting Slack/Discord : erreurs 5xx, queue backed up, disk > 80%
```

---

## 8. OPEN SOURCE — Attractivite (MOYENNE)

```
- [ ] Docker Compose pour dev (api + postgres + redis en 1 commande)
- [ ] DevContainer config (.devcontainer/devcontainer.json)
- [ ] DEVELOPMENT.md clair pour les nouveaux contributeurs
- [ ] Creer 10+ good first issues taguees sur GitHub
- [ ] GitHub Releases avec tags et changelog formate
- [ ] Project board visible (GitHub Projects) avec les taches restantes
```

---

## 9. GTM — Marketing execution (MOYENNE)

Voir `11_GTM_EXECUTION.md` pour le detail complet. Resume des actions prioritaires :

```
- [ ] 5 mini cas clients (1 page chacun avec metriques et citation)
- [ ] 3 videos demo produit (pointage, rapport, dashboard) en FR et AR
- [ ] Landing page live avec temoignages + videos + pricing
- [ ] Page /demo avec formulaire de demande
- [ ] 5 articles blog SEO (pointage biometrique, paie DZ, conges legaux, etc.)
- [ ] Prospection LinkedIn 20 contacts/semaine DRH PME Maghreb
- [ ] Partenariat ZKTeco / distributeur local
- [ ] Templates WhatsApp de relance
- [ ] Elargissement Afrique francophone : SN, CI, CM
- [ ] Participation salon GITEX Africa ou Morocco Digital Awards
```

---

## 10. Ordre d'execution recommande

### Phase A — Critique (prochains 30 jours)

| # | Tache | Dependance | Effort |
|---|-------|-----------|--------|
| A1 | PayrollCalculator DZ + MA (calcul reel) | Aucune | 3j |
| A2 | PaySlip PDF | A1 | 2j |
| A3 | Tests Feature modules critiques (paie, billing, vehicle) | Aucune | 5j |
| A4 | FormRequests + Resources (top 5 modules) | Aucune | 3j |
| A5 | Scheduled jobs billing | Aucune | 1j |
| A6 | Invoice PDF | Aucune | 1j |
| A7 | Docker Compose dev | Aucune | 1j |
| A8 | 5 cas clients + 3 videos demo | Aucune (marketing) | 5j |

### Phase B — Haute (jours 30-60)

| # | Tache | Dependance | Effort |
|---|-------|-----------|--------|
| B1 | PayrollCalculator TN + FR | A1 | 3j |
| B2 | Export SEPA + CCP | A1 | 2j |
| B3 | Dashboard admin (top 5 ecrans) | APIs pretes | 10j |
| B4 | Policies RBAC completes | Aucune | 2j |
| B5 | Tests Feature restants | Aucune | 5j |
| B6 | Monitoring production (health, logging, Sentry) | Aucune | 3j |

### Phase C — Moyenne (jours 60-90)

| # | Tache | Dependance | Effort |
|---|-------|-----------|--------|
| C1 | PayrollCalculator TR + SN | B1 | 2j |
| C2 | Dashboard admin (ecrans restants) | B3 | 10j |
| C3 | Blog MDX vitrine | Aucune | 5j |
| C4 | Mobile Flutter (top 5 ecrans) | APIs pretes | 10j |
| C5 | E2E Playwright | B3 | 3j |
| C6 | Good first issues + Releases | Aucune | 2j |
| C7 | Landing page + pricing + SEO | C3 | 3j |

### Phase D — Long terme (90+ jours)

| # | Tache | Dependance |
|---|-------|-----------|
| D1 | Mobile Flutter ecrans restants | C4 |
| D2 | IA Phase 2 (tool calling write) | Sprint 7-8 |
| D3 | IA Phase 5 (predictive) | D2 |
| D4 | Prospection Afrique francophone | GTM |
| D5 | Polish UI/UX | C2, C4 |

---

## 11. Definition of Done global

Leopardo RH est considere **"v1.0 production-ready"** quand :

- [ ] Paie legale fonctionnelle pour au moins 2 pays (DZ + MA) avec bulletins PDF
- [ ] 80%+ tests coverage backend
- [ ] Dashboard admin avec les 5 ecrans principaux
- [ ] Mobile avec pointage + conges + bulletins
- [ ] Blog vitrine avec au moins 5 articles
- [ ] Docker Compose fonctionnel pour contributeurs
- [ ] 25+ clients pilotes actifs
- [ ] CI/CD vert sur toutes les branches
- [ ] Monitoring production operationnel
- [ ] Documentation OpenAPI complete
