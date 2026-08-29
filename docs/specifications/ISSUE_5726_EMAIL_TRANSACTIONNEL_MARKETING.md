# Issue #5726 — Email transactionnel et marketing contrôlé

> Spec d'implémentation — CRM client (API tenant). Statut : livrée (PR dédiée).

## Objectif

Fournir un canal email au CRM client : envoi transactionnel et marketing
**contrôlé** — fournisseur interchangeable, consentement requis pour le
marketing, suppression (bounce/complaint/désabonnement) respectée, quotas
par tenant, audit complet.

## Décisions de conception

| Sujet | Décision |
|---|---|
| Fournisseur | Contrat `EmailProviderInterface` ; implémentations `LogEmailProvider` (défaut — aucun envoi réel, message_id synthétique, idéal CI/tests) et `MailEmailProvider` (Laravel Mail, `MAIL_MAILER`) ; sélection par `CRM_EMAIL_PROVIDER` |
| Consentement | `sendMarketing()` vérifie `CampaignConsentCheckerInterface` (fail-closed #5724 — jamais d'envoi marketing sans consentement `granted`) ; le transactionnel ne requiert pas de consentement mais respecte les suppressions |
| Suppression | Table `crm_email_suppressions` (hash SHA-256 de l'adresse — **aucune PII au repos**), motifs bounce/complaint/unsubscribe/manual ; check avant chaque envoi |
| Bounce/complaint | Webhook `POST /api/v1/crm/email/webhook` (secret partagé `CRM_EMAIL_WEBHOOK_SECRET`, header `X-Leopardo-Webhook-Secret`) : événements journalisés dans `crm_email_events`, bounce/complaint/unsubscribe → suppression + propagation aux envois de campagne (`crm_campaign_sends`) |
| Désabonnement | Lien signé HMAC (`UnsubscribeTokenService`, clé APP_KEY) → `POST /api/v1/crm/email/unsubscribe` (public) : suppression + retrait du consentement marketing email (si `crm_consents` présent, #5722) |
| Quotas | `EmailRateLimiter` (Cache, par tenant/heure) — marketing et transactionnel distincts (`CRM_EMAIL_RATE_LIMIT_PER_HOUR` / `CRM_EMAIL_TRANSACTIONAL_RATE_LIMIT_PER_HOUR`) → 429 `EMAIL_RATE_LIMITED` |
| Audit | `email.sent` / `email.failed` / `email.suppressed` / `email.suppression_added` / `email.unsubscribed` dans `audit_logs` (module crm-email) + journal `crm_email_events` |
| Campagne (#5724) | `sendCampaignSend()` prend en charge un envoi pending (résolution de l'adresse via `crm_contacts` #5708, garde schemaTableExists) ; statut du send mis à jour (`sent` + `provider_message_id`, `suppressed`, `failed`) |
| RBAC | Envois = `principal` / `marketing` (middleware + Policy `CrmEmailPolicy` liée au modèle marqueur `CrmEmailSuppression`) ; webhook/désabonnement publics par design (secret/jeton) |

## Périmètre

- Migration tenant `2026_08_28_000503_5726_create_crm_email_tables.php`
  (`crm_email_suppressions` + `crm_email_events`)
- `api/config/crm.php` + clés `.env.example` (`CRM_EMAIL_*`, garde #1487)
- `api/app/Modules/CRM/` : `Domain/DTOs/{EmailMessage, EmailDeliveryResult}`,
  `Domain/Contracts/EmailProviderInterface`, `Domain/Exceptions/*`,
  `Domain/Models/CrmEmailSuppression`,
  `Application/Services/CrmEmailService`,
  `Infrastructure/Services/{LogEmailProvider, MailEmailProvider,
  EmailRateLimiter, UnsubscribeTokenService}`,
  `Interfaces/Api/V1/Controllers/{CrmEmailController,
  CrmEmailWebhookController}` + `Requests/`
- `api/app/Policies/CrmEmailPolicy.php`, routes `/api/v1/crm/email*`
- Tests Feature `CrmEmailTest` (15 scénarios)

## Hors périmètre

- Templates HTML/éditeur (#5724 UI), campagnes multi-canaux (#5725/#5727),
  import CSV (#5714), OpenAPI des routes email (#5712).
