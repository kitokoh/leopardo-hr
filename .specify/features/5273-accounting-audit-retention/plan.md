# Plan: Audit log + rétention RGPD + purge Comptabilité (Closes #5273)

**Spec**: `.specify/features/5273-accounting-audit-retention/spec.md`
**Issue**: #5273

## Architecture

Aucun changement de schéma — réutilisation de `audit_logs` (append-only) et des casts chiffrés #5221 :

```
api/app/Modules/Accounting/
├── Infrastructure/Services/
│   └── AccountingRetentionService.php   # NOUVEAU — cutoff + purge (finalisés, cascade, PDF)
├── Interfaces/Api/V1/
│   └── AccountingAuditController.php    # NOUVEAU — GET /accounting/audit-logs (scope module)
api/routes/modules/accounting.php        # + route audit-logs
api/config/accounting.php                # NOUVEAU — retention_months (défaut 120), audit
api/app/Console/Commands/AccountingPurgeExpiredCommand.php  # NOUVEAU — accounting:purge-expired
api/tests/Feature/Accounting/AccountingAuditRetentionTest.php # NOUVEAU
docs/security/ACCOUNTING_RETENTION.md    # NOUVEAU — politique + délais légaux
```

## Décisions

1. **Trail complet déjà en place** (#5223 journalise `accounting.document_created/sent/payment/cancelled/credit_note_created` via `DataAccessAuditLogger`) — #5273 ajoute la **consultation scope module** (principal/comptable, alors que `/audit-logs` global est principal-only) + les tests qui prouvent le trail.
2. **Purge = documents finalisés uniquement** (paid/cancelled/overdue) : seuls ces statuts sont des documents légaux clos ; les brouillons/envoyés restent (susceptibles d'évolution). Cutoff sur `issue_date` (pas created_at — la rétention légale court depuis l'émission).
3. **Cascade** : lignes et paiements suivent le document (FK cascade #5221) ; PDF supprimé du storage si `pdf_path` non nul.
4. **Chiffrement** : prouvé par test (enveloppe Laravel en base + round-trip modèle) pour tax_id, reference, metadata — pas de nouveau cast.
