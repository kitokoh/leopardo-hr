# Feature Specification: API — classes DDD mortes supprimées (Closes #4699)

**Feature Branch**: `fix/4699-dead-ddd-classes`
**Created**: 2026-08-17 | **Status**: In progress
**Issue**: #4699 (P3, api, tech-debt)

## Contexte

Audit 360° 2026-08-16 (swe-qa-360). Recherche d'imports à l'échelle du repo :
plusieurs classes DDD/mails/middleware/jobs ne sont référencées nulle part
(production + tests), la logique réelle vivant dans les services canoniques
(AttendanceService, ZktecoIntegrationService, …).

## Fichiers supprimés

**Actions/DTOs (modules)** :
- `app/Modules/Attendance/Application/Actions/{ProcessCheckIn,ProcessCheckOut,SyncZKTeco}.php`
- `app/Modules/Billing/Application/Actions/ProcessWebhook.php`
- `app/Modules/Billing/Application/DTOs/{CreateSubscriptionDTO,InvoiceDTO}.php`

**Mails jamais référencés** :
- `app/Mail/{InvoiceSentMail,WelcomeEmployeeMail,SubscriptionConfirmedMail,LicenseExpiringMail,PaymentFailedMail,TrialExpiringMail,WelcomeOnboardingMail}.php`
- + 4 vues Blade orphelines référencées uniquement par ces mails morts :
  `resources/views/emails/{invoice-sent,payment-failed,trial-expiring,welcome-onboarding}.blade.php`

**Middleware/Jobs/Services** :
- `app/Http/Middleware/SentryPerformanceMiddleware.php` (jamais enregistré dans
  bootstrap/app.php)
- `app/Jobs/SendBulkNotificationsJob.php` (référencé uniquement par
  `tests/Feature/QueueJobsTest.php` → 3 cas de test retirés avec le job)
- `app/Core/Feature/Infrastructure/Services/FeatureService.php`

**Baselines PHPStan** :
- `phpstan-modules.neon` : entrée ignore ProcessCheckIn retirée.
- `phpstan-strict-baseline.neon` : 10 entrées (path des classes supprimées)
  retirées (réduction baseline dans le bon sens).

## User Stories & Testing

### User Story 1 — Aucune référence cassée (P3)

En tant que mainteneur, je veux supprimer le code mort sans rien casser.

**Acceptance Scenarios**:
1. Given les fichiers supprimés, When on cherche leurs noms de classe,
   Then 0 référence dans app/tests/config/routes/database/resources.
2. Given le job supprimé, When la suite Feature tourne, Then
   `QueueJobsTest` est vert (cas SendBulkNotifications retirés).
3. Given les baselines, When PHPStan tourne, Then aucune entrée stale
   (reportUnmatchedIgnoredErrors=false ne bloque pas, mais les entrées sont
   bien nettoyées).

## Requirements

- **FR-001**: suppression des 16 fichiers + 4 vues, zéro référence résiduelle.
- **FR-002**: `QueueJobsTest` nettoyé (import + 3 méthodes SendBulkNotifications).
- **FR-003**: baselines PHPStan nettoyées (modules + strict).
- **FR-004**: php -l vert sur les fichiers modifiés ; CHANGELOG.md à jour.

## Success Criteria

- **SC-001**: `rg "ProcessCheckIn|SendBulkNotificationsJob|InvoiceSentMail" api/` → 0.
- **SC-002**: `git diff --stat` ≈ 950 suppressions, 0 insertion hors CHANGELOG.
- **SC-003**: suite Unit/Feature des zones touchées verte (CI).
