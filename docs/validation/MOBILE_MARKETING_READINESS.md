# Mobile marketing readiness

Derniere mise a jour : 2026-05-26

## Objectif

Valider que l'application mobile peut etre montree a un prospect sans bouton decoratif, attente infinie ou parcours vide. Cette checklist complete les tests CI Flutter et doit etre rejouee avant une demo commerciale importante.

## Environnement de reference

- API : `https://gestionemployerbackend.onrender.com/api/v1`
- Build staging : APK distribue par le workflow `Deploy - Leopardo RH`
- Source comptes demo : `GET /api/v1/demo-users`
- Langue de reference produit : francais

## Personas demo prioritaires

| Persona | Surface | Parcours attendus |
|---|---|---|
| Employe | Mobile | login, accueil, pointage, demande absence, demande avance, notifications, compte |
| Manager RH | Mobile | login, equipe, ajout employe, decisions absences/avances, notifications |
| Principal | Mobile + web client | readiness entreprise, equipe, paie, exports, decisions RH |

## Scenario 1 - Employe mobile

1. Ouvrir l'application avec l'API Render configuree.
2. Se connecter avec un persona employe de `/api/v1/demo-users`.
3. Verifier que l'accueil affiche trois actions rapides maximum et des modules lisibles.
4. Ouvrir `Pointage`, verifier que l'horloge s'affiche immediatement.
5. Appuyer sur le bouton de pointage : le feedback doit etre court, sans spinner infini.
6. Ouvrir `Absences`, creer une demande, puis verifier le statut `en attente`.
7. Annuler une demande en attente : le bouton doit appeler `DELETE /absences/{id}` et rafraichir la liste.
8. Ouvrir `Avances`, creer une demande avec montant, motif et duree de remboursement.
9. Annuler une avance en attente : le bouton doit appeler `DELETE /salary-advances/{id}`.

## Scenario 2 - Manager/RH mobile

1. Se connecter avec un persona `manager` / `rh`.
2. Ouvrir `Equipe`, verifier que la liste se charge et que l'ajout employe demande au minimum date d'embauche, role et base salariale.
3. Ajouter un employe de test sur staging ou environnement demo autorise.
4. Verifier que la liste se rafraichit apres creation.
5. Ouvrir `Absences` avec une demande d'un collaborateur : les actions `Approuver` et `Refuser` doivent apparaitre.
6. Refuser une absence : un commentaire est obligatoire, puis la liste se rafraichit.
7. Ouvrir `Avances` avec une demande d'un collaborateur : les actions `Approuver` et `Refuser` doivent apparaitre.
8. Approuver une avance : la decision doit appeler `PUT /salary-advances/{id}/approve`.
9. Verifier qu'une demande personnelle du manager/RH affiche `Annuler la demande`, pas `Approuver`.

## Scenario 3 - Readiness demo commerciale

- Les ecrans critiques restent lisibles sur 390 x 844 et 430 x 1000.
- Les erreurs API affichent un message actionnable et un retry quand c'est pertinent.
- Les actions critiques affichent un SnackBar succes/echec.
- Les routes mobiles critiques sont listees dans `docs/validation/FRONTEND_API_CONTRACT_MATRIX.md`.
- La CI `Mobile CI - Flutter` est verte sur le commit deploye.
- Les workflows `Deploy - Leopardo RH`, `E2E - Playwright Staging`, `OWASP ZAP Baseline` et `Launch Observability Smoke` sont verts apres merge `main`.

## Tests automatises associes

- `front/mobile/test/features/attendance/attendance_screen_test.dart`
- `front/mobile/test/features/attendance/attendance_repository_actions_test.dart`
- `front/mobile/test/features/mobile_surface_smoke_test.dart`
- `front/mobile/test/features/mobile_marketing_readiness_test.dart`
- `front/mobile/test/repositories/repository_contract_test.dart`

## Signaux no-go

- Le bouton pointage reste en chargement plus de 12 secondes.
- Une action visible ne correspond pas a une route API reelle.
- Un employe voit des boutons de decision RH.
- Un manager/RH peut s'auto-approuver une demande personnelle.
- Les erreurs reseau Render restent silencieuses.
- Les donnees demo ne couvrent pas au moins un employe et un manager/RH connectables.
