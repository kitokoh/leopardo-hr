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

---

<!-- Version antérieure de la même session (fusionnée via #3300) — conservée pour traçabilité -->

# Tasks: QA Expert #5 — Test exhaustif plateforme (2026-08-15)

**Input**: spec.md — chaque tâche correspond à une issue GitHub ouverte (label `qa-expert5-2026-08-15`).

## Phase 1 — P1 (sécurité & parcours critiques)
- [ ] T001 [#3231] API — scopes company_id manquants sur 6 contrôleurs (IDOR) + test isolation
- [ ] T002 [#3232] API — EmployeePolicy company_id + test
- [ ] T003 [#3233] API — drift OpenAPI : aligner spec sur routes live (ou déprécier)
- [ ] T004 [#3234] API — clamp per_page uniforme (helper max(1,min(100)))
- [ ] T005 [#3235] API — masquer messages bruts (SSO, auth Google, import) + Log
- [ ] T006 [#3282] Mobile manager — déclarer les 9 GoRoutes manquantes
- [ ] T007 [#3283] Mobile employee — PUT /notifications/read-all
- [ ] T008 [#3267] Admin — WebhooksView : aligner contrat (active, company_name)
- [ ] T009 [#3268] Admin — UsersView : détail depuis /platform/users (company.employee_id)
- [ ] T010 [#3269] Admin — stack temps réel : neutraliser bandeaux/mark-all-read ou provisionner Reverb
- [ ] T011 [#3270] Admin — migrer les vues accessibles vers $t
- [ ] T012 [#3246] Vitrine — marquer témoignages/cas comme démo, retirer chiffres invérifiables
- [ ] T013 [#3247] Vitrine — source unique des plans (aligner pricing.ts/checkout/PlanSeeder)
- [ ] T014 [#3248] Vitrine — localiser les 10+ pages FR-only

## Phase 2 — P2 (cohérence, i18n, fiabilité)
- [ ] T015 [#3236] API — Log::error sur catches argent (billing, import, taux, auth Google)
- [ ] T016 [#3237] API — messages via __()/lang files (~36 chaînes)
- [ ] T017 [#3238] API — firstOrCreate/unique sur évaluations + paie
- [ ] T018 [#3239] API — fusionner les 2 moteurs de checklist onboarding
- [ ] T019 [#3240] API — supprimer squelette Training mort
- [ ] T020 [#3241] API — throttle /health* + /sso/providers
- [ ] T021 [#3242] API — SSRF guard sur tokenUrl/jwksUri OIDC
- [ ] T022 [#3243] API — fail-closed demo password + decryptField
- [ ] T023 [#3284] Mobile HR — retirer 9 routes mortes
- [ ] T024 [#3285] Mobile manager — retirer 6 routes mortes
- [ ] T025 [#3286] Mobile — maxRetriesOverride:0 sur register/google-signin/publish
- [ ] T026 [#3249] Vitrine — restaurer les accents FR/TR
- [ ] T027 [#3250] Vitrine — hreflang/x-default (ou localisation par chemin)
- [ ] T028 [#3251] Vitrine — fusionner site.ts/site-url.ts
- [ ] T029 [#3252] Vitrine — retirer /share et /offline du sitemap
- [ ] T030 [#3253] Vitrine — aligner l'ancre fonctionnalités
- [ ] T031 [#3254] Vitrine — corriger ?topic=enterprise + renommer Forum
- [ ] T032 [#3255] Vitrine — corriger {copy.info.responseTime}
- [ ] T033 [#3257] Vitrine — retirer/transformer la page Desktop fantôme
- [ ] T034 [#3258] Vitrine — étiqueter honnêtement les boutons de téléchargement
- [ ] T035 [#3260] Vitrine — générer JSON-LD offers depuis la source pricing
- [ ] T036 [#3261] Vitrine — OG par locale + comptes d'apps corrigés
- [ ] T037 [#3262] Vitrine — localiser manifest/PWAProvider, masquer leopardo.local
- [ ] T038 [#3263] Vitrine — badge « Archivé » sur les vieux posts blog
- [ ] T039 [#3264] Vitrine — liste unique des pays + catégories FAQ dédoublonnées
- [ ] T040 [#3271] Admin — ChatView : état honnête « indisponible »
- [ ] T041 [#3272] Admin — débloquer /exports et /fleet, stubler 10 routes tenant
- [ ] T042 [#3273] Admin — ajouter slug/created_at au payload health (ou retirer)
- [ ] T043 [#3274] Admin — états d'erreur visibles + santé par défaut unknown
- [ ] T044 [#3275] Admin — source unique des raccourcis (Alt+R)
- [ ] T045 [#3276] Admin — SystemView : supprimer ou brancher les 6 sections
- [ ] T046 [#3277] Admin — toIntlLocale partout

## Phase 3 — P3 (hygiène, cleanup)
- [ ] T047 [#3244] API — /employees/link-user sous api.manager
- [ ] T048 [#3245] API — dédupliquer MeController/HrController
- [ ] T049 [#3265] Vitrine — supprimer structured-data.ts
- [ ] T050 [#3266] Vitrine — uniformiser badges Leo IA + suffixe AR
- [ ] T051 [#3278] Admin — migrer 14 fichiers vers glass tokens, fusionner MetricCard
- [ ] T052 [#3279] Admin — corriger CTA intégrations
- [ ] T053 [#3280] Admin — supprimer route /users/:id morte
- [ ] T054 [#3281] Admin — nettoyer les valeurs i18n
- [ ] T055 [#3287] Mobile — currencySuffix au lieu de FCFA
- [ ] T056 [#3288] Mobile — amorcer la migration l10n (ou retirer locales annoncées)
- [ ] T057 [#3289] Mobile — downloadWithRetry
- [ ] T058 [#3290] Mobile — timeouts Dio core_providers
- [ ] T059 [#3291] Mobile — smart_attendance queryParameters + extractDataList
- [ ] T060 [#3292] Mobile — tracesSampleRate 0.2 platform_admin
- [ ] T061 [#3293] Mobile — /create-post dans le ShellRoute marketing
- [ ] T062 [#3294] Mobile — retirer client ID Google OAuth debug

## Vérification globale
- [ ] Chaque PR : `Closes #N` dans le body + entrée CHANGELOG.md
- [ ] Checks requis main verts (Backend Coverage, PHPStan Strict L8, Module Structure, ESLint+TS, actionlint)
- [ ] Nettoyage des branches après merge
