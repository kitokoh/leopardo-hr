# Couche Application — BC-14 INTEGRATION

Le garde de structure (`architecture-check.yml`, PA2-ARCH-006) exige les cinq
couches pour tout module sous `api/app/Modules`.

Cette couche est **volontairement vide** aujourd'hui, et c'est un choix, pas un
oubli :

- l'orchestration d'une connexion (valider la saisie contre `field_schema`,
  persister, auditer) est **déjà** portée par
  `Infrastructure/Services/ConnectorSchemaValidator` et par les contrôleurs, qui
  ne font qu'enchaîner trois appels sans règle métier propre ;
- il n'existe donc aucune logique applicative à déplacer ici sans créer une
  couche qui ne ferait que déléguer.

Dès qu'une règle métier apparaîtra (rotation de secrets, réconciliation d'un
compte connecté, propagation d'un changement de statut vers les abonnements),
elle ira dans `Application/Actions/` — pas dans `Infrastructure/`.

Même situation que `Modules/CRM/Application/.gitkeep`.
