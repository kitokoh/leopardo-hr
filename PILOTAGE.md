# 📑 PILOTAGE — LEOPARDO RH
# PROGRAM_VERSION = 4.1.34 | 12 Avril 2026
# CE FICHIER EST LA SEULE SOURCE DE VÉRITÉ OPÉRATIONNELLE
# Statut des anciens fichiers : voir section "Gouvernance documentaire"

---

## CONVENTION DE VERSIONING

```
PROGRAM_VERSION  = 4.1.14  → Version globale du projet/pilotage (ce fichier fait foi)
DOC_VERSION      = propre   → Chaque doc technique garde sa version interne
                              (ex: ERD v2.0, API v2.1, SQL v1.1)
CODE_VERSION     = 0.0.0   → Version release applicative (sera 0.1.0 au premier déploiement)

Règle :
- PROGRAM_VERSION est la SEULE version de référence pour l'état du projet
- Les DOC_VERSION techniques ne remplacent JAMAIS PROGRAM_VERSION
- CODE_VERSION sera gérée par git tag lors des releases
- PROGRAM_VERSION change quand : scope modifié, gouvernance modifiée, phase changée
```

---

## LE PROJET EN 1 PHRASE

**Leopardo RH** = SaaS de gestion RH pour petits patrons (5-50 employés).
MVP : "Combien je dois à mes employés aujourd'hui ?" — en 1 clic.

---

## ÉTAT ACTUEL

```
Date MAJ       : 05 Avril 2026
Conception     : ✅ Terminée (40+ documents dans docs/dossierdeConception/)
Code           : ✅ MVP-01 à MVP-06 mergés sur `main`
Phase active   : MVP — scope verrouillé ci-dessous
Prochaine action : SPRINT 4 — Beta réelle + déploiement VPS
Objectif       : Premier utilisateur payant en 8 semaines
Validation locale backend : Docker d'abord (voir `docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md`)
```

---

## SCOPE MVP VERROUILLÉ (ce qu'on livre, rien de plus)

### ✅ INCLUS

| Feature | Endpoints | Priorité |
|---------|-----------|----------|
| Auth (login/logout/me) | 3 | Sprint 1 |
| CRUD Employés | 5 | Sprint 1 |
| Pointage (check-in/out/today/historique) | 4 | Sprint 2 |
| Daily Summary ("ce que je dois") | 1 | Sprint 2 |
| Quick Estimate (période libre) | 1 | Sprint 3 |
| Reçu PDF informatif | 1 | Sprint 3 |
| Dashboard web (Blade) | 2 pages | Sprint 3 |
| App Flutter (login + pointage + historique) | 3 écrans | Sprint 3 |
| **TOTAL** | **~15 endpoints** | **6 semaines** |

### ❌ EXCLU DU MVP (tout ça existe dans les specs, sera fait plus tard)

Absences/Congés · Paie complète · Avances · Tâches · Évaluations · ZKTeco biométrie ·
Multi-langue · RTL arabe · Multi-pays · SSE · Notifications push · Export bancaire ·
Import CSV · Vue.js/Inertia · Mode schema Enterprise · 5 sous-rôles gestionnaire

### 🛡️ GARDE-FOU SCOPE

> **RÈGLE : Toute feature hors du tableau vert → répondre "Phase 2" et continuer.**
> Si un agent IA ou une personne propose d'ajouter une feature MVP, vérifier dans le tableau.
> Si elle n'y est pas → non. Pas de discussion, pas d'exception.

---

## ARCHITECTURE MVP (simplifiée)

```
Stack MVP :
  Backend    : Laravel 11 + PHP 8.4
  BDD        : PostgreSQL 16
  Cache      : File driver (pas Redis)
  Queue      : Sync (pas Redis/Horizon)
  Auth       : Sanctum (tokens opaques)
  Frontend   : Blade + Alpine.js + Tailwind CSS (pas Vue.js/Inertia)
  Mobile     : Flutter (3 écrans)
  PDF        : DomPDF (synchrone)
  Hébergement: Render (Web Service) + Neon.tech (PostgreSQL)
  Tests      : Pest PHP

Multitenancy MVP :
  Mode SHARED uniquement (Global Scope company_id)
  PAS de mode schema (Enterprise → Phase 2+)
  PAS de search_path PostgreSQL switching

RBAC MVP :
  2 rôles : manager + employé
  PAS de sous-rôles (rh, dept, comptable, superviseur → Phase 2)

Langues MVP :
  Français uniquement
  i18n préparé (fichiers lang/) mais 1 langue seulement

Pays MVP :
  Algérie uniquement (1 modèle RH : CNAS + IRG)
```

---

## SPRINTS — ORDRE D'EXÉCUTION

### Sprint 0 — Préparation (1-2 jours)

