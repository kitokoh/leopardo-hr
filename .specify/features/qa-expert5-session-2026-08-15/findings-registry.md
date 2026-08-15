# Findings Registry — QA Expert 5 (2026-08-15)

> Méthode : chaque constat vérifié (live ou code) → issue GitHub `[QA][P#]` + suivi PR.
> Anti-doublon : vérification branches/PRs/issues existantes avant dépôt (protocole #2400).

## Constats LIVE (prod/staging)
| ID | Sév. | Constat | Issue | PR | Statut |
|----|------|---------|-------|----|--------|
| L-01 | P1 | `/api-explorer` → 500 prod (staging stale v4.23.5, fix #2287 non déployé) | #2287/F-E4-01 | — | 📋 ops (déploiement) |
| L-02 | P1 | guided-trial : `provisioning_token` absent de la réponse prod → flow cassé live | #2629 | #2864 | ⏳ (stale deploy) |
| L-03 | P2 | `/contact` littéral `{copy.info.responseTime}` (FR) | **#3352 (expert5)** | **#3357** | ✅ PR ouverte |
| L-04 | P3 | `/blog` 404 (sitemap le publie) | swarm (web-blog-sitemap) | — | 📋 couvert |
| L-05 | P3 | `leopardo-rh.com` NXDOMAIN vs domaine live | #3190/#3193 | #3193 | 📋 couvert |

## Constats STATIQUES (audits 4 surfaces → rapports `docs/qa/audit-expert5-2026-08-15/`)
| ID | Sév. | Constat | Issue existante | Statut |
|----|------|---------|-----------------|--------|
| A-01 | P1 | Test SSRF `TestRtspSsidGuardTest` rouge sur main (203.0.113.10 + DNS) | **#3324 (expert5)** | ✅ fix #3344 |
| A-02 | P1 | IDOR `GET /employees/{id}/leave-balances` (garde rôle manquante) | #3055 | PR #3177/#3214 |
| A-03 | P1 | OAuth Google → employé tenantless + token valide | #2998 | ⏳ |
| A-04 | P1 | ApprovalRequestPolicy jamais invoquée (approve/reject sans garde) | #3146 | PR #3174 mergée / #3187 |
| A-05 | P1 | SSRF `POST /cameras/test-rtsp` (ffprobe) | #3147 | ✅ mergée (test fixé #3324) |
| A-06 | P1 | Clé signature QR onboarding fail-open hardcodée | #3060 | PR #3171 |
| A-07 | P1 | Manager mobile : navigation /team /tasks /me/monthly → routes jamais enregistrées | #3205/#3223 | PR #3209/#3230 |
| A-08 | P2 | Races : magic link 2× (ProvisionDemoTenantJob), bulk-pay fail-open Redis down, payout sans plafond, doublons candidature ATS | #3002/#2997/#2999 | ⏳ |
| A-09 | P2 | `trial_days`/`trial/verify` incohérents (14 vs 30 j) entre chemins | #3056/#3164 | PR #3218/#3343 |
| A-10 | P2 | OTP échec mail avalé → 200 faux succès | #3057 | PR #3211/#3297 |
| A-11 | P2 | CSV admin sans échappement anti-formule (PayrollView, LeavesView) | #3045/#3340 | ⏳ |
| A-12 | P2 | Recherche header admin cassée (useRouter hors setup) | #3042 | PR #3201 |
| A-13 | P2 | Métriques fabriquées vitrine (500+, 50K+) | #3015/#3327 | ⏳ |
| A-14 | P2 | Pricing vitrine incohérent (Enterprise 3 représentations, surcoût absent checkout) | #3023/#3024/#3328 | PR #3202/#3208 |
| A-15 | P3 | Exceptions brutes exposées (AuthController, SSOController) | — | 📋 à créer si non couvert |
| A-16 | P3 | `DateTime.parse` non gardés (core attendance_log + HR attendance_repository) | #3157/#3342 | ⏳ |
| A-17 | P3 | deep-linking mobile absent (4 manifestes sans intent-filter) | — | 📋 à créer |
| A-18 | P3 | URLs hardcodées (leopardo.local:7878 mobile, ws://localhost:6001 admin) | #3331/#3191 | PR #3194 |
| A-19 | P3 | SyncService : subscription Connectivity jamais cancelée | — | 📋 à créer |
| A-20 | P3 | N+1 liveMap Traccar + per_page non bornés | #3059/#3148/#3321 | ✅ #3059 mergée |

## Issues créées par la session expert 5
- **#3324** [P1] TestRtspSsidGuardTest rouge sur main → fix mergé via PR #3344
- **#3352** [P2] /contact placeholder FR → fix via PR #3357
