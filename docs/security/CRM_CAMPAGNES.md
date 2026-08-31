# CRM — Campagnes marketing : consentement, observabilité, rétention

> Module CRM client — Issue #5724.

## Consentement

- **Aucun envoi sans consentement requis** : le `start()` filtre l'audience
  via `CampaignConsentCheckerInterface` (implémentation par défaut :
  `crm_consents` #5722, statut `granted`, finalité `marketing`, canal
  correspondant). Fail-closed : si la table de consentement est absente,
  aucun contact n'est autorisé — jamais d'envoi non vérifiable.
- Le retrait d'un consentement pendant l'exécution annule les envois
  `pending`/`queued` du contact (`PropagateConsentRevocation` → handler
  #5722, table `crm_campaign_sends`).

## Observabilité

- Chaque envoi porte un statut (`pending|queued|sent|failed|bounced|
  cancelled|suppressed`) et un `provider_message_id` (lien vers le fournisseur
  de canal).
- `GET /api/v1/crm/campaigns/{id}/report` : décompte par statut.
- Les événements `CampaignStarted` / `CampaignFinished` alimentent les canaux
  (#5726 email, #5725 WhatsApp, #5727 SMS) sans couplage inter-modules.

## Rétention

| Donnée | Rétention |
|---|---|
| `crm_campaigns` (définitions + snapshots d'audience) | Tant que le tenant est actif ; suppression explicite (détruit les envois) |
| `crm_campaign_sends` | 3 ans après la fin de la campagne (observabilité + preuve d'envoi RGPD), purge par job de rétention du socle |
| `audit_logs` (mutations) | 5 ans (politique d'audit) |

## Anonymisation

- À l'anonymisation d'un contact, ses `crm_campaign_sends` sont supprimées ;
  les snapshots d'audience (listes de contact_id) sont purgés des références
  au contact dans la même opération — jamais d'export de PII depuis les
  campagnes hors périmètre habilité (`principal`/`marketing`, audit).
