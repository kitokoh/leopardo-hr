# Plan 37 - Fiche collaborateur manager mobile

## Objectif

Rendre la section equipe mobile plus operationnelle pour les managers/RH : un collaborateur existant doit pouvoir etre consulte puis modifie sans passer par le back-office web.

## Livrables

- API `PATCH /api/v1/employees/{employee}` etendue aux champs RH essentiels : date d'embauche, salaire, taux horaire et horaire de travail.
- Controle tenant conserve sur `schedule_id`.
- Mobile manager : action `Voir la fiche` avec resume poste, departement, lieu, salaire et horaire.
- Mobile manager : action `Modifier la fiche` avec sauvegarde via API et rafraichissement de la liste equipe.
- Modele mobile `Employee` enrichi avec telephone, departement, poste et lieu.
- Tests backend couvrant la modification horaire/salaire/poste.

## Critere de sortie

La fiche equipe n'est plus une simple liste : le manager peut corriger les donnees necessaires au lancement terrain sans perdre l'isolation tenant ni la coherence API/mobile.
