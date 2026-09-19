# Feature Specification: Espace gérant — verticale Agence de voyage (web)

**Feature Branch**: `bc/bc-24-espace-gerant-web`
**Created**: 2026-09-14 | **Status**: Draft → In progress
**Épic**: #7642 — Issues : #7633 (A1), #7634 (A2), #7635 (A3), #7636 (A4), #7637 (A5)

## Problème

La verticale TravelAgency dispose d'un backend complet (`api/routes/modules/travelagency.php`,
préfixe `/travel`, feature flag tenant `travelagency`) et d'un portail voyageur
(`/travel/portal`), mais le **gérant** d'agence n'a aucun espace web dans `front/web` :
l'index `/travel` redirige vers le portail voyageur et la navigation ne propose aucun
point d'entrée métier (référentiel, voyages, réservations, rapports), contrairement à la
verticale modèle Restaurant (`(dashboard)/restaurant/` : hub + sous-pages).

## User Stories & Testing

### User Story 1 — A1 #7633 : hub gérant + navigation (P1)
En tant que gérant d'agence, j'arrive sur `/travel` et je vois les KPIs du jour et des
accès rapides vers les sous-espaces.

**Acceptance Scenarios**:
1. Given un tenant `travelagency` actif et un manager connecté, When il ouvre `/travel`,
   Then un hub s'affiche (plus de redirect) : KPIs de `GET /travel/reports/dashboard`
   (ventes, passagers, recette nette, occupation, annulations, trajets) avec états
   loading / empty / error, cartes vers `/travel/network`, `/travel/trips`,
   `/travel/bookings`, `/travel/reports` et lien vers `/travel/portal`.
2. Given le catalogue nav `client-features.ts`, Then l'entrée `travel` pointe sur `/travel`
   (hub gérant) et une sous-entrée `travel_portal` pointe sur `/travel/portal`, toutes deux
   gatées par `featureKeys: ['travelagency','travel_agency']`.
3. Given l'API en erreur, Then un bandeau d'erreur + bouton réessayer (clés
   `travel.common.loadErrorTitle/Body`, `travel.common.retry`), pas d'écran blanc.
4. i18n : clés `travel.*` existantes réutilisées ; les nouvelles clés existent dans les
   4 locales (fr, en, tr, ar) de `shared/i18n/locales/` ET du miroir
   `front/web/src/lib/i18n/locales/` (sync `shared/i18n/sync/sync-web.js`). RTL (ar) OK.

### User Story 2 — A2 #7634 : page /travel/network (P1)
CRUD gares (`/travel/stations`), bureaux (`/travel/offices`), lignes (`/travel/routes`)
et arrêts ordonnés (`/travel/routes/{r}/stops`), avec listes de référence
(cities, countries, carriers, classes, vehicles).

### User Story 3 — A3 #7635 : page /travel/trips (P1)
Liste + recherche (`GET /travel/trips`, `GET /travel/trips/search`), création de voyage
(ligne, horaires, véhicule, transporteur), tarifs par classe (`/travel/trips/{t}/prices`),
publication (`POST /travel/trips/{t}/publish`), annulation (`POST /travel/trips/{t}/cancel`),
manifeste (`GET /travel/trips/{t}/manifest`).

### User Story 4 — A4 #7636 : page /travel/bookings (P1)
Liste, détail (`GET /travel/bookings/{b}`), actions confirm / cancel / refund /
refund-passenger, émission billets (`issue-ticket`), PDF (`GET /travel/tickets/{t}/pdf`),
check-in (`POST /travel/tickets/{t}/check-in`). Dé-skipper et adapter
`front/web/e2e/travel-admin-smoke.spec.ts`.

### User Story 5 — A5 #7637 : page /travel/reports (P1)
4 rapports (`sales`, `occupancy`, `revenue`, `cancellations`) avec sélection de période,
et export asynchrone (`POST /travel/reports/export` puis `GET /travel/reports/export/{asset}`).

## Non-Goals
- Ne pas modifier le backend ni les routes API.
- Ne pas casser le portail voyageur `/travel/portal` (le hub garde un lien vers lui).
- Aucune modification hors `front/web`, `shared/i18n`, `CHANGELOG.md`, `.specify/features/`.
- Pas de push : le superviseur pushe la branche.

## Contraintes de vérification
`cd front/web && npm run lint && npx tsc --noEmit` — zéro erreur sur les fichiers touchés,
pas de `any` gratuits, réponses API typées.
