# Registre des constats — QA Expert 5 runtime (2026-08-15)

| # | Surface | Sévérité | Constat | Suivi |
|---|---------|----------|---------|-------|
| F1 | web | P3 | SignupForm placeholder « Choisir » en dur (catalogue `teamPlaceholder` existant) | issue #3295 → PR #3299 |
| F2 | web | P3 | sw.js précache routes authentifiées + sync tags morts | issues #3028/#3029 (PRs #3206/#3212/#3221) |
| F3 | web | P3 | Homepage sans alternates hreflang (sitemap les déclare) | issue #3417 → PR #3419 |
| F4 | web | P3 | Canonical www vs non-www (2 sources site-url.ts/site.ts) | issue #3190 → PR #3193 |
| F5 | web | P3 | Plans fantômes Starter/Business dans /download FAQ + /branding | issues #2984/#2978 |
| F6 | admin | P2 | LoginView sans accès démo (AGENTS.md v4.16.250 + contrat) | issue #3296 → PR #3307 |
| F7 | api | P1 | PHPStan Strict level 8 rouge sur main (4 erreurs post-baseline) | issue #3453 → PR #3455 |
| F8 | tooling | P3 | check-issues-left-open-by-merged-prs.sh plante (Python set non sérialisable) | à tracer |
| F9 | api | — | 17 issues laissées ouvertes par PRs mergées (garde #2512) | 12 fermées avec preuve code |
| F10 | pricing | P2 | Backend Starter/Business/Enterprise vs vitrine Free/Pilot/Operations/Enterprise | issues #3163/#2977 (arbitrage produit) |
| F11 | mobile | P3 | HR attendance_repository DateTime.parse non gardé | couvert par #3342 (autre agent) |

## Bilan des vérifications locales

- Vitrine : build/lint/tsc/mojibake ✅ · 0 lien mort sur 21 pages ✅ · hreflang sur /employes /pricing ✅
- Admin : build ✅ · lint 0 erreur ✅
- API : AuthLoginTest 3/3 ✅ · PayrollCalculatorUnitTest 41/41 ✅ · TenantIsolation 35/35 ✅ · PHPStan Strict → OK après PR #3455 ✅
- Mobile : anti-patterns AGENTS.md ✅ (statique)
