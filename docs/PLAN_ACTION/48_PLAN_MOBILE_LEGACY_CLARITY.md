# Plan 48 - Clarification mobile legacy vs apps de lancement

## Objectif

Eviter qu'une nouvelle evolution produit soit ajoutee dans l'ancienne app
Flutter unique alors que les apps de lancement sont maintenant :

- `front/mobile_apps/leopardo_employee`
- `front/mobile_apps/leopardo_manager`
- `front/mobile_apps/leopardo_platform_admin`

## Decisions

- `front/mobile/` reste maintenu pour l'historique et les tests existants.
- Aucune nouvelle fonctionnalite employee, manager/RH ou platform admin ne doit
  etre developpee dans `front/mobile/`.
- Les workflows legacy gardent une valeur de regression, mais leur nom doit le
  dire explicitement.
- La CI et la distribution store des nouvelles apps restent portees par
  `mobile-apps-ci.yml` et `mobile-distribute.yml`.

## Livrables realises

- Workflow `Mobile CI - Flutter` renomme en `Legacy Mobile CI - Flutter`.
- Jobs et artefacts de l'ancienne app renommes en `legacy`.
- Release GitHub : l'APK historique est publie sous
  `leopardo-rh-legacy-{tag}.apk`.
- README `front/mobile_apps` enrichi avec la regle de contribution et les
  workflows canoniques.
- `AGENTS.md` mis a jour pour les prochains agents.

## Prochaines etapes

- Migrer progressivement les tests utiles de `front/mobile/test/**` vers les
  apps scindees quand un workflow produit est modifie.
- Supprimer `front/mobile/` uniquement apres une decision explicite de fin de
  support legacy et apres remplacement complet des preuves CI utiles.
