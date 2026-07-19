# 12 — PRIORITES & ROADMAP D'EXECUTION

**Derniere mise a jour :** 2026-05-11 (post sprints 1-18)

**Objectif :** Definir l'ordre d'execution optimal, les dependances entre modules, et une timeline realiste.

---

## 1. Principes de priorisation

1. **Ce qui fait vendre d'abord** — Paie et conges sont les plus demandes
2. **API d'abord, interfaces ensuite** — Chaque module commence par le backend
3. **Tests avec chaque livraison** — Pas de code sans test
4. **Pas de big bang** — Livraison incrementale, 1 module a la fois
5. **La base avant les extras** — Architecture solide avant IA et tracking

---

## 2. Roadmap par sprint (2 semaines/sprint)

### Sprint 1-2 : Fondations (Semaines 1-4) ✅ TERMINE

**Statut :** Merge dans `main`. 8 domain events, AuditLog system, Webhook dispatcher, middleware RequestId, indexes DB.

**Ce qui reste de ce sprint :**
- Docker Compose pour dev (T-OSS-01) — NON FAIT
- DEVELOPMENT.md contributeurs (T-OSS-03) — NON FAIT
- Configurer Sentry performance (T-MON-06) — NON FAIT

### Sprint 3-4 : Paie complete (Semaines 5-8) ✅ TERMINE

**Statut :** Merge dans `main`. SalaryStructure, SalaryComponent, PayrollRun, PaySlip, PaySlipLine, TaxSlab, SocialContribution, BankExport + endpoints complets.

**Ce qui reste de ce sprint :**
- PaySlip PDF generator (T-PAIE-07) — NON FAIT (template DomPDF/Snappy a implementer)
- PayrollCalculator service (T-PAIE-04) — squelette cree, logique calcul a completer par pays
- FormRequests validation formelle (T-PAIE-11) — NON FAIT
- API Resources serialisation (T-PAIE-11) — NON FAIT
- Tests Feature paie (T-PAIE-13/14) — NON FAIT

### Sprint 5-6 : Conges avances + Contrats (Semaines 9-12) ✅ TERMINE

**Statut :** Merge dans `main`. LeavePolicy, LeaveBalance, LeaveAccrual, Contract, ContractAmendment, ApprovalWorkflow + endpoints.

**Ce qui reste de ce sprint :**
- Accrual automatique (job schedule mensuel) — NON FAIT
- Carry forward logic — NON FAIT
- PDF contrat generation — NON FAIT
- Tests Feature conges + contrats — NON FAIT

### Sprint 7-8 : IA Phase 1 (Semaines 13-16) ✅ TERMINE

**Statut :** PR #385 merge dans `main`. AIConversation, AIAuditLog, AIToolRegistry, LLMClient (OpenAI+Claude), ToolRegistry, IntentEngine, MemoryManager, AIOrchestrator, AIAuditLogger, 3 middlewares, 15 tools seeder.

**Ce qui reste de ce sprint :**
- Blog MDX setup vitrine (T-VITRINE-01/02) — NON FAIT (frontend)
- Pages pricing + demo (T-VITRINE-03/04) — NON FAIT (frontend)
- SEO (T-VITRINE-05) — NON FAIT (frontend)
- 5 premiers articles blog (T-GTM-06) — NON FAIT (contenu)
- Tests Feature IA (T-IA-13/14) — NON FAIT

### Sprint 9-10 : Tracking vehicules (Semaines 17-20) ✅ TERMINE

**Statut :** PR #386 merge dans `main`. 5 tables tracking, 5 modeles, TraccarService complet, 6 controllers, ~25 endpoints.

**Ce qui reste de ce sprint :**
- Tests Feature tracking (T-TRACK-10/11/12) — NON FAIT

### Sprint 11-12 : RH Avances (Semaines 21-24) ✅ TERMINE

**Statut :** PR #387 merge dans `main`. SelfServiceController, JobPostingActionController, AdvancedReportController, ~17 endpoints.

**Ce qui reste de ce sprint :**
- Tests Feature recrutement/formation/prets — NON FAIT

### Sprint 13-14 : Billing, Onboarding, Feature Flags (Semaines 25-28) ✅ TERMINE

**Statut :** PR #388 en attente CI. 5 tables, 5 modeles, 4 controllers, FeatureService, FeaturePlanMatrixSeeder (17 features x 4 plans), ~15 endpoints.

**Ce qui reste de ce sprint :**
- Scheduled jobs billing (billing:check-trials, billing:check-overdue, billing:generate-invoices) — NON FAIT
- Invoice PDF generation (T-OBD-11) — NON FAIT
- Templates email facturation i18n (T-OBD-10) — NON FAIT
- Tests Feature billing/webhooks (T-OBD-12/13) — NON FAIT

### Sprint 15-16 : Dashboard API + Notifications + Exports (Semaines 29-32) ✅ TERMINE (API)

**Statut :** PR #389 CI passe. DashboardController, NotificationController, ExportController, ~9 endpoints.

**Ce qui reste de ce sprint — FRONTEND (non implemente, backend API uniquement) :**
- Dashboard admin Next.js : ecrans paie, conges, contrats, recrutement Kanban, tracking carte live (T-WEB-03 a T-WEB-11)
- Widget chat IA (T-WEB-09)
- Blog MDX vitrine (T-VITRINE-01 a T-VITRINE-08)
- Mobile Flutter : bulletins, conges, chat IA, voice (T-MOB-01 a T-MOB-06)
- Composants partages : DataTable, MetricCard, ChartWidget, MapWidget, KanbanBoard, ChatWidget

