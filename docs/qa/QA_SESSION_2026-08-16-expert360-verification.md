# QA Session — Expert 360 (vérification) : audit implémentations + sondes prod (2026-08-16)

> Session : 2026-08-16 | Agent : expert360 (phase 0 — vérification que les audits
> précédents sont réellement implémentés, main vert) | Branche : `docs/qa-expert360-verification`

## Méthode

1. **Cartographie** : 61 issues ouvertes + 41 PRs ouvertes au moment du scan (13:55 UTC).
2. **Vérification code** : pour chaque issue, présence sur `main` (git log / grep) + PR
   ouverte ou mergée la référençant.
3. **Sondes prod réelles** : requêtes HTTP contre `https://gestionemployerbackend.onrender.com`
   (13:34 UTC) pour les issues P1 runtime.
4. **CI main** : état des 5 checks requis + file d'attente GitHub Actions.

## État CI main (13:40 UTC)

| Constat | Détail |
|---|---|
| **Baseline PHPStan rouge (résolu)** | `main` portait 18 erreurs PHPStan Strict dans des tests (SuperAdmin::$role, WarmPaySlipPdfPathsForPayrollRunJob::$timeout/$tries, DemoSuperAdminSyncTest stdClass, env hors config) — bloquait ~20 PRs. Corrigé par **#4382** (mergée 13:37). |
| File d'attente | 113→199 runs queued au pic ; 51 runs orphelins sur commits supersédés annulés (cleanup dispatché). |
| Checks requis | Backend Coverage, PHPStan Strict, Module Structure, Frontend ESLint+TS, actionlint — vérifiés sur les PRs avant merge. |
| Cascade de merges | 9 PRs mergées en rafale (13:33–13:37) : #4348, #4349, #4366, #4371, #4374, #4375, #4376, #4377, #4382 — l'orchestrateur swarm est actif. |

## Matrice de vérification (issues ouvertes au 13:55 UTC)

Légende : **PR-MERGED** = une PR la référençant est mergée sur main · **PR-OPEN** = PR en cours ·
**OPEN** = aucune PR, à implémenter.

