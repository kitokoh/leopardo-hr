# Enterprise i18n - Leopardo RH

`shared/i18n/locales/*.json` is the single source of truth for multilingual product text that is meant to be shared between backend, web and mobile.

## Layout

- `locales/`: canonical locale catalogs
- `glossary/`: locked business terminology
- `schemas/`: JSON schema for locale catalogs
- `versions/`: checksums, variants and rollout metadata
- `validators/`: validation scripts used by CI
- `sync/`: generators for backend, web and mobile targets
- `analytics/`: event contract for observability

## Operating model

1. edit canonical locale JSON
2. run validation
3. run sync scripts
4. commit generated outputs together
5. let CI block invalid catalogs

## Translator workflow

External translation work should follow `docs/GUIDES/GUIDE_JULES_TRADUCTION_MULTILINGUE.md`.

Translators should edit only locale catalogs and ARB/JSON translation files. They should not modify application components, controllers, routes, tests or generated localization code. Hardcoded text found in code should be reported as i18n debt and migrated by an engineering agent.

## Debt tracking

Use:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File dev-hub/tools/validate-i18n-debt.ps1
```

The script writes `docs/validation/I18N_DEBT_REPORT_2026_06_06.md` by default and groups hardcoded text signals by surface. Use `-Strict` only after the P1 backlog is intentionally reduced.
