# HealthManager — Modèle de données (BC-30 HEALTH)

> Issues #7786..#7791 — spec `docs/specifications/HEALTHMANAGER_SOLUTION.md` §3.
> 17 tables tenant préfixées `health_`, toutes `company_id` uuid NON nullable
> (`BelongsToCompany`), `UNIQUE(id, company_id)`, CHECK sur les statuts,
> gardes F-17 (#1593/#1613).

## Classification des données

| Donnée | Classification | Protection |
| :--- | :--- | :--- |
| `health_patients.full_name` | PII nominative | En clair (listes/recherche), RBAC strict, jamais hors tenant |
| `health_patients.mrn` | Identifiant métier | Unique par tenant, généré serveur (`PAT-YYYY-NNNN`) |
| naissance, contacts, personne à prévenir, assurance | PII sensible | **Chiffré au repos** (cast `encrypted`) |
| allergies, antécédents | **Donnée de santé** | **Chiffré au repos**, lecture praticiens + direction + réception (administratif) |
| `health_consultations.*_encrypted` (examen, diagnostic, constantes, notes) | **Donnée de santé** | **Chiffré au repos**, praticiens + direction UNIQUEMENT (réception refusée même en lecture) |
| `health_prescriptions` + items | **Donnée de santé** | Même règle que les consultations |
| `health_invoices` + paiements | Financier patient | admin + billing gèrent, réception lit |

## Cycle de vie

- Patients : `active | deceased | archived` — archivage logique, **jamais de
  suppression physique** (droit à l'effacement via le registre
  `privacy_requests` existant, pattern CRM/Edu).
- Factures émises : immuables (annulation seulement).
- Lits : `free | occupied | maintenance` — synchronisés avec les admissions
  sous transaction + `lockForUpdate`.

## Tables

Voir la spec §3 pour le détail des colonnes. Relations clés :

```
health_departments 1─n health_rooms 1─n health_beds
health_departments 1─n health_practitioners n─n health_specialties (pivot tenant-scopé)
health_patients 1─n health_appointments n─1 health_practitioners
health_consultations n─1 health_patients / health_practitioners / health_appointments?
health_prescriptions n─1 health_consultations ; 1─n health_prescription_items
health_admissions n─1 health_patients / health_beds / health_departments
health_invoices n─1 health_patients ; 1─n health_invoice_items (n─1 health_care_acts?) ; 1─n health_invoice_payments
```

## Invariants (voir spec §4)

- MRN et numéros de facture séquentiels par tenant/année, race-safe
  (verrou pessimiste + contrainte UNIQUE en garde ultime).
- Chevauchement de rendez-vous praticien → 409 `HEALTH_APPOINTMENT_CONFLICT`.
- Lit occupé → 409 `HEALTH_BED_OCCUPIED` ; cohérence lit ↔ admission transactionnelle.
- Totaux facture recalculés serveur (centimes), prix figés à la ligne,
  sur-paiement → 422 `HEALTH_OVERPAYMENT`.
