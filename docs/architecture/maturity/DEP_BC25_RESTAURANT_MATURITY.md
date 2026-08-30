# DEP-BC25 — Rapport de maturité BC-25 RESTAURANT

> **Issue :** RESTO-104 (à créer)
> **Contexte :** BC-25 — RestaurantManager (verticale restauration : établissements, tables, catalogue, POS/caisse, commandes, réservations, stock/COGS, achats, livraison, fidélité, rapports)
> **Date :** 2026-08-29 (mise à jour 2026-08-30 — lot RESTO-401..410)
> **Statut :** **En cours de livraison** — fondations (1xx), schéma & domaine (2xx), référentiel (3xx) et POS/commandes/paiements (4xx) livrés sur la branche `bc/bc25-restaurant-*` ; maturité à mesurer à l'arrivée sur `main` (issues RESTO-001..030).
> **Spécification :** `docs/specifications/SOLUTION_RESTAURANT_MANAGER.md`

## 1. Cartographie (état branche BC-25, en cours de merge)

| Élément | État |
|---|---|
| `api/app/Modules/RestaurantManager` | Livré (squelette DDD, fondations RESTO-101..108) |
| Migrations `*restaurant*` | Livrées (35 tables tenant, RESTO-201..216) |
| Routes `/api/v1/restaurant/*` | Livrées : référentiel (RESTO-301..306) + POS/commandes/paiements (RESTO-401..410, 22 endpoints) |
| Feature flag `restaurantmanager` + middleware | Livré (RESTO-102) |
| Registre BC | `BC-25` = `active`, owner @kitokoh, dépendances BC-02/03/04/11/13/20 |

## 1bis. Progression des lots

| Lot | Issues | État |
|---|---|---|
| Fondations & gouvernance (1xx) | RESTO-101..108 | ✅ Livré (PR #6254) |
| Schéma & domaine (2xx) | RESTO-201..216 | ✅ Livré (branche `bc/bc25-restaurant-schema`) |
| API référentiel (3xx) | RESTO-301..306 | ✅ Livré (PR #6274, branche `bc/bc25-restaurant-referential`) |
| POS, commandes & paiements (4xx) | RESTO-401..410 | ✅ Livré (PR #6274 — lot du 2026-08-30, 10 issues, 53 tests) |
| POS — restant (4xx) | RESTO-411 (stock à la vente), RESTO-412 (événement pos.closed) | ✅ Livré (branche `bc/bc25-restaurant-stock`, lot du 2026-08-30) |
| Stock, achats & inventaire (5xx) | RESTO-501..506 | ✅ Livré (branche `bc/bc25-restaurant-stock`, lot du 2026-08-30) |
| Réservations, livraison & fidélité (6xx) | RESTO-601..602 livrés ; RESTO-603..608 à livrer | 🚧 En cours |
| Rapports & UI (7xx) | RESTO-701..708 | ⏳ À livrer |
| Mobile & extensions (8xx) | RESTO-801..808 | ⏳ À livrer |
| Qualité, docs & pilote (9xx) | RESTO-901..906 | ⏳ À livrer |

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
