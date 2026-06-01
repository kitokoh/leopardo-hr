# Rapport mobile GPS/geofence - 2026-06-01

## Perimetre

- `front/mobile_apps/leopardo_employee`
- `front/mobile_apps/leopardo_manager`
- `front/mobile_apps/leopardo_core`
- API attendance Laravel

## Objectif

Finaliser le Lot 67.3 : le pointage mobile doit envoyer la position quand elle est disponible, sans bloquer le pointage si la permission GPS est refusee, lente ou indisponible.

## Contrat livre

- Le service partage `AttendanceLocationService` collecte la position avec `geolocator` et timeout court.
- Les apps employee et manager declarent les permissions Android `ACCESS_FINE_LOCATION` / `ACCESS_COARSE_LOCATION`.
- Les apps employee et manager declarent `NSLocationWhenInUseUsageDescription` sur iOS.
- Les providers employee et manager demandent la position au moment du pointage uniquement.
- Les repositories envoient `gps_lat`, `gps_lng`, `gps_accuracy` et `device_timezone`.
- Le backend valide `gps_accuracy`, l'ajoute au DTO et l'expose en lecture via `gps.accuracy_m`.
- Le pointage reste possible sans GPS : le retour utilisateur indique simplement que la verification de zone n'a pas pu etre faite.
- Si le backend repond `geofence.inside=false`, l'app affiche un message doux sans bloquer le workflow.

## Garde CI

Le script `dev-hub/tools/validate-mobile-location-readiness.ps1` verifie :

- dependance `geolocator` dans `leopardo_core`;
- service GPS non bloquant;
- permissions natives employee/manager;
- provider location employee/manager;
- payload GPS/accuracy dans les repositories;
- validation backend `gps_accuracy`;
- exposition `gps.accuracy_m` dans `AttendanceLogResource`.

Ce script est execute par `mobile-apps-ci.yml` dans le job `Mobile apps split guard`.

## Risques restants

- La preuve terrain necessite un test device Android/iOS avec permission accordee et refusee.
- La notification manager hors-zone dependra du Lot 67.5 pour la preuve FCM de bout en bout.
- La precision GPS varie selon device, OS et environnement; le backend doit garder une UX bienveillante et non punitive.
