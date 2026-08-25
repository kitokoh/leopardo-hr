# Tasks: Workflow documents + numérotation paramétrable (Closes #5223)

**Spec**: `.specify/features/5223-accounting-document-workflow/spec.md`
**Plan**: `.specify/features/5223-accounting-document-workflow/plan.md`

- [x] T1. Analyse du socle #5221 (modèles, contrainte unique, contrats) + conventions repo (routes, RBAC, patterns race)
- [x] T2. Spec + plan + tasks `.specify`
- [ ] T3. `DocumentNumberingService` (implements `DocumentNumberingInterface`) — séries paramétrables + défauts par type
- [ ] T4. `DocumentWorkflowService` — createDraft (totaux + numéro + retry 23505), send, recordPayment, cancel, createCreditNote, refreshOverdue
- [ ] T5. Binding `DocumentNumberingInterface` dans `AccountingServiceProvider`
- [ ] T6. `AccountingDocumentController` + routes `accounting.php` + require dans `api.php`
- [ ] T7. Tests `AccountingDocumentWorkflowTest` (cycle complet + règles de transition)
- [ ] T8. Tests `AccountingDocumentNumberingTest` (formats, idempotence, **course**, isolation tenant, RBAC 403, cross-tenant 404)
- [ ] T9. OpenAPI (chemins documents) + coverage 0 drift + régénération SDK/miroir
- [ ] T10. Docs : CHANGELOG [Unreleased], matrices frontend/RBAC, `docs/specifications/ISSUE_5223.md`
- [ ] T11. Vérifs locales : tests Accounting + PHPStan strict + Pint
- [ ] T12. PR `feat(accounting): workflow documents + numérotation paramétrable concurrent-safe (Closes #5223)` → CI verte → merge → suppression branche
