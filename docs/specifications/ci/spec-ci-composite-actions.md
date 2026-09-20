# Spec Kit — Extraire les duplications CI en actions composites (#4723)

## Contexte

Issue #4723 (audit 360° 2026-08-16, P3 CI) : trois logiques CI copiées-collées :
- « Check deployment status » (should-run E2E/ZAP) : ~45 lignes identiques dans `e2e-staging.yml` et `owasp-zap.yml`.
- « Verify required workflow conclusions » (gate de déploiement) : ~180 lignes dans `deploy-main.yml` et ~110 dans `deploy-staging.yml` (variante simplifiée).
- « Verify Firebase App Distribution release is visible » : ~100 lignes dans `mobile-distribute.yml` et `mobile-distribute-main.yml`.

## Objectif

Une seule implémentation de chaque logique, comportement inchangé.

## Changement

- `.github/actions/deploy-gate/action.yml` : should-run E2E/ZAP (issues #1833) — sortie `result` ; gardes fail-closed #4720 conservées dans les workflows.
- `.github/actions/verify-deploy-workflows/action.yml` : gate de déploiement (#1705, #3545) — inputs `sha`, `required_workflows` (JSON), `web_changed` ; sorties `tests_conclusion`, `web_conclusion`, `should_deploy`.
- `dev-hub/tools/verify-firebase-readback.sh` : readback Firebase App Distribution — script partagé.
- Workflows migrés : `e2e-staging.yml`, `owasp-zap.yml`, `deploy-main.yml`, `deploy-staging.yml`, `mobile-distribute.yml`, `mobile-distribute-main.yml` (−~390 lignes).

## Critères d'acceptation

1) `grep github-script` dans les workflows : plus aucune copie du script should-run / gate (uniquement dans les actions).
2) Sorties consommées inchangées (`needs.should-run.outputs.run_e2e/run_scan`, `steps.workflow_gate.outputs.*`).
3) Tous les YAML passent `python -c yaml.safe_load` et `git diff --check`.
4) Le check CI `actionlint (+ shellcheck)` est vert sur la PR.

## Validation locale

- `yaml.safe_load` OK sur les 8 fichiers YAML modifiés.
- `git diff --check` propre.
- `actionlint` non installable dans le sandbox (téléchargement GitHub bloqué) — le check distant reste la validation syntaxique obligatoire.
