# ISSUE_4102 — device tokens / mark-all-read non câblés (garde mobile)

**Statut**: Fixed (PR `fix/4102-device-tokens-wiring`) · **Priorité**: P2 · **Module**: mobile

## Constat

`validate-mobile-workflow-contracts.ps1` échouait sur 3 endpoints :
`/device-tokens` (employee+manager) et `/notifications/mark-all-read` (manager).

## Analyse

- `/device-tokens` est implémenté dans `leopardo_core`
  (`PushNotificationService.registerToken/unregisterCurrentToken`, ×2) — le
  validateur ne scannait que les racines d'apps → faux positif.
- Le contrat manager listait `/notifications/mark-all-read` (alias conservé
  côté dashboard.php) alors que les deux repos appellent
  `/notifications/read-all` (canonique #2674/#2955, rh.php).

## Correctif

1. Validateur : `$libContent` inclut désormais `leopardo_core/lib` (endpoints
   partagés reflétés).
2. Contrat : entrée manager → `/notifications/read-all`.
