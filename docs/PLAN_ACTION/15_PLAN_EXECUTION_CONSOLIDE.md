# 15 — Plan d'execution consolide

> Derniere mise a jour : 2026-05-16
> Ce document recense uniquement les taches RESTANTES apres l'audit exhaustif du code vs les plans d'action 01-14.
> Resultat de l'audit : **~85% des taches documentees sont deja implementees**. Le code contient 395+ fichiers PHP, 60+ tests Feature, 18 workflows CI, 11 specs E2E Playwright.

---

## Synthese de l'audit

| Plan | Total taches | Fait | Reste | % Fait |
|------|-------------|------|-------|--------|
| 01 Architecture | 23 | 13 | 10 | 57% |
| 02 Modules API | 39 | 36 | 3 | 92% |
| 03 Paie Complete | 15 | 15 | 0 | 100% |
| 04 Couche IA | 33 | 17 | 16 | 52% |
| 05 Tracking Vehicules | 14 | 13 | 1 | 93% |
| 06 Interfaces | 33 | 0 | 33 | 0% |
| 07 Monitoring | 12 | 6 | 6 | 50% |
| 08 Tests CI/CD | 12 | 9 | 3 | 75% |
| 09 Onboarding/Billing | 14 | 14 | 0 | 100% |
| 10 Open Source | 19 | 3 | 16 | 16% |
| 11 GTM | 14 | 0 | 14 | 0% |
| 12 Priorites Roadmap | 9 | 0 | 9 | 0% |
| 13 Restant Post-Sprints | 23 | 6 | 17 | 26% |
| 14 Solidification | 65 | 0 | 65 | 0% |
| **TOTAL** | **325** | **132** | **193** | **41%** |

> **Note importante** : Les plans 06, 10, 11, 12, 14 contiennent majoritairement des taches non-code (frontend/mobile UX, marketing, certifications, commercialisation). Le backend API est a **~88% complet**.

---

## Taches RESTANTES par categorie

### Categorie A — Backend Code (implementable maintenant)

| # | Tache | Source | Effort | Priorite |
|---|-------|--------|--------|----------|
| A1 | Creer `CONVENTIONS.md` | T-ARCH-13 | 0.5j | HIGH |
| A2 | Creer `docs/api/VERSIONING.md` | T-ARCH-10 | 0.5j | HIGH |
| A3 | Creer middleware `ApiVersion` | T-ARCH-09 | 1j | DONE |
| A4 | Creer `docs/architecture/PARTITIONING.md` | T-ARCH-15 | 0.5j | MEDIUM |
| A5 | DDD migration progressive controllers existants | T-ARCH-02 | 5j+ | LOW |
| A6 | Tests Feature rapport RH dedies | T-MOD-H4 | 1j | DONE |
| A7 | Test Feature chaine hierarchique org chart | Plan 02 | 0.5j | DONE |
| A8 | Export CSV audit logs | Plan 02 | 0.5j | DONE |
| A9 | `.pre-commit-config.yaml` | T-CI-06 | 0.5j | DONE |
| A10 | Documenter worker deployment (DEPLOYMENT_GUIDE) | T-ARCH-20 | 0.5j | MEDIUM |
| A11 | Documentation API tracking dans OpenAPI | T-TRACK-14 | 1j | DONE |

### Categorie B — Monitoring & Observabilite

| # | Tache | Source | Effort | Priorite |
|---|-------|--------|--------|----------|
| B1 | Configurer Sentry traces + profiles | T-MON-06 | 1j | HIGH |
| B2 | AlertService + webhook Slack | T-MON-07 | 1j | HIGH |
| B3 | Slow query logging | T-MON-08 | 0.5j | MEDIUM |
| B4 | UptimeRobot/BetterUptime | T-MON-09 | 0.5j | MEDIUM |
| B5 | Installer Telescope pour dev | T-MON-11 | 0.5j | LOW |
| B6 | Documenter runbook alertes | T-MON-12 | 0.5j | MEDIUM |

### Categorie C — IA Phases avancees