| # | Action | Responsable | Statut |
|---|--------|-------------|--------|
| S0-1 | Choisir et commander le VPS | Humain | ⬜ |
| S0-2 | Corriger les 6 contradictions docs (voir CORRECTIONS.md) | Humain/IA | ⬜ |
| S0-3 | Créer la landing page (Carrd ou HTML statique) | Humain | ⬜ |
| S0-4 | Réserver le domaine leopardo-rh.com | Humain | ⬜ |

### Sprint 1 — Fondations (semaines 1-2) ✅ Terminé

| # | Ticket | Prompt | Critère de validation | Statut |
|---|--------|--------|-----------------------|--------|
| S1-1 | Init Laravel + migrations public + seeders | `MVP-01` | `php artisan test` → 0 failure, `GET /health` → 200 | ✅ |
| S1-2 | Trait BelongsToCompany + TenantMiddleware simplifié | `MVP-01` | Test isolation company A ≠ company B | ✅ |
| S1-3 | Auth (login / logout / me) | `MVP-02` | Token valide, company suspendue → 403 | ✅ |
| S1-4 | CRUD Employés (list/create/show/update/archive) | `MVP-02` | 5 endpoints, RBAC manager/self | ✅ |
| S1-5 | Premier déploiement VPS | Humain | App en ligne, health check OK | ⬜ |

### Sprint 2 — Cœur métier (semaines 3-4) ✅ Terminé

| # | Ticket | Prompt | Critère de validation | Statut |
|---|--------|--------|-----------------------|--------|
| S2-1 | Pointage check-in/check-out | `MVP-03` | Horodatage serveur, GPS optionnel | ✅ |
| S2-2 | GET /attendance/today | `MVP-03` | État du jour correct | ✅ |
| S2-3 | Historique pointages | `MVP-03` | Filtres mois/employé | ✅ |
| S2-4 | Daily Summary | `MVP-04` | Montant journalier estimé correct | ✅ |
| S2-5 | Quick Estimate | `MVP-04` | Simulation période libre | ✅ |

### Sprint 3 — Interface + Polish (semaines 5-6) ✅ Terminé

| # | Ticket | Prompt | Critère de validation | Statut |
|---|--------|--------|-----------------------|--------|
| S3-1 | Dashboard web Blade (liste employés + pointages) | `MVP-05` | 2 pages fonctionnelles | ✅ |
| S3-2 | Reçu PDF période | `MVP-04` | PDF généré et téléchargeable | ✅ |
| S3-3 | Flutter : login + pointage + historique | `MVP-06` | 3 écrans fonctionnels | ✅ |
| S3-4 | Bug fixes + tests | Tous | 0 bug bloquant | ✅ |

### Sprint 4 — Beta (semaines 7-8) 🚧 En cours

| # | Ticket | Responsable | Critère de validation | Statut |
|---|--------|-------------|----------------------|--------|
| S4-1 | Déployer backend + web sur VPS | Humain | Domaine/VPS répondent, login web OK | ⬜ |
| S4-2 | Brancher mobile sur environnement beta réel | IA/Humain | Login, check-in/out, history OK sur backend déployé | ⬜ |
| S4-3 | Inviter 3-5 prospects beta | Humain | Retours collectés | ⬜ |
| S4-4 | Corrections prioritaires | IA | Feedback implémenté | ⬜ |
| S4-5 | Ouvrir les inscriptions | Humain | Premier client payant | ⬜ |

---

## BLOCAGES / POINTS DE VIGILANCE

- Backend local/CI aligné en PHP 8.4 (cohérent avec `composer.lock` actuel).
- `PILOTAGE.md` avait du retard sur l'état réel du dépôt ; corrigé dans cette version.
- L'infrastructure cible a été déplacée de o2switch vers Render + Neon pour plus de stabilité et de gratuité en phase dev.

---

## PROMPTS D'EXÉCUTION MVP (v3 — remplacent les CC-01 à CC-08)

### Principe : 1 prompt = 1 sprint ticket = 1 session IA

> **Tous les prompts sont dans `docs/PROMPTS_EXECUTION/v3/`**

| Prompt | Contenu | Durée | Prérequis |
|--------|---------|-------|-----------|
| `MVP-01` | Init Laravel + multitenancy shared + migrations + seeders | 4-6h | VPS commandé |
| `MVP-02` | Auth (3 endpoints) + CRUD Employés (5 endpoints) | 6-8h | MVP-01 vert |
| `MVP-03` | Pointage (4 endpoints) + schedules basique | 4-6h | MVP-02 vert |
| `MVP-04` | Daily Summary + Quick Estimate + PDF reçu | 4-6h | MVP-03 vert |
| `MVP-05` | Dashboard Blade (2 pages) | 4-6h | MVP-04 vert |
| `MVP-06` | Flutter (3 écrans : login + pointage + historique) | 6-8h | MVP-04 vert |

