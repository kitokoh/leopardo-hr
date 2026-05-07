# ðŸ“‘ PILOTAGE â€” LEOPARDO RH
# PROGRAM_VERSION = 4.1.103 | 2026-05-08
# CE FICHIER EST LA SEULE SOURCE DE VÃ‰RITÃ‰ OPÃ‰RATIONNELLE
# Statut des anciens fichiers : voir section "Gouvernance documentaire"

> âš ï¸ **Avertissement â€” divergence scope vs code livrÃ©**
> La section Â« SCOPE MVP VERROUILLÃ‰ Â» ci-dessous dÃ©crit le pÃ©rimÃ¨tre
> initialement figÃ©. Le code sur `main` **a dÃ©passÃ© ce pÃ©rimÃ¨tre** :
> multitenancy mode `schema` activÃ©, 6 sous-rÃ´les manager (`principal`,
> `rh`, `dept`, `comptable`, `superviseur`, `employee`), plusieurs pages
> Blade, hÃ©bergement cible **Render** (voir `.github/workflows/deploy-main.yml`).
> Tant que la dÃ©cision produit n'est pas prise pour aligner ce document
> sur la rÃ©alitÃ©, se rÃ©fÃ©rer Ã  `docs/REFERENTIEL_PRODUIT/ROADMAP.md` + `docs/REFERENTIEL_PRODUIT/AUDIT_v2_v3_COMPLIANCE.md`
> pour l'Ã©tat rÃ©el. Voir aussi `docs/GESTION_PROJET/CORRECTIONS.md`.

---

## CONVENTION DE VERSIONING

```
VERSION  = 4.1.87   â†’ Version globale du projet/pilotage (ce fichier fait foi)

                              Doit rester synchrone avec :
                                - CHANGELOG.md (derniÃ¨re entrÃ©e)
                                - api/config/app.php â†’ 'version'
                                - GET /api/v1/health â†’ champ "version"
DOC_VERSION      = propre   â†’ Chaque doc technique garde sa version interne
                              (ex: ERD v2.0, API v2.1, SQL v1.1)
CODE_VERSION     = 0.1.0    â†’ Version release applicative (Release MVP)

RÃ¨gle :
- PROGRAM_VERSION est la SEULE version de rÃ©fÃ©rence pour l'Ã©tat du projet
- Les DOC_VERSION techniques ne remplacent JAMAIS PROGRAM_VERSION
- CODE_VERSION sera gÃ©rÃ©e par git tag lors des releases
- PROGRAM_VERSION change quand : scope modifiÃ©, gouvernance modifiÃ©e, phase changÃ©e
```

---

## LE PROJET EN 1 PHRASE

**Leopardo RH** = SaaS de gestion RH pour petits patrons (5-50 employÃ©s).
MVP : "Combien je dois Ã  mes employÃ©s aujourd'hui ?" â€” en 1 clic.

---

## Ã‰TAT ACTUEL

```
Date MAJ       : 2026-05-07
Conception     : âœ… TerminÃ©e (40+ documents dans docs/dossierdeConception/ + README d'orientation)
Code           : âœ… `main` inclut le MVP livrÃ© + i18n + hardening P0/P1/P2 + salary advances + payroll RH (voir CHANGELOG.md jusqu'Ã  4.1.100)
Phase active   : Stabilisation beta + gouvernance documentaire + durcissement progressif (voir docs/REFERENTIEL_PRODUIT/ROADMAP.md)
                 Note: la section "SCOPE MVP VERROUILLÃ‰" plus bas reflÃ¨te
                 le scope initial figÃ©, pas l'Ã©tat actuel du code.
Prochaine action : Consolider les parcours pilotes, fermer les Ã©carts documentaires restants et prÃ©parer les prochains modules sans dÃ©rive de scope
Objectif       : Premier utilisateur payant en 8 semaines
Validation locale backend : Docker d'abord (voir `docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md`)
```

---

## SCOPE MVP VERROUILLÃ‰ (ce qu'on livre, rien de plus)

### âœ… INCLUS

