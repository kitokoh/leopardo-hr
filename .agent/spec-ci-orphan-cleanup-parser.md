# Spec Kit — Cleanup fiable des runs orphelins

## Contexte

Le workflow `CI Queue — Orphan Runs Cleanup` était présent mais son script pouvait échouer avant toute annulation. GitHub CLI injectait des séquences ANSI dans les réponses JSON, et les endpoints paginés renvoyaient plusieurs tableaux concaténés que `jq` ne pouvait pas parser directement.

## Objectif

Rendre le nettoyage automatique réellement exécutable en cron, lors de la fermeture d’une PR et en déclenchement manuel, sans annuler le run le plus récent de `main` ou d’une PR ouverte.

## Changement

Le script `dev-hub/tools/cancel-orphan-runs.sh` normalise les réponses API avant `jq` et agrège les pages avec `jq -s`. La protection des branches reste stricte : `main` et les têtes de PR ouvertes ne sont jamais traités comme orphelins. Pour les runs queued supersédés, le script conserve le plus récent par couple branche/workflow, y compris sur `main`, et annule uniquement les copies plus anciennes.

## Critères d’acceptation

- `bash -n dev-hub/tools/cancel-orphan-runs.sh` réussit.
- Le dry-run parcourt les branches, PRs et runs paginés sans erreur JSON.
- `--dry-run` n’annule rien.
- Le mode réel annule uniquement les runs identifiés comme orphelins/supersédés.
- `main` et les branches des PR ouvertes restent protégés contre le nettoyage orphelin.
- Sur chaque couple branche/workflow, le run queued le plus récent reste conservé, y compris sur `main`.

## Validation réalisée

Le dry-run a identifié des runs orphelins et des doublons queued supersédés. Le mode réel a annulé uniquement des copies obsolètes ; le run le plus récent de chaque couple branche/workflow, ainsi que les runs actifs des PR ouvertes, ont été conservés.
