# Rapport Plan 69.3 - Smoke API manager/RH et isolation

Date : 2026-06-01  
Backend : `https://gestionemployerbackend.onrender.com/api/v1`  
Compte : `ahmed.benali@techcorp-algerie.dz`  
Reference main testee avant correction : `efd4c150`

## Verdict

**No-go partiel avant correction.** Les endpoints manager critiques repondent sauf la liste equipe `GET /employees?per_page=50`, qui retourne `500` sur Render. Cette route alimente l'ecran Equipe manager/RH : tant qu'elle casse, le manager peut voir le dashboard mais ne peut pas exploiter correctement son equipe.

Correction livree dans ce lot :

- `EmployeeController@index` selectionne maintenant les champs serialises par `EmployeeResource` seulement quand ils existent dans le schema courant.
- `EmployeeResource` tolere les attributs optionnels absents sur les modeles partiellement charges.
- Objectif : eviter les erreurs de ressource sur modeles Eloquent partiellement charges ou schemas Render partiellement migres, tout en gardant la pagination et l'isolation tenant existantes.

## Resultats Render avant correction

### Authentification

- `POST /auth/login` manager principal : OK.
- `GET /auth/me` : OK.

### Endpoints operationnels

- `GET /dashboard/manager-digest` : OK.
- `GET /schedules` : OK.
- `GET /tasks/today` : OK, liste vide coherente.
- `GET /absences` : OK, 4 demandes visibles avec contexte.
- `GET /salary-advances` : OK, 3 demandes visibles.
- `GET /attendance/corrections` : OK, 1 demande visible.

### Endpoint bloquant

- `GET /employees?per_page=50` : `500 Server Error`.

Impact :

- ecran Equipe manager/RH peut rester en erreur ou spinner ;
- creation de taches depuis le mobile est degradee car le select collaborateur depend de cette liste ;
- verification d'isolation par liste equipe ne peut pas etre conclue avant correction/deploiement.

## Corrections appliquees

- `api/app/Http/Controllers/Api/V1/EmployeeController.php`
- `api/app/Http/Resources/Api/V1/EmployeeResource.php`

## Validations locales rapides

- `php -l api/app/Http/Controllers/Api/V1/EmployeeController.php` : OK.
- `git diff --check` : OK.

Le test local `php artisan test --filter=EmployeesRbacTest` n'a pas pu etre exploite sur ce poste Windows car `api/vendor` est incomplet (`symfony/deprecation-contracts/function.php` manquant). La source de verite reste GitHub Actions.

## Prochaine preuve attendue

Apres merge/deploiement Render :

1. retester `GET /employees?per_page=50` avec le manager TechCorp ;
2. confirmer que tous les `company_id` retournes correspondent au tenant du manager ;
3. creer puis supprimer une tache assignee a Karim ;
4. creer puis archiver un collaborateur temporaire sans invitation ;
5. documenter le verdict final Plan 69.3.
