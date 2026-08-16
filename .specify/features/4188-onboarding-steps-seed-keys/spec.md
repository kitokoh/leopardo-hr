# Feature Specification: Onboarding — étapes seedées avec les bonnes clés (issue #4188)

**Feature Branch**: `fix/4188-onboarding-steps-seed-keys`
**Created**: 2026-08-16
**Status**: Draft → À implémenter
**Input**: Audit 360° 2026-08-16 — `SeedDefaultSteps.php:31` crée `OnboardingStep` avec `key`/`label` alors que le schéma expose `step_key`/`title` → étapes NULL + dédup cassée.

## Problème

- `OnboardingStep::$fillable = [company_id, step_key, title, description, status, completed_at, completed_by, order, required, metadata]`.
- Le seeder passe `'key' => ...`, `'label' => ...` → non fillable, et `label` n'est pas une colonne → `step_key = NULL`, `title = NULL` pour chaque étape.
- `pluck('key')` lit un attribut inexistant → la dédup renvoie `[null]` → re-provisioning = doublons.
- Impact : onboarding nouveau tenant sans étapes identifiables (parcours bloqué/illisible).

## Décision

1. `SeedDefaultSteps` : `'step_key' => $step['key']`, `'title' => $step['label']` (+ `'description'` depuis DEFAULT_STEPS si présent).
2. Dédup : `pluck('step_key')` + `in_array($step['key'], $existing)`.
3. Test : provisioning → étapes avec step_key/title non NULL ; second appel → aucune ligne ajoutée.

## User Scenarios & Testing

### User Story 1 — Nouveau tenant : les étapes d'onboarding sont visibles (Priority: P1)
**Independent Test**: `php artisan test --filter=OnboardingSeedStepsTest`

**Acceptance Scenarios**:
1. **Given** un tenant provisionné, **When** on lit `OnboardingStep` pour la compagnie, **Then** chaque étape a `step_key` et `title` non NULL (6 étapes par défaut).
2. **Given** un re-provisioning, **When** `SeedDefaultSteps` s'exécute à nouveau, **Then** aucune ligne dupliquée (dédup par `step_key`).
3. **Given** une étape complétée (`completed_at`), **When** re-seed, **Then** l'étape n'est pas réinsérée.

## Edge Cases

- Les lignes existantes déjà seedées avec `step_key NULL` ne sont pas réparées par le seeder seul → recommandation : migration/one-off de backfill (step_key depuis metadata si présent), hors périmètre si non pertinent.
- `order`/`required`/`status` inchangés (déjà corrects).

## Functional Requirements

1. `SeedDefaultSteps` écrit `step_key`/`title` (plus `key`/`label`).
2. Dédup par `step_key`.
3. Test : non-nullité + idempotence.

## Success Criteria

- 100 % des tenants provisionnés après fix ont 6 étapes avec step_key/title non NULL.
- `SeedDefaultSteps` est idempotent (2ᵉ appel = 0 insert).
- PHPStan strict 0 erreur ; CHANGELOG mis à jour.
