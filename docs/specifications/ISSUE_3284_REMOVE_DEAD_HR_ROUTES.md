# Spec — Retrait des 9 routes GoRouter mortes (app HR mobile)

**Issue** : #3284 | **Statut** : Implémenté | **Date** : 2026-08-15

## Problème

L'app `leopardo_hr` déclare 9 routes GoRouter sans aucune entrée UI ni
référence dans le manifest backend (`MobileExperienceService`) :
`/notifications`, `/evaluations`, `/history`, `/modules/rh`, `/training`,
`/expenses`, `/ai-chat`, `/ai-voice`, `/vehicle-map`.

Preuves :
- 0 `context.push/go` vers ces chemins dans `front/mobile_apps/leopardo_hr/lib`
- les tabs bottom-nav sont `/`, `/attendance`, `/absences`, `/team`, `/settings`
- `ModulesScreen` n'était référencé que par la route morte `/modules/rh`

## Correctif

- Retrait des 9 `GoRoute` + imports associés dans `app.dart` (motif identique
  au chantier manager #3285 / PR #3702).
- Les fichiers écrans restent dans le repo (réutilisables, pas de casse
  d'import croisé).

## Critères d'acceptation

1. `flutter analyze` (leopardo_hr) : 0 erreur.
2. Aucune référence résiduelle aux 9 chemins dans `lib/`.
3. Le manifest backend ne pointe vers aucune route retirée (vérifié :
   `/evaluations`, `/notifications` restent exposés par le manifest mais sans
   écran — à brancher lors du chantier modules RH).
