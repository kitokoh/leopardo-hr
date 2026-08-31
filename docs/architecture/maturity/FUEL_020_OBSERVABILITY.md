# FUEL-020 — Sécurité, performance et observabilité FuelStation

> Issue #5814 — dépendances FUEL-011 à FUEL-019 (livrées dans le lot `bc/bc15-fuel-ops`).
> Mise à jour : 2026-08-30.

## Threat model (résumé)

| Zone | Menace principale | Contrôle |
|---|---|---|
| Relevés/ventes | Rejeu réseau → doublons | `idempotency_key` / `external_id` uniques par tenant |
| Rapprochements | Ajustement silencieux d'écart | statut `exception`, mouvement `adjustment` explicite + audité |
| Outbox | Rejeu de consommation | clé unique `(company_id, idempotency_key)`, consommateurs idempotents |
| Alertes | Double notification | `alert_key` unique par tenant |
| Imports | Ligne malveillante | validation ligne à ligne, rollback logique, taille ≤ 2 Mo / 5 000 lignes |
| Cross-tenant | Lecture d'un autre tenant | FKs composites `(x, company_id)`, 404 fail-closed, policies deny-by-default |
| PII/logs | Fuite dans les traces | logs structurés sans payload, secrets jamais loggés |

## Rate limits (FUEL-020)

| Limiter | Cible | Borne |
|---|---|---|
| `throttle:api` | global API (existant) | 300 req/min/entreprise |
| `throttle:api-plan` | plan tenant (existant) | piloté par plan |
| `throttle:fuel-sensitive` | écritures FuelStation (POST/PUT/PATCH) | 120 req/min/entreprise (config `security.rate_limits.fuel_per_minute`) |
| `throttle:metrics` | `/fuel-station/health/metrics` | 30 req/min (anti-scraping) |

## Index dédiés (migration 5814)

- `fuel_meter_intervals (company_id, meter_id, calculated_at)` — reporting volumes par pompe
- `fuel_meter_readings (company_id, meter_id, captured_at_utc)` — historique/corrections
- `fuel_stock_movements (company_id, station_id, movement_at)` — rapprochement/journal
- `fuel_shift_assignments (company_id, shift_id, assignment_date)` — ventes par shift

N+1 guards : agrégats SQL groupés (`SUM ... GROUP BY meter_id`), noms de shifts chargés en une requête.

## Objectifs de latence (p95/p99)

Mesurés via le middleware de timing API existant ; cibles par ressource (API v1, tenant partagé) :

| Ressource | p95 | p99 |
|---|---|---|
| GET snapshots / reports | ≤ 150 ms | ≤ 300 ms |
| POST relevé / vente (idempotent) | ≤ 250 ms | ≤ 500 ms |
| POST rapprochement | ≤ 400 ms | ≤ 800 ms |
| POST import CSV (≤ 1 000 lignes) | ≤ 1 000 ms | ≤ 2 000 ms |
| GET /health/metrics | ≤ 100 ms | ≤ 200 ms |

Dégradé documenté : snapshots read models (> 24 h → `snapshots_stale`), file outbox profonde (≥ 100 pending → alerte queue), alertes ouvertes (≥ 10 → revue).

## Alertes queue/DB (FUEL-019 + métriques)

- `fuel_alerts.alert_key` unique — déduplication garantie ;
- `fuel:alerts-scan` (30 min) : anomalies compteur, clôtures manquantes (> 24 h), maintenance due (48 h) ;
- `GET /fuel-station/health/metrics` (manager) : `outbox_pending`, `outbox_failed`, `alerts_open`, `readings_today`, `snapshots_stale`, `reconciliations_exception` ;
- logs structurés `fuel.outbox.dispatched` / `fuel.alerts.scanned` (canal `structured`, zéro PII/payload).

## Preuve CI

- Scans sécurité : Composer Audit + CodeQL (workflows existants, inchangés).
- Tests : `FuelObservabilityApiTest` (401/403/200 métriques) + l'ensemble des tests Feature FUEL-009..019 du lot.
