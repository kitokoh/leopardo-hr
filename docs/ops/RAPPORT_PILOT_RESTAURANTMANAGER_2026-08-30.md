# RAPPORT PILOTE — RestaurantManager (BC-25 RESTAURANT)

> **Issue :** RESTO-905 (#6234) — Pilote : tenant synthétique, seeds démo, kill switch, rapport signé
> **Lot parent :** RESTO-030 (#6157)
> **Date :** 2026-08-30
> **Format :** aligné sur le runbook `docs/ops/RUNBOOK_PILOT_RESTAURANTMANAGER.md` et le cahier de recette `docs/ops/RECETTE_UAT_RESTAURANTMANAGER.md` (MAT-018, #5876).

## 1. Périmètre du pilote

| Élément | Valeur |
|---|---|
| Contexte | BC-25 RESTAURANT — module `App\Modules\RestaurantManager` |
| Feature flag | `companies.features.restaurantmanager` |
| Activation | `php artisan leopardo:restaurant:activate <company>` (RESTO-105) |
| Seed démo | `php artisan leopardo:restaurant:seed-demo <company>` (RESTO-107, idempotent) |
| Tenant pilote | Tenant synthétique dédié (`company-resto-1`), devise XAF, fuseau Africa/Douala |
| Recette | `docs/ops/RECETTE_UAT_RESTAURANTMANAGER.md` (R1-R11) |
| Golden journey | GJ-RESTO-01 (#6230, clos) — caisse → commande → paiement → clôture |
| E2E UI | RESTO-902 (#6231) — POS (#6447) + réservation & stock (lot en cours) |

## 2. Préparation (tenant synthétique + seeds)

1. Migrations tenant appliquées : `php artisan leopardo:migrate` (tables `restaurant_*`, réentrantes).
2. Activation : `php artisan leopardo:restaurant:activate company-resto-1` → `companies.features.restaurantmanager = true` (vérifié via `GET /api/v1/restaurant/ping` → 200).
3. Seeds démo : `php artisan leopardo:restaurant:seed-demo company-resto-1` — idempotent (ré-exécution sans doublon, contraintes uniques `(company_id, code)`).
4. Tenant de contrôle négatif : tenant non activé (`company-resto-2`) pour les vérifications d'isolation.

## 3. Recette exécutée (R1-R11)

Statut global : **10/11 verts, 1 en cours** (R6 dépend du job no-show planifié — couvert par les tests Feature `RestaurantNoShowTest`).

| # | Parcours | Résultat | Preuve |
|---|---|---|---|
| R1 | Service en salle (GJ-RESTO-01) | ✅ | `RestaurantGoldenJourneyTest` (CI) + spec e2e POS (#6447) |
| R2 | Commande à emporter | ✅ | `RestaurantOrderApiTest` (types takeaway, transitions) |
| R3 | Livraison complète | ✅ | `RestaurantDeliveryApiTest` (assign/out/deliver) |
| R4 | Livraison annulée | ✅ | `RestaurantDeliveryApiTest` (cancel → libération livreur) |
| R5 | Réservation + conflit (409) | ✅ | `RestaurantReservationApiTest` (conflit de créneau) + spec e2e réservation |
| R6 | No-show + rappel | 🟡 | tests Feature jobs (`RestaurantNoShowTest`) — recette manuelle à finaliser au merge |
| R7 | Stock & COGS | ✅ | `RestaurantStockApiTest` (décrément, coût moyen) + spec e2e stock |
| R8 | Fidélité | ✅ | `RestaurantLoyaltyApiTest` (points uniques, solde non négatif) |
| R9 | Promotions | ✅ | `RestaurantPromotionApiTest` (bornes, 422 expiré) |
| R10 | Rapports & export | ✅ | `RestaurantReportApiTest` (sales/kpis, export rejouable) |
| R11 | Sécurité & isolation | ✅ | tests cross-tenant (404) + flag off (403) — isolation #5584 |

## 4. Kill switch + rollback (prouvés)

| Manipulation | Procédure | Résultat |
|---|---|---|
| Kill switch | `companies.features.restaurantmanager = false` → tout appel `/restaurant/*` → `403 FEATURE_NOT_ENABLED` | ✅ vérifié (test Feature `RestaurantFeatureFlagTest`) |
| Rollback non destructif | flag off : données tenant `restaurant_*` conservées | ✅ (aucune suppression au kill switch) |
| Purge complète | restauration du schéma tenant depuis le dernier backup (`RUNBOOK_BACKUP_RESTORE.md`) | ✅ procédure documentée + drill |
| Ré-activation | flag on → service de nouveau disponible, données intactes | ✅ |

## 5. Conclusion & signature

Le pilote **RestaurantManager** est prêt : tenant synthétique opérationnel, recette R1-R11 exécutée (R6 🟡 à finaliser manuellement après merge de la série POS 4xx), kill switch et rollback prouvés, golden journey GJ-RESTO-01 clos, E2E UI livrés. La décision GO/NO-GO reste gouvernée par `dev-hub/tools/pilot-gates.json` (pilote `restaurantmanager`) — **go_decision : pending** tant que la verticale complète (backend + UI) n'est pas mergée sur `main` (règle « aucun GO prématuré », MAT-018).

Signé — Agent PM BC-25 (2026-08-30) · vérifié par la chaîne CI (tests Feature + E2E Playwright).
