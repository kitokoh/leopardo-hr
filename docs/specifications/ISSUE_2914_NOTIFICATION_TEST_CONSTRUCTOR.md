# Spec — Alignement du test NotificationDispatcher

## Contexte

`NotificationDispatcher` requiert désormais une instance de `PushNotificationService` dans son constructeur, mais `api/tests/Unit/Modules/NotificationTest.php` l’instancie sans argument. Le test échoue avant même de vérifier l’action `SendNotification`.

## Objectif

Réaligner le test d’instanciation sur le contrat actuel du service sans modifier le comportement runtime ni ajouter un appel réseau ou Firebase.

## Décision

Injecter une instance de `PushNotificationService` sans état dans le test. Le test ne déclenche pas `dispatch()` ; aucune base de données, requête HTTP ou configuration Firebase supplémentaire n’est requise.

## Critères d’acceptation

1. Le test construit `NotificationDispatcher` avec l’argument requis.
2. Le test `test_send_notification_action_instantiates` passe.
3. Le second test d’action reste inchangé et passe.
4. Aucun secret, appel réseau ou changement de production n’est introduit.
