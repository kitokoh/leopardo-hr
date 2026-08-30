# RUNBOOK PILOTE — TravelAgency (BC-24 TRAVEL)

> **Issue :** TRAVEL-1010 (#6123) — Runbook pilote + recette UAT (MAT-015 #5873).
> **Format :** aligné sur les runbooks plateforme (`docs/GESTION_PROJET/RUNBOOK_*.md`) et le format FuelStation (MAT-018).
> **Périmètre :** tenant pilote, données synthétiques, kill switch, backup/restore (RPO < 24h / RTO < 4h), incident/rollback, supervision.
> **Statut :** prêt pour recette — GO/NO-GO gouverné par `dev-hub/tools/pilot-gates.json` (pilote `travelagency`).

## 1. Vue d'ensemble du pilote

| Élément | Valeur |
|---|---|
| Contexte | BC-24 TRAVEL — module `App\Modules\TravelAgency` |
| Feature flag | `companies.features.travelagency` (middleware `module.travelagency`) |
| Activation | `php artisan leopardo:travel:activate {company}` (TRAVEL-105) |
| Seed démo | `php artisan leopardo:travel:seed-demo {company}` (TRAVEL-107, idempotent) |
| Migrations | tenant, via `php artisan leopardo:migrate` (tables `travel_*`, réentrantes) |
| Périmètre pilote | réseau (routes, trajets, gares, compagnies), vente en ligne (recherche, réservation, paiement mobile money, billets PDF), locations de véhicules |
| Surfaces sensibles | PII passagers (réservations, pièces d'identité), paiements (mobile money, callbacks signés), billets/URLs PDF signées |
| SLA données | **RPO < 24h / RTO < 4h** (backup/restore plateforme, schémas tenant) |

## 2. Préparation de l'environnement

1. `main` vert sur les checks requis (coverage, PHPStan strict, Module Structure, ESLint+TS, actionlint).
2. Migrations tenant : `php artisan leopardo:migrate`.
3. Activation : `php artisan leopardo:travel:activate <company_id>` (vérifier `companies.features.travelagency = true`).
4. Données synthétiques : `php artisan leopardo:travel:seed-demo <company_id>` (idempotent) — réseau (gares, compagnies, classes, véhicules, routes), offres.

## 3. Smoke test post-activation (2 min)

| # | Vérification | Appel | Attendu |
|---|---|---|---|
| 1 | Flag actif | `GET /api/v1/travel/ping` (token employé du tenant) | `200` |
| 2 | Flag inactif | même appel sur un tenant non activé | `403 FEATURE_NOT_ENABLED` |
| 3 | Isolation | `GET /api/v1/travel/stations` avec un token d'un autre tenant | `404`/liste vide — jamais de données croisées |

## 4. Flux métier de recette

Voir `docs/ops/RECETTE_UAT_TRAVELAGENCY.md` — scénarios R1..R10 (recherche → réservation → paiement → billet → check-in, annulation/remboursement, locations, rapports, exports, sécurité).

## 5. Opérations planifiées (scheduler)

| Job | Fréquence | Rôle |
|---|---|---|
| `travel:outbox-dispatch` | toutes les minutes | consomme les événements outbox (billets, notifications) |
| `travel:expire-pending-bookings` | 15 min | réservations en attente de paiement expirées |

Surveiller les dead-letter outbox (`status=failed`, `last_error`) : consommateur manquant ou régression.

## 6. Incidents connus et résolution

| Symptôme | Cause probable | Résolution |
|---|---|---|
| `403 FEATURE_NOT_ENABLED` | flag absent/inactif | `leopardo:travel:activate` |
| Callback PVIT cassé (recherche par id au lieu de la référence) | contrat de passerelle (A3 de la spec) | passerelle avec callbacks signés + idempotents ; rejouer le callback |
| `409` sur transition de réservation | transition hors workflow | vérifier l'état (`GET /travel/shop/bookings/{reference}`) |
| `422` au paiement | montant client ≠ montant serveur | recalculer via la réservation |
| Billet PDF introuvable | génération asynchrone | vérifier le job + l'asset (URL signée éphémère) |
| Migration échouée | collision de préfixe | `php artisan leopardo:migrate` (réentrant) |

## 7. Kill switch & rollback

1. **Kill switch** : `companies.features.travelagency = false` → tout appel `/travel/*` refuse (403) — rollback non destructif, données conservées.
2. **Rollback complet** : restauration du schéma tenant depuis le dernier backup (RPO < 24h).
3. **RTO** : restauration cible < 4h (drill DR-24).

## 8. Backups / Restore

- Tables `travel_*` tenant-scoped : couvertes par `RUNBOOK_BACKUP_RESTORE.md` (dump schémas tenant).
- **Drill** : exercice de restauration daté dans `RUNBOOK_DRILLS_LOG.md` (DR-24, MAT-015).

## 9. Recette UAT et décision de GO

- Recette signée : `docs/ops/RECETTE_UAT_TRAVELAGENCY.md`.
- GO/NO-GO : 9 gates de `dev-hub/tools/pilot-gates.json` (pilote `travelagency`) — **aucun GO tant qu'un gate est pending** (garde `check-pilot-gates.sh`).
