# Spec — Retrait des 11 routes GoRoute dupliquées (app Manager)

**Issue** : #3746 | **Statut** : Implémenté | **Date** : 2026-08-15

## Problème

`front/mobile_apps/leopardo_manager/lib/app.dart` déclarait 44 `path:` pour
~33 routes uniques : `/tasks`, `/salary-advances`, `/payrolls`,
`/notifications`, `/modules`, `/me/monthly`, `/history`, `/evaluations`,
`/attendance`, `/absences`, `/team` déclarés **2×** dans le même ShellRoute
(artefact des re-ajouts #3223/#3205 après restructuration #2801). GoRouter
n'utilise que la 1ʳᵉ déclaration — les doublons sont morts et source de
drift/confusion.

## Correctif

Suppression du second bloc de 11 `GoRoute` (avec ses commentaires #3223).
Les routes uniques `/manager/*` et `/smart-attendance/*` sont conservées ;
tous les écrans des routes retirées restent utilisés par la 1ʳᵉ déclaration
(aucun import mort).

## Critères d'acceptation

1. 33 déclarations `path:` uniques (0 doublon) dans app.dart.
2. `flutter analyze` leopardo_manager : 0 erreur.
3. Navigation manager inchangée (les routes existaient en double — la 1ʳᵉ
   gagnait déjà).
