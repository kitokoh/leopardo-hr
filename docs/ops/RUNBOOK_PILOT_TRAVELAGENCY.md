# RUNBOOK PILOTE — TravelAgency (BC-24 TRAVEL)

> **Issue :** TRAVEL-1010 (#6123) — Runbook pilote + recette UAT + pilot gates (MAT-018, #5876)
> **Format :** aligné sur les runbooks plateforme (`docs/GESTION_PROJET/RUNBOOK_*.md`) et les pilotes FuelStation/EduManager/RestaurantManager (MAT-018).
> **Périmètre :** activation, seed, flux opérationnels, incidents, rollback, backup/restore du pilote TravelAgency.
> **Statut :** prêt pour recette — la décision GO/NO-GO reste gouvernée par `dev-hub/tools/pilot-gates.json` (pilote `travelagency`).

## 1. Vue d'ensemble du pilote

| Élément | Valeur |
|---|---|
| Contexte | BC-24 TRAVEL — module `App\Modules\TravelAgency` |
| Feature flag | `companies.features.travelagency` (middleware `module.travelagency`) |
| Activation | `php artisan leopardo:travel:activate {company}` (TRAVEL-105) |
| Seed démo | `php artisan leopardo:travel:seed-demo {company}` (TRAVEL-107, idempotent) |
| Migrations | tenant, via `php artisan leopardo:migrate` (tables `travel_*`, réentrantes) |
| Périmètre pilote | réseau (pays/villes/stations/routes), trajets & tarifs, réservations, paiements, billets, check-in, rapports & exports, contenu éditorial |
| Surfaces sensibles | PII passagers (réservations), paiements (cash/PVIT mobile money), billets (codes de validation) |
| Backups | runbook plateforme `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` (schémas tenant) |

## 2. Préparation de l'environnement

1. Vérifier que `main` est vert sur les checks requis (coverage, PHPStan strict, Module Structure, ESLint+TS, actionlint).
2. Appliquer les migrations tenant : `php artisan leopardo:migrate`.
3. Activer le module pour le tenant pilote : `php artisan leopardo:travel:activate <company_id>` (vérifier `companies.features.travelagency = true`).
4. (Optionnel) Données de démonstration : `php artisan leopardo:travel:seed-demo <company_id>` (idempotent).

## 3. Smoke test post-activation (2 min)

| # | Vérification | Appel | Attendu |
|---|---|---|---|
| 1 | Flag actif | `GET /api/v1/travel/ping` (token employé du tenant) | `200` |
| 2 | Flag inactif | même appel sur un tenant non activé | `403 FEATURE_NOT_ENABLED` |
| 3 | Isolation | `GET /api/v1/travel/stations` avec un token d'un autre tenant | `404`/liste vide — jamais de données croisées |

## 4. Flux métier de recette

Voir `docs/ops/RECETTE_UAT_TRAVELAGENCY.md` : R1 vente d'un billet en ligne (flux roi), R2 guichet (cash), R3 check-in & manifeste, R4 annulation/remboursement, R5 correspondances, R6 multi-devise, R7 rapports & export, R8 contenu éditorial (articles/commentaires/engagement), R9 sécurité & isolation.

## 5. Opérations planifiées (scheduler)

| Job | Fréquence | Rôle |
|---|---|---|
| `travel:recalculate-read-models` | 03:30 quotidien | recalcule les read models de reporting (ventes journalières, occupation) — idempotent |

Surveiller le run de recalcul : une reprise doit produire le MÊME état (upsert par clé unique).

## 6. Incidents connus et résolution

| Symptôme | Cause probable | Résolution |
|---|---|---|
| `403 FEATURE_NOT_ENABLED` sur `/travel/*` | flag absent/inactif | `leopardo:travel:activate` |
| Paiement mobile money en `pending` sans callback | provider sandbox indisponible | re-conciliation `verify()` (retry borné) ; rejeu idempotent par `idempotency_key` |
| `409` sur transition de réservation | transition hors workflow | vérifier l'état actuel (`GET .../{id}`) avant de rejouer |
| Billet introuvable au check-in | `validation_code` erroné | re-générer le billet (`issue-ticket` idempotent) ; vérifier le QR |
| Rapports incohérents | read models périmés | `php artisan travel:recalculate-read-models` (idempotent) |
| Migration échouée | collision de préfixe ou état partiel | `php artisan leopardo:migrate` (réentrant) ; jamais hors `leopardo:migrate` |

## 7. Rollback & désactivation

- Kill switch : `companies.features.travelagency = false` → toutes les routes `/travel/**` répondent `403 FEATURE_NOT_ENABLED` (middleware fail-closed).
- Rollback de déploiement : runbook plateforme `docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md`.
- Restauration de données : `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md` (schémas tenant).
