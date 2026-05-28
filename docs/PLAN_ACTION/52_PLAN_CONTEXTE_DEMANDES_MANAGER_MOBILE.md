# Plan 52 - Contexte demandes manager mobile

Date : 2026-05-28

## Objectif

Corriger le retour testeur indiquant que le manager voyait des demandes trop vagues du type "quelqu'un a demande une avance".

## Livraisons

- API avances sur salaire :
  - chargement du demandeur sur les listes, details et decisions,
  - exposition de `employee`, `employee_name`, `company_id`, `requested_at`, `decision_comment`, `repayment_months`, `monthly_deduction`, `amount_remaining`, `repayment_plan`.
- API absences :
  - les reponses create/show/approve/reject/cancel rechargent aussi le demandeur et le type d'absence.
- Mobile manager :
  - les cartes avance affichent demandeur, montant, date, motif et remboursement avant les actions,
  - les cartes absence affichent demandeur, type, periode, duree, date de demande et motif,
  - les dialogues de validation reprennent le contexte metier avant confirmation.
- Robustesse reseau :
  - repositories manager `absences`, `salary_advances` et `team` branches sur `ApiClient.requestWithRetry`,
  - timeouts explicites lecture/action pour eviter les chargements infinis.

## Validation attendue

- `php -l` sur les fichiers backend touches.
- `dart format` sur les fichiers Flutter touches.
- `git diff --check`.
- GitHub Actions : backend quality si declenche, `Analyze leopardo_core`, `Analyze leopardo_manager`, `Build Debug leopardo_manager`.

## Suite

- Ajouter une vue operationnelle manager equipe/presence pour le point 20/22.
- Ajouter la gestion mobile de nomination/revocation RH pour le point 19.
