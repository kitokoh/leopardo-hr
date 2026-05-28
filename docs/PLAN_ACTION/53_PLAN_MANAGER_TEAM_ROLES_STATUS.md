# Plan 53 - Equipe manager, statuts operationnels et roles RH

Date : 2026-05-28

## Objectif

Traiter les retours testeurs sur l'equipe manager qui chargeait sans fin, les statistiques peu actionnables et l'absence de gestion RH depuis mobile.

## Livraisons

- API employees :
  - `GET /api/v1/employees` enrichit chaque collaborateur avec `work_state` et `work_state_label`,
  - les statuts terrain sont derives du tenant courant : pointage ouvert, mission/deplacement, pause cloturee, absence approuvee, employe non actif ou hors ligne,
  - les champs sont exposes dans `EmployeeResource` pour les apps mobiles sans route parallele.
- Securite roles :
  - un manager non principal ne peut plus modifier `role` ou `manager_role` via `PATCH /employees/{employee}`,
  - la promotion `manager_role=principal` reste interdite a tout manager tenant,
  - repasser un compte en `role=employee` nettoie `manager_role`.
- Mobile manager :
  - la liste equipe affiche une synthese operationnelle du jour,
  - chaque collaborateur montre son etat terrain,
  - les actions rapides ouvrent fiche, statistiques/pointages et taches,
  - le manager principal peut nommer ou revoquer un RH depuis la fiche action.
- Tests :
  - couverture Feature du payload `work_state`,
  - couverture Feature de la regle principal-only pour nomination/revocation RH.

## Validation attendue

- `php -l` sur les fichiers backend touches.
- `dart format` sur le modele core et l'ecran team manager.
- `git diff --check`.
- GitHub Actions : backend quality, `Analyze leopardo_core`, `Analyze leopardo_manager`, `Build Debug leopardo_manager`, governance.

## Suite

- Plan suivant recommande : QR onboarding reel et fluide pour l'ajout employee manager.
- Ensuite : super admin mobile avec login strict et dashboard entreprises avant les notifications FCM.
