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
