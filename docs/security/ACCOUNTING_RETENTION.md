# Politique de rétention des documents comptables (#5273)

## Délais légaux

| Document | Délai | Référence |
|---|---|---|
| Factures / avoirs / reçus (comptabilité) | **10 ans** | Code de commerce FR art. L123-22 ; Algérie : code de commerce (art. 12), loi 18-07 (protection des données) |
| Livres et registres comptables | 10 ans | idem |
| Audit logs (traçabilité) | 24 mois par défaut | `docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md` — commande `audit:purge --older-than=24` |

## Mise en œuvre (module Comptabilité)

- **Rétention** : `config('accounting.retention_months', 120)` (surchargeable par env `ACCOUNTING_RETENTION_MONTHS`).
- **Purge** : `php artisan accounting:purge-expired [--older-than=120] [--dry-run]`.
  - Seuls les documents **finalisés** (`paid`, `cancelled`, `overdue`) sont éligibles ; les brouillons/envoyés sont conservés (encore en évolution).
  - Le cutoff court depuis `issue_date` (émission du document), pas la création.
  - Les lignes et paiements suivent le document (FK cascade) ; le PDF archivé est supprimé du storage.
  - `--dry-run` : rapport sans suppression (recommandé avant toute exécution).
- **Audit** : chaque action comptable (création, envoi, paiement, annulation, avoir) écrit une ligne `audit_logs` (qui, quoi, quand — cible morph, IP, user-agent). Consultation : `GET /api/v1/accounting/audit-logs` (principal/comptable).
- **Chiffrement au repos** : `accounting_contacts.tax_id`, `accounting_payments.reference`, `metadata` (documents/paiements/contacts) sont chiffrés (casts `encrypted`/`encrypted:array`) — vérifié par tests.

## Garde-fous

- La purge est **irréversible** : exécuter en `--dry-run` d'abord, sauvegarder avant (backup quotidien existant).
- Toute modification de délai = mise à jour simultanée de cette politique + `config/accounting.php` + tests.
