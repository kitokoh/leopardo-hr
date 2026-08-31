# RUNBOOK PILOTE — RestaurantManager (BC-25 RESTAURANT)

> **Issue :** RESTO-903 (#6232) — Runbook pilote + recette UAT + pilot gates (MAT-018, #5876)
> **Format :** aligné sur les runbooks plateforme (`docs/GESTION_PROJET/RUNBOOK_*.md`) et les pilotes FuelStation/EduManager (MAT-018).
> **Périmètre :** activation, seed, flux opérationnels, incidents, rollback, backup/restore du pilote RestaurantManager.
> **Statut :** prêt pour recette — la décision GO/NO-GO reste gouvernée par `dev-hub/tools/pilot-gates.json` (pilote `restaurantmanager`).

## 1. Vue d'ensemble du pilote

| Élément | Valeur |
|---|---|
| Contexte | BC-25 RESTAURANT — module `App\Modules\RestaurantManager` |
| Feature flag | `companies.features.restaurantmanager` (middleware `module.restaurantmanager`) |
| Activation | `php artisan leopardo:restaurant:activate {company}` (RESTO-105) |
| Seed démo | `php artisan leopardo:restaurant:seed-demo {company}` (RESTO-107, idempotent) |
| Migrations | tenant, via `php artisan leopardo:migrate` (tables `restaurant_*`, réentrantes) |
| Périmètre pilote | POS & caisse, commandes (salle/emporter/livraison), réservations, stock/achats, livraison, fidélité, promotions, rapports |
| Surfaces sensibles | PII clients (réservations, contacts), paiements (cash/carte/mobile money), caisse (écarts) — cf. audit RESTO-904 |
| Backups | runbook plateforme `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` (schémas tenant) |

## 2. Préparation de l'environnement

1. Vérifier que `main` est vert sur les checks requis (coverage, PHPStan strict, Module Structure, ESLint+TS, actionlint).
2. Appliquer les migrations tenant : `php artisan leopardo:migrate`.
3. Activer le module pour le tenant pilote : `php artisan leopardo:restaurant:activate <company_id>` (vérifier `companies.features.restaurantmanager = true`).
4. (Optionnel) Données de démonstration : `php artisan leopardo:restaurant:seed-demo <company_id>` (idempotent).

## 3. Smoke test post-activation (2 min)

| # | Vérification | Appel | Attendu |
|---|---|---|---|
| 1 | Flag actif | `GET /api/v1/restaurant/ping` (token employé du tenant) | `200` |
| 2 | Flag inactif | même appel sur un tenant non activé | `403 FEATURE_NOT_ENABLED` |
| 3 | Isolation | `GET /api/v1/restaurant/branches` avec un token d'un autre tenant | `404`/liste vide — jamais de données croisées |

## 4. Flux métier de recette

Voir `docs/ops/RECETTE_UAT_RESTAURANTMANAGER.md` : R1 service en salle (GJ-RESTO-01), R2 à emporter, R3 livraison complète, R4 annulation livraison, R5 réservation + conflit, R6 no-show + rappel, R7 stock & COGS, R8 fidélité, R9 promotions, R10 rapports & export, R11 sécurité & isolation.

## 5. Opérations planifiées (scheduler)

| Job | Fréquence | Rôle |
|---|---|---|
| `restaurant:outbox-dispatch` | toutes les minutes | consomme les événements outbox (fidélité, notifications) |
| `restaurant:no-show-expire` | 15 min | réservations non honorées → `no_show` |
| `restaurant:send-reminders` | chaque heure | rappel J-1 des réservations confirmées |

Surveiller `[restaurant:outbox-dispatch]` : des dead-letter récurrentes (`no_consumer`, `permanent:`) signalent un consommateur manquant ou une régression.

## 6. Incidents connus et résolution

| Symptôme | Cause probable | Résolution |
|---|---|---|
| `403 FEATURE_NOT_ENABLED` sur `/restaurant/*` | flag absent/inactif | `leopardo:restaurant:activate` |
| Événements outbox en dead-letter | consommateur absent ou erreur permanente | lire `last_error` ; corriger/ajouter le consommateur, puis re-publier (idempotence) |
| `409` sur transition de livraison/commande | transition hors workflow | vérifier l'état actuel (`GET .../{id}`) avant de rejouer |
| `422 Amount mismatch` au paiement | montant client ≠ reste à payer serveur | recalculer via `GET /orders/{order}/bill` |
| Caisse : écart inexpliqué | totaux recalculés serveur vs fonds | motif d'écart obligatoire à la clôture ; auditer les encaissements confirmés |
| Migration échouée | collision de préfixe ou état partiel | `php artisan leopardo:migrate` (réentrant) ; jamais hors `leopardo:migrate` |

## 7. Rollback (pilote)

Rollback = désactivation, **pas** de suppression de données :

1. Désactiver le flag (`companies.features.restaurantmanager = false`) — le middleware refuse alors tout appel `/restaurant/*` (kill switch opérationnel).
2. Les données tenant (`restaurant_*`) sont conservées (rollback non destructif).
3. Purge complète si nécessaire : voir `RUNBOOK_BACKUP_RESTORE.md` (restauration du schéma tenant depuis le dernier backup).

## 8. Backups / Restore

- Les tables `restaurant_*` sont des tables **tenant** : couvertes par le runbook plateforme de backup/restore (`docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`).
- **Drill** : exercice de restauration daté dans `docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md` (DR-25, règle MAT-015).

## 9. Recette UAT et décision de GO

- La recette signée vit dans `docs/ops/RECETTE_UAT_RESTAURANTMANAGER.md`.
- La décision GO/NO-GO est pilotée par les **9 gates** de `dev-hub/tools/pilot-gates.json` (pilote `restaurantmanager`) — **aucun GO tant qu'un gate est pending** (garde `check-pilot-gates.sh`).
