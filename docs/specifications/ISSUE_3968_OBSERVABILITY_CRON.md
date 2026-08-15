# ISSUE_3968 — launch-observability-smoke : runs qui s'empilent

**Statut**: Fixed (PR `fix/3968-observability-cron`) · **Priorité**: P3 · **Module**: CI

## Correctif

`cancel-in-progress: true` sur le groupe de concurrence : un run cron en
retard remplace le précédent au lieu de s'empiler (48 runs/jour × checkout
LFS complet sur une queue déjà saturée #3545).
