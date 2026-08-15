## Plan technique
1. `PayrollRunController@calculate` : après le garde de statut, résoudre `$this->calculator->getRules($payrollRun->country_code)` ; si `placeholder` et `acknowledge_placeholder` absent → 422 (message `payroll.placeholder_acknowledge_required`) ; si accepté → `AuditLog::create(...)` (mêmes champs que les simulations + `context`/`run_id`). La garde s'exécute AVANT `$payrollRun->update(['status' => 'calculating'])`.
2. `PayrollRunControllerTest` : `bindFakePayrollCalculator()` étendu avec un stub `getRules` (paramètre `confidenceLevel`, défaut `production`).
3. Nouveaux tests : 422 sans confirmation (run reste draft, pas d'audit, calculateRun non appelé) ; 200 avec confirmation (audit créé) ; 200 non-placeholder (aucun audit).
4. OpenAPI : requestBody `acknowledge_placeholder` sur POST `/payroll-runs/{payrollRun}/calculate` + réponse 422.
5. CHANGELOG + PR `fix/2332-run-placeholder-gate` (`Closes #2332`).
