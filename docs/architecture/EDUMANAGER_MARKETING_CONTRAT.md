# EduManager ↔ Marketing/CRM client — Contrat d'admissions (EDU-015)

> **Issue :** #5831 (EDU-015) — « Utiliser CRM/Marketing client pour campagnes
> d'admission, segments et relances consenties. »

## Principe

EduManager **ne duplique pas** la logique Marketing (BC-12 : segments,
campagnes, relances). Il expose une seule capacité : les **segments de
prospects CONSENTIS** pour les campagnes d'admission.

## Surface exposée

### `EduMarketingService::marketingEligible()` (direction uniquement)

Prospects éligibles = admissions avec `consent_contact = true` ET statut
ouvert (`new | document_pending | review | waitlisted`) sur une fenêtre de
dates `applied_at`. **RGPD : jamais un prospect sans consentement.**

| Champ | Rôle |
|---|---|
| `crm_contact_id` | Référence de contrat vers le contact CRM client (EDU-004, sans FK) — Marketing rattache la relance sans coupler les tables |
| `admission_number` | Référence métier EduManager |
| `applied_at` / `status` | Fenêtre temporelle + étape du pipeline |

## Ce que Marketing doit faire (consommateur)
1. Interroger `marketingEligible` (ou lire `edu_admissions` filtré
   `consent_contact = true` + statuts ouverts) pour constituer ses segments.
2. Ne relancer que via les canaux autorisés du CRM client (V1) — jamais
   hors consentement, jamais le CRM commercial plateforme.
3. Référencer `crm_contact_id` pour la traçabilité de bout en bout.

## Non-régression
- Aucun `use` croisé EduManager ↔ Marketing/CRM (garde #5584).
- Test `EduMarketingServiceTest` : exclusion des non-consentis, fenêtre de
  dates, isolation tenant.
