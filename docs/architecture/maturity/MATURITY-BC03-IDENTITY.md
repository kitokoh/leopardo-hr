# Rapport de maturité — BC-03 IDENTITY

> **DEP-BC03 (issue #5879)** — Deep maturity, BC-03 Identity & Access.
> Audité le 2026-08-28 (main `8b3609f`). Agent propriétaire : 03.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-03).

## Périmètre

Identité & accès : `api/app/Core/Auth` (AuthService, TwoFactorAuthService,
TotpService, SuperAdminService, SSO/), `api/app/Policies`, invitations,
sessions, rôles/permissions, MFA. Séparation stricte platform (super-admin) /
tenant (employés) — jamais de décision métier Payroll/CRM/FuelStation/EDU.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | DDD complet (Application/Domain/Infrastructure/Interfaces), exceptions dédiées (TwoFactorException, RegistrationNotAvailableException), vocabulaire stable (employee/membership/session). |
| D2 | Données | 🟢 PRÉSENT | Employees/memberships en schéma tenant, super_admins en public, secrets 2FA chiffrés (SensitiveDataEncryptor), migrations publiques/tenant distinctes. |
| D3 | Tenant | 🟢 PRÉSENT | Employees scopés par company (BelongsToCompany), resolution tenant via TenantMiddleware, `tenant_scope_required` fail-closed. |
| D4 | API | 🟢 PRÉSENT | AuthController complet (login/register/2FA/password reset/SSO callbacks), Requests validées (StoreRegistrationRequest), routes /auth + /platform/auth + /sso, 401/403 testés. |
| D5 | Autorisation | 🟢 PRÉSENT | Guards `auth:sanctum` / `auth:super_admin_api`, Policies métier (`api/app/Policies/` : EmployeePolicy, AttendancePolicy, …), 2FA requis par politique tenant (`mfa_required_roles`), marqueurs fail-closed. |
| D6 | Transactions | 🟢 PRÉSENT | RegisterAction (invitation → role, création employé), 2FA enable/disable avec challenges TOTP, SSO avec états anti-replay. |
| D7 | Asynchronisme | 🟡 PARTIEL | Mails d'invitation/password-reset (Mailable), pas de jobs identity dédiés (acceptable — surface synchrone). |
| D8 | Sécurité | 🟢 PRÉSENT | **Audit trail** : `DataAccessAuditLogger` (record/recordSensitive, actions sensibles préfixées), `AuditLog` (schéma tenant), LogoutAction audité. Secrets hors code (recovery codes hachés, TOTP). |
| D9 | Frontend | 🟢 PRÉSENT | Écrans mobile 2FA (leopardo_core TwoFactorSettingsScreen), pages web login/2FA/SSO. |
| D10 | Performance | 🟢 PRÉSENT | Buckets throttle (`auth-sensitive`, `platform-sensitive`), ResilientThrottleRequests, lookup indexés. |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés, Sentry context, runbooks SSO/2FA (`docs/ops/`). |
| D12 | Produit | 🟡 PARTIEL | Parcours login → 2FA → session testé (15 tests locaux verts : TwoFactorAuthTest, RegisterLoginFlowTest, AuthMeLogoutTest). Pas de seed pilote identity dédié. |

## Vérification locale (preuve CI)

```
php artisan test --filter="TwoFactorAuthTest|RegisterLoginFlowTest|AuthMeLogoutTest"
→ 15 passed (71 assertions)
```

## Recommandations (PR futures, non bloquantes)

1. **Invitations idempotentes** (backlog) : formaliser le rejeu d'invitation
   (même email, invitation expirée/rejouée) avec un test dédié — le flux
   RegisterAction accepte une invitation unique ; un test anti-doublon
   verrouillerait le comportement.
2. **Audit des changements de permission** (backlog) : les changements de rôle
   passent par les updates Employee (BC-04) — définir un contrat d'audit
   `identity.permission.*` avec DataAccessAuditLogger (alignement de préfixes,
   cf. pattern `accounting.share.*` #5439).
3. **Expiration/revocation de session** : audit des tokens Sanctum expirés +
   test de révocation multi-appareils.
4. **Service accounts** (backlog) : pas encore de concept dédié — à spécifier
   avant toute API machine-to-machine.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
