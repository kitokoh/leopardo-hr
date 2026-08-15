# Feature Specification: Régression manifeste mobile — routes manager restaurées (issue #3205)

**Feature Branch**: `fix/3205-manifest-manager-routes`

**Created**: 2026-08-15

**Status**: Draft → Implemented

**Input**: Constat QA 2026-08-15 — la garde `check-mobile-manifest-routes.sh` (#2212) échoue sur main : `MobileExperienceService` sert 11 routes que le routeur GoRouter de `leopardo_manager` ne déclare plus (régression PR #3117).

## Problème

La PR #3117 (« routes mortes retirées », Closes #2801) a retiré du routeur `leopardo_manager/lib/app.dart` bien plus que les 3 vraies routes mortes (`/ai-chat`, `/vehicle-map`, `/modules/rh`) : **11 routes encore vivantes** ont été supprimées :

`/attendance`, `/absences`, `/salary-advances`, `/payrolls`, `/evaluations`, `/notifications`, `/history`, `/me/monthly`, `/modules`, `/team`, `/tasks`.

Or :
- le manifeste continue de les servir (sections base / principal / base_actions / principal_actions) ;
- les écrans existent toujours et sont importés dans `app.dart` ;
- l'UI fait encore `context.push(...)` sur ces routes (`home_screen.dart:613` `/tasks`, `attendance_screen.dart:290` `/me/monthly`, `team_screen.dart:410` `/tasks`, modules hub `context.push(module.route!)`).

Conséquence : GoError à l'exécution (crash / écran d'erreur) sur la majorité des modules et quick actions de l'app Manager, et CI mobile rouge sur main (`Mobile apps split guard` → failure).

## User Scenarios & Testing

### User Story 1 — Les modules du manager s'ouvrent (Priority: P1)

Un manager connecté à l'app Leopardo Manager tape sur les cartes Pointage, Absences, Avances, Paie, Évaluations, Notifications, Équipe, Tâches, Mon mois, Historique, Modules RH → l'écran correspondant s'ouvre, aucun GoError.

**Independent Test**: `bash dev-hub/tools/check-mobile-manifest-routes.sh` → exit 0 (toute route servie par le manifeste est déclarée dans le routeur).

**Acceptance Scenarios**:

1. **Given** le manifeste actuel, **When** on exécute la garde `check-mobile-manifest-routes.sh`, **Then** exit 0 avec « OK — toutes les routes du manifeste existent dans les routeurs GoRouter ».
2. **Given** un manager authentifié, **When** il tape sur une carte module (ex. Pointage), **Then** l'écran `AttendanceScreen` s'affiche.
3. **Given** un manager authentifié, **When** il tape sur une quick action (ex. Mon mois), **Then** l'écran `MonthlySummaryScreen` s'affiche.

### User Story 2 — Pas de résurrection des vraies routes mortes (Priority: P2)

Les routes réellement mortes (`/modules/rh`, `/ai-chat`, `/vehicle-map`) restent absentes du routeur.

**Independent Test**: grep du routeur manager → ces 3 routes absentes ; garde #2212 verte.

**Acceptance Scenarios**:

1. **Given** le correctif appliqué, **When** on inspecte `app.dart`, **Then** aucune déclaration `/modules/rh`, `/ai-chat`, `/vehicle-map`.
2. **Given** la CI mobile, **When** `flutter analyze` sur leopardo_manager, **Then** 0 erreur (aucun import inutilisé résiduel).

## Edge Cases

- Les imports des 11 écrans existent déjà dans `app.dart` (vérifié) — aucun import à ajouter, seules les déclarations GoRoute sont à restaurer.
- La route `/team` sert aussi bien les modules que les quick actions du manager (dédupliquée, une seule déclaration suffit).
- Vérifier l'ordre des routes dans la ShellRoute : conserver la déclaration `/cabinet/folder/:folderId` existante (issue #2748) et ne pas recréer l'ancienne `/cabinet/:folderId` (cassée, doublon #3049).
