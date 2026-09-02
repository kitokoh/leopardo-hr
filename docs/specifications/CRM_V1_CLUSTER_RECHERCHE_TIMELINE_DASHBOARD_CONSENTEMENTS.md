# CRM V1 — Cluster recherche, timeline, dashboard & consentements

> Spec d'implémentation du batch agent PM — issues #5719, #5720, #5721, #5722.
> Complète les specs de référence CRM V0 (`PLAN-V0-V1-CRM-CLIENT-SQL-INTEGRATIONS.md`,
> `MODULE_CRM_INTERNE_CLIENT.md`, `ADR-CRM-DUAL-CONTEXTS.md`) une fois mergées.
> Dernière mise à jour : 2026-08-28.

## 1. Périmètre du cluster

Ce cluster couvre les quatre premières issues **CRM V1** du plan, dans le
**bounded context CRM client** (tenant) — jamais le CRM commercial Platform :

| Issue | Livrable | Dépendances V0 |
|---|---|---|
| #5719 | Recherche tenant-scoped accounts/contacts (`GET /crm/search`) | V0-08 (routes), V0-09 (PII) |
| #5720 | Timeline d'activités + tâches bornées + relances internes idempotentes | V0-06 (activities/tasks) |
| #5721 | Dashboard pipeline + qualité des données (read models agrégés) | #5719, #5720 |
| #5722 | Consentements & préférences de communication par contact | V0-09 (PII/audit) |

## 2. Principes transverses

- **Tenant-scoped strict** : toutes les tables portent `company_id` uuid NOT
  NULL, tous les modèles utilisent `App\Shared\Traits\BelongsToCompany`
  (fail-closed #3727) — un accès cross-tenant retourne 404/403 sûr.
- **RBAC** : middleware `api.manager:principal,rh,marketing` (rôles commerciaux
  tenant) + Policies dédiées. Le rôle `marketing` est autorisé côté **tenant**
  (équipe commerciale), distinct du CRM commercial Platform.
- **Entrées strictement contrôlées** : enums fermés, allowlists, pagination
  bornée (50 max), aucun tri/SQL fourni par le client.
- **N+1** : eager-load systématique (owner, account) ; read models bornés.
- **Audit** : toute mutation sensible écrit `AuditLog` (préfixe `crm.`).

## 3. Issue #5719 — Recherche

- Route `GET /api/v1/crm/search` ; filtres allowlistés
  (`q` 2..120 requis, `type` account|contact, `status` active|inactive|
  archived, `owner_id`, pagination 50 max).
- Requête : ILIKE borné par type (200 résultats/type), pertinence fixe
  (nom, puis création récente). `%`/`_` échappés.
- Note EXPLAIN/latence : les ILIKE `%term%` n'utilisent pas les index btree ;
  le bornage à 200/type garantit une latence bornée sans scan complet de la
  table. Les index (`company_id`+`status`, `company_id`+`owner_id`) servent
  aux filtres non-ILIKE.
- Policy `crm.search` (Gate) appliquée **avant** toute requête.
- Resource discriminante `type: account|contact`.

## 4. Issue #5720 — Timeline, tâches, relances

- `GET /crm/accounts/{account}/timeline` : cursor pagination sur
  `crm_activities` (append-only), filtres type/owner, eager-load owner.
- `GET|POST /crm/tasks`, `PATCH /crm/tasks/{task}`, `POST /crm/tasks/{task}/
  complete`, `DELETE /crm/tasks/{task}` : champs bornés (title ≤ 255, due_at
  timestamptz, status `todo|in_progress|done|cancelled`, priority
  `low|medium|high`, assignee du tenant), filtres allowlistés
  (`status`, `overdue`, `owner_id`, `account_id`).
- Relances : job planifié `CrmTaskOverdueRemindersJob` (toutes les 30 min) qui
  détecte les tâches en retard et émet une notification interne via événement
  `App\Shared\Events\CrmTaskOverdue` (économe en couplage : la payload est
  scalaire, le listener vit dans le module Notification). Idempotence :
  table `crm_task_reminders` avec UNIQUE (`task_id`, `remind_date`) — une
  relance par tâche et par jour.
- Timezone : `due_at` en UTC ; le calcul d'overdue utilise le fuseau du tenant
  (champ `timezone` de `companies`).

## 5. Issue #5721 — Dashboard pipeline & qualité

- `GET /crm/dashboard/pipeline` : agrégats exacts tenant-scoped — counts par
  stage (`crm_opportunities` join `crm_stages`), valeur totale, stagnation
  (opportunités sans activité depuis N jours), owners sans opportunité,
  tâches en retard.
- `GET /crm/dashboard/quality` : métriques de qualité — accounts sans contact
  primaire, contacts sans email/téléphone, doublons estimés (email normalisé).
- Read model `CrmDashboardReadModel` : requêtes SQL agrégées bornées
  (WHERE company_id + index), pas de scan non justifié ; P95 documenté dans
  le module. Permissions manager/rep respectées (Policy + RBAC).

## 6. Issue #5722 — Consentements & préférences

- Tables : `crm_contact_consents` (état courant : contact_id, channel
  `email|sms|whatsapp|phone|call`, purpose `marketing|transactional|
  newsletter`, status `granted|revoked`, UNIQUE contact/channel/purpose,
  `granted_at`/`revoked_at`) + `crm_consent_events` (historique append-only :
  action `grant|revoke`, source, actor_id).
- Routes : `GET /crm/contacts/{contact}/consents`, `PUT /crm/contacts/
  {contact}/consents` (grant/revoke), `GET /crm/consents/events` (historique
  paginé).
- Garde d'envoi : `ConsentService::canContact()` — aucun envoi si le
  consentement requis est absent ; le retrait est propagé (état + événement).
- Audit : chaque mutation écrit `AuditLog` (`crm.consent.*`) + événement
  append-only. Rétention/anonymisation documentées
  (`docs/security/CRM_CONSENT_RGPD.md`).

## 7. Contrats de schéma (coordination inter-agents)

Noms canoniques convenus avec la fondation V0 (commentaires #5708/#5709/#5710) :
`crm_accounts`, `crm_contacts`, `crm_leads`, `crm_pipelines`, `crm_pipeline_stages`,
`crm_opportunities`, `crm_activities`, `crm_tasks` (schéma tenant) ; modèles
`App\Modules\CRM\Domain\Models\{CrmAccount, CrmContact, CrmLead, CrmPipeline,
CrmStage, CrmOpportunity, CrmActivity, CrmTask}`.

## 8. Ajustements post-coordination (2026-08-28)

- Schémas réels de la fondation intégrés : stages = `crm_pipeline_stages`
  (is_won/is_lost, pas de colonne `status` sur `crm_opportunities` — statut
  dérivé du stage) ; tasks = `assigned_to`/`completed_at`/`created_by` ;
  activities = `occurred_at`/`created_by` (relation `actor`).
- #5708 a deux variantes de schéma concurrentes (PR #5754 vs #5757) : le code
  de recherche/qualité est défensif (Schema::hasColumn) en attendant la
  décision.
- #5722 : implémentation canonique = PR #5756 (swarm) ; ce cluster ne porte
  plus les consentements (ma branche yieldée).

## 9. Definition of Done (transverse)

Tests écrits avant l'implémentation (Pest/Feature), contrôles tenant,
validations d'entrée, absence de N+1 critique, OpenAPI aligné, matrice RBAC
mise à jour, CHANGELOG à jour, checks CI requis au vert.
