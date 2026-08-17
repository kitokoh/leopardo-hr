# Spec Kit — Cleanup fiable des runs orphelins

## Contexte

Le workflow `CI Queue — Orphan Runs Cleanup` était présent mais son script pouvait échouer avant toute annulation. GitHub CLI injectait des séquences ANSI dans les réponses JSON, et les endpoints paginés renvoyaient plusieurs tableaux concaténés que `jq` ne pouvait pas parser directement.

## Objectif

Rendre le nettoyage automatique réellement exécutable en cron, lors de la fermeture d’une PR et en déclenchement manuel, sans annuler les runs de `main`, des branches vivantes ou des PR ouvertes.

## Changement

Le script `dev-hub/tools/cancel-orphan-runs.sh` normalise les réponses API avant `jq` et agrège les pages avec `jq -s`. La logique de protection des branches reste inchangée : seuls les runs orphelins et les runs queued supersédés sont éligibles.

## Critères d’acceptation

- `bash -n dev-hub/tools/cancel-orphan-runs.sh` réussit.
- Le dry-run parcourt les branches, PRs et runs paginés sans erreur JSON.
- `--dry-run` n’annule rien.
- Le mode réel annule uniquement les runs identifiés comme orphelins/supersédés.
- `main` et les branches des PR ouvertes restent protégés.

## Validation réalisée

Le dry-run a identifié quatre runs orphelins et le mode réel les a annulés. Aucun run de `main` ni de PR ouverte n’a été ciblé.
