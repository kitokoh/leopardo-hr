# RAPPORT PILOTE — RestaurantManager (BC-25 RESTAURANT) — RESTO-905 (#6234)

> **Statut : recette CI exécutée, kill switch prouvé, BC-25 actif.**
> Décision GO/NO-GO finale : **en attente du chef de projet** (gates
> `performance` et `observability` restent à valider — cf.
> `dev-hub/tools/pilot-gates.json`, pilote `restaurantmanager`).

## 1. Tenant pilote synthétique

- Tenant de recette créé via `Company::factory()` (pays CM, devise XAF) —
  repris par le test Feature `RestaurantGoldenJourneyTest` (GJ-RESTO-01) et
  les tests de la verticale (`RefreshTenantDatabase`).
- Activation : `php artisan leopardo:restaurant:activate {company}`
  (RESTO-105, idempotent — `ActivateRestaurantManagerCommand`).
- Seed démo : `php artisan leopardo:restaurant:seed-demo {company}`
  (RESTO-107, idempotent — `SeedRestaurantDemoCommand`).

## 2. Recette exécutée (preuves CI)

| Scénario | Preuve |
|---|---|
| R1 Service en salle complet (GJ-RESTO-01) | `RestaurantGoldenJourneyTest::test_golden_journey_gj_resto_01` — caisse → commande → article → cuisine → service → addition → paiement → clôture (écart 0) |
| R2..R11 (UAT) | Runbook/RECETTE_UAT_RESTAURANTMANAGER.md (RESTO-903) + tests Feature Restaurant* (POS, stock, réservations, livraison, fidélité, RBAC) |
| Mobile serveur/livreur/gérant (801..803) | `RestaurantMobileServerApiTest`, `RestaurantMobileRiderApiTest`, `RestaurantMobileManagerApiTest` |
| Offline idempotent (804) | `RestaurantMobileSyncTest` — rejeu sans doublon |
| Boutique publique (805) | `RestaurantPublicShopApiTest` — aucun accès inter-tenant |
| Apps de livraison (806) | `RestaurantDeliveryAppWebhookTest` — HMAC fail-closed, rejeu sans doublon |
| Kiosque (807) | `RestaurantKioskTest` |

## 3. Kill switch & rollback prouvés

1. **Kill switch** : le middleware `module.restaurantmanager` (RESTO-102)
   refuse tout appel verticale si `companies.features.restaurantmanager`
   est désactivé (`403 FEATURE_NOT_ENABLED`) — testé par
   `RestaurantFeatureFlagTest`.
2. **Rollback** : désactiver le flag = gel immédiat des surfaces de la
   verticale sans toucher aux données (les tables `restaurant_*` restent
   inertes ; aucune écriture sur les tables coeur). Les migrations tenant
   sont réentrantes (`leopardo:migrate`).
3. **Backup/restore** : runbook plateforme `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`.

## 4. BC-25 → actif

- Registre des bounded contexts : `dev-hub/governance/bounded-context-registry.json`
  — BC-25 RESTAURANT `status: active` (RESTO-103).
- CODEOWNERS : BC-25 couvert (RESTO-103).
- Golden journey GJ-RESTO-01 enregistré dans `dev-hub/tools/golden-journeys.json`
  (solution `restaurantmanager`, status `active`) — garde `check-golden-journeys.sh` ✅.
- Gates pilote mis à jour : `manifest`, `core_flow`, `api_security`,
  `runbook`, `security_review`, `golden_journey`, `recette` → **validated**
  (preuves ci-dessus) ; `performance`, `observability` → **pending**.

## 5. Rapport signé

| Champ | Valeur |
|---|---|
| Date | 2026-08-30 |
| Auteur | Agent PM (lot BC-25 mobile-public-qualite) |
| SHA de référence | à la merge de la PR `bc/bc25-restaurant-mobile-public` |
| Décision GO/NO-GO | ⬜ en attente du chef de projet (gates perf/observability) |

**Signature (chef de projet)** : ____________
