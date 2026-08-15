# Feature Specification: Session QA expert 4 — 2026-08-15

**Feature Branch**: multiples (fix/3055-3063-3060-3062-api-cleanup, fix/3034-3036-3037-3038-admin-data, fix/3045-3022-3041-csv-otp-shortcut, fix/3146-approval-policy-enforced, fix/3147-ssrf-rtsp-guard, fix/3154-3157-mobile-compile, fix/3183-i18n-diff-catalog-allowlist)

**Created**: 2026-08-15 | **Status**: Implémentation + PRs ouvertes

**Input**: Mission du propriétaire — merger le max de branches mergeables au main, tester la plateforme dans tous les sens (vitrine, web, admin, mobiles, workflows, APIs, logiques, onboarding, cohérence), documenter tout manquement (méthode Spec Kit), implémenter les manquements et le max d'issues ouvertes, main vert.

**Contexte**: Les vagues expert #2 (PR #3116) et v3 (PR #3160) ont déjà tracé #2972→#3065 et #3140→#3158. Cette session couvre : (a) la **vérification sur main courant** des constats expert #2 restés ouverts ; (b) l'implémentation des plus critiques (sécurité API, admin données réelles, vitrine OTP) ; (c) la dé-duplication des PRs parallèles (protocole #2400) ; (d) le merge des branches vertes.

## User Stories

### US1 — Les soldes congés ne fuient plus (P1/P2 — #3055, #3063)
Un employé authentifié ne peut plus lire les soldes de congés d'un collègue (y compris cross-tenant). La route dupliquée `employees/{employeeId}/leave-balances` (contrôleur Absence sans garde) est supprimée ; seuls `/leave-balances` (manager, scopé société) et `/me/leave-balances` (soi-même) subsistent.

### US2 — Les QR d'onboarding ne sont plus forgeables (P2 — #3060)
`OnboardingQrService::signingKey()` fail-closed : plus de fallback `leopardo-local-onboarding-key` quand `APP_KEY` est vide.

### US3 — Les approbations exigent un manager (P2 — #3146)
`ApprovalRequestPolicy` (enregistrée mais jamais invoquée) est appliquée : un employé simple reçoit 403 sur `POST /approvals/{id}/approve|reject`.

### US4 — Le test RTSP n'est plus un vecteur SSRF (P2 — #3147)
`POST /cameras/test-rtsp` refuse les cibles internes (loopback, RFC1918, link-local, CGNAT, multicast, IPv6 privées, DNS vers IP privée).

### US5 — Le cockpit admin affiche des données réelles (P1/P2 — #3034, #3036, #3037)
`CompanyDetailView` ne crashe plus (`kiosk` absent du payload → carte remplacée par une métrique réelle ; `slug`/`created_at` exposés). `DashboardView` lit `company.*`/`subscription.mrr`/`company_name`/`email` conformes au contrat API.

### US6 — Le flux OTP du signup n'affiche plus de clés brutes (P2 — #3022, #3031)
Les erreurs OTP résolvent via le catalogue localisé ; le titre de l'étape success est localisé (4 locales).

### US7 — Exports CSV et raccourcis admin assainis (P3 — #3045, #3041)
`AnalyticsView` échappe les cellules CSV (anti-injection de formule) ; le raccourci `Alt+R` (route tenant gardée) est retiré.

### US8 — Compilation mobile réparée (P2/P3 — #3154, #3157)
Imports `features/*` déplacés avant les déclarations (platform_admin) ; `DateTime?` → `DateTime` avec fallback (manager, aligné HR).

### US9 — La garde i18n ne bloque plus les catalogues vitrine (P3 — #3183)
`check-i18n-diff.js` ignore les catalogues/contenus localisés de la vitrine (fausses alertes sur chaque PR de contenu).

## Acceptance Scenarios
1. `GET /api/v1/employees/{id}/leave-balances` → 404 (route supprimée) — test `LeavePolicyApiTest::test_legacy_employee_leave_balances_route_is_removed`.
2. `POST /api/v1/approvals/{id}/approve` par un employé → 403, statut inchangé — `ApprovalControllerTest::test_plain_employee_cannot_approve_or_reject`.
3. 14 cibles RTSP privées rejetées, 2 publiques autorisées — `TestRtspSsidGuardTest`.
4. CI verte sur les PRs de la vague (backend tests, PHPStan strict, Module Structure, ESLint/TS, actionlint).
