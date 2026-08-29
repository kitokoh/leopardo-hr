# CRM — Canal email : consentement, suppression, rétention

> Module CRM client — Issue #5726.

## Consentement

- **Aucun message marketing sans consentement requis** : `sendMarketing()`
  vérifie `CampaignConsentCheckerInterface` (table `crm_consents` #5722,
  statut `granted`, finalité marketing, canal email) — fail-closed.
- Le transactionnel (factures, relances, notifications de service) ne
  requiert pas de consentement marketing mais respecte **toujours** les
  suppressions (bounce/complaint/désabonnement) : une adresse supprimée ne
  reçoit plus aucun email du tenant.

## Suppression (bounce / complaint / désabonnement)

| Source | Mécanisme |
|---|---|
| Bounce / complaint | Webhook provider (`POST /api/v1/crm/email/webhook`, secret partagé) — événement journalisé (`crm_email_events`), suppression ajoutée, envoi de campagne marqué `bounced`/`failed` |
| Désabonnement | Lien signé (jeton HMAC) → `POST /api/v1/crm/email/unsubscribe` — suppression `unsubscribe` + retrait du consentement marketing email |
| Manuel | `CrmEmailService::suppress(company, email, reason, source)` (API interne) |

L'adresse n'est **jamais** stockée en clair : hash SHA-256 uniquement
(`crm_email_suppressions.email_hash`, recherche exacte par hash).

## Quotas

- Marketing : `CRM_EMAIL_RATE_LIMIT_PER_HOUR` (défaut 500/tenant/heure).
- Transactionnel : `CRM_EMAIL_TRANSACTIONAL_RATE_LIMIT_PER_HOUR` (défaut 2000).
- Dépassement → HTTP 429 `EMAIL_RATE_LIMITED` (aucun envoi).

## Rétention

| Donnée | Rétention |
|---|---|
| `crm_email_suppressions` | 3 ans après la dernière interaction ; purge par job de rétention du socle (anonymisable : le hash n'est pas réversible) |
| `crm_email_events` | 3 ans (preuve d'envoi RGPD, observabilité) |
| `audit_logs` (module crm-email) | 5 ans (politique d'audit) |

## Anonymisation

- Les suppressions et événements ne contiennent pas d'adresse en clair : le
  hash SHA-256 reste (non réversible) et n'a pas à être purgé pour
  l'anonymisation ; les `contact_id` référencés sont nullés à la purge du
  contact.
