# ISSUE #5273 — Audit log + rétention RGPD + purge (Comptabilité Phase A)

**Statut** : implémenté (branche `mod/accounting/5273-audit-retention`).

- **Audit trail** : actions documents (création, envoi, paiement, annulation, avoir) journalisées via `DataAccessAuditLogger` (`audit_logs.metadata.resource = accounting.*`, acteur, cible morph, horodatage) ; allowlist `sensitive_access_logging` étendue.
- **Consultation** : `GET /api/v1/accounting/audit-logs` (principal/comptable, scope tenant, filtres resource/user/date).
- **Rétention + purge** : `config/accounting.php` (120 mois par défaut) ; `accounting:purge-expired` (`--older-than`, `--dry-run`) — purge des documents finalisés antérieurs au cutoff, cascade lignes/paiements, PDF storage supprimés ; politique documentée `docs/security/ACCOUNTING_RETENTION.md`.
- **Chiffrement au repos** : prouvé par tests (tax_id, reference, metadata).

Tests : `AccountingAuditRetentionTest` (6 tests). Branche empilée sur #5223 (`mod/accounting/5223-document-workflow`), rebasée après son merge.
