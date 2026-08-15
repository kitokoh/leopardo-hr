# QA Leopardo RH — Session expert #5 du 2026-08-15

Mission (propriétaire) : tester la plateforme dans tous les sens — vitrine, web app, admin,
mobiles, workflows, API, logiques, onboarding, cohérence — consigner chaque manquement selon la
méthode Spec Kit (issue + spec/plan/tasks), implémenter les manquements, traiter le backlog
d'issues ouvertes et merger le maximum de branches. **Main doit rester vert.**

## Méthode

1. **Tests dynamiques réels** contre l'API de production `gestionemployerbackend.onrender.com`
   (login démo 5 personas + 45 endpoints lus), cf. `qa_data/live_api_smoke.txt`.
2. **Revue statique experte par surface** (4 agents en parallèle) : API Laravel, vitrine Next.js,
   admin Vue 3, mobiles Flutter + cross-checks (routes vs OpenAPI, endpoints SPA vs routes,
   endpoints mobiles vs routes, i18n, docs vs code).
3. **Builds réels locaux** : `npm run build` vitrine ✅ et admin ✅ (verts sur main @ 96eea5eb).
4. **Anti-doublon #2400** : chaque finding vérifié contre issues ouvertes, PRs ouvertes et
   branches distantes avant création (l'environnement tourne avec plusieurs agents experts en
   parallèle — 30+ PRs créées pendant la session).

## Tests dynamiques (live, prod Render)

| Vérification | Résultat | Statut |
|---|---|---|
| `POST /api/v1/auth/login` manager principal/RH/comptable/employee (password123) | 200 OK | ✅ |
| `POST /api/v1/auth/login` super-admin `admin@leopardo-rh.com/password123` | 401 INVALID_CREDENTIALS | ❌ **#2646** (confirmé live) |
| `GET /api/v1/demo-users` | 404 | ⚠️ attendu si DEMO_MODE off |
| `/auth/me`, `/me/leave-balances`, `/launch-readiness`, `/employees`, `/attendance`, `/schedules`, `/company/branding`, `/notifications`, `/notification-preferences`, `/communication/analytics`, `/recruitment/jobs`, `/expense-claims`, `/salary-advances`, `/me/pay-slips`, `/attendance/today` | 200 | ✅ |
| `/api-explorer` | **500** | ❌ **#2632/#2627** (prod stale) |
| `/i18n/catalog/fr` | **500** | ❌ **#2812** (prod stale) |
| `/payroll/runs`, `/payroll/estimation`, `/training`, `/me/training-enrollments`, `/me/vehicles`, `/me/expenses`, `/exports`, `/supported-countries` | 404 | ❌ **#2627/#2812** (prod v4.23.5 vs main) |
| `/docs`, `/docs/openapi.yaml` | 200 | ✅ |

> Conclusion dynamique : le cœur API est sain sur les parcours manager/employee ; les échecs
> restants sont des **déploiements production périmés** (#2627/#2632/#2812 — ops, nécessitent
> déploiement Render/Vercel, non réalisable sans accord propriétaire) et le **login super-admin
> démo KO** (#2646).

## Findings NOUVEAUX (issues créées, label `qa-expert5-2026-08-15`)

| # | Surface | Sév. | Constat |
|---|---|---|---|
| (créées ci-dessous) | API | P2 | Routes écriture `/payrolls/{id}` (PUT/PATCH/DELETE/validate) sans gate route ; contrôleur accepte tout manager (dept/superviseur) vs policy `principal,comptable` de payroll_engine.php — résiduel #3150 |
| | API | P3 | `LaunchReadiness` — `communication_governance` vert sur tenant vide (0 employé = 0 ≥ 0) |
| | API | P3 | `AttendanceController@requestCorrection` — erreur « heure future » attachée à `requested_check_in` même quand seul `requested_check_out` est fautif |
| | API | P3 | `EmployeeController@index` — projection liste expose PII/salaire (personal_email, recovery_email, personal_phone, address, emergency_contact_*, salary_base, hourly_rate) |
| | API | P3 | Config PHPStan — excludePaths morts (« (?) ») dans phpstan-modules.neon, modules analysés en level 5 vs 8 strict, `reportUnmatchedIgnoredErrors: false` |
| | Admin | P2 | `CompanyDetailView` — champs « Identité Technique » vides : `PlatformCompanyHealthService` n'émet ni `slug` ni `created_at` |
| | Admin | P3 | Clés labels pays manquantes — 18 codes référencés, 12 définis dans `common.countries` (CG/CF/TD/GQ/NE/BJ/TG…) → clés brutes |
| | Mobile | P2 | `DZD` codé en dur — création entreprise platform_admin + 5 modèles partagés (fallback devise) |
| | Mobile | P3 | `generate: true` sans `l10n.yaml`/ARB dans 4 apps (config stale) |
| | Docs | P3 | Cartographie apps incohérente — AGENTS.md/README listent `leopardo_kiosk` parmi les apps Flutter (c'est une web app) et omettent `leopardo_employee` (app Flutter réelle) |
| | Web | P3 | `sitemap.ts` publie `/blog` même quand `NEXT_PUBLIC_ENABLE_BLOG=false` (layout `notFound()`) |

## Findings déjà couverts (vérifiés, pas de doublon créé)

- **Mobile #3003/#3004/#3005** (compile HR onboarding, routes manager, verbes notifications) →
  fix dans PR **#3125** ouverte — merge requis.
- **Admin /supported-countries → logout** (401 tenant-gated) → fix dans PR **#3111** (#2789).
- **Admin realtime.js read-all 404** → fix dans PR **#3217** (verbe PUT).
- **Admin clés i18n / title FR / composables orphelins / maintenances mortes** → #3201, #3222,
  #3219, #3194, #2995/#3145 déjà tracés.
- **Web canonicals/sitemap/domaine** → #3017, #3140, #3190 (+ PRs #3193/#3198).
- **Web PWA/sw.js** → #2983, #3028, #3029 (+ PRs #3221/#3206/#3212).
- **Web SignupForm FR, dashboard Leo IA factice, checkout fallback** → #3031, #3027, #3135.
- **API OAuth Google auto-provision, throttles, races, SSRF** → #2998, #3000, #2997, #3147
  (+ PRs #3179/#3215/#3214…).
- **PHPStan main rouge / OpenAPI drift** → #3176, #3130, #3158, #2638, #2675 (+ PRs #3182,
  #3196, #3207, #3159).
- **Pricing/plans incohérents** → #2977, #2978, #3163 (+ PRs #3202/#3208/#3218).
- **Docs RBAC matrix onboarding** → #3064 (PR #3199).

## Vérifié sain (pas d'action)

- Builds locaux vitrine + admin verts ; 0 href="#" dans la vitrine ; 0 clé i18n manquante côté
  admin ; imports @heroicons valides ; contrats mobiles ↔ routes Laravel (128 endpoints, 0
  mismatch) ; middleware de routes sensibles vérifiés ; `SmartAttendance` **est** routé via son
  provider (fausse alerte écartée après vérification).

## Implémentation

Issues → branches `fix/<issue>-<slug>` + PR `Closes #N` (Constitution §VII), CHANGELOG sous
`## [Unreleased]`. Spec Kit : `.specify/features/qa-expert5-2026-08-15/`.

## Backlog & merges

- PRs vertes mergeables suivies en continu (la file CI est saturée par les vagues parallèles) :
  #3125, #3111, #3182, #3196, #3207, #3217, #3221, #3212, #3201, #3222, #3193, #3198, #3161…
- Vercel `build-rate-limit` = statut legacy **non requis** pour merge (5 checks requis seulement).
