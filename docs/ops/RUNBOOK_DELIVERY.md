# RUNBOOK — Livraison dernier-kilomètre (BC-26 DELIVERY)

> Module de livraison **générique multi-tenant** : agence, restaurant (BC-25),
> retail (BC-17), e-commerce (BC-14), CRM (BC-11). Spec :
> `docs/specifications/SOLUTION_DELIVERY.md`. Matrice d'autorisation :
> `docs/architecture/DELIVERY_RBAC.md`. Registre runbooks : MAT-015 (BC-26).

## Activation par tenant

- Feature flag `companies.features.delivery` → gate middleware `module.delivery`
  (kill switch). 403 `FEATURE_NOT_ENABLED` sinon.
- Registre MAT-001 : BC-26 `active` ; CODEOWNERS sur `api/app/Modules/Delivery/`.

## Endpoints (préfixe `/api/v1/delivery`)

| Méthode | Route | Rôle (BC-26-D05) | Issue |
|---|---|---|---|
| GET | `/delivery/ping` | auth | DELIVERY-101 |
| GET/POST | `/delivery/deliveries` | dispatcher+admin | DELIVERY-201 |
| GET | `/delivery/deliveries/{id}` | dispatcher+admin | DELIVERY-201 |
| POST | `/delivery/deliveries/{id}/tracking-link` | dispatcher+admin | DELIVERY-204 |
| POST | `/delivery/deliveries/events` | dispatcher/admin/rider* | DELIVERY-204 |
| GET | `/delivery/deliveries/{id}/tracking` | dispatcher/admin/manager/rider* | DELIVERY-204 |
| POST | `/delivery/deliveries/routes` | dispatcher+admin | DELIVERY-202 |
| POST | `/delivery/deliveries/routes/{id}/assign` | dispatcher+admin | DELIVERY-202 |
| POST | `/delivery/deliveries/routes/{id}/close` | dispatcher+admin | DELIVERY-202 |
| GET | `/delivery/deliveries/routes/{id}` | dispatcher+admin (+rider si sa tournée) | DELIVERY-202 |
| GET | `/delivery/deliveries/reports/summary` | manager+admin | DELIVERY-207 |
| GET | `/delivery/deliveries/reports/export` | manager+admin | DELIVERY-207 |
| GET | `/api/v1/deliveries/tracking/{token}` | **public** (token = credential) | DELIVERY-204 |

\* rider : borné à SES tournées (`driver_id = id`) par les Policies — sinon 403.

## Invariants métier (machine à états `DeliveryStateMachine`)

- `created → assigned → picked_up → out_for_delivery → arrived → delivered`.
- `delivered` exige une POD (`proof_document_id`, BC-20 par valeur) —
  sinon 409 `PROOF_REQUIRED`.
- États terminaux (`delivered` / `returned` / `cancelled`) : aucune réouverture.
- Tournée : 1 livreur + 1 véhicule par date (index unique
  `delivery_routes_company_date_driver_unique`), clôture idempotente, refus
  `ROUTE_INCOMPLETE` si stops non terminés.

## Idempotence (rejeu mobile/edge)

- Événements : unique `(company_id, delivery_id, type, event_at)` +
  `idempotency_key` client → un rejeu retourne l'événement existant.
- Création de livraison source : unique `(company_id, source, source_reference)`
  → zéro doublon par commande source (webhook e-commerce rejoué = 1 livraison).
- Clôture : deux exécutions → mêmes totaux (exigence sortie BC-26, testé).

## Asynchronisme (BC-26-D07) & jobs

- `CloseDeliveryRouteJob` : clôture asynchrone volumineuse (commandes
  `delivery:close-route {route} {company}`), idempotente, retry 3 × backoff
  10/30/60 s, DLQ sur échec persistant.
- `ExportDeliveryReportJob` : snapshot JSON déterministe du read model
  (`delivery:export-report {company} --from= --to=`), même `runKey` → même
  fichier (zéro doublon).
- **DLQ** : table `delivery_dead_letters` alimentée par le hook `failed()` ;
  rejeu contrôlé `php artisan delivery:replay-dlq [--id=N]`. Une DLQ
  `failed` = à trier manuellement (job_class inconnue).
- Tous les jobs sont tenant-scoped (`EnsureTenantContext`, contexte restauré
  en fin de job — contrat BC-02) ; logs corrélés `company_id`/`job_id`/`route_id`
  (`delivery.route.closed`, `delivery.job.failed`, `delivery.dlq.replayed`).
- Alertes : `failed_jobs` global (QueueObservability, seuil 10) + DLQ Delivery.

## Performance (MAT-014 `dev-hub/tools/performance-budgets.json`)

