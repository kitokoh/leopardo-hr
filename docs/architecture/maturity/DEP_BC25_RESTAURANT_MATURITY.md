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
| POS — restant (4xx) | RESTO-411 (stock à la vente), RESTO-412 (événement pos.closed) | ⏳ À livrer |
| Stock, achats & inventaire (5xx) | RESTO-501..506 | ⏳ À livrer |
| Réservations, livraison & fidélité (6xx) | RESTO-601..608 | ⏳ À livrer |
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
> **Issue :** DEP-BC25 (#6276) — Deep maturity du contexte RestaurantManager (fondations RESTO-104).
> **Contexte :** BC-25 — RestaurantManager (verticale restauration : établissements, tables, catalogue, POS/caisse, commandes, réservations, stock/COGS, achats, livraison, fidélité, rapports).
> **Date :** 2026-08-30 — **Statut :** **En cours de livraison** — fondations (1xx), schéma (2xx), référentiel (3xx), POS/paiements (4xx), livraison/fidélité/promotions (6xx) et rapports (7xx) livrés sur les branches BC-25 ; maturité finale à confirmer sur `main`.
## 1. Progression des lots
| Fondations & gouvernance (1xx) | RESTO-101..108 | ✅ Livré (PR #6254 / consolidation #6306) |
| Schéma & domaine (2xx) | RESTO-201..216 | ✅ Livré (consolidation #6306) |
| API référentiel (3xx) | RESTO-301..306 | ✅ Livré (PR #6274) |
| POS, commandes & paiements (4xx) | RESTO-401..410 | ✅ Livré (PR #6274 — lot 2026-08-30) |
| POS — restant (4xx) | RESTO-411 (stock à la vente), RESTO-412 (pos.closed.v1) | ✅ Livré (branche `feat/bc25-resto-411-412`, PR à confirmer) |
| Stock, achats & inventaire (5xx) | RESTO-501..506 | 🏃 Lot A orchestré (PR #6311) |
| Réservations (6xx) | RESTO-601..604 | 🏃 Lot A orchestré (PR #6311) |
| Livraison, fidélité & promotions (6xx) | RESTO-605..608 | ✅ Livré (lot B orchestré, PR #6313) |
| Rapports & exports (7xx) | RESTO-701..703 | ✅ Livré (lot B orchestré, PR #6313) |
| UI admin (7xx) | RESTO-704..708 | 🏃 Lot C orchestré (PR #6328) + écran cuisine (RESTO-707, PR #6336) |
| Qualité, docs & pilote (9xx) | RESTO-901..906 | 🏃 Runbook/gates/audit/maturité livrés (PR #6333) ; golden journey + E2E + recette signée à venir (dépendent de la série POS sur main) |
## 2. Scorecard des 12 dimensions (état branches BC-25, 2026-08-30)
| # | Dimension | Statut | Constat / preuve |
|---|---|---|---|
| 1 | Domaine | ✅ | Enums + Value Objects (RESTO-214), machines à états commandes/livraison, invariants testés (BillCalculator serveur, transitions 409) |
| 2 | Données | ✅ | 35 tables tenant réentrantes (RESTO-201..216) + migrations d'idempotence ; index tenant-first ; parité `CreatesMvpSchema` |
| 3 | Tenant | ✅ | `company_id` partout, `BelongsToCompany` fail-closed, 404 cross-tenant, tests d'isolation |
| 4 | API | ✅ | Référentiel + POS/paiements + livraison/fidélité/promotions + rapports ; Requests strictes ; OpenAPI couvert |
| 5 | Autorisation | ✅ | Matrice `restaurant.*` (RESTO-306) + Policies par ressource ; rapports restreints `restaurant.reports` |
| 6 | Transactions | ✅ | Création/paiement idempotents, verrou optimiste `version` (caisse, commandes), re-vérification sous transaction, stock `SELECT FOR UPDATE` |
| 7 | Asynchronisme | ✅ (partiel) | Outbox `restaurant_outbox_events` + `restaurant:outbox-dispatch` (claim atomique, backoff, dead-letter) ; événements versionnés |
| 8 | Sécurité | ✅ | Callback HMAC idempotent, secrets en config, payloads outbox redigés, audit RESTO-904 sans finding bloquant |
| 9 | Frontend | 🏃 | UI admin web en cours (lot C : référentiel/réservations/stock/livraison/rapports + écran cuisine RESTO-707) |
| 10 | Performance | ⏳ | Gate `performance` pending (benchmarks POS/réservations) |
| 11 | Exploitation | ✅ (partiel) | Runbook pilote + recette UAT (RESTO-903) ; alerting dead-letter à brancher (recommandation R2 de l'audit) |
| 12 | Produit | ✅ (partiel) | GJ-RESTO-01 (RESTO-901) à confirmer quand la série POS 4xx sera sur `main` |
Le pilote `restaurantmanager` est enregistré dans `dev-hub/tools/pilot-gates.json` (9 gates).
**Aucun GO prématuré possible** (garde CI) : 3 gates `validated` (manifest, api_security, runbook,
security_review), 5 gates `pending` (core_flow, performance, observability, golden_journey, recette).
## 4. Prochaine action
1. Merger sur `main` la chaîne BC-25 : #6306 (fondations/schéma), #6311 (lot A stock/réservations),
   #6313 (lot B livraison/fidélité/rapports), #6328 (lot C UI), #6333 (qualité/pilote), #6336 (cuisine),
   + la série POS 4xx (via #6274 ou sa re-création orchestrée).
2. Livrer mobile/extensions (RESTO-801..808).
3. Valider les gates `performance`/`observability`/`recette` + recette UAT signée avant GO pilote.
=======
||||||| merged common ancestors
<<<<<<<<< Temporary merge branch 1
||||||||| 48418fe39
=========
||||||||| merged common ancestors
<<<<<<<<<<< Temporary merge branch 1
||||||||||| 455618aef
===========
=======
>>>>>>> origin/pm/merge-all-open-branches
# DEP-BC25 — Rapport de maturité BC-25 RESTAURANT

> **Issue :** DEP-BC25 (#6276) — Deep maturity du contexte RestaurantManager (fondations RESTO-104).
> **Contexte :** BC-25 — RestaurantManager (verticale restauration : établissements, tables, catalogue, POS/caisse, commandes, réservations, stock/COGS, achats, livraison, fidélité, rapports).
> **Date :** 2026-08-30 — **Statut :** **En cours de livraison** — fondations (1xx), schéma (2xx), référentiel (3xx), POS/paiements (4xx), livraison/fidélité/promotions (6xx) et rapports (7xx) livrés sur les branches BC-25 ; maturité finale à confirmer sur `main`.
> **Spécification :** `docs/specifications/SOLUTION_RESTAURANT_MANAGER.md`

## 1. Progression des lots

| Lot | Issues | État |
|---|---|---|
| Fondations & gouvernance (1xx) | RESTO-101..108 | ✅ Livré (PR #6254 / consolidation #6306) |
| Schéma & domaine (2xx) | RESTO-201..216 | ✅ Livré (consolidation #6306) |
| API référentiel (3xx) | RESTO-301..306 | ✅ Livré (PR #6274) |
| POS, commandes & paiements (4xx) | RESTO-401..410 | ✅ Livré (PR #6274 — lot 2026-08-30) |
| POS — restant (4xx) | RESTO-411 (stock à la vente), RESTO-412 (pos.closed.v1) | ✅ Livré (branche `feat/bc25-resto-411-412`, PR à confirmer) |
| Stock, achats & inventaire (5xx) | RESTO-501..506 | 🏃 Lot A orchestré (PR #6311) |
| Réservations (6xx) | RESTO-601..604 | 🏃 Lot A orchestré (PR #6311) |
| Livraison, fidélité & promotions (6xx) | RESTO-605..608 | ✅ Livré (lot B orchestré, PR #6313) |
| Rapports & exports (7xx) | RESTO-701..703 | ✅ Livré (lot B orchestré, PR #6313) |
| UI admin (7xx) | RESTO-704..708 | 🏃 Lot C orchestré (PR #6328) + écran cuisine (RESTO-707, PR #6336) |
| Mobile & extensions (8xx) | RESTO-801..808 | ⏳ À livrer |
| Qualité, docs & pilote (9xx) | RESTO-901..906 | 🏃 Runbook/gates/audit/maturité livrés (PR #6333) ; golden journey + E2E + recette signée à venir (dépendent de la série POS sur main) |

## 2. Scorecard des 12 dimensions (état branches BC-25, 2026-08-30)

| # | Dimension | Statut | Constat / preuve |
|---|---|---|---|
| 1 | Domaine | ✅ | Enums + Value Objects (RESTO-214), machines à états commandes/livraison, invariants testés (BillCalculator serveur, transitions 409) |
| 2 | Données | ✅ | 35 tables tenant réentrantes (RESTO-201..216) + migrations d'idempotence ; index tenant-first ; parité `CreatesMvpSchema` |
| 3 | Tenant | ✅ | `company_id` partout, `BelongsToCompany` fail-closed, 404 cross-tenant, tests d'isolation |
| 4 | API | ✅ | Référentiel + POS/paiements + livraison/fidélité/promotions + rapports ; Requests strictes ; OpenAPI couvert |
| 5 | Autorisation | ✅ | Matrice `restaurant.*` (RESTO-306) + Policies par ressource ; rapports restreints `restaurant.reports` |
| 6 | Transactions | ✅ | Création/paiement idempotents, verrou optimiste `version` (caisse, commandes), re-vérification sous transaction, stock `SELECT FOR UPDATE` |
| 7 | Asynchronisme | ✅ (partiel) | Outbox `restaurant_outbox_events` + `restaurant:outbox-dispatch` (claim atomique, backoff, dead-letter) ; événements versionnés |
| 8 | Sécurité | ✅ | Callback HMAC idempotent, secrets en config, payloads outbox redigés, audit RESTO-904 sans finding bloquant |
| 9 | Frontend | 🏃 | UI admin web en cours (lot C : référentiel/réservations/stock/livraison/rapports + écran cuisine RESTO-707) |
| 10 | Performance | ⏳ | Gate `performance` pending (benchmarks POS/réservations) |
| 11 | Exploitation | ✅ (partiel) | Runbook pilote + recette UAT (RESTO-903) ; alerting dead-letter à brancher (recommandation R2 de l'audit) |
| 12 | Produit | ✅ (partiel) | GJ-RESTO-01 (RESTO-901) à confirmer quand la série POS 4xx sera sur `main` |

## 3. Gates pilote (MAT-018 #5876)

Le pilote `restaurantmanager` est enregistré dans `dev-hub/tools/pilot-gates.json` (9 gates).
**Aucun GO prématuré possible** (garde CI) : 3 gates `validated` (manifest, api_security, runbook,
security_review), 5 gates `pending` (core_flow, performance, observability, golden_journey, recette).

## 4. Prochaine action

1. Merger sur `main` la chaîne BC-25 : #6306 (fondations/schéma), #6311 (lot A stock/réservations),
   #6313 (lot B livraison/fidélité/rapports), #6328 (lot C UI), #6333 (qualité/pilote), #6336 (cuisine),
   + la série POS 4xx (via #6274 ou sa re-création orchestrée).
2. Livrer mobile/extensions (RESTO-801..808).
3. Valider les gates `performance`/`observability`/`recette` + recette UAT signée avant GO pilote.
<<<<<<< HEAD
>>>>>>> origin/bc/bc25-restaurant-qualite-pilote
||||||| merged common ancestors
<<<<<<<<< Temporary merge branch 1
>>>>>>>>> Temporary merge branch 2
||||||||| merged common ancestors
>>>>>>>>>>> Temporary merge branch 2
=========
>>>>>>>>> Temporary merge branch 2
=======
>>>>>>> origin/pm/merge-all-open-branches
