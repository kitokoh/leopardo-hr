# Session QA Expert — Tests réels + merge campaign — 2026-08-15

> Auteur : agent expert (mission de test complète) · Base : `main` (mouvant, swarm de sessions parallèles)
> Méthode : Spec Kit (`.specify/constitution.md`) — vérification **réelle** (tests exécutés, builds, gardes repo), conversion en issues label `qa-expert-2026-08-15`.

## ✅ Ce qui a été exécuté (résultats réels, pas du code-reading seul)

| Vérification | Résultat |
|---|---|
| PHPStan strict level 8 (`phpstan-strict.neon`) sur main | **0 erreur** ✅ |
| `front/web` — ESLint + `next build` + `check:mojibake` | verts ✅ |
| `front/admin-dashboard` — ESLint + `vite build` | verts ✅ |
| Web jest (16 suites) | **305/305** ✅ |
| Kiosk ZKTeco — `node --check` + tests i18n/feedback | verts ✅ |
| Env parity (config ↔ .env.example) | 271 clés, 0 manquante ✅ |
| Migrations — collisions basenames | 0 ✅ |
| Catalogue pays | 19 codes OK ✅ |
| `check-mobile-manifest-routes.sh` sur main | **ÉCHEC** → issue #3223, corrigé (PR #3230 mergée), garde re-vert ✅ |
| Suite tests backend locale (PG16, ~1900 tests) | en cours — échecs constatés ci-dessous |
| Runtime live : vitrine (Vercel 200), admin (pages.dev 200), API Render (200, **v4.23.5 stale**) | /i18n/catalog/fr 500 + /supported-countries 404 (prod périmée, connu #2627/#2812) |

## 🔴 Constats API (suite locale) — à recouper avec la CI (sessions parallèles)

- `PayrollCalculatorEdgeCasesTest` : 2 goldens heures sup → arrondi final (connu #2685/#2970, PR #2971 ouverte).
- `AIGatewayAndAnalyticsTest` : compteur workflows (connu #3118/#2808, PRs #3119/#3207).
- `NotificationDispatcherTest` (FCM fail-open) + groupes Auth : **probablement artefacts locaux** (état DB perturbé par mes migrations manuelles pendant le run) — à revalider en CI.
- `ContractPdfAliasTest` : 1 échec — à revalider.

## 🐛 Issues créées (label `qa-expert-2026-08-15`)

1. **#3223 [P2][mobile]** — Régression garde #2212 : manifeste sert `/history /modules /team /tasks /absences /attendance /evaluations /me/monthly /notifications /payrolls /salary-advances` au routeur Manager qui ne les déclare plus (screens existants). **Fix mergé** (PR #3230) : +11 GoRoutes, garde vert.
2. **#3325 [P2][web]** — `OnboardingWizard` PATCH `/onboarding-setup/configure_schedules/complete` → 404 sur tenant frais (`onboarding_steps` jamais seedé au provisioning ; seul le checklist seede) + échec avalé → onboarding backend à 0 % malgré wizard fermé. **Fix mergé** (PR #3350) : seed via checklist + complétion de la dernière étape requise + erreur affichée.

## 🔀 Merge campaign (session)

- Mergées par moi : **#3446** (docs expert3), **#3199** (docs RBAC), **#3230** (mobile manifest), **#3350** (onboarding web), **#3354** (ancre footer), **#3397** (lien offline), **#3355** (sitemap /share+/offline) — 7 PRs.
- Fermée en doublon : **#3399** (contenu déjà dans main via #3355).
- Issues #3223/#3325 fermées automatiquement par les merges.

## ⚠️ État CI / main

- **File GitHub Actions saturée/gelée** : des centaines de runs queued, rien ne se termine (concurrence des ~40 PRs des sessions parallèles + merges admin). Les checks requis de main sont en « pending » — pas rouges, mais pas encore verts au moment de la rédaction.
- Les merges de cette session sont : docs (risque code nul) ou fixes vérifiés localement (eslint/tsc/build/gardes repo).
- Prochaine action recommandée : laisser la file se vider, vérifier les 5 checks requis sur le head de main, puis revalider la suite backend complète en CI.

## 📚 Références

- Constitution : `.specify/constitution.md` · Issues : label `qa-expert-2026-08-15`
- PRs : #3230, #3350, #3354, #3355, #3397, #3446, #3199
