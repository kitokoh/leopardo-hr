## Plan technique
1. Gate dans `PayrollRunController::calculate()` (avant `status=calculating`) — pattern copié des contrôleurs de simulation.
2. OpenAPI : requestBody acknowledge_placeholder (2 miroirs).
3. Tests : bindFakePayrollCalculator($confidenceLevel) + 2 tests.
4. CHANGELOG + PR.
