# Translation Events

## Product analytics events

- `i18n.locale_selected`
  - payload: `user_id`, `surface`, `requested_locale`, `resolved_locale`, `is_rtl`
- `i18n.locale_auto_resolved`
  - payload: `surface`, `device_locale`, `resolved_locale`
- `i18n.catalog_sync_started`
  - payload: `surface`, `requested_locale`, `known_version`, `known_checksum`
- `i18n.catalog_sync_completed`
  - payload: `surface`, `locale`, `version`, `checksum`, `source` (`remote` or `cache`)
- `i18n.catalog_sync_failed`
  - payload: `surface`, `locale`, `status_code`, `network_state`, `fallback_used`
- `i18n.translation_missing_key`
  - payload: `surface`, `locale`, `key`, `fallback_locale`, `module`
- `i18n.translation_glossary_override_detected`
  - payload: `locale`, `term`, `actual_value`, `expected_value`
- `i18n.translation_rtl_validation_failed`
  - payload: `locale`, `key`, `reason`

## Dashboards to prepare

- Active users by locale and surface
- Top modules by locale
- Missing key rate by release
- Remote catalog sync success rate
- Average age of locale catalogs on mobile
- Glossary drift incidents by locale
