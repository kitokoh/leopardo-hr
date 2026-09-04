# Registre machine des PII et politiques (MAT-011, #5869)

## Objectif

Chaque champ sensible de la plateforme possède une **politique déclarée**
(catégorie, chiffrement, anonymisation, export, accès, rétention, base
légale). La source de vérité machine est `api/config/privacy.php`, lue par
`App\Core\Privacy\PiiFieldRegistry`.

## Contrat

| Politique | Signification |
|---|---|
| `category` | `identity`, `contact`, `national_id`, `banking`, `biometric`, `auth`, `misc` |
| `encrypted` | champ chiffré au repos (cast Laravel `encrypted`) — `bank_account`, `iban`, `national_id` |
| `anonymized` | champ effacé par `php artisan gdpr:anonymize-employee` (RGPD art. 17 / Loi 18-07) |
| `exported` | champ inclus dans le bundle `GET /api/v1/privacy/export` (portabilité) |
| `access` | contextes autorisés (`self`, `manager`, `rh`, `payroll`, `system`) |
| `retention` | règle de conservation (cf. `docs/security/POLITIQUE_RETENTION_DOCUMENTS.md`) |
| `legal_basis` | base légale du traitement |

## Droits RGPD couverts

1. **Portabilité / export** : `GET /api/v1/privacy/export`
   (`App\Modules\HR\Interfaces\Api\V1\Controllers\PrivacyController`) — bundle
   de l'employé authentifié, audité (`hr_data.privacy_exported`).
2. **Effacement / anonymisation** : `php artisan gdpr:anonymize-employee
   {employee_id} [--company=...] [--dry-run] [--force]` — efface les PII
   (identité, contact, identifiants, banque, biométrie, photo, 2FA) et
   **conserve l'historique de paie** (obligation légale DZ 10 ans), audité
   (`gdpr_employee_anonymized`).
3. **Rétention** : `php artisan audit:purge` (rétention configurable par
   tenant, `audit_retention_months`, défaut 36 mois).
4. **Demande de suppression** : `POST /api/v1/privacy/deletion-requests`.

## Ajouter / modifier un champ PII

1. Déclarer la politique dans `api/config/privacy.php` (entité `employee` →
   `fields`) ;
2. mettre à jour `GdprAnonymizeEmployeeCommand` si le champ doit être effacé ;
3. les tests `PiiFieldRegistryTest` (parité anonymisation, casts `encrypted`,
   existence des colonnes) valident la cohérence.

## Garde-fous

- Le registre ne contient **jamais** de valeur réelle : uniquement des noms
  de champs et des politiques (aucun secret/PII dans le code ni les logs).
- Ne pas déclarer `encrypted: true` sans cast `encrypted` réel — le test de
  parité échoue.
