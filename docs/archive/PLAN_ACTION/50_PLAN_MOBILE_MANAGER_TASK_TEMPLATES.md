# Plan 50 - Templates de taches manager

## Objectif

Rendre la creation de taches manager plus exploitable sur le terrain, sans
ajouter de nouvelle API ni dupliquer la logique metier cote mobile.

## Probleme traite

Le manager pouvait deja creer une tache, mais les presets etaient trop limites
pour les secteurs cites dans la vision produit : agriculture, elevage,
commerce, maintenance, RH et logistique.

## Livrables realises

- Ajout d'une categorie explicite dans le formulaire de tache.
- Ajout d'une frequence : ponctuelle, quotidienne ou hebdomadaire.
- Extension des templates metier :
  - agriculture ;
  - elevage ;
  - maintenance ;
  - commerce ;
  - logistique ;
  - RH onboarding.
- Les presets alimentent uniquement les champs API deja supportes :
  - `category` ;
  - `template_key` ;
  - `recurrence_rule` ;
  - `priority` ;
  - `estimated_minutes`.

## Validation

- `dart format front/mobile_apps/leopardo_manager/lib/features/tasks/screens/task_list_screen.dart`
- `git diff --check`
- `powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-workflow-contracts.ps1`

## Suite logique

- Ajouter plus tard une bibliotheque de templates pilotable depuis l'admin
  plateforme ou le backend tenant, quand les premiers clients auront valide les
  categories terrain les plus utiles.
