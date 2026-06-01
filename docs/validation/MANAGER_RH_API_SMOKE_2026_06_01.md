# Rapport Plan 69.3 - Smoke API manager/RH et isolation

Date : 2026-06-01  
Backend : `https://gestionemployerbackend.onrender.com/api/v1`  
Compte : `ahmed.benali@techcorp-algerie.dz`  
Reference main testee avant correction : `efd4c150`  
Reference main validee apres corrections : `8ba81073`

## Verdict

**Go apres corrections #679/#680.** Les endpoints manager critiques repondent, la liste equipe `GET /employees?per_page=50` ne retourne plus de `500` sur Render, et les actions manager de base sont executees avec le contrat mobile.

Correction livree dans ce lot :

- `EmployeeController@index` selectionne maintenant les champs serialises par `EmployeeResource` seulement quand ils existent dans le schema courant.
- `EmployeeResource` tolere les attributs optionnels absents sur les modeles partiellement charges.
- `EmployeeController@index` filtre aussi les colonnes relationnelles `company` / `schedule`, la recherche et le tri attendance via `Schema::hasColumn`.
- Objectif atteint : eviter les erreurs de ressource sur modeles Eloquent partiellement charges ou schemas Render partiellement migres, tout en gardant la pagination et l'isolation tenant existantes.

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

## Preuve finale Render apres corrections

1. `GET /employees?per_page=50` avec le manager TechCorp : OK.
2. Isolation liste equipe : OK, 13 collaborateurs retournes, un seul `company_id` (`9fffe35e-6777-42c1-b1db-faaee04d0ef7`).
3. Creation puis suppression d'une tache assignee a Karim : OK, tache temporaire `10`.
4. Creation puis archivage d'un collaborateur temporaire avec le contrat mobile (`send_invitation=true`, `salary_type=fixed`) : OK, collaborateur temporaire `31`.

Verdict Plan 69.3 : **Go** pour les parcours manager/RH de base sur Render.
