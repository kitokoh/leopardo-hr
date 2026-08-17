# Spec Kit — Checkout avant actions locales CI

## Contexte

Le run `E2E - Playwright Prod Smoke` du SHA courant de `main` échouait avant les probes avec `Can't find 'action.yml' ... Did you forget to run actions/checkout before running your local action?`. Les workflows E2E et OWASP appelaient `.github/actions/deploy-gate` dans `should-run` avant d’avoir checkout le dépôt.

## Objectif

Garantir que les actions locales utilisées par les gates de déploiement sont toujours résolues avant exécution, sans récupérer l’historique inutilement ni modifier le comportement des contrôles.

## Changement

Ajouter un checkout `actions/checkout` en lecture seule avec `fetch-depth: 1` et `lfs: false` dans les jobs `should-run` de `e2e-staging.yml` et `owasp-zap.yml`, immédiatement avant `.github/actions/deploy-gate`.

## Critères d’acceptation

- E2E et ZAP chargent `deploy-gate` sans erreur `action.yml` manquant.
- Le checkout reste limité au dépôt et à un historique shallow.
- Le gate conserve ses permissions `contents: read` et `actions: read`.
- Les smoke tests HTTP et le scan ZAP restent inchangés.
- `git diff --check` et actionlint passent dans la CI.
- Le workflow de déploiement ne produit plus de faux rouge causé par le chargement d’action locale.
