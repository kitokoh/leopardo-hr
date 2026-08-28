# Matrice API — frontière CRM commercial (platform) / CRM client (tenant) / callbacks providers

- **Statut :** ratifié — référentiel des contrats API du programme CRM (issue #5737, CRM-PRE)
- **Date :** 2026-08-28
- **ADR :** `docs/architecture/refactoring/ADR-CRM-DUAL-CONTEXTS.md` (ADR-CRM-002 : routes/tables/menus/permissions strictement séparés)
- **Plan :** `docs/specifications/PLAN-V0-V1-CRM-CLIENT-SQL-INTEGRATIONS.md`
- **Module :** `docs/specifications/MODULE_CRM_INTERNE_CLIENT.md`
- **Prépare :** #5711 (Policies), #5712 (contrats OpenAPI), #5715 (UI web), #5717 (conversion)

---

## 1. Principe

Trois surfaces API, trois frontières, zéro croisement :

| Surface | Base URL | Auth | Schéma | Owner |
|---|---|---|---|---|
| **Platform (CRM commercial Leopardo)** | `/api/v1/platform/*` | `auth:super_admin_api` | `public` | Équipe Plateforme/Marketing Leopardo |
| **Tenant (CRM client)** | `/api/v1/crm/*` | `auth:sanctum` + `tenant` + Policies | tenant (`search_path`) | Entreprise cliente |
| **Provider callbacks (webhooks)** | `/api/v1/webhooks/*`, `/api/v1/*/webhook` | secret partagé signé | `public` (non-tenant) ou tenant selon contrat | Fournisseurs externes |

Règle absolue : **aucune route de la surface tenant ne consomme `PlatformCrmPipelineController` ni aucune route `/api/v1/platform/*`** (ADR-CRM-002). Un client tenant qui tente d'appeler le CRM commercial reçoit `401 UNAUTHENTICATED` (garde `super_admin_api`) — testé par `CrmPlatformIsolationTest`.

---

## 2. Matrice détaillée — surface tenant `/api/v1/crm/*`

Légende : **P** = Policy requise, **S** = scope tenant (toute requête est scopée `company_id` + binding vérifié dans le tenant authentifié).

### 2.1 Comptes (`crm_accounts`)

| Méthode | Route | Controller | Request | Resource | Policy | Scope | Statuts |
|---|---|---|---|---|---|---|---|
| GET | `/api/v1/crm/accounts` | `CrmAccountController@index` | — | `CrmAccountResource` (collection paginée) | `viewAny` | S | 200, 401, 403, 429 |
| POST | `/api/v1/crm/accounts` | `CrmAccountController@store` | `StoreCrmAccountRequest` | `CrmAccountResource` | `create` | S | 201, 401, 403, 422, 429 |
| GET | `/api/v1/crm/accounts/{crmAccount}` | `CrmAccountController@show` | — | `CrmAccountResource` | `view` | S + binding | 200, 401, 403, 404, 429 |
| PUT | `/api/v1/crm/accounts/{crmAccount}` | `CrmAccountController@update` | `UpdateCrmAccountRequest` | `CrmAccountResource` | `update` | S + binding | 200, 401, 403, 404, 422, 429 |
| DELETE | `/api/v1/crm/accounts/{crmAccount}` | `CrmAccountController@destroy` | — | — | `archive` | S + binding | 200 (archivé), 401, 403, 404, 409, 429 |
| GET | `/api/v1/crm/accounts/{crmAccount}/contacts` | `CrmContactController@index` | — | `CrmContactResource` (pag.) | `view` | S + binding | 200, 401, 403, 404, 429 |

### 2.2 Contacts (`crm_contacts`)

| Méthode | Route | Controller | Request | Resource | Policy | Scope | Statuts |
|---|---|---|---|---|---|---|---|
| GET | `/api/v1/crm/contacts` | `CrmContactController@index` | — | `CrmContactResource` (pag.) | `viewAny` | S | 200, 401, 403, 429 |
| POST | `/api/v1/crm/contacts` | `CrmContactController@store` | `StoreCrmContactRequest` | `CrmContactResource` | `create` | S | 201, 401, 403, 422, 429 |
| GET | `/api/v1/crm/contacts/{crmContact}` | `CrmContactController@show` | — | `CrmContactResource` | `view` | S + binding | 200, 401, 403, 404, 429 |
| PUT | `/api/v1/crm/contacts/{crmContact}` | `CrmContactController@update` | `UpdateCrmContactRequest` | `CrmContactResource` | `update` | S + binding | 200, 401, 403, 404, 422, 429 |
| DELETE | `/api/v1/crm/contacts/{crmContact}` | `CrmContactController@destroy` | — | — | `archive` | S + binding | 200 (archivé), 401, 403, 404, 409, 429 |

### 2.3 Leads (`crm_leads`)

| Méthode | Route | Controller | Request | Resource | Policy | Scope | Statuts |
|---|---|---|---|---|---|---|---|
| GET | `/api/v1/crm/leads` | `CrmLeadController@index` | — | `CrmLeadResource` (pag.) | `viewAny` | S | 200, 401, 403, 429 |
| POST | `/api/v1/crm/leads` | `CrmLeadController@store` | `StoreCrmLeadRequest` | `CrmLeadResource` | `create` | S | 201, 401, 403, 422, 429 |
| GET | `/api/v1/crm/leads/{crmLead}` | `CrmLeadController@show` | — | `CrmLeadResource` | `view` | S + binding | 200, 401, 403, 404, 429 |
| PUT | `/api/v1/crm/leads/{crmLead}` | `CrmLeadController@update` | `UpdateCrmLeadRequest` | `CrmLeadResource` | `update` | S + binding | 200, 401, 403, 404, 422, 429 |
| DELETE | `/api/v1/crm/leads/{crmLead}` | `CrmLeadController@destroy` | — | — | `archive` | S + binding | 200 (archivé), 401, 403, 404, 409, 429 |
| POST | `/api/v1/crm/leads/{crmLead}/convert` | `CrmLeadController@convert` | `ConvertLeadRequest` | `CrmLeadResource` | `convert` | S + binding | 200, 401, 403, 404, **409** (déjà converti), 422, 429 |

### 2.4 Pipelines & opportunités (`crm_pipelines`, `crm_opportunities`)

| Méthode | Route | Controller | Request | Resource | Policy | Scope | Statuts |
|---|---|---|---|---|---|---|---|
| GET | `/api/v1/crm/pipelines` | `CrmPipelineController@index` | — | `CrmPipelineResource` | `viewAny` | S | 200, 401, 403, 429 |
| POST | `/api/v1/crm/pipelines` | `CrmPipelineController@store` | `StoreCrmPipelineRequest` | `CrmPipelineResource` | `create` | S | 201, 401, 403, 422, 429 |
| GET | `/api/v1/crm/opportunities` | `CrmOpportunityController@index` | — | `CrmOpportunityResource` (pag.) | `viewAny` | S | 200, 401, 403, 429 |
| POST | `/api/v1/crm/opportunities` | `CrmOpportunityController@store` | `StoreCrmOpportunityRequest` | `CrmOpportunityResource` | `create` | S | 201, 401, 403, 422, 429 |
| GET | `/api/v1/crm/opportunities/{crmOpportunity}` | `CrmOpportunityController@show` | — | `CrmOpportunityResource` | `view` | S + binding | 200, 401, 403, 404, 429 |
| PATCH | `/api/v1/crm/opportunities/{crmOpportunity}/stage` | `CrmOpportunityController@moveStage` | `MoveOpportunityStageRequest` | `CrmOpportunityResource` | `update` | S + binding | 200, 401, 403, 404, 422, 429 |

### 2.5 Tâches & activités (`crm_tasks`, `crm_activities`)

| Méthode | Route | Controller | Request | Resource | Policy | Scope | Statuts |
|---|---|---|---|---|---|---|---|
| GET | `/api/v1/crm/tasks` | `CrmTaskController@index` | — | `CrmTaskResource` (pag.) | `viewAny` | S | 200, 401, 403, 429 |
| POST | `/api/v1/crm/tasks` | `CrmTaskController@store` | `StoreCrmTaskRequest` | `CrmTaskResource` | `create` | S | 201, 401, 403, 422, 429 |
| PATCH | `/api/v1/crm/tasks/{crmTask}/done` | `CrmTaskController@toggleDone` | — | `CrmTaskResource` | `update` | S + binding | 200, 401, 403, 404, 429 |
| GET | `/api/v1/crm/activities` | `CrmActivityController@index` | — | `CrmActivityResource` (pag.) | `viewAny` | S | 200, 401, 403, 429 |
| POST | `/api/v1/crm/activities` | `CrmActivityController@store` | `StoreCrmActivityRequest` | `CrmActivityResource` | `create` | S | 201, 401, 403, 422, 429 |

### 2.6 Import CSV & déduplication (livrés par #5714/#5718)

| Méthode | Route | Controller | Request | Resource | Policy | Scope | Statuts |
|---|---|---|---|---|---|---|---|
| POST | `/api/v1/crm/imports` | `CrmImportController@store` | `StoreCrmImportRequest` | `CrmImportResource` | `create` | S | **201**, 401, 403, 422, 429 |
| GET | `/api/v1/crm/imports/{crmImport}` | `CrmImportController@show` | — | `CrmImportResource` | `view` | S + binding | 200, 401, 403, 404, 429 |
| POST | `/api/v1/crm/imports/{crmImport}/commit` | `CrmImportController@commit` | — | `CrmImportResource` | `commit` | S + binding | **202** (async) ou 200 (sync), 401, 403, 404, **409** (idempotence), 429 |
| POST | `/api/v1/crm/imports/{crmImport}/cancel` | `CrmImportController@cancel` | — | `CrmImportResource` | `cancel` | S + binding | 200, 401, 403, 404, 409, 429 |
| GET | `/api/v1/crm/dedup/suggestions` | `CrmDedupController@suggestions` | — | — | `viewSuggestions` | S | 200, 401, 403, 429 |
| GET | `/api/v1/crm/merge/preview` | `CrmDedupController@preview` | — | — | `preview` | S | 200, 401, 403, 404, 429 |
| POST | `/api/v1/crm/merge` | `CrmDedupController@merge` | `CrmMergeRequest` | — | `merge` (principal) | S | 200, 401, 403, 404, 422, 429 |

### 2.7 Recherche (V1, #5719)

| Méthode | Route | Controller | Request | Resource | Policy | Scope | Statuts |
|---|---|---|---|---|---|---|---|
| GET | `/api/v1/crm/search?q=&entity=&page=` | `CrmSearchController@index` | — | collection mixte | `search` | S | 200, 401, 403, 422, 429 |

---

## 3. Conventions transverses

### 3.1 Route binding (ADR-CRM-003)
- Paramètres : `{crmAccount}`, `{crmContact}`, `{crmLead}`, `{crmOpportunity}`, `{crmTask}`, `{crmImport}` — **implicit binding Eloquent avec `whereNumber`**.
- Le binding est résolu **dans le tenant authentifié** : tout id hors tenant → **404** (jamais 403, jamais 200) grâce au scope global `BelongsToCompany`.
- Aucun `company_id` fourni par le client ne constitue une autorisation (règle non négociable).

### 3.2 Pagination, tri, filtres, expansions
- **Pagination** : `per_page` plafonné à **100** (défaut 25), enveloppe Laravel `data` + `meta` (`current_page`, `last_page`, `per_page`, `total`).
- **Tri** : whitelist par entité (`name`, `created_at`, `status`, `amount`, `due_at`…) via `Rule::in` — colonne inconnue → 422.
- **Filtres** : `status`, `source`, `owner_id` (id du tenant), `account_id`, `stage` — allowlistés ; valeur inconnue → 422.
- **Expansions** : `account`, `pipeline`, `owner`, `primary_contact` — allowlistées (`with` param contrôlé).
- **N+1** : eager loading systématique sur les index (comptes → contact primaire, opportunités → pipeline/owner).

### 3.3 Idempotency key & correlation ID
- **Idempotency key** : header `Idempotency-Key` (UUID) accepté sur les POST sensibles (import commit, merge, conversion) ; clé mémorisée 24 h ; rejeu → réponse mémorisée (200) sans nouvel effet (pattern `WebhookEventRegistry::begin()`).
- **Correlation ID** : header `X-Correlation-ID` (ou généré) propagé dans les logs, l'audit (`AuditLog.request_id`) et les jobs (payload).

### 3.4 Codes d'erreur
| Code | Usage | Exemple |
|---|---|---|
| 401 | `UNAUTHENTICATED` (garde sanctum / super_admin_api) | token absent/invalide |
| 403 | Policy refusée | rôle non autorisé, feature `crm` désactivée |
| 404 | introuvable OU **hors tenant** (volontairement identique) | `CRM_ACCOUNT_NOT_FOUND` |
| 409 | conflit d'état | import déjà committé, lead déjà converti |
| 422 | validation / whitelist | statut inconnu, tri inconnu |
| 429 | rate limit (`throttle:api-plan`) | dépassement quota |
| 202 | accepté (async) | commit d'import |

Erreurs localisées ×4 (fr/en/tr/ar) avec code métier (`CRM_*`) dans le champ `error`.

### 3.5 Feature flag `crm`
- Toutes les routes tenant `/api/v1/crm/*` sont derrière la feature `crm` (ADR-CRM-004) : désactivée par défaut, activation plateforme auditée (`crm.feature.*`), désactivation = 403.

---

## 4. Surface platform (CRM commercial — inchangée)

| Méthode | Route | Controller | Auth | Scope |
|---|---|---|---|---|
| GET | `/api/v1/platform/crm/pipeline` | `PlatformCrmPipelineController` | `super_admin_api` | public (leads marketing) |
| GET | `/api/v1/platform/marketing/leads` | `MarketingLeadController` | `super_admin_api` | public |
| POST | `/api/v1/marketing/leads` | `MarketingLeadController` (persist lead vitrine) | secret `MARKETING_LEAD_WEBHOOK_TOKEN` | public |

**Interdits côté tenant** : tout accès à ces routes (testé), toute importation des agrégats Platform/Marketing dans le module CRM (garde d'isolation #5584).

---

## 5. Callbacks providers

| Méthode | Route | Contrat | Idempotence | Exemple |
|---|---|---|---|---|
| POST | `/api/v1/webhooks/stripe` | signature Stripe | oui (webhook_events) | paiement |
| POST | `/api/v1/webhooks/email-bounce` | secret partagé | oui | bounce |
| POST | `/api/v1/crm/providers/whatsapp/callback` (V1, #5725) | signature HMAC + `Idempotency-Key` | **inbox persistée avant réponse 2xx** (#5741) | message entrant |
| POST | `/api/v1/crm/providers/email/callback` (V1, #5726) | secret partagé | **inbox avant réponse 2xx** | événement de délivrabilité |

Règle : un callback provider qui échoue en cours de traitement doit pouvoir être **rejoué sans doublon métier** (pattern inbox/outbox, #5741).

---

## 6. Non-régression (testée)

`api/tests/Feature/Crm/CrmPlatformIsolationTest.php` :
1. Un manager tenant authentifié (`sanctum`) appelant `GET /api/v1/platform/crm/pipeline` → **401** (garde super_admin_api) — le tenant ne peut pas consommer le CRM commercial.
2. Un super-admin (`super_admin_api`) appelant la même route → **200** (CRM commercial intact).
3. Un manager tenant appelant une route tenant inconnue → **404** (pas de fuite vers une autre surface).