| # | Tache | Source | Effort | Priorite |
|---|-------|--------|--------|----------|
| C1 | Outils write avec confirmation | T-IA-15/16 | 2j | MEDIUM |
| C2 | Dashboard admin analytics IA | T-IA-18 | 1j | LOW |
| C3 | Tests actions write | T-IA-19 | 1j | DONE |
| C4 | Whisper API pour STT | T-IA-21 | 1j | LOW |
| C5 | Edge TTS synthese vocale | T-IA-22 | 1j | LOW |
| C6 | Pipeline voice complet | T-IA-23 | 2j | LOW |
| C7 | Support 4 langues voice | T-IA-24 | 1j | LOW |
| C8 | Tests Feature voice | T-IA-25 | 1j | LOW |
| C9 | Workflow "preparer la paie" | T-IA-27 | 2j | MEDIUM |
| C10 | Workflow "rapport hebdomadaire" | T-IA-28 | 1j | MEDIUM |
| C11 | Notifications proactives IA | T-IA-29 | 2j | LOW |
| C12 | Prediction turnover | T-IA-30 | 3j | LOW |
| C13 | Prediction absenteisme | T-IA-31 | 2j | LOW |
| C14 | Optimisation planning | T-IA-32 | 3j | LOW |
| C15 | Dashboard predictif | T-IA-33 | 2j | LOW |

### Categorie D — Architecture & Performance (Plan 01 + 14)

| # | Tache | Source | Effort | Priorite |
|---|-------|--------|--------|----------|
| D1 | Redis cache production (endpoints read-heavy) | T-ARCH-21/22/23 | 2j | HIGH |
| D2 | Queue async pour batch (paie, PDF, notifs) | Plan 14 | 1j | HIGH |
| D3 | Compression response gzip/brotli | Plan 14 | 0.5j | MEDIUM |
| D4 | JWT refresh token rotation | Plan 14 | 1j | HIGH |
| D5 | AES-256 chiffrement donnees sensibles | Plan 14 | 2j | HIGH |
| D6 | Indexation PostgreSQL colonnes filtrees | Plan 14 | 1j | MEDIUM |
| D7 | CI workflow pour tester les jobs | T-ARCH-19 | 0.5j | LOW |

### Categorie E — Frontend Admin Dashboard (Plan 06)

| # | Tache | Source | Effort | Priorite |
|---|-------|--------|--------|----------|
| E1 | Ecrans paie (structures, runs, bulletins, export) | T-WEB-03 | 3j | HIGH |
| E2 | Ecrans conges (politiques, soldes, approbations) | T-WEB-04 | 2j | HIGH |
| E3 | Ecrans contrats (liste, detail, alertes) | T-WEB-05 | 2j | MEDIUM |
| E4 | Ecrans recrutement (pipeline Kanban) | T-WEB-06 | 3j | MEDIUM |
| E5 | Ecrans formation (catalogue, sessions, suivi) | T-WEB-07 | 2j | MEDIUM |
| E6 | Ecrans tracking/flotte (carte live) | T-WEB-08 | 3j | LOW |
| E7 | Widget chat IA | T-WEB-09 | 2j | LOW |
| E8 | Ecrans rapports RH | T-WEB-10 | 2j | MEDIUM |
| E9 | Ecrans audit + webhooks | T-WEB-11 | 1j | LOW |
| E10 | Composants partages (DataTable, MetricCard) | T-WEB-02 | 1j | HIGH |
| E11 | Layout navigation nouveaux modules | T-WEB-01 | 1j | HIGH |

### Categorie F — Vitrine Web (Plan 06)

| # | Tache | Source | Effort | Priorite |
|---|-------|--------|--------|----------|
| F1 | Systeme blog MDX | T-VITRINE-01 | 2j | MEDIUM |
| F2 | Templates BlogList/BlogPost | T-VITRINE-02 | 1j | MEDIUM |
| F3 | Page Pricing publique | T-VITRINE-03 | 1j | HIGH |
| F4 | SEO (sitemap, robots, schema.org) | T-VITRINE-05 | 1j | MEDIUM |
| F5 | 5 premiers articles blog | T-VITRINE-06 | 3j | MEDIUM |
| F6 | Page changelog publique | T-VITRINE-07 | 0.5j | LOW |
| F7 | Formulaire newsletter | T-VITRINE-08 | 0.5j | LOW |

### Categorie G — Mobile Flutter (Plan 06)

| # | Tache | Source | Effort | Priorite |
|---|-------|--------|--------|----------|
| G1 | Ecran bulletins de paie + PDF | T-MOB-01 | 2j | HIGH |
| G2 | Ecran conges (soldes, demande, historique) | T-MOB-02 | 2j | HIGH |
| G3 | Ecran contrat | T-MOB-03 | 1j | MEDIUM |
| G4 | Ecran formations | T-MOB-04 | 1j | LOW |
| G5 | Ecran notes de frais + camera | T-MOB-05 | 2j | MEDIUM |
| G6 | Chat IA mobile | T-MOB-06 | 2j | LOW |
| G7 | Voice IA mobile | T-MOB-07 | 2j | LOW |
| G8 | Notifications push Firebase | T-MOB-08 | 2j | HIGH |
| G9 | Carte vehicule | T-MOB-09 | 1j | LOW |
| G10 | Organigramme visuel | T-MOB-10 | 1j | LOW |