| Feature | Endpoints | PrioritÃ© |
|---------|-----------|----------|
| Auth (login/logout/me) | 3 | Sprint 1 |
| CRUD EmployÃ©s | 5 | Sprint 1 |
| Pointage (check-in/out/today/historique) | 4 | Sprint 2 |
| Daily Summary ("ce que je dois") | 1 | Sprint 2 |
| Quick Estimate (pÃ©riode libre) | 1 | Sprint 3 |
| ReÃ§u PDF informatif | 1 | Sprint 3 |
| Dashboard web (Next.js) | 5+ pages | Sprint 3 |
| App Flutter (login + pointage + historique + Ã©quipe) | 5 Ã©crans | Sprint 3 |
| BiomÃ©trie (visage/empreinte) | 3 endpoints | Sprint 4 |
| Borne Kiosque ZKTeco | - | Sprint 4 |
| **TOTAL** | **~25 endpoints** | **8 semaines** |

### âŒ EXCLU DU MVP (tout Ã§a existe dans les specs, sera fait plus tard)

Absences/CongÃ©s Â· Paie complÃ¨te Â· Avances Â· TÃ¢ches Â· Ã‰valuations Â·
Multi-pays Â· SSE Â· Notifications push Â· Export bancaire Â·
Import CSV Â· Vue.js/Inertia

### ðŸ›¡ï¸ GARDE-FOU SCOPE

> **RÃˆGLE : Toute feature hors du tableau vert â†’ rÃ©pondre "Phase 2" et continuer.**
> Si un agent IA ou une personne propose d'ajouter une feature MVP, vÃ©rifier dans le tableau.
> Si elle n'y est pas â†’ non. Pas de discussion, pas d'exception.

---

## ARCHITECTURE MVP (simplifiÃ©e)

```
Stack MVP :
  Backend    : Laravel 11 + PHP 8.4
  BDD        : PostgreSQL 16
  Cache      : File driver (pas Redis)
  Queue      : Sync (pas Redis/Horizon)
  Auth       : Sanctum (tokens opaques)
  Frontend   : Next.js + Tailwind CSS (App Router)
  Mobile     : Flutter (3 Ã©crans)
  PDF        : DomPDF (synchrone)
  HÃ©bergement: Render (Web Service) + Neon.tech (PostgreSQL)
  Tests      : Pest PHP

Multitenancy MVP :
  Mode SHARED par dÃ©faut (Global Scope company_id)
  Support mode SCHEMA inclus (mais gelÃ©/rÃ©servÃ© Enterprise)
  Bascule automatique via search_path PostgreSQL

RBAC MVP :
  2 rÃ´les : manager + employÃ©
  6 sous-rÃ´les manager : principal, rh, dept, comptable, superviseur, employee (managerial)

Langues :
  FR + AR (RTL) + TR + EN â€” production-ready
  Middleware SetLocale, endpoint PATCH /auth/language
  Fichiers lang/ complets (errors, auth, attendance, employees, finance, emails, pdf, cameras, validation)

Pays MVP :
  AlgÃ©rie uniquement (1 modÃ¨le RH : CNAS + IRG)
```

---

## SPRINTS â€” ORDRE D'EXÃ‰CUTION

### Sprint 0 â€” PrÃ©paration (1-2 jours)

| # | Action | Responsable | Statut |
|---|--------|-------------|--------|
| S0-1 | Valider environnement Render | Humain | âœ… |
| S0-2 | Corriger les 7 contradictions docs (voir CORRECTIONS.md) | Humain/IA | âœ… |
| S0-3 | CrÃ©er la landing page (Carrd ou HTML statique) | Humain | â¬œ |
| S0-4 | RÃ©server le domaine leopardo-rh.com | Humain | â¬œ |

### Sprint 1 â€” Fondations (semaines 1-2) âœ… TerminÃ©

