# Mini-spécification — Issue #3612

## Objectif

Ramener le nombre d'inputs `workflow_dispatch` de `.github/workflows/k6-load-smoke.yml` à ≤ 10 (limite GitHub Actions), afin que le dispatch manuel du workflow ne soit ni refusé par l'API GitHub ni partiellement ignoré, et que le prochain bump d'actionlint ne fasse pas échouer le check `actionlint (+ shellcheck)`.

## Constat

Le workflow déclarait 11 inputs (`base_url`, `health_vus`, `manager_vus`, `employee_vus`, `duration`, `run_attendance_punch_scale`, `attendance_punch_mode`, `attendance_stage_duration`, `run_payroll_progressive_scale`, `payroll_stage_duration`, `payroll_allow_mutations`). La règle actionlint `events` (introduite en 1.7.7) refuse > 10 inputs ; la CI épinglait une version plus ancienne et laissait la dette latente.

## Décision

`attendance_punch_mode` est l'input le moins critique : il ne sélectionne qu'un mode interne du script `attendance-punch-scale.js` (défaut `manual`), sans lien avec un secret ni un environnement. Il est retiré du bloc `inputs` et remplacé par une variable d'environnement `PUNCH_MODE: manual` codée en dur dans le job — comportement strictement identique au défaut précédent.

## Critères d'acceptation

1. Le bloc `workflow_dispatch.inputs` contient exactement 10 entrées.
2. Le job `attendance-punch-scale` reçoit toujours `PUNCH_MODE` (valeur `manual`) via l'environnement.
3. `actionlint` (dernière version) passe sur le workflow : 0 erreur `events`.
4. `python3 -c "import yaml; yaml.safe_load(...)"` OK et `git diff --check` OK.

## Plan de retour arrière

Réversion du commit ; aucun secret ni environnement n'est modifié.
