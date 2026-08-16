# ISSUE_3966 — entrypoint edge : échecs avalés (`|| true`, /dev/null)

**Statut**: Fixed (PR `fix/3966-edge-entrypoint-fail-visible`) · **Priorité**: P3 · **Module**: edge-sync

## Correctif

`edge/docker-entrypoint.edge.sh` :
- migrations (critique) → `run_critical` : exit 1 + état dans
  `/data/edge-entrypoint-error` (restart loop visible) ;
- caches (non bloquant) → `run_soft` : log + état persisté ;
- scheduler → logs dans `/data/logs/edge-scheduler.log`.
Vérifié : `sh -n` OK.
