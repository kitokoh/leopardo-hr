# Plan 26 - Mobile multi-app production-ready

Date : 2026-05-26
Statut : en execution

## Objectif

Transformer la separation mobile creee en v4.16.143 en socle exploitable pour la production :

- proteger l'archive `leopardo_mobile_legacy` ;
- empecher le retour de logique manager dans `leopardo_employee` ;
- garder `leopardo_core` strictement partage et neutre ;
- rendre les builds employee/manager reproductibles en CI ;
- documenter les regles de contribution avant les prochains lots fonctionnels.

Ce plan ne cree pas de nouvelle fonctionnalite metier. Il solidifie la structure pour que les prochains travaux mobile puissent avancer vite sans dette architecturale.

## Diagnostic

La separation en deux apps est maintenant presente :

- `leopardo_employee` contient les parcours personnels ;
- `leopardo_manager` conserve le perimetre manager/RH ;
- `leopardo_core` centralise API, theme, widgets, modeles, storage et i18n ;
- `leopardo_mobile_legacy` sert de filet de securite.

Le risque principal n'est plus le code existant, mais la regression future : un dev peut modifier l'archive, remettre une route equipe dans l'app employe, importer le package legacy, ou placer une dependance role-specifique dans le core.

## Lot 26.1 - Garde-fous de structure

Livrable :

- script `dev-hub/tools/validate-mobile-apps-split.ps1` ;
- detection des dossiers interdits dans l'app employe ;
- detection des marqueurs de roles manager interdits dans l'app employe ;
- verification que `leopardo_core` n'importe aucune app ;
- verification que les deux apps dependent du core via `../leopardo_core` ;
- verification des routes placeholders manager ;
- blocage des modifications de `leopardo_mobile_legacy` dans les PR.

## Lot 26.2 - CI multi-app durcie

Livrable :

- job `Mobile apps split guard` dans `.github/workflows/mobile-apps-ci.yml` ;
- analyse Flutter des trois projets apres le garde ;
- build debug APK employee et manager apres analyse ;
- workflow declenche aussi si le script de garde change.

## Lot 26.3 - Documentation de contribution

Livrable :

- README `front/mobile_apps/README.md` enrichi ;
- procedures de validation par lot ;
- rappel explicite : modifications partagees dans `leopardo_core`, ecrans specifiques dans l'app concernee ;
- sous-roles manager geres dans les ecrans via `employee.managerRole`, pas par multiplication d'apps ou par router.

## Definition of done

- `dev-hub/tools/validate-mobile-apps-split.ps1` passe localement.
- CI `Mobile Apps CI - Flutter` verte.
- `leopardo_mobile_legacy` non modifie.
- `leopardo_employee` sans marqueur manager.
- `leopardo_core` sans import app-specifique.
- Documentation et changelog a jour.

## Suite recommandee

Plan 27 devra commencer le vrai travail produit sur cette base :

- assets/branding distincts employee et manager ;
- configuration de distribution Android/iOS par app ;
- smoke E2E par persona mobile ;
- migration progressive des tests utiles depuis `front/mobile` vers les deux apps ;
- decision produit sur la date de bascule officielle hors legacy.
