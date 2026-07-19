# Plan 41 - Branding mobile natif

## Objectif

Retirer l'empreinte Flutter par defaut des trois nouvelles applications mobiles et poser un socle visuel natif propre avant distribution testeurs.

## Perimetre

- `front/mobile_apps/leopardo_employee`
- `front/mobile_apps/leopardo_manager`
- `front/mobile_apps/leopardo_platform_admin`

L'archive `front/mobile_apps/leopardo_mobile_legacy` reste intouchable.

## Livrables

- Icônes Android launcher par densite.
- Icônes iOS AppIcon completes, dont marketing 1024x1024.
- Splash images iOS personnalisees.
- Splash Android sombre avec logo centre.
- Adaptive icons Android 8+.
- Icônes notification Android monochromes.
- Previews visuels dans `docs/assets/mobile-branding/`.

## Decisions

- Chaque app garde une identite distincte :
  - employee : vert RH et empreinte pointage.
  - manager : bleu/teal et motif dashboard.
  - platform admin : orange/violet et bouclier plateforme.
- Les notifications locales utilisent `@drawable/ic_notification` via `leopardo_core`.
- Les notifications FCM declarent une icone et une couleur par application dans chaque `AndroidManifest.xml`.

## Validation

- Verifier que `leopardo_mobile_legacy` n'est pas modifie.
- Verifier que chaque AppIcon iOS correspond aux dimensions de `Contents.json`.
- Verifier que chaque app Android possede :
  - `mipmap-*/ic_launcher.png`
  - `mipmap-*/launch_image.png`
  - `mipmap-anydpi-v26/ic_launcher.xml`
  - `drawable/ic_launcher_foreground.xml`
  - `drawable/ic_notification.xml`
  - `values/colors.xml`
- Laisser GitHub Actions compiler les trois apps, conformement a la strategie CI du projet.