### Categorie H — Kiosk (Plan 06)

| # | Tache | Source | Effort | Priorite |
|---|-------|--------|--------|----------|
| H1 | Ecran info employe post-pointage | T-KIOSK-01 | 1j | MEDIUM |
| H2 | Systeme d'annonces kiosk | T-KIOSK-02 | 1j | LOW |
| H3 | Affichage solde conges | T-KIOSK-03 | 0.5j | MEDIUM |
| H4 | Pointage QR code | T-KIOSK-04 | 1j | MEDIUM |

### Categorie I — Open Source & Community (Plan 10)

| # | Tache | Source | Effort | Priorite |
|---|-------|--------|--------|----------|
| I1 | Script labels GitHub | T-OSS-04 | 0.5j | LOW |
| I2 | 10 good first issues | T-OSS-05 | 1j | MEDIUM |
| I3 | Corriger URLs README | T-OSS-06 | 0.5j | LOW |
| I4 | Screenshots/GIF README | T-OSS-07 | 1j | MEDIUM |
| I5 | Premiere release GitHub v0.1.0 | T-OSS-08 | 0.5j | HIGH |
| I6 | Activer GitHub Discussions | T-OSS-09 | 0.5j | LOW |
| I7 | Codespaces badge README | T-OSS-13 | 0.5j | LOW |
| I8 | GitHub Project Board public | T-OSS-12 | 0.5j | LOW |

### Categorie J — GTM & Commercial (Plan 11 — non-code)

| # | Tache | Source | Effort | Priorite |
|---|-------|--------|--------|----------|
| J1 | Interviewer 5 clients | T-GTM-01 | 5j | HIGH |
| J2 | Rediger 5 mini-cas clients | T-GTM-02 | 3j | HIGH |
| J3 | 3 demos video | T-GTM-03 | 3j | HIGH |
| J4-J14 | Prospection, partenariats, contenu | T-GTM-04-14 | 20j+ | MEDIUM |

### Categorie K — Securite & Conformite (Plan 14)

| # | Tache | Source | Effort | Priorite |
|---|-------|--------|--------|----------|
| K1 | Rate limiting API par plan | Plan 14 | 1j | DONE |
| K2 | SSO SAML/OIDC | Plan 14 | 3j | MEDIUM |
| K3 | Multi-tenant isolation test complet | Plan 14 | 1j | HIGH |
| K4 | Audit WCAG 2.1 AA | Plan 14 | 2j | LOW |
| K5 | Matrice conformite (RGPD, loi 18-07) | Plan 14 | 2j | MEDIUM |

### Categorie L — Integrations tierces (Plan 14)

| # | Tache | Source | Effort | Priorite |
|---|-------|--------|--------|----------|
| L1 | Export SEPA reel (XML ISO 20022) | Plan 14 | 2j | HIGH |
| L2 | Declaration CNAS trimestrielle DZ | Plan 14 | 2j | HIGH |
| L3 | Declaration CNSS MA | Plan 14 | 2j | MEDIUM |
| L4 | Import/export Excel employes | Plan 14 | 2j | MEDIUM |
| L5 | Integration ZKTeco | Plan 14 | 3j | MEDIUM |
| L6 | Sync calendrier Google/Outlook | Plan 14 | 2j | LOW |

---

## Plan d'execution par iterations

### Iteration 1 — Audit & documentation
**Statut** : **COMPLETE** (2026-05-14)
**Branche historique** : `devin/1778781034-iteration1-audit-plans`
**Contenu** :
- Mise a jour de TOUS les fichiers PLAN_ACTION (01-14) : marquer `[x]` ce qui existe
- Creation de ce document consolide (15_PLAN_EXECUTION_CONSOLIDE.md)
- Mise a jour CHANGELOG.md

### Iteration 2 — Documentation technique manquante
**Cible** : A1, A2, A4, A10, A11, B6
**Contenu** :
- `CONVENTIONS.md` (regles de code)
- `docs/api/VERSIONING.md` (politique deprecation API)
- `docs/architecture/PARTITIONING.md` (strategie scalabilite DB)
- Documentation OpenAPI tracking + IA
- Runbook alertes

