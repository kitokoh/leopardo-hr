# Feature Specification: Edge — schéma SQLite jamais provisionné (Closes #4411)

**Feature Branch**: `fix/4411-edge-sqlite-schema`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4411 (P1, ops, edge-sync — pointage hors-ligne mort en silence)

## Contexte

L'installation Edge ne provisionne jamais le schéma SQLite :
- `edge/docker-entrypoint.edge.sh:85` lance `migrate --path=database/migrations/edge`
  mais le dossier n'existe pas → « rien à migrer » en silence ;
- l'image publiée (`Dockerfile.publish`, CMD supervisord) ne migre pas du tout ;
- `/api/v1/edge/health` reste vert (pas de probe DB) pendant que le daemon
  boucle sur `no such table: sync_queue` → pointage hors-ligne mort en silence.

## User Stories & Testing

### User Story 1 — Un nœud frais démarre avec un schéma prêt (P1)

**Acceptance Scenarios**:
1. Given install.sh + compose sur nœud vierge, When premier démarrage,
   Then `edge.sqlite` contient edge_nodes/sync_logs/sync_queue/edge_licenses.
2. Given schéma absent, When `GET /edge/readiness`, Then 503 `edge_schema_missing`.
3. Given schéma présent, When `GET /edge/readiness`, Then 200 `schema: provisioned`.
4. Given `/edge/health`, Then 200 sans dépendance DB (liveness offline intact).

## Requirements

### Functional Requirements

- **FR-001**: `api/database/migrations/edge/2026_06_29_000001_create_edge_sqlite_tables.php`
  (miroir SQLite-safe de la migration tenant, 4 tables).
- **FR-002**: `supervisord.edge.conf` — programme `edge-migrate` (priority 1,
  autostart, autorestart=false) dans l'image publiée.
- **FR-003**: service compose edge-sync : migrate idempotent avant le daemon.
- **FR-004**: `GET /api/v1/edge/readiness` (probe `SELECT 1 FROM sync_queue`),
  healthchecks compose + Dockerfile.publish → readiness.
- **FR-005**: test `EdgeReadinessTest` (health DB-free + readiness ok/not_ready).

## Success Criteria

- **SC-001**: EdgeReadinessTest vert ; EdgeDownloadControllerTest intact.
- **SC-002**: aucun `no such table` dans les logs d'un nœud frais (smoke Docker).
- **SC-003**: PHPStan strict vert, Pint propre.
