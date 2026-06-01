# Rapport Plan 69.2 - Smoke API employe terrain

Date : 2026-06-01  
Backend : `https://gestionemployerbackend.onrender.com/api/v1`  
Compte : `karim.aouad@techcorp-algerie.dz`  
Reference main testee avant correction : `a4a0e1f2`

## Verdict

**Go partiel.** Le parcours pointage multiple et avance fonctionne sur Render. Le parcours absence a revele une rupture de contrat mobile/API : les apps mobiles lisaient `/leave-balances`, route reservee manager, alors que le contrat self-service canonique est `/me/leave-balances`.

Correction livree dans ce lot :

- `leopardo_employee` lit maintenant `/me/leave-balances`.
- `leopardo_manager` lit aussi `/me/leave-balances` pour son formulaire de demande personnelle.
- Le contrat mobile reference `/me/leave-balances`.
- Le seed demo cree/backfill des `leave_balances` pour les entreprises demo existantes afin que les testeurs puissent demander une absence sans intervention RH manuelle.

## Resultats Render

### Authentification

- `GET /demo-users` : OK, expose les comptes QA attendus.
- `POST /auth/login` employee : OK.
- `GET /auth/me` employee : OK.

Un `500` transitoire a ete observe une fois au login, puis cinq tentatives consecutives ont reussi. A surveiller comme signal Render/cold-start, pas encore comme regression reproductible.

### Lectures employee

Endpoints lus avec token employee :

- `GET /attendance/today` : OK.
- `GET /tasks/today` : OK.
- `GET /me/monthly-summary` : OK.
- `GET /absences` : OK.
- `GET /salary-advances` : OK.
- `GET /notifications?unread=true` : OK.
- `GET /me/career` : OK.
- `GET /cabinet/stats` : OK.

### Pointage multiple

Avant pointage, `attendance/today` retournait :

- `checked_in=false`
- `sessions=[]`
- `summary.sessions_count=0`
- `timezone=Africa/Algiers`

Actions executees :

1. `POST /attendance/check-in` avec `work_type=normal` : OK, log `365`, session `1`.
2. `POST /attendance/check-out` : OK, session `1` fermee.
3. `POST /attendance/check-in` avec `work_type=overtime` : OK, log `366`, session `2`.
4. `POST /attendance/check-out` : OK, session `2` fermee.

Etat final :

- `sessions_count=2`
- `is_working=false`
- `work_types=normal,overtime`
- `session_numbers=1,2`

Le compte demo n'est pas laisse en session ouverte.

### Avances

Actions executees :

1. `POST /salary-advances` montant `1000`, `repayment_months=1` : OK, demande `5`.
2. `DELETE /salary-advances/5` : OK, demande rejetee/annulee.

La reponse contient le contexte attendu : employe, entreprise, montant, motif, statut et champs de double validation.

### Absences

Constat avant correction :

- `POST /absences` sans `absence_type_id` retourne correctement `422 VALIDATION_ERROR`.
- `GET /leave-balances` retourne `403` pour un employe, car la route est manager-only.
- `GET /me/leave-balances` retourne OK mais vide sur la demo actuelle de Render.

Conclusion :

- Le backend avait le bon contrat self-service, mais les apps mobiles utilisaient l'ancienne route manager.
- Le seed demo ne remplissait pas `leave_balances`, seulement `leave_balance_logs`, ce qui rendait impossible une demande d'absence complete pour les testeurs demo.

## Corrections appliquees

- `front/mobile_apps/leopardo_employee/lib/features/absences/data/absence_repository.dart`
- `front/mobile_apps/leopardo_manager/lib/features/absences/data/absence_repository.dart`
- `dev-hub/tools/mobile-workflow-contracts.json`
- `api/database/seeders/DemoCompanySeeder.php`
- `api/database/seeders/DemoCompanyOnceSeeder.php`

## Validations locales rapides

- `php -l api/database/seeders/DemoCompanySeeder.php` : OK.
- `php -l api/database/seeders/DemoCompanyOnceSeeder.php` : OK.
- `dev-hub/tools/validate-mobile-workflow-contracts.ps1` : OK.

## Prochaine preuve attendue

Apres merge/deploiement Render :

1. verifier `GET /me/leave-balances` sur Karim retourne au moins un type annuel ;
2. creer une absence employee via `POST /absences` avec ce type ;
3. annuler la demande via `DELETE /absences/{id}` ;
4. confirmer cote mobile que le formulaire absence ne reste plus bloque sur "aucun type disponible".