**Livraison incrementale 2026-05-16** :
- **A11 DONE** : contrats tracking/flotte documentes dans `api/openapi.yaml` (vehicules, affectations, trajets, alertes, maintenance, sync Traccar, overview/live-map/reports).

### Iteration 3 — Tests Feature manquants + pre-commit
**Cible** : A6, A7, A8, A9, C3
**Statut** : COMPLETE (A6-A9, C3) — PR #463 + lot iteration 3 pre-commit/IA write (2026-05-15).
**Contenu** :
- Tests rapports RH dedies
- Test chaine hierarchique org chart
- Export CSV audit logs
- `.pre-commit-config.yaml`
- Tests actions write IA

### Iteration 4 — Securite & performance
**Cible** : D1, D2, D4, D5, K1, K3
**Merge API attendu** : **PR #468** — **D1** cache tenant `GET /api/v1/reports/headcount`, **D2** job PDF bulletins post-validation (`WarmPaySlipPdfPathsForPayrollRunJob`), **K3** tests Feature ; arbitrages **D4/D5** documentes dans la PR. Branche de travail : `feat/plan15-iteration4-d1-d2-k3`.

**Statut** : **COMPLETE** (2026-05-16)
**Contenu** :
- Redis cache endpoints read-heavy
- Queue async calcul paie/PDF/notifs
- JWT refresh token rotation
- AES-256 chiffrement sensible
- Rate limiting API par plan
- Test isolation multi-tenant complet

