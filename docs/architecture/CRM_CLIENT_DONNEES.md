
## 5. Timeline et tâches (CRM-V0-06, issue #5710)

### `crm_activities` — timeline append-only

| Colonne | Type | Contrainte |
|---|---|---|
| `id` | bigint PK | auto |
| `company_id` | uuid | NOT NULL, index |
| `subject` | varchar(255) | NOT NULL |
| `activity_type` | varchar(30) | défaut `note`, **CHECK `crm_activities_type_check`** |
| `description` | text | nullable |
| `related_type` | varchar(30) | nullable, **CHECK `crm_activities_related_type_check`** |
| `related_id` | bigint | nullable, index |
| `owner_id` | bigint | nullable, index (employé du tenant) |
| `happened_at` | timestamp | défaut `now()` |
| `metadata` | text | nullable, **cast `encrypted:array`** |

- **Append-only** : aucune route de modification ; suppression réservée aux
  managers du tenant (Policy). Chaque création/suppression est auditée
  (`audit_logs`, trait `Auditable`).
- CHECK : `activity_type IN ('note','call','email','meeting','other')` ;
  `related_type IS NULL OR related_type IN ('lead','opportunity','contact','account')`.
- Index : `(company_id, related_type, related_id)`, `(company_id, happened_at)`,
  `(company_id, owner_id)` — timelines paginées sans N+1.

### `crm_tasks` — tâches bornées

| Colonne | Type | Contrainte |
|---|---|---|
| `id` | bigint PK | auto |
| `company_id` | uuid | NOT NULL, index |
| `subject` | varchar(255) | NOT NULL |
| `description` | text | nullable |
| `status` | varchar(20) | défaut `todo`, **CHECK `crm_tasks_status_check`** |
| `priority` | varchar(10) | défaut `medium`, **CHECK `crm_tasks_priority_check`** |
| `due_at` / `completed_at` | timestamp | nullable |
| `assignee_id` / `created_by_id` | bigint | nullable, index |
| `related_type` / `related_id` | — | comme activities |

- Cycle : `todo → in_progress → done | cancelled`. `done` horodate
  `completed_at` ; `done`/`cancelled` sont terminaux via l'API.
- **Partage explicite** : pivot `crm_task_assignees` (`task_id`, `employee_id`,
  `assigned_by_id`, `UNIQUE(task_id, employee_id)`, FK cascade).
- CHECK : `status IN ('todo','in_progress','done','cancelled')` ;
  `priority IN ('low','medium','high')` ; related_type comme activities.
- Index : `(company_id, assignee_id, status)`, `(company_id, status)`,
  `(company_id, due_at)`, `(company_id, related_type, related_id)`.
- Ownership : l'assigné, le créateur et les managers du tenant (`principal`,
  `rh`) accèdent à la tâche (Policies CRM-V0-07, issue #5711).

## 6. Contrats API et OpenAPI (CRM-V0-08, issue #5712)

- **Périmètre** : routes `/api/v1/crm/*` (fichier `api/routes/modules/crm.php`),
  middleware `throttle:api` + `auth:sanctum` + `token.refresh` + `tenant` +
  `throttle:api-plan`, puis `api.manager:principal,rh` (403 pour les
  non-managers / rôles hors périmètre) et Policies `App\Policies\Crm\*`
  (garde fine, aucune garde inline).
- **Distinction Platform CRM** : le CRM commercial Leopardo reste sous
  `/api/v1/platform/crm/*` (super-admin, `PlatformCrmPipelineController`) —
  aucune route client sous `/platform`, aucun import croisé (garde #5584).
- **Contrat OpenAPI** : 16 chemins `/crm/*` et 15 schémas
  (`CrmLead`, `CrmOpportunity`, `CrmActivity`, `CrmTask`, `CrmAccount`,
  `CrmContact`, `CrmPipeline`, `CrmPipelineStage` + payloads) dans
  `api/openapi.yaml` ; garde de couverture `check-openapi-route-coverage.py`
  (895/895, 0 drift) ; miroir `dev-hub/openapi/v1.yaml` et SDK JS/Python
  régénérés via `make openapi-sync` (garde `make openapi-check`).
- **Réponses** : enveloppe Laravel `{data, meta}` paginée sur les listes ;
  erreurs stables 401 `UNAUTHENTICATED`, 403 `MANAGER_REQUIRED` /
  `INSUFFICIENT_ROLE` (middleware) ou 403 Policy, 404 `RESOURCE_NOT_FOUND`
  (hors tenant ou inexistant), 422 validation (dont `_unknown` pour les
  champs inconnus).
- **Invariants API** : append-only pour la timeline (`/crm/activities` sans
  PUT → 405) ; `won_at`/`lost_at` dérivés de l'étape ; `completed_at` dérivé
  de la transition `done` ; archivage doux (`status: archived`) pour les
  comptes/contacts.
