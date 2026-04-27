# Dossier de reponse au cahier des charges

## Objet

Ce document decrit l'etat reel du produit a date, les capacites deja visibles dans le depot, les validations existantes et les ecarts encore ouverts par rapport a une reponse complete au besoin initial.

Ce n'est pas une nouvelle spec cible.

## Nature du document

Ce document sert a :

- decrire l'etat d'avancement reel
- donner une lecture de conformite partielle ou complete selon les zones
- faciliter la decision produit/projet
- eviter de sur-vendre ou sous-vendre le depot

## Vue d'ensemble du produit

Leopardo RH est un monorepo contenant :

- une API Laravel multitenant dans `api/`
- une interface web manager embarquee
- une application mobile Flutter dans `mobile/`
- une CI GitHub Actions
- une cible de deploiement cloud basee sur Render pour l'API/web

## Architecture actuelle de deploiement

### Backend / Web

- runtime : PHP 8.4 + FrankenPHP
- conteneur : `api/Dockerfile.prod`
- demarrage : `api/docker-entrypoint.sh`
- hebergement cible : Render
- base de donnees : PostgreSQL

### Mobile

- Flutter stable
- build CI via GitHub Actions
- distribution staging via Firebase App Distribution

### CI/CD

- tests PR/push : `.github/workflows/tests.yml`
- deploy main : `.github/workflows/deploy-main.yml`
- distribution mobile : `.github/workflows/mobile-distribute.yml`

## Surface fonctionnelle visible sur `main`

### Core API

- healthcheck
- auth API
- auth plateforme
- onboarding public par invitation

### RH

- employes
- estimations
- attendance
- self-service `me/*`
- invitations
- kiosque / biometrie
- absences
- salary advances
- payrolls
- departements / positions / sites / schedules
- notifications
- projects / tasks

### Cameras

- CRUD cameras
- access tokens
- permissions
- access logs
- stream token
- viewer public

### Web manager

- login / logout
- dashboard manager
- consultation employes
- estimation rapide
- recu PDF

### Mobile

Le mobile consomme deja ou vise directement les parcours relies a :

- auth
- me
- employes
- attendance
- estimations critiques
- onboarding / invitations selon l'evolution des ecrans

## Ce qui est valide a date

### Valide dans le code et la CI

- auth login / me / logout
- garde-fous auth
- isolation tenant
- attendance du jour et historique
- estimations
- recu PDF
- contrats JSON critiques pour mobile
- onboarding par invitation
- plusieurs checks CI backend, mobile, securite et gouvernance

### Indices de validation visibles dans les tests

Le depot contient notamment des tests pour :

- auth
- attendance
- employees / RBAC
- health
- onboarding
- cameras
- estimations
- contrats mobile
- chiffrement employee

## Ce qui reste partiel ou a consolider

1. la documentation API ne reflete pas encore partout la surface reelle de `main`
2. la cohesion entre vision, pilotage et CDC/reponse CDC n'est pas totalement refermee
3. certains modules etendus visibles dans les routes meritent encore une meilleure tracabilite contractuelle
4. la recette pilote par role reste a consolider

## Reponse synthese au CDC

### Ce qui est repondu aujourd'hui

- base SaaS RH multi-tenant deployable
- coeur RH exploitable
- attendance exploitable
- estimations et recu PDF
- web manager fonctionnel
- mobile branche sur les parcours coeur
- pipeline CI/CD actif

### Ce qui est repondu mais doit etre mieux documente

- onboarding public
- modules RH etendus
- cameras
- plateforme super-admin

### Ce qui reste a produire pour une reponse plus complete

- une documentation API current-state plus fiable
- une recette d'acceptation transverse par role
- des corrections issues des pilotes beta
- l'arbitrage sur les evolutions fonctionnelles a poursuivre ou geler

## Documents a croiser

1. `../../PILOTAGE.md`
2. `PROCHAINES_ACTIONS_MAIN_2026-04-27.md`
3. `ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md`
4. `../../api/routes/api.php`
5. `../../api/routes/modules/rh.php`
6. `../../api/routes/modules/cameras.php`

## Conclusion

Le depot n'est plus une simple intention ni un MVP minimal.
Il contient deja une base plus large, mais cette largeur doit maintenant etre rendue lisible, verifiee et pilotable sans ambiguite.
