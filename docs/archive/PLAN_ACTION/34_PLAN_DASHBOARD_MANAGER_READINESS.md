# Plan 34 - Dashboard manager readiness

Date : 2026-05-27

## Objectif

Transformer la carte manager mobile "A surveiller aujourd'hui" en cockpit branche sur les donnees reelles, sans fuite inter-tenant ni chiffres statiques.

## Livrable realise

- API `GET /api/v1/dashboard/manager-digest` reservee manager.
- Comptage tenant-scope des presences du jour, retards, sessions ouvertes, absences pending, avances pending et corrections pending.
- Scope equipe :
  - `principal` et `rh` voient l'entreprise courante.
  - les autres managers voient seulement eux-memes et leurs collaborateurs directs (`manager_id`).
- Carte mobile manager branchee sur l'API avec etat loading inline, refresh manuel et CTA vers les modules utiles.
- Tests backend couvrant les donnees reelles et l'isolation company/equipe directe.
- Matrice frontend/API et scenarios API mis a jour.

## Risques couverts

- Donnees d'un autre tenant visibles dans le dashboard manager.
- Retours testeurs bases sur des chiffres hardcodes.
- Boutons home manager sans contrat API associe.

## Suite logique

Plan 35 doit traiter l'ecran manager des horaires/regles de travail : liste des horaires, creation/modification, pauses, tolerances de retard, seuils heures supplementaires, puis raccord aux employes.
