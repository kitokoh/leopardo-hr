# Classification PII & cycle de vie — MAT-011 (#5869)

> Programme de maturité BC-01 PLATFORM — issue [MAT-011 #5869](https://github.com/kitokoh/leopardo-hr/issues/5869).
> Source de vérité machine : `api/config/pii.php` (catalogue versionné).

## Objectif

Cataloguer les données personnelles (PII), leur rétention, anonymisation,
export, suppression, chiffrement et accès par contexte — et tester/auditer les
droits RGPD / Loi 18-07. **Chaque champ sensible possède une politique.**

## Le catalogue (`config/pii.php`)

Chaque entrée déclare :

| Champ | Signification |
|---|---|
| `context` | Module / BC propriétaire (hr, auth, payroll, attendance, leave, expense) |
| `sensitivity` | `low` / `medium` / `high` (`PiiSensitivity`) |
| `encrypted` | Chiffrement attendu au repos (DATA_AT_REST.md) |
| `retention_months` | Rétention légale (mois) — `null` = vie du compte |
| `exportable` | Droit de portabilité (inclus dans `/privacy/export`) |
| `anonymizable` | Droit à l'effacement (remplacement par valeurs neutres) |
| `deletable` | Suppression physique autorisée |
| `default_policy` | Politique appliquée aux entrées incomplètes (défensif) |

Catégories : `identity` (identité), `contact` (coordonnées), `financial`
(bancaire/paie), `biometric` (biométrie/consentement), `workforce`
(vie professionnelle : pointages, absences, bulletins, notes de frais).

## Cycle de vie (`PiiLifecycleService`)

| Droit | Implémentation | Preuve |
|---|---|---|
| Classification | `PiiRegistry::policy()/sensitivity()/validateCatalog()` | `PiiLifecycleTest::test_pii_catalog_is_complete_and_valid` |
| Export / portabilité | `PiiLifecycleService::exportBundle()` (bundle `GET /api/v1/privacy/export`) | `test_export_bundle_contains_employee_and_activity_summary` |
| Anonymisation (effacement) | `PiiLifecycleService::anonymize()` — conserve l'historique de paie (DZ 10 ans), purge biométrie + photo, audite `gdpr_employee_anonymized` | `test_anonymize_removes_pii_keeps_payroll_and_audits` |
| Suppression | `PiiLifecycleService::requestDeletion()` (demande tracée, non destructive) | `test_deletion_request_is_recorded` |
| Rétention | `PiiLifecycleService::retentionSchedule()` + commandes de purge (audit 36 m, biométrie 24 m, comptable 120 m) | `test_retention_schedule_is_derived_from_catalog` |
| Chiffrement | Champs `encrypted`/`encrypted:array` (iban, national_id, références biométriques…) + `DATA_AT_REST.md` | docs/security/DATA_AT_REST.md |
| Accès par contexte | Isolation tenant + RBAC + `DataAccessAuditLogger` (exports sensibles tracés) | `PrivacyControllerTest`, `AuditLogGlobalTest` |

## Fiabilité

- **Idempotence** : un employé déjà anonymisé n'est jamais re-traité (test dédié).
- **Dry-run** : `gdpr:anonymize-employee --dry-run` n'écrit rien (test dédié).
- **Garde tenant** : la commande refuse un `--company` ne correspondant pas à l'employé.
- **Confirmation** : la commande demande confirmation AVANT toute écriture.

## Dépendances documentaires

- `docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md` (traitements)
- `docs/security/POLITIQUE_RETENTION_DOCUMENTS.md` (rétention documents)
- `docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md` (conformité)
- `docs/security/DATA_AT_REST.md` (chiffrement)
- `docs/security/ENDPOINTS_SENSIBLES.md` (surface d'accès)
