# DEP-BC15 — Rapport de maturité BC-15 FUEL

> **Issue :** [DEP-BC15 #5891](https://github.com/kitokoh/leopardo-hr/issues/5891)
> **Contexte :** BC-15 — FuelStation (verticale pilote : stations, pompes, compteurs, sessions pompistes, ventes, dépôts, bilans)
> **Date :** 2026-08-28
> **Statut :** **Planifié** — la solution n'est pas encore sur `main` ; maturité à mesurer à l'arrivée du code (issues FUEL-001..022 en cours de livraison).

## 1. Cartographie (état `main`)

| Élément | État |
|---|---|
| `api/app/Solutions/FuelStation` | Absent de `main` (à créer — registre BC, statut `planned`) |
| Migrations `*fuel*` | Absentes de `main` (à livrer, FUEL-002/003/004) |
| Routes `/api/v1/fuel/*` | Absentes (à livrer, FUEL-005..011) |
| Manifest + activation tenant | En cours (FUEL-001, PRs #5841/#5844/#5851) |
| Registre BC | `BC-15` = `planned`, owner @kitokoh, allowed_dependencies BC-02/03/04/05 |

## 2. Scorecard des 12 dimensions

| Dimension | Statut | Constat |
|---|---|---|
| Toutes (1-12) | ⏳ Planifié | Chaque dimension sera évaluée à l'arrivée du code sur `main` — le DoD commun (12 dimensions) est le critère de sortie du pilote |

## 3. Gates pilote (MAT-018 #5876)

Le go/no-go du pilote FuelStation est verrouillé par le registre `pilot-gates.json` (9 gates : manifest, core flow, API/Policies, runbook, sécurité, performance, observabilité, golden journey GJ-06, recette signée FUEL-022). **Aucun GO prématuré possible** (garde CI).

## 4. Dépendances

- FUEL-001..008 : PRs en cours (9 PRs ouvertes sur le périmètre FUEL/EDU).
- FUEL-009..022 : issues libres, à implémenter après fusion des fondations.
- Contrats : BC-02 (TENANT), BC-03 (IDENTITY), BC-04 (HR), BC-05 (WORKFORCE).

## 5. Prochaine action

Quand FUEL-001..008 seront mergés : basculer `BC-15` en `status: active` dans le registre, livrer FUEL-009..014 (stocks, incidents, API, offline), puis exécuter la scorecard 12 dimensions + mettre à jour ce rapport.
