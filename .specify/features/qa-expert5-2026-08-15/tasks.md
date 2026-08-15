# Tasks — Session QA expert 5 (2026-08-15, vague tardive)

## Tâches documentation (issues à créer)
- [x] T001 (#3317) — Issue API-1 : licences Edge forgeables (fail-open) [P1]
- [x] T002 (#3318) — Issue API-2 : SSRF OIDC + /sso/configure sans garde rôle [P2]
- [x] T003 (#3319) — Issue API-3 : émission licence Edge sans garde + valid_days non borné [P2]
- [x] T004 (#3320) — Issue API-4 : RateLimiter trial-status dupliqué [P3]
- [x] T005 (#3321) — Issue API-5 : per_page non borné 8 endpoints [P3]
- [x] T006 (#3323) — Issue API-6 : OpenAPI /public-holidays fantôme [P3]
- [x] T007 (#3326) — Issue WEB-1 : checkout crash plan inconnu [P2]
- [x] T008 (#3327) — Issue WEB-2 : métriques fabriquées testimonials/about [P2]
- [x] T009 (#3328) — Issue WEB-3 : Enterprise « Sur devis » vs prix [P2]
- [x] T010 (#3329) — Issue WEB-4 : CTA home pilot → checkout payant [P2]
- [x] T011 (#3330) — Issue WEB-5 : résidus FR SignupForm [P3]
- [x] T012 (#3331) — Issue WEB-6 : lien mort /offline [P3]
- [x] T013 (#3332) — Issue WEB-7 : sitemap /share [P3]
- [x] T014 (#3333) — Issue WEB-8 : guides 2024 périmés [P3]
- [x] T015 (#3334) — Issue WEB-9 : pages landing FR-only [P3]
- [x] T016 (#3336) — Issue ADM-1 : WebhooksView contract mismatch [P2]
- [x] T017 (#3337) — Issue ADM-2 : EdgeNodesView phantom fields [P3]
- [x] T018 (#3338) — Issue ADM-3 : DashboardView slug absent payload [P3]
- [x] T019 (#3339) — Issue ADM-4 : CompanyDetailView created_at absent [P3]
- [x] T020 (#3340) — Issue ADM-5 : CSV injection LeavesView [P3]
- [x] T021 (#3341) — Issue ADM-6 : ChatView avale 501 [P3]
- [x] T022 (#3342) — Issue MOB-1 : DateTime.parse HR sans tryParse [P3]

## Tâches implémentation (PR par issue, Closes #X)
- [ ] T101 — API-1 : fail-closed licence Edge + test (branche `fix/<issue>-edge-license-failclosed`)
- [ ] T102 — API-2 : garde api.manager /sso + validation anti-SSRF + tests
- [ ] T103 — API-3 : garde api.manager licence + bornes valid_days + tests
- [ ] T104 — API-4 : dédup RateLimiter trial-status
- [ ] T105 — API-5 : bornes per_page 8 endpoints
- [ ] T106 — API-6 : openapi.yaml public-holidays nettoyé
- [ ] T107 — WEB-1 : checkout fallback sûr + erreur propre
- [ ] T108 — WEB-2 : badge démo / chiffres sourcés testimonials + about
- [ ] T109 — WEB-3 : Enterprise cohérent (prix ou « Sur devis »)
- [ ] T110 — WEB-4 : CTA home pilot → /signup
- [ ] T111 — WEB-5 : résidus FR signup via catalogues
- [ ] T112 — WEB-6 : lien mort /offline retiré
- [ ] T113 — WEB-7 : /share retiré du sitemap
- [ ] T114 — WEB-8 : guides checklist paie 2026
- [ ] T116 — ADM-1 : WebhooksView aligné sur le contrat réel
- [ ] T117 — ADM-2 : EdgeNodesView aligné sur payload réel
- [ ] T118 — ADM-3 : slug exposé par health OU ligne retirée
- [ ] T119 — ADM-4 : created_at exposé par health
- [ ] T120 — ADM-5 : échappement CSV complet (=+-@)
- [ ] T121 — ADM-6 : ChatView affiche l'erreur backend honnête
- [ ] T122 — MOB-1 : DateTime.tryParse HR attendance

## Tâches coordination
- [ ] T201 — Merge des PRs vertes de la campagne (vérifier checks avant merge ; main vert)
- [ ] T202 — Nettoyage branches mergées (delete) + mise à jour AGENTS.md/CHANGELOG si leçon
- [ ] T203 — Vérification finale : `gh pr checks` sur les PRs mergées, main vert

## Ordre d'exécution
1. P1/P2 sécurité API (T101-T103) — priorité max.
2. P2 vitrine (T107-T110).
3. P2 admin (T116).
4. P3 (le reste).
