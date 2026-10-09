# DEP-BC25 — Rapport de maturité BC-25 RESTAURANT

> **Issue :** DEP-BC25 (#6276) — Deep maturity du contexte RestaurantManager (fondations RESTO-104).
> **Contexte :** BC-25 — RestaurantManager (verticale restauration : établissements, tables, catalogue, POS/caisse, commandes, réservations, stock/COGS, achats, livraison, fidélité, rapports).
> **Date de consolidation :** 2026-10-09 — **Statut :** **Pilote en préparation** — les lots 1xx→7xx sont livrés et présents sur `main` ; le mobile (8xx) et la clôture qualité/pilote (9xx) restent à livrer.
> **Spécification :** `docs/specifications/SOLUTION_RESTAURANT_MANAGER.md`
>
> **Note de consolidation (2026-10-09).** Ce document contenait auparavant **trois états
> successifs contradictoires** (rapports du 2026-08-29, du 2026-08-30 et un troisième
> dupliqué, avec des marqueurs de branche parasites `origin/pm/merge-all-open-branches`
> et `origin/bc/bc25-restaurant-qualite-pilote`). Les tableaux ci-dessous sont l'**état
> unique de référence**, vérifié sur `main` au 2026-10-09. Les états antérieurs sont
> historiques et ne doivent plus être cités.

## 1. Cartographie (état `main`, 2026-10-09)

| Élément | État |
|---|---|
| `api/app/Modules/RestaurantManager` | ✅ Livré (squelette DDD, fondations RESTO-101..108) |
| Migrations `*restaurant*` | ✅ Livrées (35 tables tenant, RESTO-201..216) |
| Routes `/api/v1/restaurant/*` | ✅ Livrées : référentiel (3xx) + POS/commandes/paiements (4xx) + stock/achats (5xx) + réservations/livraison/fidélité (6xx) + rapports (7xx) — ~150 endpoints |
| Feature flag `restaurantmanager` + middleware | ✅ Livré (RESTO-102) |
| Manifest au `SolutionCatalogue` | ✅ Livré (BOS-014 #8201) — codes `restaurant` (descripteur) et `restaurantmanager` (opérationnel) |
| Registre BC | `BC-25` = `active`, owner @kitokoh, dépendances BC-02/03/04/11/13/20 |

## 2. Progression des lots (état unique)

| Lot | Issues | État |
|---|---|---|
| Fondations & gouvernance (1xx) | RESTO-101..108 | ✅ Livré (PR #6254 / consolidation #6306) |
| Schéma & domaine (2xx) | RESTO-201..216 | ✅ Livré (consolidation #6306) |
| API référentiel (3xx) | RESTO-301..306 | ✅ Livré (PR #6274) |
| POS, commandes & paiements (4xx) | RESTO-401..410 | ✅ Livré (PR #6274 — lot 2026-08-30) |
| POS — restant (4xx) | RESTO-411 (stock à la vente), RESTO-412 (`pos.closed.v1`) | ✅ Livré |
| Stock, achats & inventaire (5xx) | RESTO-501..506 | ✅ Livré (PR #6311) |
| Réservations (6xx) | RESTO-601..604 | ✅ Livré (PR #6311) |
| Livraison, fidélité & promotions (6xx) | RESTO-605..608 | ✅ Livré (PR #6313) |
| Rapports & exports (7xx) | RESTO-701..703 | ✅ Livré (PR #6313) |
| UI admin (7xx) | RESTO-704..708 | ✅ Livré (PR #6328) + écran cuisine (RESTO-707, PR #6336) |
| Onboarding vertical (nouveau) | RESTO-ONBOARDING | ✅ Livré (branche `feat/restaurant-onboarding-and-ux`) — checklist métier restaurant + remontée des échecs d'activation + UX front |
| Mobile & extensions (8xx) | RESTO-801..808 | ⏳ **À livrer** — contrats API livrés et testés, aucune app Flutter |
| Qualité, docs & pilote (9xx) | RESTO-901..906 | 🚧 Runbook/gates/audit/maturité livrés (PR #6333) ; golden journey E2E + recette signée à venir |

## 3. Scorecard des 12 dimensions (état `main`, 2026-10-09)

| # | Dimension | Statut | Constat / preuve |
|---|---|---|---|
| 1 | Domaine | ✅ | Enums + Value Objects (RESTO-214), machines à états commandes/livraison, invariants testés (BillCalculator serveur, transitions 409) |
| 2 | Données | ✅ | 35 tables tenant réentrantes (RESTO-201..216) + migrations d'idempotence ; index tenant-first ; parité `CreatesMvpSchema` |
| 3 | Tenant | ✅ | `company_id` partout, `BelongsToCompany` fail-closed, 404 cross-tenant, tests d'isolation |
| 4 | API | ✅ | Référentiel + POS/paiements + stock/achats + réservations/livraison/fidélité + rapports ; Requests strictes ; OpenAPI couvert |
| 5 | Autorisation | ✅ | Matrice `restaurant.*` (RESTO-306) + Policies par ressource ; rapports restreints `restaurant.reports` |
| 6 | Transactions | ✅ | Création/paiement idempotents, verrou optimiste `version` (caisse, commandes), re-vérification sous transaction, stock `SELECT FOR UPDATE` |
| 7 | Asynchronisme | 🟡 Partiel | Outbox `restaurant_outbox_events` + `restaurant:outbox-dispatch` (claim atomique, backoff, dead-letter) ; événements versionnés. **Manque :** alerting dead-letter (recommandation R2 de l'audit) |
| 8 | Sécurité | ✅ | Callback HMAC idempotent, secrets en config, payloads outbox redigés, audit RESTO-904 sans finding bloquant |
| 9 | Frontend | 🟡 Partiel | UI admin web livrée (référentiel/réservations/stock/livraison/rapports + écran cuisine + POS + online + team + payments). **Manque :** apps mobiles serveur/livreur/gérant (8xx) |
| 10 | Performance | ⏳ | Gate `performance` **pending** (benchmarks POS/réservations non exécutés) |
| 11 | Exploitation | 🟡 Partiel | Runbook pilote + recette UAT (RESTO-903) ; **manque :** alerting dead-letter branché |
| 12 | Produit | 🟡 Partiel | GJ-RESTO-01 (RESTO-901) couvert par `RestaurantGoldenJourneyTest` en CI ; **manque :** recette UAT signée par le restaurateur pilote |

## 4. Gates pilote (MAT-018 #5876)

Le pilote `restaurantmanager` est enregistré dans `dev-hub/tools/pilot-gates.json` (9 gates).
**Aucun GO prématuré possible** (garde CI).

| État | Gates |
|---|---|
| ✅ `validated` (4) | manifest, api_security, runbook, security_review |
| ⏳ `pending` (5) | core_flow, performance, observability, golden_journey, recette |

`go_decision: pending` — le GO pilote exige la validation des 5 gates restants.

## 5. Dette identifiée (2026-10-09)

| # | Point | Type | Référence |
|---|---|---|---|
| 1 | Aucune app mobile restaurant (serveur/livreur/gérant) | Mobile | RESTO-801..808 — `front/mobile_apps/` |
| 2 | Modération des avis absente (endpoints `/restaurant/reviews*` inexistants) | Backend + Front | lot RESTO-902 |
| 3 | Recette UAT R2–R10 jamais exécutée, signature pilote vide | Qualité | `docs/ops/RECETTE_UAT_RESTAURANTMANAGER.md` |
| 4 | Gates performance / observabilité / recette en attente | Pilote | `dev-hub/tools/pilot-gates.json` |
| 5 | Pas d'API publique de profil établissement par slug | Backend | `docs/architecture/VERTICAL_RESTAURANT_AUDIT_2026-09-08.md` §1.3 |
| 6 | Couverture de tests très faible sur les verticales sœurs (TravelAgency 2 tests, RestaurantManager 1 test) | Tests | `api/tests/Feature/` |

## 6. Prochaine action

1. Livrer le mobile/extensions (RESTO-801..808) — les contrats API sont prêts et testés.
2. Livrer le lot RESTO-902 (modération des avis) et activer la section front correspondante.
3. Exécuter la recette UAT R2–R10 et faire signer le restaurateur pilote.
4. Valider les gates `performance` / `observability` / `recette` avant GO pilote.
