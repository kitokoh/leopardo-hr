# Tasks: Audit log + rétention RGPD + purge Comptabilité (Closes #5273)

**Spec**: `.specify/features/5273-accounting-audit-retention/spec.md`
**Plan**: `.specify/features/5273-accounting-audit-retention/plan.md`

- [x] T1. Claim : issue #5273 self-assignée + branche `mod/accounting/5273-audit-retention` poussée (protocole #2400)
- [x] T2. Audit de l'existant (DataAccessAuditLogger, PurgeAuditLogsCommand, casts chiffrés #5221, FK cascade)
- [x] T3. Spec + plan `.specify`
- [ ] T4. `config/accounting.php` (retention_months, audit)
- [ ] T5. `AccountingRetentionService` + commande `accounting:purge-expired` (--older-than, --dry-run, PDF)
- [ ] T6. `AccountingAuditController` (GET /accounting/audit-logs) + route
- [ ] T7. Tests `AccountingAuditRetentionTest` (trail, endpoint scope/RBAC/tenant, purge, chiffrement)
- [ ] T8. OpenAPI (chemin audit-logs) + coverage + SDK/miroir
- [ ] T9. Docs : CHANGELOG, matrices, `docs/security/ACCOUNTING_RETENTION.md`, `docs/specifications/ISSUE_5273.md`
- [ ] T10. Vérifs locales : tests Accounting + PHPStan strict + Pint
- [ ] T11. PR `feat(accounting): audit log + rétention RGPD + purge (Closes #5273)` → CI verte → merge → suppression branche
