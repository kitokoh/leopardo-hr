# DEP-BC25 — Rapport de maturité BC-25 RESTAURANT

> **Issue :** RESTO-104 (à créer) — mise à jour DEP-BC25 (#6276)
> **Contexte :** BC-25 — RestaurantManager (verticale restauration : établissements, tables, catalogue, POS/caisse, commandes, réservations, stock/COGS, achats, livraison, fidélité, rapports)
> **Date :** 2026-08-29 (mise à jour 2026-08-30 — lots RESTO-401..410, 605..608, 701..703, 901..906)
> **Statut :** **En cours de livraison** — fondations (1xx), schéma & domaine (2xx), référentiel (3xx), POS/commandes/paiements (4xx), livraison/fidélité/promotions (6xx) et rapports (7xx) livrés sur les branches `bc/bc25-restaurant-*` ; scorecard 12 dimensions §2 ; maturité finale à confirmer à l'arrivée sur `main` (issues RESTO-001..030).
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
| POS — restant (4xx) | RESTO-411 (stock à la vente), RESTO-412 (événement pos.closed) | ⏳ À livrer (lot POS) |
| Stock, achats & inventaire (5xx) | RESTO-501..506 | 🏃 En cours (agent stock) |
| Réservations (6xx) | RESTO-601..604 | 🏃 En cours (agent réservations) |
| Livraison, fidélité & promotions (6xx) | RESTO-605..608 | ✅ Livré (PR #6329 — lot agent BC-25 du 2026-08-30) |
| Rapports & exports (7xx) | RESTO-701..703 | ✅ Livré (PR #6329 — lot agent BC-25 du 2026-08-30) |
| UI admin (7xx) | RESTO-704..708 | ⏳ À livrer |
| Mobile & extensions (8xx) | RESTO-801..808 | ⏳ À livrer |
| Qualité, docs & pilote (9xx) | RESTO-901..906 | 🏃 Golden journey, runbook, gates, audit livrés (PR lot qualité) ; E2E/recette signée à venir |

## 2. Scorecard des 12 dimensions (état branche BC-25, 2026-08-30)

| # | Dimension | Statut | Constat / preuve |
|---|---|---|---|
| 1 | Domaine | ✅ | Enums + Value Objects (RESTO-214), machine à états commandes/livraison, invariants testés (BillCalculator serveur, transitions 409) |
| 2 | Données | ✅ | 35 tables tenant réentrantes (RESTO-201..216) + 3 migrations idempotence lot 605-703 ; index tenant-first ; parité `CreatesMvpSchema` |
| 3 | Tenant | ✅ | `company_id` partout, `BelongsToCompany` fail-closed, 404 cross-tenant, tests d'isolation |
| 4 | API | ✅ | Référentiel + POS/paiements + livraison/fidélité/promotions + rapports ; 24 endpoints lot agent documentés OpenAPI (752 chemins) ; Requests strictes |
| 5 | Autorisation | ✅ | Matrice `restaurant.*` (RESTO-306) + Policies par ressource ; rapports restreints `restaurant.reports` |
| 6 | Transactions | ✅ | Création/paiement idempotents, verrou optimiste `version` (caisse, commandes), re-vérification sous transaction, stock `SELECT FOR UPDATE` (repo) |
| 7 | Asynchronisme | ✅ (partiel) | Outbox `restaurant_outbox_events` + `restaurant:outbox-dispatch` (claim atomique, backoff, dead-letter) ; événements versionnés publiés par les flux |
| 8 | Sécurité | ✅ | Callback HMAC idempotent, secrets en config, payloads outbox redigés, audit RESTO-904 sans finding bloquant |
| 9 | Frontend | ⏳ | UI admin (RESTO-704..707) et mobile (RESTO-801..808) à livrer |
| 10 | Performance | ⏳ | Gate `performance` pending (benchmarks POS/réservations) |
| 11 | Exploitation | ✅ (partiel) | Runbook pilote + recette UAT (RESTO-903) ; alerting dead-letter à brancher (recommandation R2 de l'audit) |
| 12 | Produit | ✅ (partiel) | GJ-RESTO-01 enregistré + test Feature (RESTO-901) ; E2E Playwright (RESTO-902) et pilote signé (RESTO-905) à venir |

## 3. Gates pilote (MAT-018 #5876)

Le pilote `restaurantmanager` est **enregistré** dans `dev-hub/tools/pilot-gates.json` (9 gates).
**Aucun GO prématuré possible** (garde CI) : 5 gates `validated` (manifest, core_flow, api_security,
runbook, security_review, golden_journey), 3 gates `pending` (performance, observability, recette signée).

## 4. Dépendances

- RESTO-001..004 : fondations (spec, squelette, registre BC, activation).
- RESTO-010..015 : schéma & domaine (référentiel, POS, stock, réservations, livraison, fidélité).
- RESTO-020..025 : API référentiel, POS/paiements, stock/achats, réservations/livraison/fidélité.
- RESTO-026..030 : rapports, UI, mobile, qualité, pilote.
- Contrats : BC-02 (TENANT), BC-03 (IDENTITY), BC-04 (HR), BC-11 (CRM), BC-13 (COMMS), BC-20 (DOCUMENTS), BC-08 (ACCOUNTING).
- Patterns repris de la verticale sœur BC-24 TRAVEL (feature flag, manifest, outbox, paiements, seeds).

## 5. Prochaine action

1. Merger la chaîne BC-25 sur `main` (PR #6306 schema → main, PR #6274 référentiel → schema,
   PR #6329 lot livraison/fidélité/rapports → référentiel, PR lot qualité → référentiel) — fermeture
   des issues via les `Closes #` des PRs.
2. Finaliser les lots en cours des autres agents (stock 5xx, réservations 601-604, POS 411/412).
3. Livrer UI admin (RESTO-704..707) puis mobile/extensions (RESTO-801..808).
4. Exécuter la scorecard 12 dimensions sur `main` + valider les gates `performance`/`observability`/
   `recette` avant GO pilote.
