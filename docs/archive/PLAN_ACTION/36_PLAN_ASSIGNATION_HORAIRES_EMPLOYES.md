# Plan 36 - Assignation des horaires aux employes

Date : 2026-05-27

## Objectif

Rendre les horaires crees au Plan 35 vraiment exploitables : le manager doit pouvoir affecter un horaire a un employe des la creation, et le backend doit refuser tout horaire provenant d'un autre tenant.

## Livrable realise

- API employees :
  - `schedule_id` accepte sur `POST /api/v1/employees` et `PUT/PATCH /api/v1/employees/{employee}` ;
  - validation `schedule_id` limitee a l'entreprise courante ;
  - `EmployeeResource` expose `schedule_id` et un resume `schedule`.
- Onboarding QR manager :
  - `POST /api/v1/company/qr-onboarding/create-employee` accepte aussi `schedule_id`.
- Mobile manager :
  - formulaire ajout employe charge les horaires via `/schedules` ;
  - selection "Horaire par defaut" ou horaire explicite ;
  - l'horaire assigne est envoye a l'API ;
  - la liste equipe affiche l'horaire associe.
- Tests :
  - creation employee avec horaire du tenant ;
  - refus d'un horaire provenant d'une autre entreprise.

## Suite logique

Plan 37 doit enrichir le detail employe mobile : edition du profil existant, changement d'horaire, changement salaire/poste, et verification que le calcul de pointage affiche clairement l'horaire applique.
