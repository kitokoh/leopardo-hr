# 🔐 Données au repos — inventaire & chiffrement (F-17)

> Programme FOCUS — état des lieux du chiffrement au repos et plan d'extension.
> Mis à jour le 2026-08-09 (session main vert, PR #1611 + migration backfill F-17).

## État actuel (2026-08-09)

- **SensitiveDataEncryptor** (`api/app/Core/Auth/Infrastructure/Services/SensitiveDataEncryptor.php`) :
  chiffrement AES-256 (clé APP_KEY) des identifiants nationaux et IBAN/banques
  des employés.
- **Metadata des documents de paie chiffré** (F-17, #1595) :
  `payment_documents.metadata` (références de paiement, montants, périodes)
  passe du cast `array` au cast **`encrypted:array`** — migration de backfill
  idempotente `2026_08_09_000003_encrypt_payment_documents_metadata.php`
  (tenant, résolution de schéma par `current_schemas(false)`, convention #1613).
- Chiffrement en transit : TLS ; base : PostgreSQL (chiffrement disque côté
  hébergeur).

## Inventaire des données sensibles paie

| Donnée | Colonne/modèle | Statut (2026-08-09) |
|---|---|---|
| Metadata documents de paiement | `payment_documents.metadata` | ✅ **Chiffré** (`encrypted:array`, backfill idempotent) |
| RIB / IBAN employé | `employees.iban` / `bank_account` | ✅ SensitiveDataEncryptor (AES-256) |
| Identifiants nationaux | `employees.*` | ✅ SensitiveDataEncryptor (AES-256) |
| Salaire de base | `salary_structures.base_salary` | ⚠️ **Exception documentée** : reste en clair — requis pour agrégations/recherches (benchmark F-12) ; chiffrement applicatif avec index dédiés = coût/retard, à re-évaluer avec le chiffrement par tenant |
| Net / brut bulletins | `pay_slips.*` | ⚠️ Exception documentée : requis pour exports/recalculs ; accès restreint RBAC + audit |
| Historique de paie | `payroll_runs.*` | ⚠️ Totaux agrégés, pas de détail individuel sensible |
| Biométrie (templates) | kiosk/mobile | 🔴 Politique de rétention : voir `POLITIQUE_RETENTION_DOCUMENTS.md` + registre RGPD (#1548) |

## Décisions (2026-08-09)

1. **Chiffrer le metadata des documents de paie** : ✅ fait (F-17, PR #1611).
2. **Colonnes de salaire** : exception documentée — le chiffrement applicatif
   casserait les agrégats (benchmark 10 k employés, F-12) et compliquerait le
   moteur de calcul ; le risque résiduel est couvert par RBAC
   (principal/comptable), `throttle:payroll-sensitive` et l'audit trail F-11.
   À re-évaluer quand le chiffrement par tenant (clé par company) sera requis
   par un client.
3. **Ne JAMAIS loguer de données sensibles** : scan CI (TruffleHog, Semgrep)
   + convention anti-secret (#1614).
4. **Rétention biométrie** : durée + purge automatique — suivi #1548/#1474.

## Tests

- Cast `encrypted:array` : round-trip + vérification que la valeur en base
  n'est pas en clair + backfill idempotent (PR #1611).
- `EmployeeEncryptionTest` (tests/Feature/Security) : chiffrement/déchiffrement
  des champs employé.