- Liste colis `GET /delivery/deliveries` : p95 ≤ 300 ms, pagination obligatoire.
- Événements `POST /deliveries/events` : p95 ≤ 200 ms. Tracking public : p95 ≤ 200 ms.
- `GET /delivery/deliveries/reports/summary` : **p95 ≤ 300 ms**, ≤ 8 requêtes.
- Export : p95 ≤ 400 ms, streamé par curseur (mémoire bornée).
- Index requis : `delivery_deliveries (company_id, status, created_at)`,
  `delivery_events (company_id, delivery_id)`, `delivery_stops (route_id,
  sort_order)` — garde `check-performance-budgets.sh` (exit 0).

## Seed pilote & golden journey (BC-26-D12)

- `php artisan db:seed --class=DeliveryPilotSeeder` : tenant `delivery-pilot-alpha`
  **100 % synthétique** (garde MAT-012), réentrant — parcours §7.1 pré-rempli
  (3 colis dont 1 COD, tournée close, règlement COD en attente de réconciliation).
- Golden journey versionné : `dev-hub/tools/golden-journeys.json` (GJ-DELIVERY-01,
  garde `check-golden-journeys.sh`).
- Test E2E : `api/tests/Feature/Delivery/DeliveryGoldenJourneyTest.php`.

## Incidents — symptôme → diagnostic → action → rollback

| Symptôme | Diagnostic | Action | Rollback |
|---|---|---|---|
| **409 `INVALID_TRANSITION`** | Événement hors séquence (ex. `delivered` sur une livraison `created`) — l'app mobile rejoue un vieux payload | Vérifier le statut : `SELECT id, status FROM delivery_deliveries WHERE id = ?` ; corriger la séquence côté client | Aucune donnée écrite (transition refusée avant écriture) — rien à rollback |
| **409 `PROOF_REQUIRED`** | `delivered` sans `proof_document_id` (POD) | Renvoyer l'événement avec la POD (BC-20) | Idem — refusé avant écriture |
| **409 `DRIVER_ALREADY_ASSIGNED`** | Livreur déjà sur une tournée du même jour (contrainte métier) | Affecter un autre livreur/véhicule ou clôturer la tournée du jour | `UPDATE delivery_routes SET driver_id = NULL WHERE id = ?` (tournée draft uniquement) |
| **409 `ROUTE_INCOMPLETE`** | Clôture avec stops non terminés | Traiter les derniers stops (delivered/failed/skipped) puis re-clôturer | Aucune (clôture refusée) |
| **404 `TRACKING_LINK_NOT_FOUND`** | Token expiré (TTL 7 j) ou invalide | Re-générer : `POST {delivery}/tracking-link` | Aucune |
| **Job clôture en DLQ** (`delivery_dead_letters`) | Échec persistant après 3 tentatives (ex. conflit d'affectation, tournée introuvable) | `SELECT * FROM delivery_dead_letters WHERE status='new'` ; corriger la cause ; `php artisan delivery:replay-dlq` | Si le rejeu échoue : `UPDATE ... SET status='failed'` (à trier manuellement) — l'API synchrone reste disponible |
| **Export dupliqué / rejeu** | Même run rejoué | Vérifier `run_key` : même clé → même fichier écrasé (idempotent, zéro doublon) | Supprimer le fichier `storage/app/delivery_reports/...` puis re-exporter |
| **Lenteur liste colis** | Absence d'index / get() non paginé | Vérifier `EXPLAIN` + registre budgets ; paginer (per_page ≤ 100) | Rétablir l'index `delivery_deliveries_company_status_date_idx` (migration réentrante) |
| **403 `DELIVERY_ROLE_REQUIRED`** | Employé sans rôle delivery (matrice deny-by-default) | Vérifier le rôle : `SELECT role, manager_role, status FROM employees WHERE id = ?` ; monter le rôle si légitime | Aucune |

## Sécurité & RGPD (POD = données personnelles)

- POD (photo/signature) = PII client : URLs temporaires, rétention BC-20,
  logs redacted — ne jamais logger `dropoff_address` complet ni les photos.
- Lien de suivi public : token 64 chars expirant (7 j), anti-énumération,
  `Referrer-Policy: no-referrer` — le token est la credential.

## Dimensions ouvertes (autres issues BC-26)

- DELIVERY-203 : app mobile livreur (tournée du jour, POD upload BC-20,
  offline replay) — scope client Flutter.
- DELIVERY-205 : règlement COD + contrat BC-08 (posting idempotent,
  réconciliation — la table `delivery_cod_settlements` est prête).
- DELIVERY-206 : notifications destinataire via contrat BC-13 COMMS.
- DELIVERY-208 : contrats sources BC-25/17/14/11.
- BC-26-D01 : glossaire unifié. BC-26-D03 : isolation tenant & tests cross-tenant.
