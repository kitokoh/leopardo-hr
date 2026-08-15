# Mini-spécification — Issue #3238

## Objectif

Éliminer le 500 sur double soumission concurrente (double-tap mobile, retry) des évaluations et des lignes de paie : la course entre `exists()` et `create()` doit être rattrapée par la contrainte unique DB et transformée en réponse 422 / exception métier explicite, jamais en QueryException brute.

## Constat

- `EvaluationController::store` (l.104-118) : `Evaluation::where(...)->exists()` puis `Evaluation::create(...)` — fenêtre de course.
- `PayrollService::create` (l.24-44) : `Payroll::where(...)->exists()` puis `Payroll::create(...)` — même fenêtre.
- Les contraintes uniques DB existent déjà (migration tenant `2026_04_01_000104` : `unique(employee_id, period, evaluator_id)` sur evaluations ; `unique(employee_id, period_year, period_month)` sur payrolls) — la course actuelle lève donc un `QueryException` (SQLSTATE 23505) → 500 au lieu du 422 métier.

## Décision

1. `EvaluationController::store` : `try/catch QueryException` → si `errorInfo[1] === 23505`, renvoyer la même 422 `EVALUATION_ALREADY_EXISTS` que le chemin séquentiel ; sinon relancer.
2. `PayrollService::create` : extraction d'une méthode privée `persist()` ; `try/catch QueryException` → si 23505, `throw PayrollPeriodConflictException($month, $year)` ; sinon relancer.

## Critères d'acceptation

1. Double soumission concurrente évaluation → 422 `EVALUATION_ALREADY_EXISTS` (pas de 500).
2. Double soumission concurrente paie → `PayrollPeriodConflictException` (pas de 500).
3. Chemin séquentiel inchangé (tests existants `EvaluationWorkflowTest`, `PayrollServiceTest` verts).
4. PHPStan Strict level 8 : 0 erreur.

## Plan de retour arrière

Réversion du commit ; aucune migration ni donnée n'est touchée (les contraintes uniques existaient déjà).
