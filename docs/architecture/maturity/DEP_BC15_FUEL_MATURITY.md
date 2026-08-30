# DEP-BC15 — Rapport de maturité BC-15 FUEL

> **Issue :** [DEP-BC15 #5891](https://github.com/kitokoh/leopardo-hr/issues/5891)
> **Contexte :** BC-15 — FuelStation (verticale pilote : stations, pompes, compteurs, sessions pompistes, ventes, dépôts, bilans)
> **Date :** 2026-08-28
> **Statut :** **En livraison** — FUEL-001..008 sur `main` (module FuelStation) ; lot FUEL-009/010/011/020 implémenté en PR sur `bc/bc15-fuel-operations` (2026-08-30), en attente de merge ; scorecard mise à jour au fil de l'eau.

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
| Dimension | Statut | Constat |
|---|---|---|
| D1 Domaine/métier | 🟢 Partiel | FUEL-009/010 : domain models + services (stocks, incidents) avec invariants testés |
| D3 Transactions | 🟢 Partiel | FK composites anti cross-tenant, transitions validées en application |
| D6 API/Policies | 🟢 Partiel | FUEL-011 : 20 routes référentiel + Policies deny-by-default, OpenAPI 0 drift |
| D7 Asynchronisme | 🟡 Ouvert | Rapprochement rejouable (upsert idempotent) ; outbox contrat Accounting (FUEL-015) non livré |
| D9 Sécurité | 🟢 Partiel | Threat model FUEL-020, idempotence rejeu, audit transitions, throttle dédié |
| D10 Performance | 🟡 Ouvert | Index tenant-first posés ; budgets p95 à établir après merge |
| D11 Observabilité | 🟢 Partiel | Logs corrélés sans PII (commande rapprochement) ; alertes à formaliser |
| Autres (D2, D4, D5, D8, D12) | ⏳ À évaluer | Au merge du lot + golden journey GJ-06 |

## 3. Gates pilote (MAT-018 #5876)

Le go/no-go du pilote FuelStation est verrouillé par le registre `pilot-gates.json` (9 gates : manifest, core flow, API/Policies, runbook, sécurité, performance, observabilité, golden journey GJ-06, recette signée FUEL-022). **Aucun GO prématuré possible** (garde CI).

## 4. Dépendances

- FUEL-001..008 : PRs en cours (9 PRs ouvertes sur le périmètre FUEL/EDU).
- FUEL-009..022 : issues libres, à implémenter après fusion des fondations.
- Contrats : BC-02 (TENANT), BC-03 (IDENTITY), BC-04 (HR), BC-05 (WORKFORCE).

## 5. Prochaine action

Quand FUEL-001..008 seront mergés : basculer `BC-15` en `status: active` dans le registre, livrer FUEL-009..014 (stocks, incidents, API, offline), puis exécuter la scorecard 12 dimensions + mettre à jour ce rapport.
