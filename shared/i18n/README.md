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