---

## 10 RÈGLES ABSOLUES

```
1.  SCOPE       → Feature hors tableau vert = "Phase 2". Pas de négociation.
2.  HORODATAGE  → now() côté serveur. JAMAIS le timestamp du client.
3.  TENANT      → Global Scope BelongsToCompany sur tous les modèles tenant.
                   JAMAIS de WHERE company_id dans les controllers.
4.  STATUS      → employees.status VARCHAR ('active'|'suspended'|'archived').
                   PAS de is_active BOOLEAN.
5.  SALAIRE     → salary_base = dans employees (fixe mensuel/journalier/horaire).
                   gross_salary = dans payrolls (calculé, Phase 2).
6.  TESTS       → Écrire les tests Pest AVANT le code. TenantIsolationTest = priorité #1.
7.  VALIDATION  → FormRequest Laravel, jamais dans le Controller.
8.  LOGIQUE     → Service class, jamais dans le Controller.
9.  TRANSACTION → Opérations multi-tables = toujours DB::transaction().
10. PORTE VERTE → Ne JAMAIS passer au prompt suivant si le précédent a des tests rouges.
```

---

## GOUVERNANCE DOCUMENTAIRE

### Sources de vérité ACTIVES (ordre de priorité)

| # | Document | Chemin | Usage |
|---|----------|--------|-------|
| 1 | **PILOTAGE.md** (ce fichier) | `PILOTAGE.md` | Scope, phases, règles, état, versioning |
| 2 | Garde-fous | `docs/GESTION_PROJET/GARDE_FOUS.md` | Règles anti-dérive |
| 3 | SQL Complet | `docs/dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql` | Structure DB fait loi |
| 4 | ERD | `docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md` | Relations et entités |
| 5 | API Contrats | `docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` | Payloads et réponses |
| 6 | Règles Métier | `docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md` | Formules de calcul |
| 7 | Prompts MVP v3 | `docs/PROMPTS_EXECUTION/v3/MVP-*.md` | Instructions par session |

> En cas de contradiction entre 2 documents → le plus haut dans cette liste gagne.

### Filière de prompts ACTIVE

```
ACTIVE  : docs/PROMPTS_EXECUTION/v3/MVP-*.md     (6 prompts MVP)
LEGACY  : docs/PROMPTS_EXECUTION/v2/backend/CC-*  (lecture seule — NE PAS EXÉCUTER)
LEGACY  : docs/PROMPTS_EXECUTION/v2/mobile/JU-*   (lecture seule — NE PAS EXÉCUTER)
LEGACY  : docs/PROMPTS_EXECUTION/v2/frontend/CU-* (lecture seule — NE PAS EXÉCUTER)
LEGACY  : docs/PROMPTS_EXECUTION/patches/*         (absorbé dans v3 — NE PAS APPLIQUER)
LEGACY  : docs/PROMPTS_EXECUTION/ORCHESTRATION/*   (remplacé par PILOTAGE.md)
```

### Fichiers de pilotage — statut officiel

| Fichier | Statut | Explication |
|---------|--------|-------------|
| `PILOTAGE.md` | ✅ **ACTIF — MAÎTRE** | Source de vérité unique pour le pilotage |
| `docs/GESTION_PROJET/GARDE_FOUS.md` | ✅ **ACTIF** | Règles de survie du projet |
| `docs/GESTION_PROJET/CORRECTIONS.md` | ✅ **ACTIF** | Corrections Sprint 0 à appliquer |
| `CHANGELOG.md` | ✅ **ACTIF** | Continue à recevoir les entrées |
| `JOURNAL_RACINE.md` | ✅ **ACTIF** | Continue à recevoir les entrées |
| `ORCHESTRATION_MAITRE.md` | 📦 **HISTORIQUE** | Remplacé par PILOTAGE.md — lecture seule |
| `docs/GESTION_PROJET/INDEX_CANONIQUE.md` | 📦 **HISTORIQUE** | Absorbé dans PILOTAGE.md |
| `docs/GESTION_PROJET/CONTEXTE_SESSION_IA.md` | 📦 **HISTORIQUE** | Absorbé dans PILOTAGE.md |
| `docs/GESTION_PROJET/JOURNAL_DE_BORD.md` | 📦 **HISTORIQUE** | Absorbé dans PILOTAGE.md |
| `docs/GESTION_PROJET/BACKLOG_PHASE1_UNIQUE.md` | 📦 **HISTORIQUE** | Remplacé par les sprints dans PILOTAGE.md |
| `docs/PROMPTS_EXECUTION/ORCHESTRATION/CONTINUE.md` | 📦 **HISTORIQUE** | Remplacé par PILOTAGE.md |
| `docs/GESTION_PROJET/SUIVI_PROMPTS.md` | 📦 **HISTORIQUE** | Suivi intégré dans PILOTAGE.md |
| `docs/GESTION_PROJET/EXECUTION_BLOCKERS_AND_NEXT.md` | 📦 **HISTORIQUE** | Blockers intégrés dans Sprint 0 |