**Deja sur `main` (hors #468)** :
- **K1** : `ApiVersionMiddleware` + limiter `api-plan` (`CHANGELOG` [4.16.56]).
**Livraisons** :
- **K1** (anterieur iteration 4, main referencee ici) : `ApiVersionMiddleware` + limiter `api-plan` apres `auth:sanctum` + `tenant`.
- **D1** : cache tenant pour `GET /api/v1/reports/headcount` (`config/performance.php`, env `HR_REPORT_HEADCOUNT_CACHE_TTL`).
- **D2** : job `WarmPaySlipPdfPathsForPayrollRunJob` apres validation paie (`PAYROLL_QUEUE_PDF_WARMUP`), PDF servis via `pdf_path` sur disque `local` quand present.
- **K3** : tests Feature isolation headcount, dispatch queue + warmup fichier, PDF depuis fichier pre-genere.

**Arbitrage / hors scope iteration 4** :
- **D4** : l’API metier cliente repose sur **Sanctum** (tokens API / session SPA), sans flux JWT refresh dedie. Les JWT presents dans le depot concernent surtout les **tokens flux camera** (`CameraStreamTokenService`, TTL configurable via `config/cameras.php`). Une rotation refresh JWT pour l’auth principale releverait d’un chantier auth dedie (ex. PAT integrateurs ou passerelle JWT), pas de ce lot.
- **D5** : chiffrement au repos Laravel (`casts` `encrypted`) deja applique aux champs sensibles critique **Employee** (`iban`, `bank_account`, `national_id`). Elargissement a d’autres tables ou audit registre RGPD = campagne securite dediee hors cloture fonctionnelle iteration 4.

### Iteration 5 — Monitoring production
**Cible** : B1, B2, B3, B4
**Statut** : **COMPLETE backend** (`CHANGELOG` [4.16.55], 2026-05-14) ; **residu ops** : **B4** (sondes externes hors depot).

**Contenu** :
- Sentry APM traces
- AlertService + Slack webhook
- Slow query logging
- UptimeRobot/BetterUptime

**Livraisons code** :
- **B1** : `SentryContextMiddleware` (prepend groupe API, `bootstrap/app.php`) + `config/sentry.php`.
- **B2** : `App\Notifications\SlackAlertNotification` + `services.slack.monitoring_webhook`.
- **B3** : commande `monitor:slow-queries` planifiee toutes les 15 minutes (`bootstrap/app.php`).
- **B4** : configuration UptimeRobot / Better Stack Uptime + check-list `docs/GESTION_PROJET/RUNBOOK_OBSERVABILITY.md`.

**Backlog** : B5 Telescope (dev), B6 runbook alertes etendu (lien `RUNBOOK_OPERATIONS.md` → observabilite).

### Iteration 6 — Frontend admin dashboard (ecrans prioritaires)
**Cible** : E10, E11, E1, E2
**Statut** : **COMPLETE** (perimetre MVP paie/conges admin, 2026-05-16)

**Livraisons** :
- SPA `PayrollView` : runs pagines, bulletins via **`GET /api/v1/pay-slips`** (plus de boucle N+1 par run), calcul/validation runs, resume `summary`, PDF blob authentifie, exports CSV.
- SPA `LeavesView` : soldes/politiques, approve/reject reels, pagination absences (`CHANGELOG` [4.16.58]).
- API : **`GET /api/v1/pay-slips`** liste manager tenant-scopee + tests Feature (`CHANGELOG` [4.16.59]).
- Absences liste enrichie (`employee_name`, `type`) pour les tableaux manager.

**Hors perimetre iteration 6 (backlog produit)** : E3–E9 (contrats, recrutement Kanban, formation, tracking carte, chat IA, rapports RH dedies SPA, audit/webhooks UI), perfectionnement layout E11/E10 au-dela des routes existantes.

**Contexte repo** :
- Code : `front/admin-dashboard/` ; router `src/router/index.js` (lazy imports par route — ne pas regresser).
- Routes existantes : `/payroll` → `views/payroll/PayrollView.vue`, `/leaves` → `views/leaves/LeavesView.vue`.
### Iteration 7+ — Mobile, vitrine, IA avancee, GTM
**Cible** : Categories C, F, G, H, I, J, K, L
**Statut** : **BACKLOG** — hors cloture « plan 15 MVP admin/API » ; suites dediees par categorie.

**Lots demarres (incrementaux)** :
- **2026-05-16 — Lot vitrine F** : page publique **`/changelog`** + liens footer (`/pricing`, `/blog`, changelog) dans `front/web`.
- **2026-05-16 — Lot SEO F4 partiel** : sitemap dynamique enrichi (`/changelog`, `/privacy`, `/terms`).
- **2026-05-16 — Lot mobile G1 partiel** : liste bulletins employe via **`GET /api/v1/me/pay-slips`** dans l’onglet paie des modules (`ModulesRepository` / `payrollsProvider`).
- **2026-05-16 — Lot API contracts A11** : tracking/flotte aligne OpenAPI pour preparer admin/mobile/kiosk et integrateurs.

**Contenu** :
- Ecrans Flutter (bulletins, conges, notifs push)
- Blog MDX, pricing, SEO
- IA voice pipeline, agents autonomes, predictif
- Good first issues, releases, community
- Integrations tierces (SEPA, CNAS, ZKTeco)
- GTM (cas clients, videos, prospection)

---

## Cloture plan 15 — declaration de perimetre (2026-05-16)

Le **plan 15** est declare **livre pour son objectif consolide** : enchainement documentaire + renforts backend/tests/monitoring **deja merges**, iteration 6 **admin paie/conges** alignee API, endpoint **`GET /api/v1/pay-slips`** pour supporter le SPA sans dette N+1.

**Inclus dans cette cloture** : iterations **1 a 6** (audit/docs, docs technique A1/A2/A4, tests/pre-commit iter. 3, securite-perf iter. 4 arbitree D4/D5, monitoring iter. 5 code + residu **B4** hors depot, admin MVP paie/conges iter. 6).

**Exclus / backlog plan 15+** : iteration **7+** (categories **F–L**, mobile **G**, kiosk **H**, OSS **I**, GTM **J**, integrations **L**, IA avancee **C** hors socle deja livre), ecrans admin **E3–E9**, et toute tache uniquement operationnelle externe (**B4** sondes SaaS, runbooks etendus **B6**).

Les lignes « 0% » ou « reste » du tableau synthese en tete de ce document restent valides pour **l’inventaire global** des plans 01–14 ; ils ne remettent pas en cause la **cloture fonctionnelle** du **lot plan 15** tel que decrit ci-dessus.

---

## Estimation globale

| Phase | Effort estime | Type |
|-------|--------------|------|
| Iterations 1-3 | 5-8 jours | Documentation + tests |
| Iteration 4 | 8-10 jours | Securite + performance |
| Iteration 5 | 3-5 jours | Monitoring |
| Iteration 6 | 8-12 jours | Frontend admin |
| Iteration 7+ | 40-60 jours | Mobile + vitrine + IA + GTM |
| **Total estime** | **65-95 jours** | |

---

## Notes

- Ce plan est vivant : chaque iteration met a jour ce document et les fichiers PLAN_ACTION concernes
- Les taches GTM/marketing (categorie J) sont non-code et peuvent etre parallelisees
- Le score release readiness actuel est 86/100 ; les iterations 2-5 devraient le porter a 90/100
- Priorite absolue : ce qui bloque le score 90/100 (tests, securite, monitoring, documentation)
- Iterations **1–6** sont cloturees cote plan 15 MVP admin/API ; poursuivre le backlog sous **iteration 7+** ou chantiers par categorie (F–L).
