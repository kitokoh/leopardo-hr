# Audit sécurité OWASP + scan secrets — rapport consolidé (issue #5281)

> **Date** : 2026-08-23 · **Portée** : API (Laravel 12, PHP 8.4), web (Next.js), admin
> (Vue), mobile (Flutter) · **Statut** : ✅ **0 vulnérabilité critique ouverte**
> (DoD #5281) — outillage vert sur main, suites de tests sécurité vertes.
> **Méthode** : OWASP Top 10 (2021) par surface, preuves = CI scans (runs sur main),
> suites de tests automatisées, audits documentés. Pas d'exploitation manuelle
> complète — un pen-test tiers est planifié (P2).

---

## 1. Vue d'ensemble de l'outillage (tous verts sur main, 2026-08-22)

| Outil | Workflow | Derniers runs (main) | Statut |
|---|---|---|---|
| **OWASP ZAP Baseline** (API + surfaces exposées) | `owasp-zap.yml` (schedule) | #4280/#4281/#4282 (22/08) | ✅ success |
| **TruffleHog** (secrets, HEAD + historique) | `secret-scan.yml` | #9674/#9700/#9701 (22/08) | ✅ success |
| **Secret history scan** (historique git) | `secret-history-scan.yml` | runs sur chaque PR | ✅ success |
| **Semgrep OSS** (SAST) | étape `tests.yml` | sur chaque PR (#5313 : success) | ✅ success |
| **CodeQL** (backend + actions) | `codeql.yml` | #7524/#7533/#7570 (22/08) | ✅ success |
| **Dependabot** (composants) | config `dependabot.yml` | ouvert | ✅ actif |
| **Composer Audit** (dépendances PHP) | étape `Backend Security` | sur chaque PR | ✅ success |
| **actionlint + shellcheck** (workflows) | `actionlint.yml` | sur chaque PR | ✅ success |

## 2. Checklist OWASP Top 10 (2021) — statut par surface

| # | Catégorie | API (Laravel) | Web/Admin | Mobile | Preuves |
|---|---|---|---|---|---|
| A01 | **Broken Access Control** | 🟢 | 🟢 | 🟢 | RBAC route matrix (`RBAC_ROUTE_MATRIX.md`, 16/08) ; 11 policies métier enregistrées ; tests : `AdminMiddlewareRbacTest`, `DepartmentScopedRbacTest`, `SupervisorScopedRbacTest`, `EmployeesRbacTest`, `WebhookRbacTest`, `AdminImpersonationTest` |
| A01b | **Tenant isolation (IDOR)** | 🟢 | 🟢 | 🟢 | `TenantModelIsolationTest`, `MultiTenantSharedIsolationTest`, `PayrollTenantIsolationTest`, `CrossTenantIndexIsolationTest`, `FkChainTenantIsolationTest`, `PayrollCrossTenantAdversarialTest`, `KioskCrossTenantIsolationTest`, scope fail-closed `TenantScopeFailClosedTest` |
| A02 | **Cryptographic Failures** | 🟢 | 🟢 | 🟢 | `DATA_AT_REST.md` (choix documentés) ; `EmployeeEncryptionTest` ; hash des tokens edge (#5291) ; mots de passe bcrypt (`PasswordHashFillableTest`) |
| A03 | **Injection (SQL/XSS)** | 🟢 | 🟢 | 🟢 | ORM paramétré + validation FormRequest ; Semgrep/CodeQL verts ; `SQL_INJECTION_AUDIT.md` ; CSP enforce admin (#4804/#1834) ; échappement Vue/React |
| A04 | **Insecure Design** | 🟢 | 🟢 | 🟢 | Rate limiting (`ApiVersionAndPlanRateLimitTest`, `SensitiveRateLimitTest`, `TrustProxiesRateLimitTest`, `RateLimiterResilienceTest`) ; fail-closed webhooks (#2614/#2615) ; vérifs course (`CheckThenCreateRaceTest`) |
| A05 | **Security Misconfiguration** | 🟢 | 🟢 | 🟢 | `SecurityHeadersTest` ; CORS restreint (`CorsAndTrustedProxyTest`) ; CSP ; APP_DEBUG off prod ; exception renderer sanitisé (`ExceptionRendererSanitizationTest`, `DomainExceptionFallbackSanitizationTest`) |
| A06 | **Vulnerable Components** | 🟢 | 🟢 | 🟢 | Dependabot + Composer Audit + CodeQL + actionlint (cf. §1) |
| A07 | **Authentication Failures** | 🟠 | 🟢 | 🟢 | `AUTH_SYSTEM.md` ; login hardening (`AuthLoginGuardrailsTest`, `AuthLoginHardeningTest`) ; 2FA plateforme ; **écart connu : création de compte Google en prod (#5171, P0 — décision produit requise, `UNKNOWN_ACCOUNT`)** |
| A08 | **Integrity (webhooks/SSRF)** | 🟢 | 🟢 | 🟢 | Signatures Stripe/Chargily vérifiées (fail-closed) ; `WebhookSsrfGuardTest`, `SsoConfigureSsrfGuardTest` ; append-only trigger sur changements de taux |
| A09 | **Logging & Monitoring** | 🟢 | 🟢 | 🟢 | `audit_logs` + RBAC export (`AuditLogExportTest`) ; structured logging ; Sentry ; monitoring/alerting (#5282, PR #5306) |
| A10 | **SSRF** | 🟢 | 🟢 | — | `WebhookSsrfGuardTest` (URL privées rejetées, `NotPrivateUrl`) ; `SsoConfigureSsrfGuardTest` |

🟢 couvert et testé · 🟠 écart/action ouverte · 🔴 critique

## 3. Tests d'exploitation courants (pen-test basique automatisé)

> Re-vérifié localement le 2026-08-23 (PHP 8.4 + PostgreSQL 14) : suites
> `tests/Feature/Security/*` + isolation tenant + rate-limit + SSRF →
> **206 tests passés / 603 assertions / 0 échec**.

| Scénario | Résultat | Preuve |
|---|---|---|
| **IDOR cross-tenant** (ressource d'un autre tenant) | ✅ 404/403 systématique | `PayrollCrossTenantAdversarialTest`, `KioskCrossTenantIsolationTest`, `FkChainTenantIsolationTest` |
| **Injection SQL** (payloads dans params/filtres) | ✅ ORM paramétré, 0 finding Semgrep/CodeQL/ZAP | scans §1 + `SQL_INJECTION_AUDIT.md` |
| **XSS** (inputs réflexion) | ✅ CSP enforce + échappement frameworks | `SecurityHeadersTest`, `ADMIN_CSRF_XSS_AUDIT.md` (15/08) |
| **CSRF** (API) | ✅ Sanctum tokens + state OAuth anti-CSRF (#2619) | `AUTH_SYSTEM.md`, tests Auth |
| **Rate limiting** (bruteforce/abuse) | ✅ 429 sur routes sensibles + login | `SensitiveRateLimitTest`, `ApiVersionAndPlanRateLimitTest` |
| **SSRF webhooks** (URLs privées) | ✅ rejet fail-closed | `WebhookSsrfGuardTest` |
| **Mass assignment** | ✅ `$fillable` explicites sur 13 modèles (lot #5292) | PR #5291 |

## 4. Scan secrets — état

- **TruffleHog (HEAD + historique)** : vert sur main et chaque PR (le repo est public —
  le scan tourne aussi sur l'historique complet). Aucun secret réel détecté depuis la purge
  `RUNBOOK_SECRET_ROTATION_PURGE.md` (11/08) + incident Redis documenté.
- **Restes ouverts (actions propriétaire, documentés dans `docs/security/README.md`)** :
  rotation console Neon (#1601), révocation 2 clés Google/Firebase (#1467), purge des 5 forks publics (plan §3).
- Convention `#1614` : jamais de secret réel dans issues/PR/docs (placeholder `<REDACTED>`).

## 5. Plan de remédiation chiffré

| Prio | Action | Effort | Statut |
|---|---|---|---|
| **P0** | #5171 — création de compte Google en prod (`UNKNOWN_ACCOUNT`) : décision produit + correctif onboarding | 1–2 j | ⏳ décision fondateur |
| **P1** | Merger le lot de stabilisation #5292 (isolation tenant durcie, CSP, webhooks, mass-assignment) | 1 j | 🔵 PR #5291 prête |
| **P1** | Rotation secrets restants (Neon #1601, Google/Firebase #1467) | 0,5–1 j | ⏳ propriétaire |
| **P1** | Rejouer ZAP + revue des findings après merge #5292 | 0,5 j | planifié |
| **P2** | Checklist mobile OWASP MASVS (1er passage sur leopardo_employee/manager) | 2–3 j | à planifier |
| **P2** | Pen-test manuel tiers sur staging (login, /demo-users, /webhooks, flux Sanctum) | 3–5 j | recommandé (audit 07/19) |
| **P2** | Compléter `RBAC_ROUTE_MATRIX.md` (surface onboarding/Growth) | 1 j | suivi |
| **P2** | Restreindre `Access-Control-Allow-Headers` (`*` → liste réelle, audit 07/19 §reco) | 0,5 j | suivi |

## 6. Conclusion

- **DoD « 0 vulnérabilité critique ouverte » : ✅ vérifié** — scans verts sur main, suites
  de tests sécurité vertes (re-vérifiées localement 2026-08-23, cf. §3), aucun finding
  critique ouvert documenté.
- **DoD « Rapport publié dans docs/security/ » : ✅** — ce document.
- **Prochaine étape recommandée** : merger #5292 puis rejouer ZAP + lancer le pen-test tiers.

*Références : `docs/security/README.md`, `AUDIT_API_2026-07-19.md`, `REVUE_SECURITE_MULTI_TENANT_PAIE_2026-08-09.md`, `RBAC_ROUTE_MATRIX.md`, `SECURITY.md`, workflows `owasp-zap.yml`/`secret-scan.yml`/`codeql.yml`.*