| # | Ticket | Prompt | CritÃ¨re de validation | Statut |
|---|--------|--------|-----------------------|--------|
| S1-1 | Init Laravel + migrations public + seeders | `MVP-01` | `php artisan test` â†’ 0 failure, `GET /health` â†’ 200 | âœ… |
| S1-2 | Trait BelongsToCompany + TenantMiddleware simplifiÃ© | `MVP-01` | Test isolation company A â‰  company B | âœ… |
| S1-3 | Auth (login / logout / me) | `MVP-02` | Token valide, company suspendue â†’ 403 | âœ… |
| S1-4 | CRUD EmployÃ©s (list/create/show/update/archive) | `MVP-02` | 5 endpoints, RBAC manager/self | âœ… |
| S1-5 | Premier dÃ©ploiement Render | Humain | App en ligne, health check OK | âœ… |

### Sprint 2 â€” CÅ“ur mÃ©tier (semaines 3-4) âœ… TerminÃ©

| # | Ticket | Prompt | CritÃ¨re de validation | Statut |
|---|--------|--------|-----------------------|--------|
| S2-1 | Pointage check-in/check-out | `MVP-03` | Horodatage serveur, GPS optionnel | âœ… |
| S2-2 | GET /attendance/today | `MVP-03` | Ã‰tat du jour correct | âœ… |
| S2-3 | Historique pointages | `MVP-03` | Filtres mois/employÃ© | âœ… |
| S2-4 | Daily Summary | `MVP-04` | Montant journalier estimÃ© correct | âœ… |
| S2-5 | Quick Estimate | `MVP-04` | Simulation pÃ©riode libre | âœ… |

### Sprint 3 â€” Interface + Polish (semaines 5-6) âœ… TerminÃ©

| # | Ticket | Prompt | CritÃ¨re de validation | Statut |
|---|--------|--------|-----------------------|--------|
| S3-1 | Dashboard web Blade (liste employÃ©s + pointages) | `MVP-05` | 2 pages fonctionnelles | âœ… |
| S3-2 | ReÃ§u PDF pÃ©riode | `MVP-04` | PDF gÃ©nÃ©rÃ© et tÃ©lÃ©chargeable | âœ… |
| S3-3 | Flutter : login + pointage + historique | `MVP-06` | 3 Ã©crans fonctionnels | âœ… |
| S3-4 | Bug fixes + tests | Tous | 0 bug bloquant | âœ… |

### Sprint 4 â€” Beta (semaines 7-8) ðŸš§ En cours

| # | Ticket | Responsable | CritÃ¨re de validation | Statut |
|---|--------|-------------|----------------------|--------|
| S4-1 | DÃ©ployer backend + web sur Render | Humain | Domaine/Render rÃ©pondent, login web OK | âœ… |
| S4-2 | Brancher mobile sur environnement beta rÃ©el | IA/Humain | Login, check-in/out, history OK sur backend Render | âœ… |
| S4-3 | Inviter 3-5 prospects beta | Humain | Retours collectÃ©s | â¬œ |
| S4-4 | Corrections prioritaires | IA | Feedback implÃ©mentÃ© | â¬œ |
| S4-5 | Ouvrir les inscriptions | Humain | Premier client payant | â¬œ |
| S4-6 | **Plan d'Action d'AmÃ©lioration (Phase 1)** | IA | SÃ©curitÃ© P0 : Chiffrement, CORS, Lockout | âœ… |

---

## BLOCAGES / POINTS DE VIGILANCE

- Backend local/CI alignÃ© en PHP 8.4 (cohÃ©rent avec `composer.lock` actuel).
- `PILOTAGE.md` avait du retard sur l'Ã©tat rÃ©el du dÃ©pÃ´t ; corrigÃ© dans cette version.
- L'infrastructure cible a Ã©tÃ© dÃ©placÃ©e de o2switch vers Render + Neon pour plus de stabilitÃ© et de gratuitÃ© en phase dev.

---

## PROMPTS D'EXÃ‰CUTION MVP (v3 â€” remplacent les CC-01 Ã  CC-08)

### Principe : 1 prompt = 1 sprint ticket = 1 session IA

> **Tous les prompts sont dans `docs/PROMPTS_EXECUTION/v3/`**

