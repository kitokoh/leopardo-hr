# QA Leopardo RH — Session expert #3 du 2026-08-15

Mission (propriétaire) : merger le maximum de branches mergeable au main, tester la plateforme
dans tous les sens (vitrine, web app, admin, mobiles, workflows, API, logiques, onboarding,
cohérence), consigner chaque manquement selon la méthode Spec Kit (issue + spec/plan/tasks),
implémenter les manquements, traiter le backlog d'issues ouvertes, garder main VERT.

## Méthode
1. Recon : état CI/PRs/issues/branches (19 PRs ouvertes, 179 issues, main rouge sur 4 checks requis).
2. Tests black-box live : API Render, admin Pages, vitrine Vercel — workflows réels (login, onboarding,
   avances salaire double validation, kiosk, notifications, trial).
3. Exécution locale : PHPStan strict (0 erreur après correctifs), tests ciblés, suite complète en cours.
4. Anti-doublon #2400 : chaque finding vérifié contre issues/branches avant création.

## État main en début de session (13:54)
| Check | État |
|---|---|
| PHPStan Strict (level 8) | 🔴 ~163 erreurs (drift tests + app) |
| PHPStan Modules | 🔴 3 erreurs (isFuture, Socialite with, ?? non-nullable) |
| Module Structure Validator | 🔴 préfixes migrations dupliqués `2026_08_15_000001` |
| I18N Enterprise | 🔴 drift catalogues admin-dashboard vs shared |
| Web CI Vitrine (E2E) | 🔴 footer test + auth-client-smoke (dépendent de l'API live/démo off) |

## Correctifs livrés (PRs créées par cette session)
| PR | Contenu | Closes |
|---|---|---|
| #3207 | PHPStan Strict/Modules 0 erreur (29 fichiers tests + 4 app), AIGateway 2 workflows | #3118, #2808 |
| #3214 | Garde rôle leave-balances (fuite CONFIRMÉE live), link-user cross-tenant, QR fail-closed | #3055, #3065, #3060 |
| #3256 | Mobile : notifications PATCH/POST (405 confirmé live), company-requests retry guard, id cast int | #3047, #3048, #3052 |
| #3303 | Admin : CompanyDetailView crash adoption.kiosk | #3034 |
| #3343 | Billing : essai 30 j cohérent backend | #3056 |
| #3408 | Web : og:image réelles, clés OTP, dashboard honnête, pricing i18n | #3021, #3022, #3027, #3443 |
| #3420 | API : per_page borné 18 contrôleurs, route training/sessions, TrainingGlobalListTest | #3059, #3062 |

## Findings fermés (décision/par conception)
- #2695 creds démo admin (AGENTS.md v4.16.250), #3053 ThemeMode.dark (PA2-MOB-012),
  #2696 MiniGlobe (déjà corrigé), #3030 edge-nodes (déjà retiré).

## Constats live (déploiements — déjà filed, à re-déployer)
- API Render périmée : /api-explorer 500, /i18n/catalog/* 500 (#2627/#2632/#2812).
- Vitrine Vercel périmée : /blog 404 (#2647), pricing/signup anciennes chaînes (#2813).
- Démo super-admin KO en prod (DEMO_MODE_ENABLED=false) (#2646).
- 718+ runs GitHub Actions queued (saturation #2488/#2131) — blocage de tous les merges.

## Backlog
- Issues expert2 restantes (#3023-#3032 web, #3036-#3046 admin, #3049-#3054 mobile, #3057/#3058/#3061/#3063/#3064 api) : priorité P2 puis P3.
- PRs ouvertes à merger : #2969 (migrations), #3110 (i18n), #3111 (admin build), #3115 (accents), #2972/#2982 (trial copy — un seul à garder), + 14 autres.
