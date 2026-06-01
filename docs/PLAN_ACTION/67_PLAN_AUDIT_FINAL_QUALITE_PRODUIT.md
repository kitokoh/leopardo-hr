# Plan 67 - Audit final qualite produit et lancement

## Source

Ce plan prend la suite des plans 01-66 apres la matrice anti-oubli du 2026-06-01.

Il sert a passer d'une accumulation de plans a une discipline de lancement : preuve d'ouverture mobile, preuve API, preuve super-admin, preuve notifications et preuve release.

## Objectif

Amener Leopardo RH au niveau "pret lancement marche" avec des preuves simples, repetables et actionnables.

Le produit vise le positionnement :

> OS de gestion d'entreprise mobile-first.

## Regles d'execution

- Ne plus rouvrir les plans 01-66 sauf pour corriger une reference.
- Tout nouveau lot doit pointer vers la matrice `docs/validation/PLAN_ACTION_COVERAGE_MATRIX_2026_06_01.md`.
- Chaque lot doit livrer code, documentation et preuve CI ou rapport de verification.
- Les checks GitHub Actions restent la source de verite pour les validations couteuses.
- Les trois apps mobiles de lancement restent dans `front/mobile_apps/`.

## Lot 67.1 - Mobile runtime smoke strict

### Probleme

Les testeurs ont deja vu des pages grises/noires ou un logo bloque. Meme si le code bootstrap a ete corrige, il faut une preuve de non-regression par app.

### Actions

- Ajouter ou completer un smoke de demarrage pour :
  - `leopardo_employee`
  - `leopardo_manager`
  - `leopardo_platform_admin`
- Verifier que chaque app affiche un ecran actionnable sans attendre Hive, Firebase, Google Sign-In, intl ou reseau.
- Verifier les noms de build/release pour eviter la confusion `main` / `manual`.
- Documenter la preuve dans un rapport release readiness.

### Critere de sortie

Les trois apps affichent un premier ecran Flutter lisible et le workflow mobile CI/Firebase reste vert.

### Statut

**Livre en cours de validation CI.** Le garde `dev-hub/tools/validate-mobile-runtime-smoke.ps1` bloque les regressions structurelles de demarrage et le rapport `docs/validation/MOBILE_RUNTIME_SMOKE_REPORT_2026_06_01.md` documente le contrat.

## Lot 67.2 - Super-admin platform admin end-to-end

### Probleme

Le super-admin mobile est separe, mais le parcours complet doit etre prouve : login, session, creation entreprise, fiche client, abonnement, modules.

### Actions

- Verifier auth platform admin : login demo, session locale, token, erreurs 401/403/202 2FA.
- Verifier creation entreprise via `POST /api/v1/platform/companies`.
- Verifier fiche client : health, subscription, features.
- Ajouter tests backend ou contrats mobile manquants si une route n'est pas couverte.

### Critere de sortie

Un super-admin peut se connecter et creer un client sans acces tenant parasite.

### Statut

**Livre en cours de validation CI.** `PlatformRepository.createCompany()` retourne maintenant le client cree, l'app ouvre sa fiche directement et `docs/validation/PLATFORM_ADMIN_E2E_REPORT_2026_06_01.md` documente le parcours.

## Lot 67.3 - GPS natif, geofence UX et notification douce

### Probleme

Le backend sait calculer le geofence, mais la partie permission native et feedback manager/employee doit etre finalisee.

### Actions

- Ajouter l'acquisition GPS mobile de maniere non bloquante.
- Envoyer latitude/longitude/accuracy avec le pointage quand disponible.
- Afficher un message employe doux en cas de hors-zone.
- Creer une notification manager si la politique entreprise le demande.

### Critere de sortie

Le pointage reste possible sans GPS, mais devient plus fiable quand la permission est accordee.

## Lot 67.4 - Theming tenant applique

### Probleme

Le Plan 58 livre le contrat branding et l'ecran manager, mais les apps n'appliquent pas encore globalement le theme tenant.

### Actions

- Lire `GET /api/v1/company/branding` apres login.
- Appliquer logo/couleurs en mode safe dans manager et employee.
- Garder accessibilite : contraste minimal, fallback Leopardo si couleur invalide.
- Ne pas appliquer de theme tenant sur platform admin global.

### Critere de sortie

Une entreprise cliente voit son identite visuelle dans son espace sans casser la lisibilite.

## Lot 67.5 - Notifications production proof

### Probleme

Les tokens FCM, preferences et jobs existent, mais il faut une preuve simple de bout en bout.

### Actions

- Verifier enregistrement/suppression device token sur login/logout.
- Verifier notification employee, manager et super-admin sur cas metier.
- Documenter les secrets requis et le mode fallback audit-only.
- Ajouter un rapport de test manuel si FCM ne peut pas etre teste localement.

### Critere de sortie

Les notifications ne sont plus seulement architecturales : chaque profil a au moins un scenario prouve.

## Lot 67.6 - Release readiness par profil

### Probleme

Les modules sont nombreux. Le lancement exige une preuve lisible par profil et non une liste de commits.

### Actions

- Produire un rapport `docs/validation/RELEASE_READINESS_REPORT_YYYY_MM_DD.md`.
- Couvrir :
  - employee
  - manager/RH
  - platform admin
  - API publique
  - vitrine web
  - kiosk
- Lister risques restants, contournements et priorites post-lancement.

### Critere de sortie

Le depot peut etre audite rapidement avant marketing, avec un score par surface et des actions restantes.

## Lot 67.7 - Marketplace/open core cadrage

### Probleme

Marketplace et open core sont strategiques, mais non bloquants pour lancer. Les traiter trop tot peut creer un risque legal/securite.

### Actions

- Definir ce qui peut devenir open source.
- Definir ce qui reste enterprise.
- Lister les secrets, donnees demo, licences et modules a isoler.
- Cadrer plugins/webhooks/scopes API sans implementation prematuree.

### Critere de sortie

Decision strategique documentee, sans exposer de code sensible.

## Ordre recommande

1. Lot 67.1
2. Lot 67.2
3. Lot 67.3
4. Lot 67.4
5. Lot 67.5
6. Lot 67.6
7. Lot 67.7

## Statut

**Cree le 2026-06-01.** Execution a demarrer apres merge de cette planification.
