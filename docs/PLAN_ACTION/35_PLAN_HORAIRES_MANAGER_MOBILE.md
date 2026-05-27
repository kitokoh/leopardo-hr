# Plan 35 - Horaires manager mobile

Date : 2026-05-27

## Objectif

Donner au manager/RH un vrai espace mobile pour definir les horaires de travail, pauses, tolerances de retard et seuils d'heures supplementaires, sans dupliquer la logique backend.

## Livrable realise

- Ecran mobile manager `/schedules` :
  - liste des horaires tenant-scope ;
  - creation/modification ;
  - suppression des horaires non defaut ;
  - choix des jours travailles ;
  - pause en minutes ;
  - tolerance retard ;
  - seuils heures supplementaires jour/semaine ;
  - marqueur horaire par defaut.
- Repository/provider Flutter dedies a l'API `/schedules`.
- Home manager enrichie avec un CTA `Horaires`.
- Garde backend `ScheduleControllerTest` :
  - manager autorise ;
  - employe refuse ;
  - isolation tenant de la liste.
- Contrats `FrontendApiContractTest`, OpenAPI et matrice frontend/API mis a jour.

## Decision

Le mobile manager reutilise l'API Laravel existante `ScheduleController`. Aucun endpoint mobile parallele n'est cree : le backend reste la source unique pour les regles de temps.

## Suite logique

Plan 36 doit raccorder les horaires aux fiches employes : choix de l'horaire dans ajout/modification employe, affichage sur detail employe et verification que le calcul de pointage exploite bien l'horaire assigne.
