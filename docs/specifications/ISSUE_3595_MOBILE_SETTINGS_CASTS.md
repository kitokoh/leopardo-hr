# Mini-spécification — Issue #3595

## Objectif

Remplacer, dans les repositories Settings des trois apps Flutter mobiles (`leopardo_manager`, `leopardo_employee`, `leopardo_hr`), le cast direct `['data'] as Map` par l'appel au helper partagé `extractDataMap()`.

## Constat

`loadNotificationPreferences()` et `saveNotificationPreferences()` de `settings_repository.dart` faisaient :

```dart
NotificationPreferences.fromJson(
  ((response.data ?? const <String, dynamic>{})['data'] as Map)
      .cast<String, dynamic>(),
);
```

Ce pattern duplique manuellement ce que fait déjà `extractDataMap()` (`leopardo_core/lib/core/api/api_payload.dart`), en moins sûr :

- `as Map` lève un `TypeError` non capturé si la clé `data` est absente, `null`, ou d'un autre type (ex. `List`), au lieu du fallback `<String, dynamic>{}` silencieux d'`extractDataMap`.
- Il ne gère pas l'enveloppe `{ data: { item: {...} } }` (pattern `item` déjà supporté par `extractDataMap` pour d'autres endpoints).
- C'est le seul endroit des trois repositories Settings qui n'utilise pas le helper — les 8 autres appels du même fichier (career, cabinet stats, QR payload, password, biometric…) passaient déjà par `extractDataMap(response.data)`.

Six sites identifiés (2 par app × 3 apps) :

| App | Fichier | Lignes (avant fix) |
| --- | --- | --- |
| `leopardo_manager` | `lib/features/settings/data/settings_repository.dart` | 113, 129 |
| `leopardo_employee` | `lib/features/settings/data/settings_repository.dart` | 113, 129 |
| `leopardo_hr` | `lib/features/settings/data/settings_repository.dart` | 66, 82 |

## Périmètre

Remplacement mécanique, sans changement de comportement fonctionnel attendu :

```dart
NotificationPreferences.fromJson(
  extractDataMap(response.data),
);
```

`api_payload.dart` (donc `extractDataMap`) était déjà importé dans les trois fichiers pour les autres méthodes du repository — aucun nouvel import nécessaire.

Hors périmètre : `leopardo_platform_admin/lib/src/features/platform/platform_models.dart` utilise `(json['data'] as Map?)?.cast<String, dynamic>() ?? json` dans des factories de modèles (`fromJson`), pas dans un repository — pattern différent (nullable-safe, fallback sur `json` complet plutôt que map vide) et hors scope de cette issue (settings repositories uniquement).

## Critères d'acceptation

1. Aucune occurrence de `['data'] as Map` dans les repositories `settings_repository.dart` des trois apps mobiles.
2. `loadNotificationPreferences()` et `saveNotificationPreferences()` utilisent `extractDataMap(response.data)` dans les trois apps.
3. Comportement inchangé pour les réponses API bien formées ; en cas de réponse malformée, fallback silencieux (map vide) au lieu d'un `TypeError`.
4. Aucun changement de signature publique des repositories.

## Fichiers concernés

- `front/mobile_apps/leopardo_manager/lib/features/settings/data/settings_repository.dart`
- `front/mobile_apps/leopardo_employee/lib/features/settings/data/settings_repository.dart`
- `front/mobile_apps/leopardo_hr/lib/features/settings/data/settings_repository.dart`
- `docs/specifications/ISSUE_3595_MOBILE_SETTINGS_CASTS.md`
- `CHANGELOG.md`

## Plan de retour arrière

Réversion du commit de l'issue #3595. Changement purement local à trois méthodes, sans migration de données ni de schéma.

## Trace Spec Kit

Issue : #3595
Branche : `fix/3595-mobile-settings-casts`
Date : 2026-08-15
