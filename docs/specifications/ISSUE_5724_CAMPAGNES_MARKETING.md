# Issue #5724 — Campagnes marketing tenant

> Spec d'implémentation — CRM client (API tenant). Statut : livrée (PR dédiée).

## Objectif

Permettre de créer, démarrer, stopper et observer des campagnes marketing
tenant-scopées (email / sms / whatsapp) avec une audience vérifiée au
consentement, sans couplage direct entre le module CRM et les canaux.

## Décisions de conception

| Sujet | Décision |
|---|---|
| Cycle de vie | `draft → scheduled | running → paused ⇄ running → finished | cancelled` — transitions invalides = 422 stable |
| Audience | Segment (`segment_id`, membres #5723) **OU** liste explicite (`audience`, max 10 000) — jamais les deux ; résolution au `start()` puis snapshot dans `crm_campaigns.audience_snapshot` |
| Consentement | Au `start()` : filtre `CampaignConsentCheckerInterface` (fail-closed — `ConsentTableCampaignConsentChecker` lit `crm_consents` #5722 ; table absente = aucun envoi, jamais d'envoi non vérifiable) |
| Envoi stoppable | `pause` / `resume` / `cancel` (cancel → envois pending/queued passés `cancelled`) ; `finish` |
| Observable | `report()` par statut (`total/pending/sent/failed/bounced/cancelled`) + `sends_count` |
| Découplage | Événements `CampaignStarted` / `CampaignFinished` (canaux #5725/#5726/#5727 à l'écoute) + `provider_message_id` sur chaque envoi — aucun import inter-modules |
| Isolation tenant | `company_id` uuid NON nullable + `BelongsToCompany` ; pas de FK vers `crm_segments`/`crm_contacts` (#5708/#5723) |
| Audit | `campaign.created/updated/started/paused/resumed/cancelled/finished` dans `audit_logs` (module crm) |
| RBAC | Lecture/report : tout manager ; écritures/actions : `principal`/`marketing` (middleware + Policy `CrmCampaignPolicy`) |

## Périmètre

- Migration tenant `2026_08_28_000007_5724_create_crm_campaign_tables.php`
  (`crm_campaigns` + `crm_campaign_sends`)
- `api/app/Modules/CRM/` : `Domain/Enums/{CampaignStatus, CampaignSendStatus}`,
  `Domain/Models/{CrmCampaign, CrmCampaignSend}`,
  `Domain/Events/{CampaignStarted, CampaignFinished}`,
  `Domain/Contracts/CampaignConsentCheckerInterface`,
  `Application/Services/CampaignService`,
  `Infrastructure/Services/ConsentTableCampaignConsentChecker`,
  `Interfaces/Api/V1/Controllers/CrmCampaignController` + `Requests/`
- `api/app/Policies/CrmCampaignPolicy.php`, routes `/api/v1/crm/campaigns*`
- Tests Feature `CrmCampaignTest` (14 scénarios)

## Hors périmètre

- Livraison effective des messages : le canal email (#5726) écoute
  `CampaignStarted` pour prendre en charge les envois `pending` ;
  WhatsApp (#5725) et SMS (#5727) suivent.
- Import CSV (#5714), automatisations (#5728), dashboard (#5721).
