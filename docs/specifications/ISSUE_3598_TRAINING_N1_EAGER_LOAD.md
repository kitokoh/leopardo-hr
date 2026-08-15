# Issue #3598 — N+1 sur GET /v1/training/enrollments et /v1/admin/training/enrollments

## Contexte

`TrainingEnrollmentResource` accède à `$this->session?->course?->title` via
`whenLoaded('session', …)`. Bien que `session` soit eager-loadé dans
`TrainingController::indexEnrollments` et
`PlatformAdminTrainingController::indexEnrollments`, la relation
`session.course` ne l'était pas → lazy-load par enregistrement (N+1).

`SelfServiceController@myTrainings` chargait déjà `session.course` correctement.

## Correctif

Ajout de `'session.course:id,title'` aux relations eager-loadées dans :

- `api/app/Modules/HR/Interfaces/Api/V1/Controllers/TrainingController.php` (`indexEnrollments`)
- `api/app/Modules/Platform/Interfaces/Api/V1/Controllers/PlatformAdminTrainingController.php` (`indexEnrollments`)

## Avant

```php
->with(['employee:id,first_name,last_name', 'session:id,training_course_id,start_date,status'])
```

## Après

```php
->with(['employee:id,first_name,last_name', 'session:id,training_course_id,start_date,status', 'session.course:id,title'])
```

## Impact

- Suppression du N+1 : 1 requête supplémentaire au lieu de N (une par enrollment).
- Aucun changement de contrat API : `course_title` déjà exposé par la Resource.
- `SelfServiceController` déjà correct — non modifié.
