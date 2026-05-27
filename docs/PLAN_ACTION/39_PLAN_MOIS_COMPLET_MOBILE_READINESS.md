# Plan 39 - Mois complet mobile et etat vide fiable

## Objectif

Eviter que le parcours mobile `Mon mois complet` donne l'impression de tourner sans fin quand le mois ne contient pas encore de pointage ou quand l'API met du temps a repondre.

## Livrables

- `GET /api/v1/me/monthly-summary` garde un contrat exploitable meme pour un mois vide.
- L'app employee affiche un etat de chargement explicite, un etat vide clair et une action vers l'historique.
- L'ecran utilise le socle visuel mobile sombre (`MobilePage`, `MobileTopBar`, `MobilePanel`) pour rester coherent avec le pointage v3.
- Le garde multi-app verifie que le parcours attendance contient toujours le resume mensuel et son etat vide.

## Tests et validations

- Test backend: mois sans pointage retourne `hours=0`, `net=0`, `breakdown=[]`.
- Garde mobile: route `/me/monthly`, endpoint `/me/monthly-summary` et libelles de secours presents.
- GitHub Actions reste la source de verite pour les analyses Flutter et la suite backend complete.
