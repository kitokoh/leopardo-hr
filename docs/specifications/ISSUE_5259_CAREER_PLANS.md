# Spécification — Plans de carrière HR (Issue #5259)

**Issue** : [#5259 [P1][HR] Organisation 100 % — départements, positions, évaluations, plans de carrière](https://github.com/kitokoh/leopardo-hr/issues/5259)
**Date** : 2026-08-22 · **Module** : HR (`api/app/Modules/HR/**`) · **Branche** : `mod/hr/5259-career-plans`
**Constitution** : Spec-Driven Development — `.specify/constitution.md` §III (méthodes dédiées, pas de calcul inline).

## 1. État des lieux sur `main` (audit 2026-08-22)

| Périmètre de #5259 | État | Preuve |
|---|---|---|
| Départements CRUD + organigramme | ✅ Fait | `DepartmentController` + `GET /departments/{id}/hierarchy` (Closes #2633) |
| Positions CRUD | ✅ Fait | `PositionController` + routes `/positions` |
| Évaluations (cycles, workflow) | ✅ Fait | `EvaluationController` : `draft → submitted → acknowledged` + RBAC + politique |
| **Plans de carrière** (promotions, augmentations, transferts → impact paie) | ❌ **Absent** | aucune table `career_*`, aucun endpoint |

**Conclusion** : le trou de 100 % restant est le **suivi de carrière** (événements : promotion, augmentation, transfert, changement de poste), avec **impact paie** (salaire de base mis à jour au moment de l'application).

## 2. Besoin métier

- Un manager enregistre un **événement de carrière** pour un employé : promotion, augmentation, transfert de département, changement de poste.
- L'événement est **tracé** (de → vers : poste, département, salaire), avec date d'effet et motif.
- **Workflow** : `pending → approved → applied` (ou `rejected`). Seul le passage à `applied` modifie les données de l'employé (poste, département, `salary_base`).
- L'employé voit **son propre parcours** (événements + contrats) dans le self-service `/me/career`.
- **Impact paie** : au moment de `applied`, `employees.salary_base` est mis à jour → le prochain run de paie consomme le nouveau brut sans intervention manuelle (DoD #5246/#5266 : « un run de paie intègre les HS/salaire sans intervention manuelle »).

## 3. Modèle de données — `career_events` (schéma tenant)

| Colonne | Type | Règles |
|---|---|---|
| `id` | increments | PK |
| `company_id` | uuid nullable | isolation tenant (NULL en mode schema) |
| `employee_id` | unsignedInteger FK → employees | cascade delete |
| `type` | enum | `promotion`, `raise`, `transfer`, `title_change` |
| `status` | enum default `pending` | `pending`, `approved`, `rejected`, `applied` |
| `from_position_id` / `to_position_id` | FK → positions | nullable, nullOnDelete |
| `from_department_id` / `to_department_id` | FK → departments | nullable, nullOnDelete |
| `from_salary` / `to_salary` | decimal(12,2) | nullable ; snapshot au store, cible au store |
| `effective_date` | date | requis |
| `reason` | string(500) | requis |
| `notes` | text | nullable |
| `approved_by` | FK → employees | nullable, nullOnDelete |
| `approved_at` / `applied_at` | timestampTz | nullable |

Index : `(employee_id, status)`, `(company_id, status, effective_date)`.
Snapshot `from_*` = état courant de l'employé **au moment de la création** (traçabilité).

## 4. API (routes `routes/modules/rh.php`, groupe RH existant)

| Méthode | Route | Acteur | Comportement |
|---|---|---|---|
| GET | `/career-events` | manager : tous (scopé tenant + filtres) ; employé : les siens | filtres `employee_id`, `type`, `status`, pagination |
| POST | `/career-events` | manager (`api.manager`) | crée `pending`, snapshot `from_*` |
| GET | `/career-events/{id}` | owner/manager | lecture |
| PUT/PATCH | `/career-events/{id}` | manager | édition tant que `pending` |
| PUT | `/career-events/{id}/approve` | manager | `pending → approved` (`approved_by`, `approved_at`) |
| PUT | `/career-events/{id}/reject` | manager | `pending → rejected` (motif en option) |
| PUT | `/career-events/{id}/apply` | manager | `approved → applied` : transaction — mise à jour `employees` (position_id, department_id, salary_base) + `applied_at` |
| DELETE | `/career-events/{id}` | manager | suppression tant que `pending` |

RBAC : même logique que `EvaluationPolicy` (PA2-SEC-002/003) — `manager_role=dept` limité à son département, `superviseur` à son équipe, `principal`/`admin` au tenant.

## 5. Impact paie

`apply` met à jour **`employees.salary_base`** (et `position_id`/`department_id` quand fournis) dans une transaction.
Le moteur paie lit `salary_base` comme brut de référence (`PaySlipResource`, `AttendanceMonthlyReportService` §diviseur 173,33) :
aucun changement moteur nécessaire ; le run suivant consomme le nouveau brut.
`to_salary` est stocké en `decimal(12,2)` ; la conversion float se fait à la lecture (convention repo : `(float)`).

## 6. i18n ×4

Clés `career_event_*` ajoutées dans `lang/{fr,en,ar,tr}/employees.php` (messages d'erreur/workflow, convention `__('employees.…')`).

## 7. Tests (Feature HR)

- CRUD : création manager (snapshot), liste filtrée, lecture, édition, suppression
- RBAC : employé → 403 sur create ; employé ne voit que ses événements ; manager `dept` scopé
- Isolation tenant : événement d'une autre compagnie → 404
- Workflow : `pending → approved → apply` met à jour salaire/poste ; `reject` ; transitions invalides → 422
- Validation : champs manquants → 422 ; cible poste/département cross-tenant refusée
- Self-service : `/me/career` inclut les événements

## 8. DoD

- [x] Un employé suit son parcours de bout en bout (contrats + événements de carrière) via `/me/career`
- [x] Une promotion/augmentation appliquée met à jour le salaire de base consommé par la paie
- [x] RBAC + isolation tenant testés (Pattern Evaluation)
- [x] i18n ×4, CHANGELOG, spec
