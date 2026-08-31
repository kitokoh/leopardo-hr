# CRM — Consentements & préférences de communication (RGPD)

> Module CRM client — Issue #5722. Politique de rétention et d'anonymisation
> des consentements, et règles de propagation du retrait.

## Base légale

- RGPD art. 6.1(a) (consentement) et art. 7 (conditions du consentement) ;
- Loi algérienne 18-07 (protection des données à caractère personnel) ;
- Un consentement CRM n'est jamais présumé : absence de ligne = refus
  (fail-closed, `CommunicationConsentService::allows()`).

## Règles de rétention

| Donnée | Durée de conservation | Justification |
|---|---|---|
| `crm_consents` (état courant) | Pendant la durée du traitement marketing + **3 ans** après le dernier contact commercial | Preuve du consentement / retrait (RGPD art. 7.1) |
| `audit_logs` (historique des mutations) | 5 ans (politique d'audit du socle, `PurgeAuditLogsCommand`) | Traçabilité des accords/refus/retraits |
| `metadata` de consentement | Alignée sur la ligne `crm_consents` correspondante | Pas de PII additionnelle stockée en clair |

## Anonymisation

- À la purge d'un contact (anonymisation RGPD), les lignes `crm_consents`
  sont **supprimées** (elles ne contiennent pas de PII en propre — le lien
  vers le contact est rompu par la suppression) ; seules les lignes
  `audit_logs` anonymisées (userId nullé, metadata purgée des références
  directes) sont conservées comme preuve.
- Le retrait (`withdrawn`) est définitif pour le canal/finalité concerné :
  aucun nouvel envoi marketing n'est possible sans un **nouvel** accord
  explicite (source tracée).

## Propagation du retrait

1. `POST /api/v1/crm/consents/{id}/revoke` (ou service `withdraw()`) ;
2. statut `withdrawn` + `revoked_at` + audit `consent.withdrawn` ;
3. événement `CrmConsentRevoked` dispatché ;
4. `PropagateConsentRevocation` → `CampaignConsentRevocationHandler` :
   envois de campagne `pending`/`queued` du contact passés `cancelled`
   (marketing uniquement, tenant-scopé) ;
5. les fournisseurs de canal (#5726 email, #5725 WhatsApp, #5727 SMS)
   consultent `allows()` avant chaque envoi — la garde est toujours appliquée
   même si l'événement n'a pas encore été traité.

## RBAC

- Lecture des consentements : tout manager du tenant (`api.manager`) ;
- Accord / refus / retrait : `principal` / `marketing` (Policy
  `CrmConsentPolicy` — aucune garde inline).
