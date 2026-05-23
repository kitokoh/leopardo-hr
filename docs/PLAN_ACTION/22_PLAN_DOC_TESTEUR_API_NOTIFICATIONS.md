# Plan 22 - Documentation testeur, API explorer et notifications vivantes

Date : 2026-05-23

## Observation

Le lancement commercial demande une validation plus directe par des testeurs non developpeurs. Les comptes demo existent dans le plan fonctionnel, mais l'experience doit etre lisible quand un seed manque, quand Render repond lentement ou quand une notification arrive apres le chargement initial.

## Objectifs

1. Rendre les profils demo connectables sur web client et mobile sans spinner infini.
2. Exposer depuis la racine Render un guide testeur clair pour web client, mobile, admin plateforme et API.
3. Fournir un espace API Explorer pre-rempli avec les personas demo et les endpoints critiques.
4. Rendre les notifications web/mobile vivantes : rafraichissement, badge non lu, lecture et feedback.
5. Transformer chaque echec demo en message actionnable plutot qu'en attente silencieuse.

## Lots futurs proposes

### Lot 22.1 - Demo runtime robuste

- Ajouter une commande ops `demo:doctor` qui verifie plans, companies, employees, user_lookups, notifications et preferences.
- Ajouter un endpoint super-admin `POST /api/v1/platform/demo/repair` reserve aux environnements autorises.
- Publier dans le guide testeur l'etat `demo_ready` calcule cote API.

### Lot 22.2 - API Explorer avance

- Ajouter les scenarios sauvegardes par profil : manager principal, RH, comptable, superviseur, employe et super-admin.
- Ajouter les requetes write safe pour preferences notifications, marquage lu, client-events et readiness.
- Ajouter export cURL/Postman depuis l'explorer.

### Lot 22.3 - Notifications temps reel

- Normaliser un contrat SSE ou WebSocket authentifie pour web client.
- Ajouter un fallback polling documente pour mobile et web si SSE est bloque par proxy.
- Ajouter un indicateur "dernier rafraichissement" et une alerte locale quand une nouvelle notification arrive.

### Lot 22.4 - QA commerciale

- Transformer le guide testeur en checklist de release avec cases par surface.
- Ajouter screenshots ou courtes videos des parcours attendus.
- Lier le rapport release readiness aux resultats API Explorer et aux comptes demo.

## Criteres de fin

- Un testeur peut partir de `/`, ouvrir le guide, choisir une surface, se connecter avec un persona demo et verifier les notifications.
- Un developpeur peut ouvrir `/api-explorer`, obtenir un token et tester les endpoints critiques sans preparer manuellement les payloads.
- Les notifications web et mobile ne sont pas statiques : elles se rafraichissent et les lectures sont visibles immediatement.
- Les erreurs demo sont explicites : mode demo ferme, compte absent, entreprise absente, timeout ou mauvais identifiants.
