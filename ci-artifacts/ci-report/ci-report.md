# CI Report — Leopardo RH

- Workflow: Tests - Leopardo RH
- Run ID: 27884355035
- Run Number: 2004
- Branch/Ref: refs/pull/770/merge
- Commit: 7a98490638bf9b10e92f8925f53be931e8fc3b41
- Actor: kitokoh
- URL: https://github.com/kitokoh/leopardo-hr/actions/runs/27884355035

## Job Results
- Backend Tests: failure
- Backend Security: failure
- Backend Quality: failure
- Backend Coverage: failure
- Governance Gates: failure
- Dependency Review: success

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
- Feature: auth, auth guardrails, RBAC, multitenant, attendance, estimation, health, contrats JSON mobile
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
- PHPStan/Larastan: failure
- PHPStan findings captured: 3
- PHPStan scope: api/app/Http/Middleware/PartnerLinkMiddleware.php,api/routes/api.php,api/routes/console.php,api/routes/modules/growth.php,api/routes/web.php
- Baseline support: api/phpstan.neon + api/phpstan-baseline.neon

## Backend Coverage Summary
- Threshold configured: 60%
- Backend statement coverage: 62.55% (13334/21316)
- HTML artifact: api/storage/coverage-html/
- Clover artifact: api/storage/test-results/clover.xml
