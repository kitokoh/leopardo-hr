# DEP-BC25 — Rapport de maturité BC-25 RESTAURANT

> **Issue :** RESTO-104 (à créer)
> **Contexte :** BC-25 — RestaurantManager (verticale restauration : établissements, tables, catalogue, POS/caisse, commandes, réservations, stock/COGS, achats, livraison, fidélité, rapports)
> **Date :** 2026-08-29
> **Statut :** **Planifié** — la solution n'est pas encore sur `main` ; maturité à mesurer à l'arrivée du code (issues RESTO-001..030).
> **Spécification :** `docs/specifications/SOLUTION_RESTAURANT_MANAGER.md`

## 1. Cartographie (état `main`)

| Élément | État |
|---|---|
| `api/app/Modules/RestaurantManager` | Absent de `main` (à créer — registre BC, statut `planned`) |
| Migrations `*restaurant*` | Absentes de `main` (à livrer, RESTO-201..216) |
| Routes `/api/v1/restaurant/*` | Absentes (à livrer, RESTO-301..412) |
| Feature flag `restaurantmanager` + middleware | À livrer (RESTO-102) |
| Registre BC | `BC-25` = `planned`, owner @kitokoh, dépendances BC-02/03/04/11/13/20 |

## 2. Scorecard des 12 dimensions

| Dimension | Statut | Constat |
|---|---|---|
| Toutes (1-12) | ⏳ Planifié | Chaque dimension sera évaluée à l'arrivée du code sur `main` — le DoD commun (12 dimensions, §9 de la spec) est le critère de sortie du pilote |

## 3. Gates pilote (MAT-018 #5876)

Le go/no-go du pilote RestaurantManager sera verrouillé par le registre `pilot-gates.json`
(9 gates : manifest, core flow, API/Policies, runbook, sécurité, performance, observabilité,
golden journey GJ-RESTO-01, recette signée). **Aucun GO prématuré possible** (garde CI).

## 4. Dépendances

- RESTO-001..004 : fondations (spec, squelette, registre BC, activation).
- RESTO-010..015 : schéma & domaine (référentiel, POS, stock, réservations, livraison, fidélité).
- RESTO-020..025 : API référentiel, POS/paiements, stock/achats, réservations/livraison/fidélité.
- RESTO-026..030 : rapports, UI, mobile, qualité, pilote.
- Contrats : BC-02 (TENANT), BC-03 (IDENTITY), BC-04 (HR), BC-11 (CRM), BC-13 (COMMS), BC-20 (DOCUMENTS), BC-08 (ACCOUNTING).
- Patterns repris de la verticale sœur BC-24 TRAVEL (feature flag, manifest, outbox, paiements, seeds).

## 5. Prochaine action

Valider la spécification `SOLUTION_RESTAURANT_MANAGER.md` (propriétaire), puis implémenter
RESTO-001 → 004 (fondations), RESTO-010..015 (schéma), puis exécuter la scorecard 12 dimensions
à l'arrivée du code sur `main`.
