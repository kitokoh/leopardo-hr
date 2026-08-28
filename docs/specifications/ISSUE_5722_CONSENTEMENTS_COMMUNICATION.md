# Issue #5722 — Consentements et préférences de communication CRM

> Spec d'implémentation — CRM client (API tenant). Statut : livrée (PR dédiée).

## Objectif

Gérer les consentements CRM (marketing / transactionnel) par contact, canal
et finalité : accord, refus, retrait — tracés, audités, et propagés aux
canaux d'envoi (campagnes #5724, email #5726) pour garantir **aucun envoi
sans consentement requis**.

## Décisions de conception

| Sujet | Décision |
|---|---|
| État courant vs historique | Table `crm_consents` = état courant (1 ligne par tenant/contact/canal/finalité) ; **historique immuable dans `audit_logs`** (actions `consent.granted` / `consent.denied` / `consent.withdrawn`, RGPD art. 7) |
| Isolation tenant | `company_id` uuid NON nullable + trait `BelongsToCompany` (scope global + fail-closed #3727) ; pas de FK vers `crm_contacts` (livrée par #5708) — l'isolation ne repose jamais sur une FK |
| Garde d'envoi | `CommunicationConsentService::allows(contact, canal, finalité)` — fail-closed : absence de ligne = refus. Consommée par les canaux (#5724/#5726) |
| Retrait | `withdraw()` → statut `withdrawn` + `revoked_at` + audit + événement `CrmConsentRevoked` ; listener `PropagateConsentRevocation` → `CampaignConsentRevocationHandler` annule les envois `pending`/`queued` (no-op documenté tant que `crm_campaign_sends` n'existe pas, #5724) |
| RBAC | Lecture : tout manager du tenant ; écritures : `principal` / `marketing` (middleware `api.manager` + Policy `CrmConsentPolicy`, jamais de garde inline) |
| Validation stricte | `GrantConsentRequest` : allowlist (canal/finalité/source = enums) + champs `id`/`company_id`/`status` interdits (`prohibited`) |
| Rétention / anonymisation | Voir `docs/security/CRM_CONSENTEMENTS.md` |

## Périmètre

- Migration tenant `2026_08_28_000005_5722_create_crm_consents_table.php`
- `api/app/Modules/CRM/` : `Domain/Enums`, `Domain/Models/CrmConsent`,
  `Domain/Events/CrmConsentRevoked`, `Application/Services/CommunicationConsentService`,
  `Application/Listeners/PropagateConsentRevocation`,
  `Infrastructure/Services/CampaignConsentRevocationHandler`,
  `Interfaces/Api/V1/Controllers/CrmConsentController` + `Requests/`
- `api/app/Policies/CrmConsentPolicy.php`, `api/routes/modules/crm.php`,
  `api/bootstrap/providers.php` (CrmServiceProvider), `api/routes/api.php`
- Tests `api/tests/Feature/CRM/CrmConsentTest.php` (13 scénarios)

## Hors périmètre

- Tables CRM contacts/accounts/leads (#5708/#5709) — référencées par id seul.
- Envoi réel des campagnes (#5724) et fournisseur email (#5726) — le handler
  de propagation est câblé dès maintenant et devient effectif à leur merge.