| Prompt | Contenu | DurÃ©e | PrÃ©requis |
|--------|---------|-------|-----------|
| `MVP-01` | Init Laravel + multitenancy shared + migrations + seeders | 4-6h | Render configurÃ© |
| `MVP-02` | Auth (3 endpoints) + CRUD EmployÃ©s (5 endpoints) | 6-8h | MVP-01 vert |
| `MVP-03` | Pointage (4 endpoints) + schedules basique | 4-6h | MVP-02 vert |
| `MVP-04` | Daily Summary + Quick Estimate + PDF reÃ§u | 4-6h | MVP-03 vert |
| `MVP-05` | Dashboard Blade (2 pages) | 4-6h | MVP-04 vert |
| `MVP-06` | Flutter (3 Ã©crans : login + pointage + historique) | 6-8h | MVP-04 vert |

---

## 10 RÃˆGLES ABSOLUES

```
1.  SCOPE       â†’ Feature hors tableau vert = "Phase 2". Pas de nÃ©gociation.
2.  HORODATAGE  â†’ now() cÃ´tÃ© serveur. JAMAIS le timestamp du client.
3.  TENANT      â†’ Global Scope BelongsToCompany sur tous les modÃ¨les tenant.
                   JAMAIS de WHERE company_id dans les controllers.
4.  STATUS      â†’ employees.status VARCHAR ('active'|'suspended'|'archived').
                   PAS de is_active BOOLEAN.
5.  SALAIRE     â†’ salary_base = dans employees (fixe mensuel/journalier/horaire).
                   gross_salary = dans payrolls (calculÃ©, Phase 2).
6.  TESTS       â†’ Ã‰crire les tests Pest AVANT le code. TenantIsolationTest = prioritÃ© #1.
7.  VALIDATION  â†’ FormRequest Laravel, jamais dans le Controller.
8.  LOGIQUE     â†’ Service class, jamais dans le Controller.
9.  TRANSACTION â†’ OpÃ©rations multi-tables = toujours DB::transaction().
10. PORTE VERTE â†’ Ne JAMAIS passer au prompt suivant si le prÃ©cÃ©dent a des tests rouges.
```

---

## GOUVERNANCE DOCUMENTAIRE

### Sources de vÃ©ritÃ© ACTIVES (ordre de prioritÃ©)

| # | Document | Chemin | Usage |
|---|----------|--------|-------|
| 1 | **PILOTAGE.md** (ce fichier) | `PILOTAGE.md` | Scope, phases, rÃ¨gles, Ã©tat, versioning |
| 2 | Garde-fous | `docs/GESTION_PROJET/GARDE_FOUS.md` | RÃ¨gles anti-dÃ©rive |
| 3 | SQL Complet | `docs/dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql` | Structure DB fait loi |
| 4 | ERD | `docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md` | Relations et entitÃ©s |
| 5 | API Contrats | `docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` | Payloads et rÃ©ponses |
| 6 | RÃ¨gles MÃ©tier | `docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md` | Formules de calcul |
| 7 | Prompts MVP v3 | `docs/PROMPTS_EXECUTION/v3/MVP-*.md` | Instructions par session |
| 8 | DÃ©cision MVP | `docs/GESTION_PROJET/GO_NO_GO_MVP.md` | DÃ©cision GO MVP et pÃ©rimÃ¨tre validÃ© |

> En cas de contradiction entre 2 documents â†’ le plus haut dans cette liste gagne.

### FiliÃ¨re de prompts ACTIVE

```
ACTIVE  : docs/PROMPTS_EXECUTION/v3/MVP-*.md     (6 prompts MVP)
LEGACY  : docs/PROMPTS_EXECUTION/v2/backend/CC-*  (lecture seule â€” NE PAS EXÃ‰CUTER)
LEGACY  : docs/PROMPTS_EXECUTION/v2/mobile/JU-*   (lecture seule â€” NE PAS EXÃ‰CUTER)
LEGACY  : docs/PROMPTS_EXECUTION/v2/frontend/CU-* (lecture seule â€” NE PAS EXÃ‰CUTER)
LEGACY  : docs/PROMPTS_EXECUTION/patches/*         (absorbÃ© dans v3 â€” NE PAS APPLIQUER)
LEGACY  : docs/PROMPTS_EXECUTION/ORCHESTRATION/*   (remplacÃ© par PILOTAGE.md)
```

### Fichiers de pilotage â€” statut officiel

