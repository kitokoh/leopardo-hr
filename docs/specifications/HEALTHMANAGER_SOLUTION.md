# Spécification — Solution verticale HealthManager (BC-31 HEALTH)

> Issues : #7785 (fondations), #7786 (référentiel), #7787 (patients),
> #7788 (rendez-vous), #7789 (consultations & prescriptions),
> #7790 (hospitalisations), #7791 (facturation), #7792 (web).
> Maturité : **pilot**. Flag : `healthmanager` (fail-closed, défaut `false`).
> Référence de pattern : EduManager (BC-16), `PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md`.

## 1. Vision

Un fondateur/directeur d'hôpital ou de clinique privée gère son établissement
de A à Z : structure (services médicaux, salles, lits), équipe médicale
(praticiens, spécialités, rôles réception/facturation), patients,
rendez-vous, consultations et prescriptions, hospitalisations et facturation
des soins — sur la fondation partagée Leopardo (RH, Documents,
Notifications ; Accounting/Payroll/CRM en option).

## 2. RBAC (deny-by-default)

| Rôle | Détermination | Périmètre |
| :--- | :--- | :--- |
| `health.admin` | manager `principal`/`rh`/sans sous-rôle | Tout |
| `health.practitioner` | fiche `health_practitioners` active liée à l'employé | Agenda, SES consultations/prescriptions, patients (lecture) |
| `health.reception` | rôle `reception` dans `health_staff_roles` | Patients (administratif), rendez-vous, admissions — jamais le contenu médical |
| `health.billing` | rôle `billing` dans `health_staff_roles` ou manager `comptable` | Actes, factures, encaissements |

Employé lambda → 403 partout. Cross-tenant → 404 (fail-closed).
Contenu médical (consultations, prescriptions) : praticiens + direction uniquement.

## 3. Modèle de données (tables tenant, préfixe `health_`)

Toutes les tables : `company_id` uuid NON nullable (BelongsToCompany),
`UNIQUE(id, company_id)` pour FK composites, index `(company_id, …)`,
gardes F-17 (`schemaTableExists()` + noms qualifiés), CHECK sur les statuts.

| Table | Colonnes clés |
| :--- | :--- |
| `health_departments` | name, code (unique/tenant), description, status `active\|inactive` |
| `health_rooms` | department_id FK, name, code (unique/tenant), type `consultation\|hospitalization\|operating\|emergency\|other`, status |
| `health_beds` | room_id FK, code (unique/tenant), status `free\|occupied\|maintenance` |
| `health_specialties` | name, code (unique/tenant) — seed standard à l'activation |
| `health_practitioners` | employee_id FK, department_id FK nullable, title `dr\|pr\|midwife\|nurse\|other`, license_number, status `active\|inactive` ; n-n spécialités via `health_practitioner_specialties` |
| `health_staff_roles` | employee_id FK, role `reception\|billing` (unique employee+role/tenant) |
| `health_patients` | mrn `PAT-YYYY-NNNN` (unique/tenant, généré serveur), full_name, birth_date_encrypted, sex `male\|female\|other`, blood_group nullable, phone/email/address (chiffrés), emergency_contact_* (chiffrés), insurance_* (chiffrés), allergies_encrypted, medical_history_encrypted, status `active\|deceased\|archived` |
| `health_appointments` | patient_id, practitioner_id, department_id nullable, starts_at, ends_at, reason, status `scheduled\|confirmed\|checked_in\|completed\|cancelled\|no_show`, notes |
| `health_consultations` | patient_id, practitioner_id, appointment_id nullable, consulted_at, reason, clinical_exam_encrypted, diagnosis_encrypted, vitals (jsonb chiffré : weight_kg, height_cm, blood_pressure, temperature_c, pulse_bpm), notes_encrypted |
| `health_prescriptions` | consultation_id, patient_id, practitioner_id, prescribed_at, notes_encrypted |
| `health_prescription_items` | prescription_id, medication, dosage, frequency, duration, instructions |
| `health_admissions` | patient_id, practitioner_id (référent), department_id, bed_id, reason, admitted_at, expected_discharge_at nullable, discharged_at nullable, status `admitted\|transferred\|discharged`, discharge_notes |
| `health_care_acts` | code (unique/tenant), label, category `consultation\|exam\|surgery\|hospitalization\|other`, price numeric(12,2), currency (3), active bool |
| `health_invoices` | number `HINV-YYYY-NNNN` (unique/tenant, serveur), patient_id, status `draft\|issued\|paid\|partially_paid\|cancelled`, currency, subtotal, discount, total, amount_paid, issued_at nullable |
| `health_invoice_items` | invoice_id, care_act_id nullable, label, unit_price (figé), quantity, line_total |
| `health_invoice_payments` | invoice_id, amount, method `cash\|card\|transfer\|mobile\|insurance\|other`, paid_at, reference |

Chiffrement au repos (casts `encrypted` / `encrypted:array`, pattern
`AccountingContact`/`EduStudent`) pour toute donnée médicale ou PII sensible.
Suppression : patients/consultations/factures émises ne sont JAMAIS
supprimés physiquement (archivage/annulation).

## 4. Invariants métier

- **Rendez-vous** : chevauchement praticien interdit (409 `HEALTH_APPOINTMENT_CONFLICT`) ;
  transitions valides : scheduled→confirmed|cancelled ; confirmed→checked_in|cancelled|no_show ;
  checked_in→completed ; terminaux : completed, cancelled, no_show (sinon 422).
- **Admissions** : lit `free` requis ; admission → lit `occupied` (transaction + verrou) ;
  double admission sur lit occupé → 409 `HEALTH_BED_OCCUPIED` ; transfert = ancien lit
  libéré + nouveau occupé (transaction) ; sortie = statut `discharged` + lit libéré.
- **Factures** : total recalculé serveur (Σ line_total − discount ≥ 0) ; prix figés à
  la ligne ; brouillon modifiable, émise non modifiable (annulation seulement) ;
  paiement : cumul ≤ total (sur-paiement 422), cumul = total → `paid`, sinon
  `partially_paid` ; numérotation séquentielle par tenant et par année.
- **MRN patients** : `PAT-YYYY-NNNN` séquentiel par tenant/année, généré serveur.

## 5. API

Préfixe `/api/v1/health-manager/*`, stack `throttle:api, auth:sanctum,
token.refresh, tenant, throttle:api-plan`. Surface complète :
`api/routes/modules/health_manager.php` (contrat). Matrice de tests par
endpoint : 401 non authentifié / 403 solution inactive / 403 employé lambda /
happy path / 404 cross-tenant.

## 6. Web (front/web)

Entrée `healthmanager` dans `client-features.ts` (scope `business`,
vertical `health`, non self-activatable) ; pages
`(dashboard)/health/{page,patients,appointments,admissions,billing,referential}` ;
i18n fr/en/ar/tr via `shared/i18n/locales` ; design tokens P05 uniquement.

## 7. Hors périmètre V0 (pistes V1)

Laboratoire/imagerie, pharmacie & stock, DMP/interop (HL7/FHIR), portail
patient public, télémédecine, intégration Accounting (outbox), rapports
réglementaires pays.
