# ISSUE_3954 — leopardo_core : TranslationSyncService mort, import cassé

**Statut**: Fixed (PR `fix/3954-dead-translation-sync`) · **Priorité**: P2 · **Module**: mobile-core

## Correctif

`front/mobile_apps/leopardo_core/lib/core/i18n/translation_sync_service.dart`
supprimé : importait `translation_catalog_cache.dart` (inexistant dans tout le
repo) et n'était référencé par aucune app (grep : 0). `dart analyze` du
package redevient exploitable.
