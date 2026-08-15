# QA Leopardo RH — Session expert #16 du 2026-08-15

Mission : audit 360° (Phase 1), nettoyage du backlog (Phase 2), implémentation
des constats (Phase 3) — protocole anti-doublon #2400 + spec-kit appliqués.

## Phase 2 — PRs web validées localement, rebasées et mergées

| PR | Issue | Contenu |
|---|---|---|
| #3821 | #3806 | ?lang= préservé dans les liens internes Navbar/Footer |
| #3824 | #3816 | aria-current restauré (conflit Navbar résolu : withLocaleHref + aria-current) |
| #3827 | #3808 | ~1 087 lignes de modules morts vitrine supprimées |
| #3823 | #3807 | sitemap/canonical/og:locale |
| #3797 | #3732 | a11y FAQ + Navbar |

Validation locale du lot complet : `tsc --noEmit` OK, `eslint` OK, 364 tests
jest OK, `next build` OK. Conflit Navbar (#3821 × #3824) résolu en gardant les
deux comportements. #3798/#3799/#3836 laissés à leurs auteurs actifs.

## Phase 3 — Issues audit implémentées et mergées (14)

| Issue | PR(s) | Surface | Fix |
|---|---|---|---|
| #3856 | #3988 | api | OpenAPI : 3 endpoints EdgeSync .sha256 (drift garde #1473 → 0) |
| #3858 | #3986 | admin | useRouter() remonté dans setup() — recherche header réparée |
| #3860 | #3987 | api | ATS : index unique (job_posting_id, email) + 409 ALREADY_APPLIED + 2 tests |
| #3854 | #3991 | web | seo.ts fallback plans Starter/Business/Enterprise (résiduel) |
| #3853 | #4023 | i18n | sync-web.js : catalogues admin en UNION (clés admin-only préservées) |
| #3852 | #3914* | web | JsonLd SITE_URL → getSiteUrl() (*PR portée par agent concurrent) |
| #3851 | #3979* | api | PlanSeeder trial_days → config('billing.trial_days') (*idem) |
| #3955 | #4033 | mobile-hr | écran d'erreur « Leopardo RH » (copier-coller corrigé) |
| #3954 | #4036 | mobile-core | fichier mort TranslationSyncService supprimé (import cassé) |
| #3962 | #4034 | web-offline | SW : fallback offline navigation → /index.html |
| #3963 | #4032 | web-offline | garde CI icônes manifest PWA |
| #3960 | #4054 | edge | HEALTHCHECK Dockerfile.publish → /api/v1/edge/health |
| #3970 | #4053 | CI | release.yml : 0 check trouvé = refus + pagination |
| #3968 | #4055 | CI | observability smoke : cancel-in-progress: true |

## Leçons

1. **Main avance très vite** (~1 merge/min, >150 PRs pendant la session) :
   merger origin/main dans la branche juste avant chaque merge, et re-vérifier
   l'état GitHub (les conflits annoncés par l'API sont souvent périmés).
2. **PR head « collé »** : si GitHub ne met pas à jour le head d'une PR après
   un push, supprimer la branche distante et la re-pousser, puis recréer la PR.
3. **CHANGELOG** : le format actuel est `## [Unreleased]` suivi directement
   d'une ligne `- **...` (pas de ligne vide) — l'insérer en tête.
4. **Concurrence sur les mêmes issues** : toujours `git fetch` + vérifier les
   branches distantes AVANT de créer une PR ; des agents parallèles portent
   parfois les mêmes correctifs (wording différent) — prendre la version main
   lors des conflits si elle est équivalente.
5. **Gardes locales utilisables sans PHP/Flutter** : check-openapi-route-coverage.py,
   i18n validators/sync, jest/tsc/eslint/next build (web), vue eslint+vite build
   (admin), bash -n + yaml (workflows), node --check (sw.js).
