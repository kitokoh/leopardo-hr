# Plan 32 - Compte employe durable et placard numerique

Date : 2026-05-27

## Objectif

Renforcer l'app employee pour qu'un utilisateur reste un vrai compte plateforme, meme quand son rattachement a une entreprise change. Le profil mobile doit afficher les informations personnelles durables, le parcours professionnel et l'entree vers le placard numerique.

## Lot livre

### API

- Ajout des champs optionnels `personal_email`, `recovery_email` et `personal_phone` sur la mise a jour de profil courant.
- Ajout de `GET /api/v1/me/career` pour exposer la disponibilite pour une nouvelle entreprise, l'entreprise courante et la timeline professionnelle.
- Fallback propre depuis la fiche employe si aucun contrat formel n'existe encore.
- Conservation de `GET /api/v1/cabinet/stats` comme source mobile pour le placard numerique.

### Mobile employee

- Modernisation de l'ecran `Compte` avec champs email personnel, email recuperation et telephone personnel.
- Ajout du bloc "Parcours professionnel".
- Ajout du bloc "Placard numerique" avec statistiques et acces direct au module documents.
- Bouton de deconnexion conserve en bas de page.

### Donnees et contrats

- Migration tenant idempotente pour `recovery_email` et `personal_phone`.
- Mise a jour de la matrice frontend/API et de la specification OpenAPI.
- Tests de garde sur la mise a jour du profil durable, la timeline carriere et les stats du placard.

## Points de vigilance

- Les informations personnelles sont facultatives : elles ne doivent pas bloquer l'utilisation RH quotidienne.
- Le parcours doit rester tenant-safe : une app employee ne doit voir que son propre rattachement.
- Le placard numerique est un espace personnel : les partages publics ou externes devront rester explicites dans un lot dedie.

## Suite logique

Le lot suivant doit traiter l'integration employe/entreprise par QR code et la correction du formulaire manager d'ajout employe, car ces workflows conditionnent l'onboarding reel terrain.
