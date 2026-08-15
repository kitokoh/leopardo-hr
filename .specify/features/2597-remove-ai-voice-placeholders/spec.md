# Feature Specification: Écrans AI Voice placeholders retirés (Closes #2597)

**Feature Branch**: `fix/2597-remove-ai-voice-placeholders`
**Issue**: #2597 (T004, P3, mobile)

## Contexte

`ai_voice_screen.dart` affiche « Bientôt disponible » dans leopardo_hr et leopardo_manager ;
`ai_voice_repository.dart` existe dans les 3 apps. Vérification : plus aucune route GoRouter
(les routes ont été retirées par #3715), le manifeste mobile ne sert aucune entrée AI Voice,
et `aiVoiceRepositoryProvider` n'a **aucun consommateur**. Code mort complet → option 1 de
l'issue : suppression.

## User Stories

### US1 — Aucun code mort AI Voice (P1)

**Acceptance Scenarios**:
1. Given les 3 apps, When `rg ai_voice|AiVoice` sur lib/, Then 0 référence.
2. Given le build Flutter, When `flutter analyze` ×3 apps, Then 0 erreur (aucun import cassé).

## Requirements

- **FR-001**: supprimer les dossiers `features/ai_voice/` (5 fichiers) dans leopardo_employee/manager/hr.
- **FR-002**: retirer `aiVoiceRepositoryProvider` + import dans les 2 `core_providers.dart`.
- **FR-003**: CHANGELOG.

## Success Criteria

- 0 référence restante ; CI mobile verte.
