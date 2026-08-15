# Issue #3956 — leopardo_hr : bouton « Tester avec un compte demo » codé en dur

## Problème

`leopardo_hr/lib/features/auth/screens/login_screen.dart:290` affichait
`Text('Tester avec un compte demo')` en dur (FR uniquement) alors que les apps
sœurs (`leopardo_employee`, `leopardo_manager`) utilisent la clé l10n
`authTryDemoAccount` (ARB × 4 locales, `leopardo_core`).

## Correctif

- Import `package:leopardo_core/l10n/l10n.dart`
- `final l10n = context.l10n;` en tête de `build()`
- `label: Text(l10n.authTryDemoAccount)`

## Critères de succès

1. `flutter analyze` leopardo_hr : 0 erreur.
2. Le libellé suit la locale active (fr/en/tr/ar) comme sur les apps sœurs.
