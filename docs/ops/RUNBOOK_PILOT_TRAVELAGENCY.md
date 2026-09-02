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
# RUNBOOK — Pilote TravelAgency (tenant pilote, kill switch, backup, restauration, rollback)
> **Issue :** [TRAVEL-050 #5998](https://github.com/kitokoh/leopardo-hr/issues/5998) — maturité DEP-BC24, runbook pilote, recette UAT, pilot gates
> **Exécution :** [TRAVEL-051 #5999](https://github.com/kitokoh/leopardo-hr/issues/5999) — pilote tenant synthétique + recette signée
> **Gates :** [MAT-018 #5876](https://github.com/kitokoh/leopardo-hr/issues/5876) (`pilot-gates.json`, pilote `travelagency`, 9 gates — aucun GO prématuré)
> **Références :** `RUNBOOK_BACKUP_RESTORE.md`, `RUNBOOK_ROLLBACK.md`, `RUNBOOK_INCIDENT_P1.md` (docs/GESTION_PROJET)
## 1. Cadre du pilote
Le pilote TravelAgency démarre **uniquement** quand les 9 gates de
`dev-hub/tools/pilot-gates.json` (pilote `travelagency`) sont `validated` :
manifest (TRAVEL-105/106), core flow (TRAVEL-2xx/3xx), API/Policies (TRAVEL-322),
runbook (TRAVEL-050), sécurité (TRAVEL-1013), performance (MAT-014), observabilité
(MAT-009), golden journey GJ-TRAVEL-01 (TRAVEL-1007), recette signée (TRAVEL-051).
Ce document prépare la phase pilote : périmètre, données, supervision et procédures
d'urgence — à valider pendant la recette.
## 2. Tenant pilote
| Élément | Valeur cible |
| Tenant | `travel-pilot-001` (déterministe, seeder dédié `leopardo:travel:seed-demo`, TRAVEL-107) |
| Pays | DZ (premier marché) — devise DZD |
| Rôles | `principal` (manager pilote), `rh` (supervision), `operator` (agent guichet/agence) |
| Données | Synthétiques uniquement (aucune PII réelle, aucun voyage réel) |
| Feature flag | `travelagency` activé tenant-scope, désactivation coupante (kill switch) |
## 3. Données synthétiques (critère « pilote reproductible »)
- Référentiel : 2 pays, 5 villes, 4 gares, 2 bureaux de vente, 3 compagnies, 2 classes, 4 véhicules ;
- Réseau : 4 routes avec étapes (tri par rang), 3 trajets publiés, sièges générés de façon
  transactionnelle et déterministe (TRAVEL-208) ;
- Ventes : réservations multi-passagers, billets émis, paiements cash/PVIT sandbox, check-ins —
  générés de façon déterministe (seed idempotent, réentrant) ;
- locations (véhicules + images) et hôtels (catalogue + chambres) avec réservations sans chevauchement ;
- aucun secret ni PII dans les fixtures (garde secret-scan/TruffleHog) ;
- benchmark séparé des fixtures fonctionnelles (pattern `CrmBenchmarkSeeder`).
## 4. Kill switch et désactivation
- `feature flags` tenant : désactivation coupante de la solution `travelagency` (opt-in activé
  par le manifest, désactivation immédiate) ;
- après désactivation : routes `/api/v1/travel/*` → 403 explicite, aucune écriture ;
- le kill switch est **testé avant le pilote** (arrêt d'urgence, scénario U-10 de la recette).
## 5. Sauvegarde / restauration (critère « restauration validée »)
Suivre `RUNBOOK_BACKUP_RESTORE.md` (procédure minimale) :
- backup quotidien PostgreSQL (dump chiffré, S3/R2) ;
- **restore mensuel vérifié** sur base scratch isolée (jamais la production) ;
- preuve datée dans `RUNBOOK_DRILLS_LOG.md` (drill **DR-26** planifié — restore schéma tenant
  TravelAgency sur staging avant GO pilote) ;
- RPO < 24 h, RTO < 4 h.
## 6. Incident et rollback (critère « arrêt d'urgence testé »)
Suivre `RUNBOOK_INCIDENT_P1.md` + `RUNBOOK_ROLLBACK.md` :
| Déclencheur | Décision |
| `/api/v1/health` fail > 2 min | Rollback immédiat |
| Écart sièges/réservations/billets inexpliqué (TravelAgency) | Gel des écritures + investigation, pas d'ajustement silencieux |
| Callback paiement PVIT en échec > seuil ou montant incohérent | Suspension des paiements en ligne + investigation (webhook signé HMAC, réconciliation TRAVEL-410) |
| Erreur > 5 % / 5 min après déploiement | Rollback immédiat |
- Rollback code : revert du tag de release pilote (aucune migration destructive — les migrations
  TravelAgency sont additives) ;
- Rollback données : restore scratch puis bascule applicative ;
- **drill d'arrêt d'urgence exécuté avant le go** : preuve datée dans `RUNBOOK_DRILLS_LOG.md`.
## 7. Supervision (observabilité)
- `correlation_id` sur les écritures de ventes/réservations/billets ;
- alertes : lag de file, dead-letters `travel_outbox_events`, échec callback PVIT, écart
  sièges/ventes, échec génération billets PDF ;
- dashboards : ventes, occupation des trajets, recettes, annulations, locations/hôtels.
## 8. Preuve de préparation (phase 1)
- [ ] Tenant pilote défini (tableau §2)
- [ ] Données synthétiques spécifiées (§3)
- [ ] Kill switch documenté + test planifié (§4, scénario U-10)
- [ ] Procédure backup/restore alignée (§5, drill DR-26)
- [ ] Procédure incident/rollback alignée (§6)
- [ ] Supervision définie (§7)
Exécution réelle (restore, kill switch, recette signée) : **gated** par la fusion des
fondations TravelAgency sur `main` (PRs #6127/#6129, #6273, #6340) et le go/no-go MAT-018.