> **RÈGLE :** Un fichier 📦 HISTORIQUE ne doit JAMAIS être lu comme instruction.
> Il sert uniquement de traçabilité. Si un agent IA le lit, il doit ignorer ses directives
> et suivre PILOTAGE.md à la place.

### Documents de conception (inchangés)

Tous les fichiers dans `docs/dossierdeConception/` restent **ACTIFS en lecture** :
- Ils sont la référence technique pour l'implémentation future (Phase 2+)
- Ils gardent leur propre DOC_VERSION interne
- Ils ne pilotent PAS le scope du MVP (seul PILOTAGE.md le fait)

---

## CORRECTIONS À APPLIQUER (liste fermée — voir CORRECTIONS.md pour détails)

| # | Quoi | Où | Priorité |
|---|------|----|----------|
| C-1 | Supprimer `POST /auth/refresh` | `api/openapi.yaml` L155-178 | Sprint 0 |
| C-2 | Remplacer `is_active` par `status` dans le modèle exemple | `docs/.../08_MULTITENANCY_STRATEGY.md` | Sprint 0 |
| C-3 | Aligner `user_lookups` PK = email | `docs/.../08_MULTITENANCY_STRATEGY.md` | Sprint 0 |
| C-4 | Corriger "Starter Gratuit" → "Starter 29€" | `docs/.../18_MARKETING_ET_VENTES.md` | Sprint 0 |
| C-5 | Corriger trait `HasCompanyScope` (bug double boot) | `docs/.../08_MULTITENANCY_STRATEGY.md` | Sprint 1 |
| C-6 | Déplacer `AUDIT_COMPLET_MANQUES.md` → `docs/notes/archive/` | Racine | Sprint 0 |
| C-7 | Supprimer `bon-fixed/` | Racine | Sprint 0 |

---

## DÉCISIONS FIGÉES (ne pas remettre en question)

| Sujet | Décision | Raison |
|-------|----------|--------|
| FK hiérarchie | `manager_id` dans employees | Unifié SQL/ERD |
| Statut employé | `status VARCHAR` pas `is_active` | 3 états nécessaires |
| FCM tokens | Table `employee_devices` (pas JSONB) | Scalable |
| Horodatage | Serveur uniquement (`now()`) | Anti-fraude |
| Audit logs | Observer Eloquent | Automatique |
| salary_base vs gross | Deux champs différents, deux tables | Clarté |

---

## COMMENT DÉMARRER UNE SESSION IA

```
ÉTAPE 1 → Lire PILOTAGE.md (ce fichier) — 5 min
ÉTAPE 2 → Vérifier quel sprint/ticket est "À faire"
ÉTAPE 3 → Lire le prompt MVP-XX correspondant
ÉTAPE 4 → Vérifier la porte verte du prompt précédent (php artisan test)
ÉTAPE 5 → Exécuter le prompt
ÉTAPE 6 → Mettre à jour le statut dans ce fichier (⬜ → ✅)
ÉTAPE 7 → Commit : "feat(scope): description"
```

---

## COMMENT FINIR UNE SESSION IA

```
ÉTAPE 1 → php artisan test → 0 failure (OBLIGATOIRE)
ÉTAPE 2 → Mettre à jour le tableau sprint dans ce fichier
ÉTAPE 3 → Ajouter une ligne dans CHANGELOG.md
ÉTAPE 4 → Commit : "chore: update PILOTAGE.md after MVP-XX"
```

---

## VISION LONG TERME (ce qu'on construira APRÈS le MVP)

La conception complète existe dans `docs/dossierdeConception/` (40+ documents).
L'ordre d'ajout des features sera guidé par les retours clients, pas par les specs :

```
Phase 2 (après 5 clients)     → Absences + Congés + Paie algérienne basique
Phase 3 (après 10 clients)    → Multi-langue + Maroc + Import CSV
Phase 4 (après 20 clients)    → Tâches + Évaluations + Vue.js dashboard
Phase 5 (après 50 clients)    → Mode schema Enterprise + ZKTeco + SSE
Phase 6 (après 100 clients)   → API publique + Export bancaire + 7 pays
```

> Les 40+ documents de conception sont le PLAN de l'immeuble complet.
> On construit étage par étage. Chaque phase utilise les specs déjà écrites.

