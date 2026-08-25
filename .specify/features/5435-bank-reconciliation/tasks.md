# Tasks — #5435 Rapprochement bancaire (Phase D)

Branche : `mod/accounting/5435-bank-reconciliation` · Protocole #2400 · Spec : `spec.md`

## Implémentation

- [ ] T1. Migration `api/database/migrations/tenant/2026_08_25_000003_5435_create_bank_statement_tables.php` — tables `bank_statements` + `bank_statement_lines` + colonne `accounting_settings.bank_statement_mapping` (additive, idempotente, réf. issue #5431)
- [ ] T2. Modèles `BankStatement` + `BankStatementLine` (`BelongsToCompany`, casts, relations, scopes status)
- [ ] T3. Enum `BankStatementStatus` (`imported|reconciling|reconciled`) + `BankStatementLineStatus` (`pending|matched`)
- [ ] T4. `BankStatementImportService` — parsing CSV (mapping paramétrable, séparateur, date, signe), validation ligne à ligne, idempotence unique `(company, period, import_reference)` → 409, `errors[]`
- [ ] T5. `BankReconciliationService` — matching heuristique (montant ± tolérance, date ± N jours, référence), score de confiance, idempotence, matching manuel, lettrage paiement (`PaymentRegistrationService::reconcile`)
- [ ] T6. `BankStatementController` + routes `api/routes/modules/accounting.php` (RBAC `api.manager:principal,comptable`) : import, index, show, reconcile, lines/{line}/match, status, export état CSV
- [ ] T7. Requests de validation (`ImportBankStatementRequest`, `MatchBankStatementLineRequest`)
- [ ] T8. i18n ×4 : clés `accounting.php` (validation/errors/status) + codes `errors.php`
- [ ] T9. Tests Feature : `BankStatementImportTest`, `BankReconciliationTest`, `BankReconciliationManualTest`, `BankStatementStatusTest` (+ isolation tenant, RBAC)
- [ ] T10. OpenAPI : paths + schemas dans `api/openapi.yaml`, `make openapi-sync`, guards `openapi-check` + coverage
- [ ] T11. CHANGELOG ×2 + `docs/architecture/COMPTABILITE_CONCEPTION.md` (§ rapprochement) + `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`
- [ ] T12. PR (1 commit final propre) + annonce sur #5435

## Gates locaux avant push

- [ ] `php8.4 vendor/bin/phpstan analyse` (strict + level-max) — fichiers neufs 0 occurrence baseline
- [ ] `php8.3 /tmp/pint.phar --test` fichiers modifiés
- [ ] `node dev-hub/tools/generate-openapi-sdk.mjs --check`
- [ ] `python3 dev-hub/tools/check-openapi-route-coverage.py --strict-staleness`
- [ ] `bash dev-hub/tools/check-migration-basename-collisions.sh`
- [ ] `python3 dev-hub/tools/check-accounting-i18n.py`
