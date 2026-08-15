# Tasks: Kiosk bridge local — durcissement

**Spec**: `.specify/features/kiosk-bridge-local-hardening/spec.md`

- [x] T1. Bridge : token de session local + injection HTML + guard `/local/*` (#3586)
- [x] T2. Bridge : allowlist statique + confinement `relative_to` (#3586)
- [x] T3. Bridge : guards Content-Type/Origin sur POST (#3586)
- [x] T4. Bridge : validation enums à l'insertion `/local/punch` (#3588)
- [x] T5. Bridge : migration douce `retry_count`/`next_retry_at` + dead-letter + backoff (#3588)
- [x] T6. Bridge : 4xx → isolation poison via erreurs `events.<i>.*`, 5xx → retry (#3588)
- [x] T7. Bridge : traitement `skipped[]` serveur + fallback legacy sans marquage global (#3587)
- [x] T8. API : `syncPunches` retourne processed+skipped avec `Log::warning` (#3587)
- [x] T9. API : `doSync` expose `skipped`/`skipped_count` + OpenAPI (#3587)
- [x] T10. Front : `localFetchJson` dans `app.js`/`admin.js` (#3586)
- [x] T11. Tests Python `test_bridge_security.py` + step CI kiosk (#3586/#3587/#3588)
- [x] T12. Test PHP `KioskSyncSkippedEventsTest` (#3587)
- [x] T13. CHANGELOG + README kiosk (auth locale) + statut spec
