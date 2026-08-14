# Feature Specification: Migration UNIQUE notification_preferences — étape de déduplication

**Feature Branch**: `fix/2268-notification-preferences-dedup`
**Created**: 2026-08-14 | **Status**: Draft → Implemented
**Issue**: #2268

## Contexte
`2026_08_03_000001_align_notification_preferences_unique_key.php` ajoute `UNIQUE(company_id, employee_id)` sans dédup. Doublons existants → échec migrate → boot container Render en échec.

## User Stories & Testing

### User Story 1 — La migration passe même avec des doublons (P1)
Un ops lance `php artisan migrate` sur un tenant avec des lignes dupliquées `notification_preferences` : la migration aboutit, la contrainte UNIQUE est créée, les doublons sont résolus (ligne la plus récente conservée).

**Acceptance Scenarios**:
1. Given un jeu de données avec 2+ lignes pour le même (company_id, employee_id), When migrate, Then pas d'erreur unique_violation et UNIQUE créée.
2. Given la migration rejouée (retry Render), When migrate à nouveau, Then idempotent.

### User Story 2 — Aucune perte silencieuse (P2)
La dédup garde la ligne la plus à jour (updated_at max, puis id max).

**Acceptance Scenarios**:
1. Given des doublons avec dates différentes, When migrate, Then la ligne conservée est la plus récente.