### Sprint 17-18 : IA Avancee (Semaines 33-36) ✅ TERMINE (API)

**Statut :** PR #390 CI en cours. VoiceController (STT Whisper/Deepgram + TTS Edge/ElevenLabs), AgentRunner, AgentController, AIAnalyticsController, ~9 endpoints.

**Ce qui reste de ce sprint :**
- Export SEPA XML (T-PAIE-08) — NON FAIT
- Export CCP Algerie Poste (T-PAIE-09) — NON FAIT
- Paie TN, FR, TR, SN (T-PAIE-05 additionnel) — NON FAIT (regles fiscales + cotisations + seeders)
- E2E Playwright (T-CI-02/11) — NON FAIT
- Polish UI/UX — NON FAIT (frontend)

---

## 3. Diagramme de dependances

```
                    ┌─────────────────┐
                    │  01 Fondations  │
                    │  (Sprint 1-2)   │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
    ┌─────────▼──────┐  ┌───▼──────────┐  ┌▼──────────────┐
    │ 03 Paie        │  │ 02 Conges    │  │ 10 Open Source │
    │ (Sprint 3-4)   │  │ (Sprint 5-6) │  │ (Continu)      │
    └────────┬───────┘  └──────┬───────┘  └───────────────┘
             │                 │
    ┌────────▼─────────────────▼────────┐
    │ 04 IA Phase 1 + 06 Blog          │
    │ (Sprint 7-8)                      │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ 05 Tracking + Notes de frais      │
    │ (Sprint 9-10)                     │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ Recrutement + Formation + Prets   │
    │ (Sprint 11-12)                    │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ Webhooks + Billing + Audit        │
    │ (Sprint 13-14)                    │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ Interfaces completes              │
    │ (Sprint 15-16)                    │
    └────────┬──────────────────────────┘
             │
    ┌────────▼──────────────────────────┐
    │ IA avancee + Polish + Deploy      │
    │ (Sprint 17-18)                    │
    └───────────────────────────────────┘
```

---

## 4. Criteres de validation par module

Chaque module est considere "done" quand :

- [ ] Tous les endpoints API sont implementes et documentes dans OpenAPI
- [ ] Tous les tests Feature passent (coverage > 80% du module)
- [ ] Les migrations sont idempotentes (PostgreSQL/Render safe)
- [ ] Les Policies RBAC sont en place
- [ ] Les messages de validation sont i18n (FR, AR, EN minimum)
- [ ] Le CHANGELOG.md est mis a jour
- [ ] L'AGENTS.md est mis a jour si une lecon operationnelle est apprise
- [ ] Le module est derriere un feature flag
- [ ] Un test E2E couvre le parcours principal (quand l'interface existe)

---

## 5. Metriques de progression

| Metrique | Pre-sprints | Actuel (post sprint 18) | Cible finale | Ecart |
|----------|-------------|------------------------|--------------|-------|
| Endpoints API | ~135 | ~280+ | ~350 | ~70 restants (frontend support, SEPA, paie pays) |
| Modeles | 30 | 70+ | ~70 | **ATTEINT** |
| Tests | 75 (263 passed) | 263+ | ~250 | **ATTEINT** (quantite, coverage a ameliorer) |
| Coverage backend | ~40% | ~40% | >80% | A augmenter (tests Feature par module) |
| Modules | 8 | 18+ | 18 | **ATTEINT** |
| Pays paie supportes | 0 (estimation) | 2 (DZ, MA) | 6 | 4 restants (TN, FR, TR, SN) |
| Langues | 4 (FR, AR, TR, EN) | 4 | 4 | **ATTEINT** |
| Workflows CI | 10 | 10 | 14 | 4 restants (Playwright, coverage gate, etc.)

---

## 6. Risques et mitigations

| Risque | Impact | Mitigation |
|--------|--------|------------|
| Dev solo overload | Critique | Open source + agents IA + prioriser ruthlessly |
| Conformite paie incorrecte | Haut | Validation par comptable local avant deploiement |
| Traccar instable | Moyen | Fallback sans tracking, module optionnel |
| LLM couts explosent | Moyen | Quotas par plan + cache responses + model switching |
| Pas assez de contributeurs | Haut | Good first issues + Docker setup + documentation |
| Client perd des donnees | Critique | Backup automatique + tests de restore |
| Concurrent local copie | Moyen | Avancer vite, construire la communaute |

---

## 7. Definition de la victoire technique

Leopardo RH est "enterprise-grade" quand :

1. Un client peut faire sa paie legale complete sans Excel
2. Un manager terrain peut piloter ses equipes depuis son telephone
3. Un comptable peut exporter les donnees vers sa comptabilite
4. Un RH peut gerer les conges, contrats et recrutement sans papier
5. Un dirigeant peut voir les KPIs en temps reel
6. Un developpeur peut contribuer en moins de 30 minutes (Docker + docs)
7. La plateforme tient 100 clients / 10 000 employes sans degradation
8. Les tests couvrent > 80% du code
9. Le deploy est automatique et rollback-safe
10. L'IA repond aux questions RH courantes en 4 langues
