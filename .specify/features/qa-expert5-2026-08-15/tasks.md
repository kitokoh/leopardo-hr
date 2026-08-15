# Tasks: Vague QA Expert 5 — 2026-08-15

**Input**: spec.md + plan.md (`.specify/features/qa-expert5-2026-08-15/`)
**Prerequisites**: plan.md (required), spec.md (required)
**Tests**: gardes scripts + lint/build + CI PR (voir plan.md « Validation »)

## Phase 1 — API (issues #3363-#3370) 🎯 MVP

- [ ] T001 [P1] #3363 Password reset : résolution `public.user_lookups` + setTenantSearchPath (forgot + reset), fusion mail/test (#3370), test tenant à schéma
- [ ] T002 [P2] #3364 /auth/register : résolution invitation → setTenant → MAJ employé existant (ou fermeture endpoint + alignement mobile)
- [ ] T003 [P2] #3365 QR punch : parser `base64url(payload).signature`, vérifier signature+expiration, employee_id scopé, rejeter non signés
- [ ] T004 [P3] #3366 Supprimer double registration `RateLimiter::for('trial-status')` (garder clé token|IP)
- [ ] T005 [P3] #3367 Monter `throttle:kiosk-punch` sur le groupe kiosque (integrations.php + rh.php)
- [ ] T006 [P3] #3368 try/finally restore search_path dans les 6 handlers kiosque
- [ ] T007 [P3] #3369 syncTrips : index unique `(company_id, traccar_trip_id)` + insertOrIgnore + bornage 31 j

## Phase 2 — Web (issues #3372-#3382, #3410, #3416)

- [ ] T008 [P2] #3372 Checkout : afficher surcoût/employé actif + sièges inclus (PlanSummaryCard + résumé)
- [ ] T009 [P2] #3373 Home CTA « pilote gratuit » → `/signup?source=...`
- [ ] T010 [P2] #3374 Enterprise : retirer de PLAN_CONFIG/checkout → `/contact?topic=enterprise`
- [ ] T011 [P2] #3375 robots.ts : disallow 13 prefixes racine (miroir middleware)
- [ ] T012 [P2] #3376 sitemap : gater /blog sur enableBlog ; retirer /share + /offline
- [ ] T013 [P2] #3377 Checkout/success : localisation FR/EN/TR/AR (erreurs, validation, labels paiement)
- [ ] T014 [P2] #3378 Dashboard métier : étendre i18n.ts + getCopy(locale) (billing d'abord)
- [ ] T015 [P2] #3379 client-features : défaut 'locked', preuve positive, capabilities arrays
- [ ] T016 [P3] #3380 Billing : retirer upgrade manual (une seule voie de paiement)
- [ ] T017 [P3] #3381 Footer : ajouter /about + /videos aux 4 catalogues section 0
- [ ] T018 [P3] #3382 Carrières : localisation portail (catalogues vitrine)
- [ ] T019 [P3] #3410 changelog-public.ts : régénérer depuis CHANGELOG.md (retirer 4.16.55-59)
- [ ] T020 [P3] #3416 web-offline : .env.example NEXT_PUBLIC_EDGE_API

## Phase 3 — Admin (issues #3388-#3395)

- [ ] T021 [P1] #3388 MarketingOAuthView : extraire OAuthProviderCard en SFC .vue
- [ ] T022 [P2] #3389 WebhooksView : GET /admin/webhooks/events + mapper is_active ↔ active
- [ ] T023 [P2] #3390 ChatView : désactiver composer + avis « chat IA plateforme indisponible »
- [ ] T024 [P3] #3391 realtime.js : PUT /v1/notifications/read-all
- [ ] T025 [P3] #3392 VITE_WEBSOCKET_URL : injecter en CI (ou défaut wss origin API)
- [ ] T026 [P3] #3393 KeyboardShortcutsModal : retirer ligne Alt+R obsolète
- [ ] T027 [P3] #3394 GrowthDashboardView : retirer affectation morte / consommer commissions
- [ ] T028 [P3] #3395 ExportsView : catch → historyError + retry

## Phase 4 — Mobile (issues #3400-#3406)

- [ ] T029 [P2] #3400 Manager : ajouter GoRoutes /tasks /team /me/monthly (port HR) + garde manifeste verte
- [ ] T030 [P2] #3401 read-all : aligner hr/manager notification_repository (PATCH/POST) avec #3167
- [ ] T031 [P2] #3402 HR : DateTime.tryParse requested_check_in + nullable
- [ ] T032 [P3] #3403 ai_voice : maxRetriesOverride: 0
- [ ] T033 [P3] #3404 Employee : retirer/câbler /me/monthly
- [ ] T034 [P3] #3405 fr_FR dates : locale dérivée (20 écrans)
- [ ] T035 [P3] #3406 casts directs : extractDataMap/List (8 sites)

## Phase 5 — Cohérence (issues #3409-#3414)

- [ ] T036 [P2] #3409 CHANGELOG : supprimer 1207-1656, fusionner 13 lignes, dédup 4.22.x
- [ ] T037 [P3] #3411 Matrix : lignes 140-144 dans la table principale
- [ ] T038 [P3] #3412 RBAC : fusionner famille Payroll engine
- [ ] T039 [P3] #3413 dev-hub refs : pointer docs/archive/PLAN_ACTION2/ ou retirer
- [ ] T040 [P3] #3414 allowlist : retirer POST approve/reject

## Phase 6 — Campagne merge & main vert

- [ ] T041 Résoudre les conflits des PRs ouvertes du swarm (git merge origin/main + push)
- [ ] T042 Merger en cascade les PRs dont les checks requis sont verts (`gh pr merge --merge --delete-branch`)
- [ ] T043 Vérifier `gh run list --branch main` vert après chaque merge ; corriger les rouges
- [ ] T044 CHANGELOG.md : entrées Unreleased pour chaque lot ; AGENTS.md si leçon opérationnelle
- [ ] T045 Rapport final : registre des issues créées/fermées, PRs mergées, état de main

## Dependencies & Execution Order

- T001-T007 parallélisables (fichiers PHP distincts) mais T001 fusionne #3370
- T008-T020 : fichiers Next.js distincts, parallélisables ; T011/T012/T017 indépendants
- T021-T028 : fichiers Vue distincts
- T029-T035 : Flutter — T029 dépend de la résolution manifeste ; T030 doit être coordonné avec #3167
- T036-T040 : docs/tooling, indépendants
- T041-T043 : continus, dès que les checks CI le permettent (file GitHub Actions saturée)
