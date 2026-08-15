# Tasks — #3810 Exception renderer sanitization

## T1 — Renderer global (`api/bootstrap/app.php`)
- [x] Détection de signature interne (SQLSTATE, chemins, traces, getMessage()).
- [x] Mapping statut → code stable (400/401/403/409/422/429/500/503).
- [x] `localized_message` via `lang/*/errors.php` + Log détaillé serveur.
- [x] Headers de throttling préservés dans les deux branches.

## T2 — Catalogues d'erreurs (`api/lang/*/errors.php`)
- [x] Codes BAD_REQUEST, CONFLICT, VALIDATION_FAILED, TOO_MANY_REQUESTS,
      SERVICE_UNAVAILABLE, HTTP_ERROR × 4 locales.

## T3 — Sites Payroll `abort(422, getMessage())`
- [x] TaxSlabController::submit → log + `rate_submit_failed`.
- [x] SocialContributionController::submit → log + `rate_submit_failed`.
- [x] RateValidationAdminController::approve → log + `rate_approve_failed`.
- [x] RateValidationAdminController::reject → log + `rate_reject_failed`.
- [x] Nouvelles clés payroll × 4 locales.

## T4 — Test de régression
- [x] `tests/Feature/Security/ExceptionRendererSanitizationTest` (5 scénarios).

## T5 — Validation
- [ ] `php -l` sur les fichiers modifiés.
- [ ] CI : PHPStan strict, Backend Coverage, tests Feature.

## T6 — Livraison
- [x] Entrée CHANGELOG (Closes #3810).
- [ ] PR `fix/3810-exception-renderer` → `main`, `Closes #3810`.
