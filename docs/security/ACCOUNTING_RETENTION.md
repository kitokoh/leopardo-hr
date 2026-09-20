# Politique de rétention des documents comptables (#5273, #7929)

## Délais légaux par juridiction (issue #7929 — vérifiés le 2026-09-20)

| Juridiction | Délai | Référence légale |
|---|---|---|
| Zone OHADA (SN CI ML BF BJ TG NE CM GA CG TD CF GQ) | **10 ans (120 mois)** | AUDCIF (acte uniforme relatif au droit comptable et à l'information financière, révisé 2017), **art. 24** : livres et pièces justificatives conservés dix ans |
| France | **10 ans (120 mois)** | Code de commerce, **art. L123-22** (documents comptables et pièces justificatives) |
| Turquie | **10 ans (120 mois)** | **TTK n° 6102, m. 82** (defter ve belgeler 10 yıl) — le VUK m. 253 prévoit 5 ans ; la durée la plus longue est retenue (prudence) |
| Canada | **6 ans (72 mois)** | Loi de l'impôt sur le revenu, **par. 230(4)** + règlement de l'impôt sur le revenu art. 5800 (six ans après la fin de la dernière année d'imposition visée) |
| Autre pays (défaut conservateur) | 10 ans (120 mois) | `config('accounting.retention_months')` — jamais plus court sans base légale identifiée |
| Audit logs (traçabilité) | 24 mois par défaut | `docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md` — commande `audit:purge --older-than=24` |

L'Algérie (DZ) reste sur le défaut 10 ans (code de commerce algérien, art. 12 ; loi 18-07 pour la protection des données).

## Mise en œuvre (module Comptabilité)

- **Résolution par pays** (#7929) : `AccountingRetentionService::retentionMonthsFor($country)` — table `RETENTION_MONTHS_BY_COUNTRY`, défaut `config('accounting.retention_months', 120)`.
- **Override opérateur conservé** : l'option `--older-than` de la commande de purge force une durée unique, prioritaire sur la résolution par pays. L'env `ACCOUNTING_RETENTION_MONTHS` (→ `config('accounting.retention_months')`) reste le **défaut** appliqué aux pays hors table — il ne raccourcit jamais une durée légale de la table.
- **Purge** : `php artisan accounting:purge-expired [--older-than=120] [--dry-run]`.
  - Sans `--older-than`, la purge résout la rétention **par pays du tenant** (`purgeByCountry`) : chaque entreprise est purgée selon la durée de sa juridiction.
  - Seuls les documents **finalisés** (`paid`, `cancelled`, `overdue`) sont éligibles ; les brouillons/envoyés sont conservés (encore en évolution).
  - Le cutoff court depuis `issue_date` (émission du document), pas la création.
  - Les lignes et paiements suivent le document (FK cascade) ; le PDF archivé est supprimé du storage.
  - `--dry-run` : rapport sans suppression (recommandé avant toute exécution).
- **Audit** : chaque action comptable (création, envoi, paiement, annulation, avoir) écrit une ligne `audit_logs` (qui, quoi, quand — cible morph, IP, user-agent). Consultation : `GET /api/v1/accounting/audit-logs` (principal/comptable).
- **Chiffrement au repos** : `accounting_contacts.tax_id`, `accounting_payments.reference`, `metadata` (documents/paiements/contacts) sont chiffrés (casts `encrypted`/`encrypted:array`) — vérifié par tests.

## Garde-fous

- La purge est **irréversible** : exécuter en `--dry-run` d'abord, sauvegarder avant (backup quotidien existant).
- Toute modification de délai = mise à jour simultanée de cette politique + `AccountingRetentionService::RETENTION_MONTHS_BY_COUNTRY` + `config/accounting.php` + tests, avec la source légale citée.
- Les durées par pays sont en confiance `pilot` tant qu'un expert local ne les a pas validées (constitution §III) — le défaut reste conservateur (10 ans).
