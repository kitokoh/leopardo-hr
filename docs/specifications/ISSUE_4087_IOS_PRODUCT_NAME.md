# Issue #4087 — leopardo_hr iOS : PRODUCT_NAME « Leopardo Manager »

## Problème

`front/mobile_apps/leopardo_hr/ios/Runner.xcodeproj/project.pbxproj` définissait
`PRODUCT_NAME = "Leopardo Manager"` (6 occurrences) — copier-coller depuis
`leopardo_manager`. L'artefact iOS de l'app RH se nommait `Leopardo Manager.app`.

## Correctif

`PRODUCT_NAME = "Leopardo RH"` ×6 (convention identique aux apps sœurs :
`Leopardo Employee`, `Leopardo Manager` — espaces OK).

## Critères de succès

1. `grep 'Leopardo Manager' leopardo_hr/ios/` → 0.
2. Build iOS leopardo_hr → artefact `Leopardo RH.app`.
