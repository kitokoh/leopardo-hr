# Spec — État explicite du kiosk non configuré

## Contexte

Lorsque `apiBaseUrl` ou `deviceCode` manque dans la configuration du kiosk, l’interface tentait de construire une URL avec un hôte vide et affichait une erreur HTTP 404 brute. Une borne neuve doit présenter un état d’installation compréhensible plutôt qu’une erreur réseau.

## Objectif

Détecter la configuration absente avant tout appel distant et afficher un état non configuré localisé, accessible et actionnable pour l’équipe d’installation.

## Décision

Le kiosk vérifie `apiBaseUrl` et `deviceCode` au démarrage. Si l’un manque, il affiche un titre et une description localisés, marque la synchronisation comme nécessitant une configuration, désactive les actions qui dépendent de l’API et n’appelle ni le bridge ni l’API distante. Le changement de langue réapplique cet état.

`kioskToken` n’est pas requis pour afficher cet état : il peut être provisionné séparément par le bridge ou l’étape d’installation.

## Critères d’acceptation

1. Sans `apiBaseUrl` ou `deviceCode`, aucun appel `/api/v1/kiosks/` n’est construit.
2. L’interface affiche un état « borne non configurée » en FR/EN/TR/AR.
3. Les boutons de pointage, recherche, QR, démo et synchronisation sont désactivés.
4. Le changement de langue met à jour l’état non configuré.
5. Avec une configuration valide, le parcours existant reste inchangé.
