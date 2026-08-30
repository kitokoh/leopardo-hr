# BC-22 — Snapshots des read models de reporting

> **Issue :** #6243 (D10) — DEP-BC22.
> **Read models concernés :** dashboard comptable
> (`GET /api/v1/accounting/dashboard`), export CSV impayés, journal/FEC.
> **Lineage :** `docs/architecture/ANALYTICS_READ_MODEL_LINEAGE.md`.
> **Runbook reporting :** `docs/ops/RUNBOOK_REPORTING_ANALYTICS.md`.
> **Budgets p95 :** `dev-hub/tools/performance-budgets.json`.

## Politique de fraîcheur (rappel)

Les read models de reporting sont **calculés à la volée** : agrégations simples
scopées `company_id`, déterministes (`GoldenDashboardRecomputeTest`), aucun
cache mutable. Un snapshot **n'est introduit que si** un endpoint de reporting
dépasse son budget p95 (registre `performance-budgets.json`) — jamais
préventivement sur des volumes sains.

## Déclencheur d'activation

1. La CI k6 / les métriques staging montrent un dépassement du budget p95
   d'un endpoint de reporting (ex. `GET /accounting/dashboard` > 300 ms).
2. On vérifie d'abord les correctifs non matériels : index manquants
   (`check-performance-budgets.sh` → `required_indexes`), pagination, requêtes
   N+1, champs sur-sélectionnés.
3. Si le dépassement persiste → activer le snapshot du read model concerné :
   ```bash
   php artisan accounting:reporting-snapshot <company> \
     --from=2026-08-01 --to=2026-08-31 --sync
   # ou en async (job tenant-scoped) :
   php artisan accounting:reporting-snapshot <company>
   ```
4. Mettre à jour `performance-budgets.json` (notes) avec la stratégie
   snapshot et re-mesurer.

## Schéma et versionnage

- Table tenant `accounting_reporting_snapshots`
  (migration `2026_08_30_000003_6243_create_accounting_reporting_snapshots_table.php`,
  additive et idempotente) ;
- clé unique `(company_id, report, period_from, period_to)` ;
- `version` : incrémentée **uniquement** quand le contenu change — deux
  recomputes identiques produisent le même résultat ET la même version
  (idempotence, exigence BC-22) ;
- `refreshed_at` : horodatage de la dernière exécution, exposé à l'API
  (`data.snapshot.refreshed_at`) pour que le consommateur connaisse l'âge de
  la donnée ;
- `payload` JSONB : agrégats du read model (jamais de données nominatives —
  pas de PII inutile).

## Recompute (job)

`RecomputeAccountingReportingSnapshotJob` :
- **tenant-scoped** (`TenantScopedJob` + `EnsureTenantContext` : `search_path`
  + `current_company` posés pour l'exécution) — zéro fuite cross-tenant ;
- retry borné (`tries: 3`) et timeout 120 s ;
- observable : log structuré corrélé `reporting.snapshot.recomputed`
  (`company_id`, `report`, période).

## Rollback

- **Désactivation** : rien à déployer — supprimer les lignes du snapshot
  concerné (le read model retombe à la volée, déterministe) :
  ```bash
  php artisan tinker --execute="App\Modules\Accounting\Domain\Models\AccountingReportingSnapshot::query()->where('company_id', '<uuid>')->delete()"
  ```
- **Migration** : additive — `down()` ne supprime que la table snapshot
  (aucune donnée transactionnelle touchée) ;
- **Données** : le snapshot est **recalculable** — une corruption éventuelle
  se répare par un recompute (`--sync`), aucune restauration de backup requise
  pour cette table.

## Tests

`AccountingReportingSnapshotTest` :
- recompute idempotent (deux recomputes → même payload, même version) ;
- version incrémentée quand le contenu change ;
- `refreshed_at` exposé par l'API (`GET /accounting/dashboard`) ;
- isolation cross-tenant (le snapshot de A n'est pas visible de B).
