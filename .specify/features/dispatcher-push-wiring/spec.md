# Feature Specification: NotificationDispatcher — push FCM/APNs émis (SendPushNotificationJob)

**Feature Branch**: `fix/2252-dispatcher-push`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2252

## Contexte
`NotificationDispatcher::dispatch()` créait la notification in-app mais le TODO `// push to FCM/APNs via PushNotificationService when device tokens exist` n'était jamais câblé. Les tokens device sont enregistrés (`/device-tokens`) mais aucune notification push n'était émise sur ce chemin. (Le chemin moderne `CommunicationService` câble déjà le push ; ce dispatcher legacy est utilisé par `NotifyTaxRateValidation` via `SendNotification`.)

## User Stories & Testing

### User Story 1 — Une notification in-app déclenche aussi le push (P1)
**Acceptance Scenarios**:
1. Given un dispatch (userId, type, titre, body, data), When dispatch(), Then la notification in-app est créée ET `SendPushNotificationJob` est dispatché (best-effort, no-op si aucun token Firebase).
2. Given `actionUrl` fourni, When dispatch(), Then `action_url` est fusionné dans les métadonnées du job.

### User Story 2 — Un échec de push ne casse jamais la notification in-app (P1)
**Acceptance Scenarios**:
1. Given une exception au dispatch du job, When dispatch(), Then l'exception est journalisée (`notification.push-dispatch-failed`) et la notification in-app reste créée.

## Requirements
- **FR-001**: `NotificationDispatcher::dispatch()` DOIT dispatcher `SendPushNotificationJob` (userId, titre, body, métadonnées) après la création de la notification, dans un try/catch best-effort.
- **FR-002**: `action_url` DOIT être ajouté aux métadonnées du push quand présent.
- **FR-003**: Aucun changement du chemin moderne (CommunicationService).

## Success Criteria
- **SC-001**: Nouveaux tests Feature : création in-app + `Queue::assertPushed(SendPushNotificationJob)` avec métadonnées (avec et sans action_url).
- **SC-002**: `php -l` vert ; CI backend vert.