| Fichier | Statut | Explication |
|---------|--------|-------------|
| `PILOTAGE.md` | âœ… **ACTIF â€” MAÃŽTRE** | Source de vÃ©ritÃ© unique pour le pilotage |
| `docs/GESTION_PROJET/GARDE_FOUS.md` | âœ… **ACTIF** | RÃ¨gles de survie du projet |
| `docs/GESTION_PROJET/CORRECTIONS.md` | âœ… **ACTIF** | Corrections Sprint 0 Ã  appliquer |
| `CHANGELOG.md` | âœ… **ACTIF** | Continue Ã  recevoir les entrÃ©es |
| `docs/GESTION_PROJET/GO_NO_GO_MVP.md` | âœ… **ACTIF** | DÃ©cision officielle GO MVP |
| `JOURNAL_RACINE.md` | âœ… **ACTIF** | Continue Ã  recevoir les entrÃ©es |
| `docs/notes/archive/ORCHESTRATION_MAITRE.md` | ðŸ“¦ **HISTORIQUE** | RemplacÃ© par PILOTAGE.md â€” lecture seule |
| `docs/notes/archive/INDEX_CANONIQUE.md` | ðŸ“¦ **HISTORIQUE** | AbsorbÃ© dans PILOTAGE.md |
| `docs/notes/archive/CONTEXTE_SESSION_IA.md` | ðŸ“¦ **HISTORIQUE** | AbsorbÃ© dans PILOTAGE.md |
| `docs/notes/archive/JOURNAL_DE_BORD.md` | ðŸ“¦ **HISTORIQUE** | AbsorbÃ© dans PILOTAGE.md |
| `docs/notes/archive/BACKLOG_PHASE1_UNIQUE.md` | ðŸ“¦ **HISTORIQUE** | RemplacÃ© par les sprints dans PILOTAGE.md |
| `docs/notes/archive/CONTINUE.md` | ðŸ“¦ **HISTORIQUE** | RemplacÃ© par PILOTAGE.md |
| `docs/notes/archive/SUIVI_PROMPTS.md` | ðŸ“¦ **HISTORIQUE** | Suivi intÃ©grÃ© dans PILOTAGE.md |
| `docs/notes/archive/EXECUTION_BLOCKERS_AND_NEXT.md` | ðŸ“¦ **HISTORIQUE** | Blockers intÃ©grÃ©s dans Sprint 0 |
| `docs/notes/archive/08_FEUILLE_DE_ROUTE.md` | ðŸ“¦ **HISTORIQUE** | RemplacÃ© par PILOTAGE.md |
| `docs/notes/archive/CU-01_ET_AGENTS.md` | ðŸ“¦ **HISTORIQUE** | RemplacÃ© par PILOTAGE.md |
| `docs/notes/archive/ARBORESCENCE_PROJET_COMPLET.md` | ðŸ“¦ **HISTORIQUE** | RemplacÃ© par PILOTAGE.md |

> **RÃˆGLE :** Un fichier ðŸ“¦ HISTORIQUE ne doit JAMAIS Ãªtre lu comme instruction.
> Il sert uniquement de traÃ§abilitÃ©. Si un agent IA le lit, il doit ignorer ses directives
> et suivre PILOTAGE.md Ã  la place.

### Documents de conception (inchangÃ©s)

Tous les fichiers dans `docs/dossierdeConception/` restent **ACTIFS en lecture** :
- Ils sont la rÃ©fÃ©rence technique pour l'implÃ©mentation future (Phase 2+)
- Ils gardent leur propre DOC_VERSION interne
- Ils ne pilotent PAS le scope du MVP (seul PILOTAGE.md le fait)
- Le point d'entrÃ©e recommandÃ© est `docs/dossierdeConception/README.md`

---

## CORRECTIONS Ã€ APPLIQUER (liste fermÃ©e â€” voir CORRECTIONS.md pour dÃ©tails)

