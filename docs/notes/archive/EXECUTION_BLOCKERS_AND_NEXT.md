# EXECUTION BLOCKERS AND NEXT ACTIONS
# Version 4.1.29 | 2026-04-09

This file tracks what still cannot be solved by documentation alone.

## Current hard blockers

1. La commande Docker locale officielle de test backend n'est pas encore versionnée dans le repo.
2. Sur cette machine, `docker context ls` répond mais `docker version` / `docker ps` expirent : Docker doit être stabilisé avant d'en faire le validateur principal.
3. GitHub branch protection must be configured in repo settings (external to local files).

## Immediate next actions (ordered)

1. Versionner la méthode Docker officielle de validation backend (`docker compose ...` ou `vendor/bin/sail ...`).
2. Faire répondre `docker version` et `docker ps` localement sans timeout.
3. Utiliser `docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md` avant chaque PR backend significative.
4. Configure branch protection using `.github/BRANCH_PROTECTION_REQUIRED.md`.
5. Run one staging restore drill and log in `RUNBOOK_DRILLS_LOG.md`.
6. Run one staging rollback drill and log in `RUNBOOK_DRILLS_LOG.md`.

## Ready check before starting feature tickets

- `INDEX_CANONIQUE.md` read
- `BACKLOG_PHASE1_UNIQUE.md` ticket selected
- `tools/check-governance.ps1` passes
- Backend validé localement via Docker ou blocage Docker explicitement noté
