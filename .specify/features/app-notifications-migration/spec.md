# Feature Specification: API — migration manquante `app_notifications` (chemin legacy in-app)

**Feature Branch**: `fix/2398-app-notifications-migration`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2398 (dette #1813)

## Problème

La table `app_notifications` n'est créée par AUCUNE migration du repo alors que `NotificationDispatcher::dispatch()` fait `AppNotification::create(...)` — le chemin legacy (modèle AppNotification, action SendNotification, listener NotifyTaxRateValidation) écrit dans cette table. En prod, le listener est enveloppé dans un try/catch (`Log::warning(tax-rate.notification-inapp-failed)`) : échec silencieux, les notifications in-app de validation de taux ne partent jamais. Les tests créaient la table à la main.

## User Stories & Testing

### User Story 1 — Le chemin legacy redevient fonctionnel (P2)
**Acceptance Scenarios**:
1. Given la migration tenant exécutée, When `AppNotification::create(...)`, Then la ligne est persistée (schéma : id, user_id indexé, type, title, body, data jsonb, read, read_at, action_url, timestampsTz).
2. Given les tests, When exécution, Then aucun test ne crée plus `app_notifications` manuellement (les deux blocs manuels sont retirés).
3. Given la migration relancée, When `artisan migrate`, Then idempotente (garde `schemaTableExists`).

## Plan technique
1. Migration tenant `2026_08_15_000001_create_app_notifications_table.php` (schéma identique au manuel des tests, garde idempotente F-17).
2. Retirer le bloc `Schema::create` manuel de `TaxSlabValidationWorkflowTest`.
3. CHANGELOG + PR `fix/2398-...` `Closes #2398`.
