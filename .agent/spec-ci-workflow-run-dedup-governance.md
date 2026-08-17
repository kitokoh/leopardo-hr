# Spec Kit — Déduplication des workflows E2E et OWASP ZAP

## Contexte

La file GitHub Actions a accumulé des dizaines de runs identiques pour un même SHA de `main`. Les workflows `E2E - Playwright Prod Smoke` et `OWASP ZAP Baseline` sont déclenchés par `workflow_run` et `workflow_dispatch`, mais leur `cancel-in-progress` était conditionné à `github.event_name == 'pull_request'`. Cette condition ne pouvait jamais être vraie pour leurs déclencheurs automatiques et laissait les doublons s’empiler.

## Objectif

Garantir un seul run actif par workflow et par SHA de déploiement, tout en conservant les runs de SHA différents et les exécutions manuelles les plus récentes.

## Changement

- Le groupe E2E devient `e2e-staging-${{ github.event.workflow_run.head_sha || github.sha }}`.
- Le groupe ZAP devient `owasp-zap-${{ github.event.workflow_run.head_sha || github.sha }}`.
- Les deux workflows utilisent `cancel-in-progress: true` pour remplacer un doublon du même SHA.

## Critères d’acceptation

- Les deux fichiers passent `git diff --check` et la validation `actionlint` en CI.
- Une nouvelle exécution automatique du même SHA annule l’ancienne au lieu de créer une seconde exécution concurrente.
- Les exécutions de SHA différents ne s’annulent pas entre elles.
- Les workflows de sauvegarde, release et distribution mobile ne sont pas modifiés par cette PR.
- La file reste à zéro run queued/pending après le nettoyage opérationnel, hors nouveaux événements légitimes.

## Validation locale

La cohérence whitespace est validée localement. `actionlint` n’est pas installé dans le sandbox ; le check GitHub `actionlint (+ shellcheck)` constitue la validation syntaxique distante obligatoire.
