# Implementation Plan: Session QA Expert 8 — 2026-08-15

**Branch**: `qa-expert8-session-2026-08-15` | **Date**: 2026-08-15 | **Spec**: `.specify/features/qa-expert8-session-2026-08-15/spec.md`

## Summary

Corriger les 16 manquements vérifiés localement non couverts par le swarm (issues #3485-#3500) : cohérence commerciale vitrine (SSO, sitemap, i18n SEO, métriques), états d'erreur admin, bornes API (throttles SSO, trial_days), propreté mobile (routes mortes, Sentry, await).

## Technical Context

- Web : Next.js 16 — `data/pricing.ts`, `sitemap.ts`, `seo.ts`, `vitrine-locale.ts`, catalogues i18n partagés
- Admin : Vue 3 + Vite — views Training/Predictions/Reports/TaxRates/Companies/Leaves/Payroll/Growth/Webhooks/Users
- API : Laravel 12 — `routes/modules/sso.php`, `database/seeders/PlanSeeder.php`
- Mobile : Flutter — app.dart (routes), main.dart (Sentry/init), smart_attendance_repository, core dead code

## Approach per finding

### F1-F5 (web) — 1 branche `fix/web-expert8-3485-3489`
SSO : retirer la feature des 4 locales pricing + checkout + comparatif. Sitemap : retirer /signup /checkout. seo.ts : locale dynamique. MiniCaseStudies : retirer chiffres. Pages FR : pattern #3248.

### F6-F11 (admin) — 1 branche `fix/admin-expert8-3490-3495`
Suppression EditUserModal + emit mort ; états d'erreur ; toasts ; dialog i18n ; KPIs ReportsView ; UsersView interpolation + glass-*.

### F12-F13 (api) — 1 branche `fix/api-expert8-3496-3497`
PlanSeeder 14j + throttle SSO callbacks + tests.

### F14-F16 (mobile) — 1 branche `fix/mobile-expert8-3498-3500`
Routes mortes employee retirées ; tracesSampleRate 0.2 ; StartupGate + extractDataList + dead code.

## Constitution Check

- Conforme §IV (PHPStan strict vert, tests), §V (aucun secret, erreurs masquées), §VII (branche fix/xxx, PR Closes #, CHANGELOG)
- Pas de modification de calculs de paie ni de migrations tenant — aucun gate Payroll/Multi-Tenant déclenché
