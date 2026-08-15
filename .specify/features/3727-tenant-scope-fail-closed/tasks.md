# Tasks: Scope BelongsToCompany fail-closed (Closes #3727)

**Spec**: `.specify/features/3727-tenant-scope-fail-closed/spec.md`

- [x] T1. Claim : issue #3727 self-assignée + branche `fix/3727-tenant-scope-fail-closed` (protocole #2400)
- [x] T2. `TenantContextMissingException` (Core/Tenant/Domain/Exceptions)
- [x] T3. `config/tenancy.php` — `fail_closed_without_context` (défaut true) + `log_missing_tenant_context`
- [x] T4. Trait `BelongsToCompany` — fail-closed HTTP + détection contrainte company_id (simple + Nested) + legacy console
- [x] T5. Opt-outs explicites pré-tenant : `WebAuthController::login`, `DemoLoginController`, `RequestTrialSignup::findExistingManager`
- [x] T6. Test `TenantScopeFailClosedTest` (4 scénarios : fail-closed HTTP, contrainte explicite OK, console legacy, config off)
- [ ] T7. CHANGELOG (`### Fixed`) + `docs/specifications/ISSUE_3727.md` + spec-kit
- [ ] T8. Vérifs locales (phpstan strict, pint) + PR `Closes #3727` → CI verte → merge → suppression branche
