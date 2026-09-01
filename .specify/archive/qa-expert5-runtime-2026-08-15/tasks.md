# Tasks — QA Expert 5 runtime 2026-08-15

## T1 — Tester la vitrine (runtime + statique)
- [x] Build/lint/tsc/mojibake locaux (verts)
- [x] Audit liens internes (0 lien mort sur 21 pages)
- [x] Audit canonicals/hreflang (constat homepage #3417 → PR #3419)
- [x] Audit SEO/sitemap/robots (divergence www vs non-www → #3190/#3193)
- [x] Test formulaire signup (OTP, locale) → constat placeholder #3295 → PR #3299
- [x] Audit PWA sw.js (précache auth + sync tags → #3028/#3029, PRs #3206/#3212/#3221 d'autres agents)
- [x] Vérif plans/pricing (divergence backend/vitrine → #3163/#2977, arbitrage produit)

## T2 — Tester l'admin
- [x] Build/lint (0 erreur, warnings préexistants)
- [x] Login runtime → constat accès démo #3296 → PR #3307

## T3 — Tester l'API
- [x] Environnement local complet (PHP 8.4 + PostgreSQL + Redis)
- [x] AuthLoginTest 3/3, PayrollCalculatorUnitTest 41/41
- [x] PHPStan Strict level 8 → drift main (4 erreurs) → PR #3455
- [x] Garde #2512 : 17 issues laissées ouvertes par PRs mergées → 12 fermées avec preuve code

## T4 — Tester le mobile (statique)
- [x] Anti-patterns AGENTS.md (dio direct, casts, DZD hardcodé, FCM) → propres
- [x] DateTime.parse non gardé HR → déjà couvert par #3342 (autre agent)
- [x] Compilation/analyse → couvert par CI mobile (flutter analyze)

## T5 — Workflows & cohérence
- [x] Contract launch-workflow-contracts.json (tokens demo)
- [x] Plans backend vs vitrine documentés (#3163)
- [x] Tooling bug check-issues-left-open-by-merged-prs.sh (Python set) — constat
