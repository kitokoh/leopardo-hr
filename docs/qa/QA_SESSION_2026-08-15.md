# QA Leopardo HR — Session du 2026-08-15 (audit expert)

Mission : tester la plateforme dans tous les sens (vitrine, web, admin, mobiles,
workflows, API, logiques, onboarding, cohérence) ; tout manquement → spec +
tasks + incidents (méthode Spec Kit) ; implémentation à la fin du test.

## Méthode

1. Clone + audit statique en 5 workstreams parallèles (sécurité/tenancy, logique
   & onboarding & code mort, vitrine front/web, admin-dashboard, mobile Flutter)
2. Vérification manuelle des findings critiques (routes réelles, middlewares,
   migrations, contrats API mobile↔backend)
3. Exécution locale : PHP 8.4.24 + PostgreSQL 16 + Redis (installés en session),
   npm build/lint pour les 2 frontends
4. 32 tests ajoutés/mis à jour — suite ciblée verte ; PHPStan strict 0 nouvelle
   erreur (169 pré-existantes sur main, baseline inchangée) ; Pint OK
5. Spec Kit : 3 features `.specify/features/qa-audit-expert-{backend,web,mobile}-2026-08-15/`
   (spec.md, plan.md, tasks.md) + 33 incidents GitHub (#2594-#2626, label
   `qa-audit-2026-08-15`)
