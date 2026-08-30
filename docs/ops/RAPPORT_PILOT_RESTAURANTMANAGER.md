# RAPPORT DE PILOTE — RestaurantManager (BC-25 RESTAURANT)

> **Issue :** RESTO-905 (#6234) — pilote : tenant synthétique, seeds démo, kill switch, rapport signé.
> **Date :** 2026-08-30 · **Statut :** pilote exécuté sur tenant synthétique — rapport signé.
> **Décision :** en attente du GO final (gates restants `performance`/`observability`/`recette_signee`, cf. `dev-hub/tools/pilot-gates.json`).

## 1. Tenant pilote synthétique

| Élément | Valeur |
|---|---|
| Tenant | `demo-resto` (Company factory, pays CM, devise XAF) |
| Activation | `php artisan leopardo:restaurant:activate {company}` (RESTO-105) |
| Seed démo | `php artisan leopardo:restaurant:seed-demo {company}` (RESTO-107, idempotent — rejouable) |
| Contenu seed | Branche DEMO, zones (Salle/Terrasse), tables T1–T8, ingrédients + stock, produits, menu Midi, session de caisse clôturée avec commande payée `RST-DEMO0001` |
| Feature flag | `companies.features.restaurantmanager = true` |
| Registre BC | `dev-hub/governance/bounded-context-registry.json` — BC-25 RESTAURANT `status: active` (paths `active`) |

Preuve seed : `api/tests/Feature/Restaurant/RestaurantDemoSeederTest.php` (idempotence vérifiée en CI).

## 2. Recette exécutée

Le cahier `docs/ops/RECETTE_UAT_RESTAURANTMANAGER.md` couvre R1–R11. Couverture automatisée apportée par ce lot :

- **R1 (flux roi GJ-RESTO-01)** : `api/tests/Feature/Restaurant/RestaurantGoldenJourneyTest.php` (caisse → commande → article → soumission → encaissement → clôture + isolation cross-tenant) — enregistré `dev-hub/tools/golden-journeys.json` (GJ-RESTO-01), garde `check-golden-journeys.sh` vert.
- **R11 (sécurité & isolation)** : tests dédiés du lot en ligne (`RestaurantPublicShopTest`, `RestaurantMarketplaceWebhookTest`) : jeton invalide → 401, cross-tenant → 404, signature invalide → 401, idempotence.
- Les scénarios interactifs R2–R10 restent à exécuter manuellement sur l'environnement de recette (tableau du cahier, colonnes Résultat/Preuve).

## 3. Kill switch & rollback (prouvés)

Le kill switch EST le feature flag : `EnsureRestaurantManagerModuleMiddleware` refuse tout appel `/restaurant/*` avec `403 FEATURE_NOT_ENABLED` quand `companies.features.restaurantmanager = false`.

- Preuve automatisée : `api/tests/Feature/Restaurant/RestaurantFeatureFlagTest.php` (flag absent → 403).
- Procédure : `docs/ops/RUNBOOK_PILOT_RESTAURANTMANAGER.md` §7 — rollback non destructif (désactivation du flag, données `restaurant_*` conservées) ; purge/restauration via `RUNBOOK_BACKUP_RESTORE.md`.
- Drill de restauration : DR-25 référencé dans le runbook (MAT-015).

## 4. Gates (MAT-018)

`dev-hub/tools/pilot-gates.json` (pilote `restaurantmanager`) — validés par ce lot :

| Gate | Statut | Preuve |
|---|---|---|
| manifest | ✅ | `RestaurantManagerManifest` + activation tenant |
| core_flow (GJ-RESTO-01) | ✅ | `RestaurantGoldenJourneyTest` en CI |
| api_security | ✅ | Matrice `restaurant.*` + Policies RBAC |
| runbook | ✅ | Runbook + recette UAT |
| security_review | ✅ | Audit RGPD (RESTO-904) |
| golden_journey | ✅ | GJ-RESTO-01 enregistré + test CI |
| performance | ⬜ | Benchmarks POS/requêtes (hors périmètre, à suivre) |
| observability | ⬜ | À compléter (MAT-009) |
| recette_signee | ⬜ | Signature métier finale du cahier R2–R10 |

Garde `check-pilot-gates.sh` : cohérent — aucun GO prématuré.

## 5. Périmètre nouveau du lot en ligne (RESTO-805/806/807)

Le pilote couvre aussi les nouveaux canaux : commande en ligne publique (menu public par tenant, jeton signé), kiosque libre-service et webhooks apps de livraison (Uber Eats/Glovo) — tests dédiés inclus (voir §2).

## 6. Signature

| Rôle | Nom | Date | Signature |
|---|---|---|---|
| Chef de projet (décision GO) | Agent PM — lot RESTO-029/030 | 2026-08-30 | ✅ (rapport établi) |
| Responsable technique | Agent PM — lot RESTO-029/030 | 2026-08-30 | ✅ |
| Représentant métier (restaurateur pilote) | — (recette interactive R2–R10 à exécuter) | ⬜ | ⬜ |

La signature finale du GO intervient une fois les gates `performance`, `observability` et la recette interactive R2–R10 complétés.
