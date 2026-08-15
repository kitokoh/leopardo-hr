# Findings Registry — QA expert 8 — 2026-08-15

| ID | Surface | Sévérité | Constat | Résolution |
|----|---------|----------|---------|------------|
| F8-01 | CI/Deploy | P1 | Famine déploiement : 48/50 derniers runs Tests sur main annulés avant démarrage (pending-run remplacé au push suivant), `Deploy API + Web to Render` skipped 100 % avec statut vert trompeur → prod figée | Spec `.specify/features/ci-deploy-pipeline-starvation/` + issue créée |
| F8-02 | API prod | P1 | `/api-explorer` 500 live | Fix mergé `4a78011c` (#2265) ; bloqué par F8-01 — pas de nouvelle issue |
| F8-03 | Vitrine | — | 20 routes 200, 0 CTA mort, 0 mojibake, sitemap/robots/manifest cohérents | Sain — rien à ticketiser |
| F8-04 | Mobile ↔ API | — | 119 endpoints réseau extraits des 5 apps Flutter : 0 route manquante | Sain — rien à ticketiser |
| F8-05 | Admin ↔ API | — | 102 endpoints extraits du SPA : 0 route manquante ; vues tenant gardées par `requiresTenant` | Sain — rien à ticketiser |
| F8-06 | Patterns interdits | — | 0 `.withOpacity(`, 0 `apiClient.dio.*`, 0 `dd()/dump()`, 0 `href="#"`, 0 `/auth/signup` sur main | Sain — rien à ticketiser |
| F8-07 | OpenAPI | — | `check-openapi-route-coverage.py` exit 0 (drift allowlisté) | Sain |
| F8-08 | Vitrine/checkout | P2 | Crash `?plan=<invalide>` (fallback `'starter'` hors `PLAN_CONFIG`) | **Fixé pendant la session** par PR #3440 (fallback `'free'`) — vérifié sur origin/main |
| F8-09 | Demo prod | P3 | `/api/v1/demo-users` 404 + demo login 401 en prod | Voulu (gate `DEMO_MODE_ENABLED`) ; arbitrage produit suivi en #2646 |
| F8-10 | Deploy observabilité | P2 | Un run `deploy-main` « success » peut n'avoir rien déployé (job skipped) — faux signal vert | Inclus dans la spec `ci-deploy-pipeline-starvation` (US2) |
