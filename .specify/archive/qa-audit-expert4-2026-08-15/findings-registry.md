# Registre des manquements — Session QA expert 4 (2026-08-15)

> Vérification sur main courant (post-vague merges) des constats expert #2/#3, implémentation
> des plus critiques, dé-duplication des PRs parallèles. Anti-doublon : tout constat déjà
> couvert par une issue ouverte avec branche est référencé, pas re-tracé.

## A. Vérifications runtime (live)

- [x] API prod Render (`gestionemployerbackend.onrender.com`) : `/api/v1/health` 200 · `/docs` 200 ·
      `/tester-guide` 200 · **`/api-explorer` 500** · **`/api/v1/i18n/catalog/fr` 500** ·
      `/api/v1/supported-countries` 404 · `/api/v1/demo-users` 404 (attendu, demo off).
      → déploiement stale (main = v4.24.0, prod = 4.23.5) — tracé #2812/#2632.
- [x] Vitrine Vercel (`gestionemployer-backend.vercel.app`) : / 200, /pricing 200 (mais 14j×8 + 30j×3 +
      Starter/Business fantômes), /blog 404 + sitemap 35 URLs dont 10 /blog/* mortes, og:image
      `/og/pricing.png` + `/og/default.png` **404** (#3021/#3014, fix #3136 en cours), /checkout garde
      des clés Starter/Business (#2975, fix #3134/#3135 en cours). Déployé ≠ main.
- [x] Admin `leo-admin.pages.dev` : 200 (login OK).

## B. Findings vérifiés sur main (post-vague) et traités dans cette session

| ID | Sév | Surface | Constat (vérifié code) | Issue | Traitement |
|----|-----|---------|------------------------|-------|------------|
| S1 | P1 | API | Route `employees/{employeeId}/leave-balances` (contrôleur dupliqué Absence) : lecture `LeaveBalance` par `employee_id` seul, **aucune garde de rôle ni scope société** → tout employé lit les soldes de n'importe qui, cross-tenant | #3055, #3063 | Corrigé (PR #3171) : route + contrôleur supprimés, test 404 |
| S2 | P2 | API | `OnboardingQrService::signingKey()` fallback codé en dur `leopardo-local-onboarding-key` (fail-open, QR forgeables) | #3060 | Corrigé (PR #3171) : fail-closed |
| S3 | P3 | API | `TrainingController::indexSessionsAll` jamais routée (la route utilise `indexAllSessions`) | #3062 | Corrigé (PR #3171) |
| S4 | P2 | API | `ApprovalRequestPolicy` enregistrée mais jamais invoquée → tout employé approuve/rejette | #3146 | Corrigé (PR #3174) : authorize() + test 403 |
| S5 | P2 | API | SSRF `POST /cameras/test-rtsp` : ffprobe sur URL utilisateur, aucune blocklist IP privées | #3147 | Corrigé (PR #3179) : blocklist + 16 tests |
| S6 | P1 | Admin | `CompanyDetailView` lit `health.adoption.kiosk.active` (clé jamais renvoyée) → crash fiche ; `slug`/`created_at` lus mais non exposés | #3034 | Corrigé (PR #3172) : carte remplacée + payload enrichi |
| S7 | P2 | Admin | `DashboardView` Priorités : `item.name/slug/mrr_eur/id` vs contrat `company.*`/`subscription.mrr` → noms vides, MRR 0, `/companies/undefined` | #3036 | Corrigé (PR #3172) |
| S8 | P2 | Admin | `DashboardView` Inscriptions en attente : `request.name/manager_email` vs `company_name`/`email` | #3037 | Corrigé (PR #3172) |
| S9 | P2 | Admin | `UserTable` lit `user.created_at` mais la vue mappe `createdAt` → colonne « - » | #3038 | Couvert par #3124 (dé-dupliqué) |
| S10 | P2 | Web | Flux OTP : `setOtpError('c.otpInvalidLength')` stocke la clé brute (préfixe `c.`) → affichée telle quelle | #3022 | Corrigé (PR #3173) |
| S11 | P2 | Web | Étape success : « Votre espace est pret ! » FR codé en dur | #3031 | Corrigé (PR #3173) : `c.successTitle` |
| S12 | P3 | Admin | Export CSV `AnalyticsView` sans échappement anti-formule (incohérent UsersView) | #3045 | Corrigé (PR #3173) |
| S13 | P3 | Admin | Raccourci `Alt+R` → `/recruitment` (route tenant gardée) → rebond muet | #3041 | Corrigé (PR #3173) |
| S14 | P2/P3 | Mobile | `platform_admin_app.dart` : imports après déclarations (analyze KO) ; manager `DateTime?`→`DateTime` | #3154, #3157 | Corrigé (PR #3181) |
| S15 | P3 | Tooling | `check-i18n-diff.js` flagge les catalogues vitrine (fausses alertes par PR de contenu) | #3183 | Corrigé (PR #3186) + issue tracée |

## C. Dé-duplications (protocole #2400)

- PR #2982 (14j) fermée → canonique : décision propriétaire 14j documentée (#3137) puis #3135.
- PR #3112 (accents) fermée → canonique : #3115 (version clean, 97 corrections, 0 régression).
- PR #3175/#3177/#3178 (instances parallèles) fermées → canoniques : #3172/#3171/#3173 (créées ~90 s avant, portée supérieure).

## D. État du merge (fin de session)

- Branches mergées par la vague parallèle : #2967→#3113, #3117, #3120, #3122, #3127, #3131, #3133, #2968, #2970, #2973, #2974, #2981, #3035, #3040, #3110, #3116, #2980.
- PRs de cette session : #3171, #3172, #3173, #3174, #3179, #3181, #3186 (+ #3182/#3184 du parallèle).
- CI : file saturée (~60 runs) — merges dès que les checks requis passent.