| Issue | P | Statut | Preuve / commentaire |
|---|---|---|---|
| #4307 | P0 | PR-OPEN (#4308) | EmployeeService non-fillable — fix en cours |
| #4428 | P1 | OPEN | Résiduel EmployeeService::create — **à vérifier vs #4308** |
| #4370 | P1 | OPEN | **Sondes prod (13:34)** : /health 200 (DB ok, Redis `ConnectionException`), mais /auth/me, /employees, /platform/companies, /platform/auth/login, /trial/signup, /demo-users → **500**. Pattern : tout endpoint touchant des tables réelles 500, tout endpoint static/config 200 → hypothèse tables/schémas manquants ou Redis down. Commentaire détaillé posté sur #4370. |
| #4395 | P1 | OPEN | VerifyTrialSignup — 6 messages FR confirmés sur main (l.80/88/110/124/136/187) |
| #4396 | P2 | OPEN | ~20 chaînes FR résiduelles — confirmé (scan : 17 littéraux dans AuthController, SSOController, BillingController, PlatformUserController, UserEmployeeLinkController, AttendanceModeController, ProactiveNotificationService…) |
| #4312 | P2 | PR-OPEN (#4371) | FR résiduels — mergé 13:33 |
| #4310/#4294 | P2 | PR-OPEN (#4362/#4355) | PayrollRunController localisé |
| #4293 | P2 | PR-OPEN (#4353) | **Vérifié** : 13 clés errors.php manquent en tr/ar (ANNOUNCEMENT_*, OIDC_*, PAYROLL_*, RATE_*, SAML, SOCIAL_CONTRIBUTION, TAX_SLAB) — toutes référencées par `__('errors.X')` |
| #4298 | P3 | PR-OPEN (#4352) | OpenAPI — 89 routes non documentées restent en allowlist (0 drift nouveau mesuré) |
| #4196 | P2 | PR-OPEN (#4290) | Pages modules lot 1 employes |
| #4197 | P2 | PR-MERGED (#4276) | deviceIntlNumberLocale présent dans leopardo_core + payroll_list_screen ✓ |
| #4218 | P1 | **CLOSED (vérifié)** | /docs + /checkout + /success localisés (commit bcc2fbfeb « Closes #4185, #4218 ») — stepLabels via `copy.steps.*`, zéro FR résiduel |
| #3248 | P1 | partiel | Tranches videos/guides/checkout/docs mergées ; résiduels suivis (#4196/#4299/#4300/#4327) |
| #3250 | P2 | partiel | hreflang implémenté (layout.tsx) ; résiduels #4400/#4401 |
| #3882 | P1 | code OK | Route + TranslationCatalogController présents ; prod 200 ✓ (sondes) |
| #3879/#3259 | P1 | code OK / prod KO | Fixes dans le code (catchs) ; **prod /trial/signup toujours 500** → même cause racine que #4370 |
| #2646 | P1 | code OK / prod KO | DemoCompanyOnceSeeder (admin@leopardo-rh.com/password123) ✓ ; /demo-users → 500 prod (cause #4370) |
| #3842 | P2 | OPEN → #4388/#4389/#4391/#4392 | Garde Plan 29 périmée (auto-trigger déplacé #1396) — PRs swarm en cours |
| #3885 | P2 | partiel | 89 routes en allowlist (voir #4298) |
| #3766 | P1 | PR-MERGED | URLs → services gratuits |
| #3846 | P3 | partiel | og assets : PRs OG mergées ; déterminisme binaire toujours à valider |
| #2906 | P2 | OPEN (ops) | Code prêt (NEXT_PUBLIC_ENABLE_BLOG) ; activation Vercel = ops |
| #2601 | P2 | OPEN | Dédup leopardo_hr/manager — chantier structurel (features parallèles absences/attendance/cabinet…) |
| #3245 | P3 | OPEN | Duplication MeController/EstimationService |
| #4101 | P2 | OPEN | 10 specs E2E admin skip si pas de backend (vérifié : test.skip sur E2E_BACKEND_URL) |
| #4216 | P3 | OPEN (externe) | Check Cloudflare Workers = intégration GitHub non configurée — non corrigeable depuis le repo |
| #3910/#3912 | P2 | OPEN | Apps marketing/platform_admin incomplètes (features) |
| #4194/#4303 | P2 | OPEN | Dette i18n mobile (~113 chaînes résiduelles / 30 fichiers) |
| #4304 | P2 | OPEN | Deep links manifest sans résolution runtime |
| #4305/#4329/#4330/#4410 | P2/P3 | OPEN | i18n admin : 15+ vues FR (Settings, System, Support, Subscriptions, Analytics…) |
| #4318 | P3 | OPEN | Exceptions paie dupliquées App\Exceptions vs Module |
| #4333 | P3 | PR-OPEN (#4386) | Catchs silencieux admin |
| #4334 | P3 | PR-OPEN (#4364) | XSS popup FleetView |
| #4328 | P1 | PR-OPEN (#4347) | SystemView 404 endpoint |
| #4321/#4402 | P1/P2 | PR-OPEN (#4348) | FAQ accordéon cassé |
| #4322–#4327 | P2/P3 | PR-OPEN (#4367/#4368/#4369) | Cluster web hygiene |
| #4340–#4346 | P1–P3 | PR-OPEN (multiples) | Cluster CI/ops — worker queues, deploy-staging, FIREBASE_APP_ID_HR, CHANGELOG doublons, CI_CD_SECRETS |
| #4380/#4381/#4383/#4388 | — | PR-OPEN | Fixes en cours (#4387/#4384/#4390/#4389) |
| #4393–#4419 | P1–P3 | PR-OPEN/OPEN | Nouvelles issues swarm (moclaw) : metadata vitrine, JSON-LD, billing legacy, edge SQLite, timeouts CI, creds scripts, refs mortes, i18n-debt hr, PartnerApply… |

## Constats originaux de cette session (non dupliqués, déjà tracés)

- **#4370** : sondes prod complètes (tableau HTTP) — commentaire avec hypothèses
  départagées (migrations non appliquées vs Redis) posté sur l'issue.
- **errors.php** : parité 4 locales vérifiée → couvert par #4293/#4353.
- **Baseline PHPStan main** : documentée → couvert par #4382 (mergée).
- **Plan 29 guard** : périmée → couvert par #4388/#4389/#4391/#4392.
- **#4218** : vérifié implémenté + clos (aucun nouveau travail nécessaire).

## Recommandations

1. **#4370 est le blocage prod n°1** : nécessite accès logs Render pour départager
   « migrations non appliquées » vs « Redis down » ; débloque #3879, #3259, #2646, #4289, #4370 en cascade.
2. La file CI reste saturée (~199 runs) : maintenir le cleanup orphelins + fusionner par
   lots en vérifiant les 5 checks requis (strict = branche à jour).
3. Résiduels i18n admin (#4305/#4329/#4330/#4410) et mobile (#4194/#4303/#4304) =
   chantiers mécaniques P2 à répartir entre agents (un lot par surface, protocole #2400).