| # | Quoi | OÃ¹ | PrioritÃ© |
|---|------|----|----------|
| C-1 | Supprimer `POST /auth/refresh` | `api/openapi.yaml` L155-178 | Sprint 0 |
| C-2 | Remplacer `is_active` par `status` dans le modÃ¨le exemple | `docs/.../08_MULTITENANCY_STRATEGY.md` | Sprint 0 |
| C-3 | Aligner `user_lookups` PK = email | `docs/.../08_MULTITENANCY_STRATEGY.md` | Sprint 0 |
| C-4 | Corriger "Starter Gratuit" â†’ "Starter 29â‚¬" | `docs/.../18_MARKETING_ET_VENTES.md` | Sprint 0 |
| C-5 | Corriger trait `HasCompanyScope` (bug double boot) | `docs/.../08_MULTITENANCY_STRATEGY.md` | Sprint 1 |
| C-6 | DÃ©placer `AUDIT_COMPLET_MANQUES.md` â†’ `docs/notes/archive/` | Racine | Sprint 0 |
| C-7 | Supprimer `bon-fixed/` | Racine | Sprint 0 |

---

## DÃ‰CISIONS FIGÃ‰ES (ne pas remettre en question)

| Sujet | DÃ©cision | Raison |
|-------|----------|--------|
| FK hiÃ©rarchie | `manager_id` dans employees | UnifiÃ© SQL/ERD |
| Statut employÃ© | `status VARCHAR` pas `is_active` | 3 Ã©tats nÃ©cessaires |
| FCM tokens | Table `employee_devices` (pas JSONB) | Scalable |
| Horodatage | Serveur uniquement (`now()`) | Anti-fraude |
| Audit logs | Observer Eloquent | Automatique |
| salary_base vs gross | Deux champs diffÃ©rents, deux tables | ClartÃ© |

---

## COMMENT DÃ‰MARRER UNE SESSION IA

```
Ã‰TAPE 1 â†’ Lire PILOTAGE.md (ce fichier) â€” 5 min
Ã‰TAPE 2 â†’ VÃ©rifier quel sprint/ticket est "Ã€ faire"
Ã‰TAPE 3 â†’ Lire le prompt MVP-XX correspondant
Ã‰TAPE 4 â†’ VÃ©rifier la porte verte du prompt prÃ©cÃ©dent (php artisan test)
Ã‰TAPE 5 â†’ ExÃ©cuter le prompt
Ã‰TAPE 6 â†’ Mettre Ã  jour le statut dans ce fichier (â¬œ â†’ âœ…)
Ã‰TAPE 7 â†’ Commit : "feat(scope): description"
```

---

## COMMENT FINIR UNE SESSION IA

```
Ã‰TAPE 1 â†’ php artisan test â†’ 0 failure (OBLIGATOIRE)
Ã‰TAPE 2 â†’ Mettre Ã  jour le tableau sprint dans ce fichier
Ã‰TAPE 3 â†’ Ajouter une ligne dans CHANGELOG.md
Ã‰TAPE 4 â†’ Commit : "chore: update PILOTAGE.md after MVP-XX"
```

---

## VISION LONG TERME (ce qu'on construira APRÃˆS le MVP)

La conception complÃ¨te existe dans `docs/dossierdeConception/` (40+ documents).
Le chemin de lecture recommandÃ© est : `docs/README.md` -> `docs/REFERENTIEL_PRODUIT/` -> `docs/dossierdeConception/README.md`.
L'ordre d'ajout des features sera guidÃ© par les retours clients, pas par les specs :

```
Phase 2 (aprÃ¨s 5 clients)     â†’ Absences + CongÃ©s + Paie algÃ©rienne basique
Phase 3 (aprÃ¨s 10 clients)    â†’ Multi-langue + Maroc + Import CSV
Phase 4 (aprÃ¨s 20 clients)    â†’ TÃ¢ches + Ã‰valuations + Vue.js dashboard
Phase 5 (aprÃ¨s 50 clients)    â†’ Mode schema Enterprise + ZKTeco + SSE
Phase 6 (aprÃ¨s 100 clients)   â†’ API publique + Export bancaire + 7 pays
```

> Les 40+ documents de conception sont le PLAN de l'immeuble complet.
> On construit Ã©tage par Ã©tage. Chaque phase utilise les specs dÃ©jÃ  Ã©crites.

