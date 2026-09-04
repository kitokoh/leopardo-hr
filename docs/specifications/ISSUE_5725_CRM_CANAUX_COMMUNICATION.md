# Spécification — Canaux de communication CRM (issues #5725, #5727)

- **Statut :** actif — livré (PR fix/5725 + fix/5727 : adaptateur SMS audit-only, registre, observabilité)
- **Date :** 2026-08-28
- **Plan :** `docs/specifications/PLAN-V0-V1-CRM-CLIENT-SQL-INTEGRATIONS.md`
- **Module :** `api/app/Modules/CRM/` (Infrastructure/Integrations, Infrastructure/Services)

---

## 1. Positionnement

Le CRM client tenant doit communiquer avec ses contacts (prospects/clients)
sans être couplé à un fournisseur unique. Ce lot pose :

1. **Un contrat canal commun** (`ChannelAdapterContract`) : `send` / `verify`
   / `normalize` / `revoke` — chaque provider (WhatsApp Cloud API, SMS, email)
   implémente l'adaptateur (issue #5727).
2. **L'intégration WhatsApp Business officielle** (Cloud API Meta, Graph API)
   via adaptateur — jamais de WhatsApp Web automatisé (issue #5725).
3. **Un socle de sécurité** : consentement (fail-closed), quotas par tenant,
   webhook signé + anti-rejeu, dead-letter des erreurs fournisseur.

## 2. Architecture

```
Interfaces/Api/V1/Controllers/CrmChannelController     — configuration + envoi + consultation
Interfaces/Api/V1/Controllers/CrmWhatsAppWebhookController — webhook public signé
        │
        ▼
Infrastructure/Services/CrmChannelService              — orchestration (consent → quota → provider → audit)
        │                                    │
        ▼                                    ▼
Infrastructure/Services/CrmConsentGuard    Infrastructure/Services/CrmQuotaService
Infrastructure/Services/CrmPhoneNormalizer Infrastructure/Services/CrmWebhookLookupService
        │
        ▼
Infrastructure/Integrations/WhatsApp/WhatsAppAdapter (ChannelAdapterContract)
Infrastructure/Integrations/WhatsApp/WhatsAppCloudApiClient (Graph API)
        │
        ▼
Domain/Models: CrmChannel, CrmChannelConversation, CrmChannelMessage (+ lookup public)
Domain/Contracts/CrmChannelMessageRepositoryInterface → Infrastructure/Repositories/CrmChannelMessageRepository
```

### Tables (tenant)

| Table | Rôle |
|---|---|
| `crm_channels` | Registre des canaux configurés (type, provider, statut, quota mensuel, usage). Les secrets n'y vivent jamais. |
| `crm_channel_conversations` | Inbox unique par correspondant (`provider_conversation_id` = hash déterministe, jamais le numéro en clair). |
| `crm_channel_messages` | Messages outbound/inbound + statut de livraison + erreurs + coût. PII (adresses, corps) chiffrée au repos (casts `encrypted`). |
| `public.crm_webhook_channel_lookup` | Mappe `provider_key` (phone_number_id) → (company_id, channel_id) pour résoudre le tenant d'un webhook public. |

### Envoi (outbound)

1. Normalisation de l'adresse (`CrmPhoneNormalizer` : E.164, email lowercase).
2. **Consentement** (`CrmConsentGuard`) : si `crm_contact_consents` existe
   (#5722), opt-in explicite (contact, canal, finalité) exigé ; sinon fallback
   `CRM_CHANNELS_CONSENT_FALLBACK` (défaut `deny` = fail-closed).
3. **Quota** (`CrmQuotaService`) : `monthly_quota` par canal, période glissante
   mensuelle, usage comptabilisé à l'envoi.
4. **Provider** (adaptateur) : 429/5xx/timeout → `CrmProviderException`
   retryable ; 4xx métier → non retryable.
5. **Persistance + dead-letter** : tentative comptée ; si échec retryable et
   `attempts < max_attempts` → statut `failed` + `RetryCrmMessageJob` (backoff
   borné) ; sinon → `dead_lettered` (terminal, jamais de retry infini).
6. **Audit + observabilité** : log structuré (sans PII), message persiste
   (statut, coût, erreur), canal passé en `error` avec `last_error_*`.

### Webhook entrant (inbound)

- GET `hub.challenge` : vérification d'abonnement Meta (fail-closed).
- POST : signature `X-Hub-Signature-256` = `sha256=HMAC-SHA256(corps brut,
  secret partagé)` — fail-closed (401 si invalide, 503 si secret absent).
- Tenant résolu via `crm_webhook_channel_lookup` (phone_number_id), puis
  traitement sous `TenantManager::withinTenant()`.
- **Anti-rejeu** : unicité `(company_id, provider_message_id)` — un rejeu est
  absorbé (acquittement 200 sans duplication).
- Statuts de livraison (sent/delivered/read/failed) appliqués idempotemment.
- Erreurs de traitement journalisées ; acquittement 200 pour éviter les rejeux
  fournisseur inutiles (sauf signature invalide → 401).

## 3. Contrat d'interopérabilité avec #5722 (consentements)

`CrmConsentGuard` lit la table tenant `crm_contact_consents` si elle existe,
avec le contrat suivant (à figer dans la PR #5722) :

```sql
crm_contact_consents(
  id uuid pk, company_id uuid not null,
  contact_id uuid not null,
  channel varchar(20) not null,   -- whatsapp|sms|email
  purpose varchar(40) not null,   -- transactional|marketing|service
  opted_in boolean not null,
  updated_at timestamptz
)
unique (company_id, contact_id, channel, purpose)
```

Tant que la table n'est pas mergée, `CRM_CHANNELS_CONSENT_FALLBACK=deny`
bloque tout envoi vers un contact identifié (fail-closed). Les envois sans
`contact_id` (ex. transactionnels ad hoc) ne sont pas soumis au consentement
de contact.

## 4. Secrets

| Secret | Où | Règle |
|---|---|---|
| `WHATSAPP_CLOUD_API_TOKEN` | env / secret manager | jamais frontend, DB, logs |
| `CRM_WEBHOOKS_SHARED_SECRET` | env | signature HMAC webhooks, fail-closed |
| `CRM_WHATSAPP_WEBHOOK_VERIFY_TOKEN` | env | vérification d'abonnement |
| tokens fournisseur (payload API) | — | **refusés** par `ConfigureChannelRequest` (`prohibited`) |

## 5. Tests

- `CrmChannelCrudTest` : RBAC (employee 403), CRUD, refus des secrets,
  isolation cross-tenant (404), PII masquée dans les Resources.
- `CrmChannelSendTest` : consentement fail-closed, envoi (Http fake),
  template, quota 429, 500 → dead-letter, 429 → retry borné puis dead-letter,
  adresse invalide sans appel provider.
- `CrmWhatsAppWebhookTest` : verify token, fail-closed sans secret, signature
  invalide 401, persistance inbound tenant-scoped, rejeu idempotent, statuts
  de livraison, phone_number_id inconnu ignoré.

## 6bis. Extension #5727 (SMS / adaptateurs)

- `CrmChannelRegistry` : types disponibles (whatsapp, sms), résolution par type.
- `SmsAdapter` : provider **audit-only** (AGENTS.md v4.16.122) — aucun fournisseur
  production tant que signatures webhook + quotas par plan ne sont pas activés.
  Le flux complet (consentement, quota, persistance, dead-letter) reste exercé
  avec un `provider_message_id` déterministe.
- Webhook idempotent : le pattern générique (lookup public + unicité
  `(company_id, provider_message_id)`) s'applique tel quel au futur fournisseur SMS.
- Observabilité : `GET /crm/channels/{channel}/observability` (totaux, échecs,
  dead-letter, tentatives, coût cumulé — aucune PII).
- Tests : `CrmChannelAdapterTest` (6).

## 6. Definition of Done

- [x] Token en secret manager (env), jamais frontend/DB/logs
- [x] Opt-in (consentement), templates, fenêtre service (docs), rate limits
      (429 retryable borné)
- [x] Webhook signé (HMAC), anti-rejeu (unicité), inbox unique
- [x] Conversation/message/delivery tenant-scoped
- [x] Provider 429/5xx + dead letter testés
- [x] OpenAPI à jour, CHANGELOG, RBAC_ROUTE_MATRIX, docs parity, gardes CI
