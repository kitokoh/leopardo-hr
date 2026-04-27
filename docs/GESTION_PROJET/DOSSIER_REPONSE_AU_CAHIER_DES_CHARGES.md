# DOSSIER DE REPONSE AU CAHIER DES CHARGES

## Objet du document

Ce document sert de reponse operationnelle au cahier des charges initial.
Il decrit l'etat reel du produit, l'architecture actuellement en place, les fonctionnalites disponibles, les validations deja automatisees, et les ecarts restant a traiter.

## Nature du document

Ce n'est pas un nouveau cahier des charges.
C'est un document de:

- conformite fonctionnelle
- etat d'avancement produit
- architecture de deploiement
- tracabilite de validation
- aide a la decision pour la suite

## Vue d'ensemble du produit

Leopardo RH est actuellement structure comme un monorepo contenant:

- une API Laravel multitenant dans `api/`
- une interface web manager incluse dans l'application Laravel
- une application mobile Flutter dans `mobile/`
- une couche CI/CD GitHub Actions
- une cible de deploiement cloud basee sur Render pour l'API/web et Firebase App Distribution pour le mobile staging

## Architecture actuelle de deploiement

### Backend / Web

- Runtime: PHP 8.4 + FrankenPHP
- Conteneur de production: `api/Dockerfile.prod`
- Entree de demarrage: `api/docker-entrypoint.sh`
- Hebergement cible: Render
- Base de donnees: PostgreSQL
- Cache / sessions techniques: Redis selon environnement

### Mobile

- Framework: Flutter stable
- Build CI: GitHub Actions
- Distribution staging: Firebase App Distribution

### Orchestration CI/CD

- Tests PR/push: `.github/workflows/tests.yml`
- Deploy main: `.github/workflows/deploy-main.yml`
- Distribution mobile: `.github/workflows/mobile-distribute.yml`

## Flux de deploiement actuel

1. Une PR est ouverte vers `main`
2. GitHub Actions execute les checks qualite
3. La PR est mergee seulement si les checks requis sont verts
4. Le push sur `main` relance les tests
5. Si les tests sur `main` sont verts, GitHub declenche le deploy Render
6. Le healthcheck API est verifie
7. Le build mobile staging peut etre distribue

## Architecture applicative actuelle

### API

Les modules actuellement visibles dans le code sont:

- authentification API
- contexte tenant
- gestion des employes
- presence / pointage
- estimation journaliere
- estimation rapide par periode
- generation PDF de recu
- endpoint de sante

### Web manager

Les routes web actuellement visibles couvrent:

- login web
- logout web
- dashboard manager
- consultation employe
- estimation rapide
- recu PDF

### Mobile

Le mobile est aligne pour consommer:

- auth login / me / logout
- listing employes
- pointage du jour
- historique presence
- parcours metiers relies a ces endpoints

## Fonctionnalites validees a date

### Validees dans le code et deja couvertes en CI

- login API
- endpoint `me`
- logout API
- healthcheck API
- controle RBAC employes
- isolation multitenant sur les listes employes
- check-in / check-out
- historique et vue du jour attendance
- estimation journaliere
- estimation rapide
- generation PDF de recu
- contrats JSON critiques consommes par le mobile
- garde-fous auth:
- compte archive refuse
- compte suspendu refuse
- acces estimation inter-tenant refuse

### Validees operationnellement par l'architecture

- migrations publiques puis tenant au boot Render
- bootstrap de table `migrations` durci pour eviter les courses au demarrage
- seeders de base idempotents
- seed demo sous controle
- pipeline mobile conditionnelle pour ne lancer les etapes lourdes que si `mobile/**` change

## Fonctionnalites partiellement couvertes ou a confirmer

Les sujets suivants sont identifies dans la documentation cible mais ne sont pas encore couverts completement dans les tests backend visibles:

- registre public complet / creation tenant par endpoint dedie
- gestion complete des conges
- paie / payroll complet avec RBAC finance
- notifications metier temps reel
- scenarios d'utilisateur bloque distincts des comptes archives
- contrats API etendus si de nouveaux ecrans mobile apparaissent

## Traceabilite des validations

### Documentation de reference

- scenarios mobile: `docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md`
- scenarios API: `docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md`
- rapport QA CI: `docs/GESTION_PROJET/RAPPORT_QA_CI_2026-04-18.md`
- runbook deploy: `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`

### Tests backend actuellement presents

- `api/tests/Feature/AuthLoginTest.php`
- `api/tests/Feature/AuthLoginGuardrailsTest.php`
- `api/tests/Feature/AuthMeLogoutTest.php`
- `api/tests/Feature/EmployeesRbacTest.php`
- `api/tests/Feature/TenantIsolationTest.php`
- `api/tests/Feature/Attendance/*`
- `api/tests/Feature/Estimation/*`
- `api/tests/Feature/Contracts/MobilePayloadContractTest.php`
- `api/tests/Unit/*`

## Reponse synthese au cahier des charges

### Ce qui est repondu aujourd'hui

- base SaaS RH multitenant en place
- authentification et securisation des acces en place
- gestion employes MVP en place
- suivi de presence exploitable en place
- estimation et recu PDF en place
- deploiement cloud automatise en place
- CI GitHub Actions active avec verifications backend, mobile, securite et gouvernance

### Ce qui est en reponse partielle

- couverture metier RH large au-dela du MVP
- paie complete
- absences/conges completes
- notifications et workflows etendus

### Ce qui reste a produire pour une reponse complete au CDC initial

- finaliser les modules metier manquants
- automatiser leurs tests backend et mobile
- consolider les contrats API finaux
- produire une recette d'acceptation par role complete

## Conclusion

Le produit n'est plus au stade de simple intention.
Il dispose deja d'une base technique coherente, deployable et verifiee en CI sur ses modules coeur actuellement presents.

Le bon intitule pour ce document est:

- dossier de reponse au cahier des charges

ou, si un nom plus formel est prefere:

- dossier de conformite fonctionnelle et technique

ou encore:

- dossier d'etat de realisation et d'architecture
