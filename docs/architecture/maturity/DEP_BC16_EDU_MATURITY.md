# DEP-BC16 — Rapport de maturité BC-16 EDU

> **Issue :** [DEP-BC16 #5892](https://github.com/kitokoh/leopardo-hr/issues/5892)
> **Contexte :** BC-16 — EduManager (verticale pilote : campus, élèves, guardians, classes, présences, notes, bulletins, admissions)
> **Date :** 2026-08-28
> **Statut :** **Planifié** — la solution n'est pas encore sur `main` ; maturité à mesurer à l'arrivée du code (issues EDU-001..022 en cours de livraison).

## 1. Cartographie (état `main`)

| Élément | État |
|---|---|
| `api/app/Solutions/EduManager` | Absent de `main` (à créer — registre BC, statut `planned`) |
| Migrations `*edu*` | Absentes (à livrer, EDU-002..004) |
| Routes `/api/v1/edu/*` | Absentes (à livrer, EDU-005..010) |
| Manifest + activation tenant | En cours (EDU-001, PRs #5903/#5916) |
| Registre BC | `BC-16` = `planned`, owner @kitokoh, allowed_dependencies BC-02/03/04 |

## 2. Scorecard des 12 dimensions

| Dimension | Statut | Constat |
|---|---|---|
| Toutes (1-12) | ⏳ Planifié | Évaluation à l'arrivée du code sur `main` — DoD commun (12 dimensions) = critère de sortie du pilote |

## 3. Gates pilote (MAT-018 #5876)

Go/no-go verrouillé par `pilot-gates.json` (9 gates : manifest, core flow, API/Policies/RBAC scolaire, runbook, sécurité, performance, observabilité, golden journey GJ-07, recette signée EDU-022). **Aucun GO prématuré possible** (garde CI).

## 4. Dépendances

- EDU-001..004 : PRs en cours (manifest, campus/élèves, années/classes, admissions).
- EDU-005..022 : issues libres, à implémenter après fusion des fondations.
- Contrats : BC-02 (TENANT), BC-03 (IDENTITY), BC-04 (HR).

## 5. Prochaine action

Quand EDU-001..004 seront mergés : basculer `BC-16` en `status: active`, livrer EDU-005..010 (présence, emplois du temps, évaluations, bulletins, API), puis exécuter la scorecard + mettre à jour ce rapport.
