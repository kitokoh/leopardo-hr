# CI Report — Leopardo RH

- Workflow: Tests - Leopardo RH
- Run ID: 29148494363
- Run Number: 2461
- Branch/Ref: refs/heads/main
- Commit: 1bf5075f905bf3d30c2e981f79c008f1b4d77ae3
- Actor: kitokoh
- URL: https://github.com/kitokoh/leopardo-hr/actions/runs/29148494363

## Job Results
- Backend Tests: failure
- Backend Security: success
- Backend Quality: success
- Backend Coverage: failure
- Governance Gates: skipped
- Dependency Review: skipped

## Scenario References
- Test registry: docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md
- Backend API scenarios: docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md
- Mobile Flutter scenarios: docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md
- Web admin scenarios: docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md
- Backend quality artifacts: backend-quality-reports
- Backend quality summary artifact: backend-quality-summary
- Backend coverage artifacts: backend-coverage-reports
- Backend coverage summary artifact: backend-coverage-summary
- Mobile quality artifacts: mobile-test-reports
- Mobile quality summary artifact: mobile-quality-summary

## Backend Coverage Intent
- Unit: services et logique metier pure
- Feature: auth, auth guardrails, RBAC, multitenant, attendance, estimation, health, contrats JSON mobile, SmartAttendance (GPS geofencing, validation manager, multi-tenant isolation)
- Gate strategy: visible immediately, threshold progressive via BACKEND_COVERAGE_MIN

## Quality Notes
- PHP static analysis: PHPStan + Larastan with versioned baseline support
- Backend coverage: clover + HTML artifact + threshold summary
- Mobile tests: machine-readable output + coverage summary artifact
- Deploy policy: main deploy is allowed only after the required workflows for the same SHA conclude successfully

## Backend Quality Summary
- Composer validate: success
- Composer install: success
- Pint: success
- PHP syntax lint: success
- PHPStan/Larastan: success
- PHPStan findings captured: 1
- PHPStan scope: full backend scope
- Baseline support: api/phpstan.neon + api/phpstan-baseline.neon

## Backend Coverage Summary
- Threshold configured: 40%
- Backend statement coverage: 58.1% (14332/24666)
- HTML artifact: api/storage/coverage-html/
- Clover artifact: api/storage/test-results/clover.xml