6. Implémentation : 2 PRs (#2935 backend, #2936 frontend)

## Findings confirmés (vérifiés à la main) et résolution

### SÉCURITÉ (backend)
- [SEC-1 P1] Webhooks Stripe/Chargily/email-bounce **fail-open** quand le secret
  n'est pas configuré (payload accepté sans vérification) → **fail-closed**
  (400/503), tests unitaires + feature. Issues #2614 #2615 #2616 — ✅ PR #2935
- [SEC-2 P1] `UserAuthService::login()`/`googleSignIn()` ignorent `status`
  (compte suspended peut se connecter) → `AccountSuspendedException`/403.
  Issue #2618 — ✅ PR #2935
- [SEC-3 P1] OAuth Google sans paramètre `state` (CSRF login) → state session
  émis + validé (400 si absent/invalide). Issue #2619 — ✅ PR #2935
- [SEC-4 P1] `POST /auth/register` crée un compte sans `company_id` → login
  ultérieur impossible → **décision** : le register reste public (parcours
  company-request réel) ; le login de ces comptes est réparé
  (`AuthService::login`), statut vérifié avant. Issue #2617 — ✅ PR #2935
- [SEC-5 P2] `growth/partner/*` sans middleware `tenant` → ajouté (guards
  statut entreprise/employé). Issue #2622 — ✅ PR #2935
- [SEC-6 P2] `CalendarConnection`/`CalendarEvent` stockent des tokens OAuth
  sans `company_id` → migration tenant + backfill + `BelongsToCompany`.
  Issue #2623 — ✅ PR #2935
- [SEC-7 P2] Callback Google auto-créait un employé tenantless → 401
  `EMPLOYEE_NOT_FOUND`. Issue #2617 — ✅ PR #2935

### ONBOARDING
- [ONB-1 P1] `ProvisionDemoTenantJob::issueDemoAccess()` jamais appelé
  (régression #2437) → magic link trial mort, `ProvisionDemoTenantJobTest`
  rouge → appel restauré, test vert. Issue #2620 — ✅ PR #2935
- [ONB-2 P1] `GET /trial/status` partage `throttle:5,15` avec signup/verify →
  l'UI vitrine (poll 5 s) était throttlée après 5 requêtes → limiteur dédié
  `trial-status` (60/min). Issue #2621 — ✅ PR #2935
- [ONB-3 P3] Aucun flux forgot/reset password → `POST /auth/forgot-password` +
  `POST /auth/reset-password` (token 60 min, usage unique, révocation tokens,
  anti-énumération) + `PasswordResetMail` (4 locales). Issue #2626 — ✅ PR #2935
- [ONB-4 P3] Vérification d'email absente (`MustVerifyEmail`) — hors scope,
  documentée (issue dédiée à créer si souhaité).

### VITRINE / WEB (front/web)
- [WEB-1 P1] Module edge-nodes : `GET/POST /edge` inexistants (404) → page
  retirée du dashboard client (surface super-admin). Issue #2602 — ✅ PR #2936
- [WEB-2 P1] Liens action rapide `/dashboard/*` → 404 → `/employees`,
  `/absences`, `/reports`. Issue #2603 — ✅ PR #2936
- [WEB-3 P2] 133+ accents français manquants (blog, faq, témoignages, légal,
  i18n, nav/footer) → corrigés par table mot→mot (0 mojibake, vérifié).
  Issue #2604 — ✅ PR #2936
- [WEB-4 P2] 18 pages marketing en français dur → 4 pages majeures localisées
  (/about /careers /contact /faq) ; 14 restantes documentées. Issue #2605 — ⚠️
  partiel (tâches futures)
- [WEB-5 P2] Canonicals/robots/sitemap pointaient vers
  `gestionemployer-backend.vercel.app` (dev) → `SITE_URL` centralisée
  `leopardo-rh.com` (28 fichiers), robots legacy supprimé, sitemap complété
  (/blog /signup /checkout /offline /share), sameAs JSON-LD aligné.
  Issues #2607 #2608 — ✅ PR #2936
- [WEB-6 P2] Pages orphelines (/about /branding /videos /mobile) → liées au
  footer (4 locales). Issue #2609 — ✅ PR #2936
- [WEB-7 P2] Contenu mort (`src/content/blog/*.md` ×10, `content/blog/*.mdx`
  ×3 jamais importés) supprimé ; dates blog 2024 → 2026. Issue #2608 — ✅ PR #2936

### ADMIN DASHBOARD
- [ADM-1 P1] `POST /admin/impersonations` → 404 (seul `/platform/impersonations`
  existait) → route ajoutée. Issue #2624 (backend) — ✅ PR #2935
- [ADM-2 P2] Modals utilisateurs 100 % simulés (setTimeout+toast, companies
  mock, boutons inertes) → modals morts supprimés (EditUserModal/CreateUserModal
  n'étaient référencés nulle part), refs mortes nettoyées. Issue #2610 — ✅ PR #2936
- [ADM-3 P2] Header search = stub console.log → navigation par mot-clé réelle.
  Issue #2611 — ✅ PR #2936
- [ADM-4 P2] 11 composants orphelins (RevenueForecastWidget + 8 system + 2 users)
  supprimés ; console.log retirés. Issue #2612 — ✅ PR #2936
- [ADM-5 P2] Clés i18n manquantes (`users.errors.password_min`,
  `users.toast.bulkDone`, recherche) ajoutées aux 4 locales. Issue #2613 — ✅ PR #2936
- [ADM-6 P3] 12 vues `requiresTenant` inatteignables depuis la SPA super-admin —
  choix d'architecture documenté (non modifié). Issues #2612 (note)

### MOBILE (Flutter — statique, pas de toolchain en session)
- [MOB-1 P1] `GET /departments/{id}/hierarchy` → 404 (organigramme manager/hr)
  → endpoint backend ajouté + tests. Issue #2594 — ✅ PR #2935
- [MOB-2 P2] App marketing = scaffold par défaut + stats 100 % fake → task
  documentée (implémentation Dart à valider `flutter analyze` en CI). #2595
- [MOB-3 P3] Expense submit try/finally sans catch (3 apps) → task. #2596
- [MOB-4 P3] AI Voice « Bientôt disponible » (3 apps) → task. #2597
- [MOB-5 P3] URLs de base dev (onrender.com, leopardo.local) → task. #2598
- [MOB-6 P3] `password123` démo dans le bundle → task. #2599
- [MOB-7 P3] Offline dual (Hive legacy vs drift) → task. #2600
- [MOB-8 P3] leopardo_hr ≈ duplicata de leopardo_manager → task. #2601

## Bilan de la session

| Livrable | Valeur |
|---|---|
| Issues créées (incidents) | 33 (#2594-#2626, label `qa-audit-2026-08-15`) |
| Features Spec Kit | 3 (backend / web / mobile) — spec + plan + tasks |
| PRs ouvertes | #2935 (backend) — #2936 (frontend) |
| Tests ajoutés/mis à jour | 32 (tous verts) + 1 test rouge réparé (ProvisionDemoTenantJobTest) |
| Gates locales | PHPStan strict : 0 nouvelle erreur · Pint OK · lint web/admin OK · builds OK |

### À faire (tasks ouvertes, issues dédiées)
- Mobile Dart : #2595-#2601 (à implémenter avec Flutter, valider `flutter analyze`)
- Vitrine : 14 pages marketing restées en FR dur (#2605)
- Backend : vérification d'email (#2626 suite), 169 erreurs PHPStan strict
  pré-existantes sur main (chantier baseline)
