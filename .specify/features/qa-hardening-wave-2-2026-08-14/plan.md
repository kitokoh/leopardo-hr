# Plan: Vague QA Hardening 2 — Backend & Surfaces Web/Mobile (2026-08-14)

**Input**: spec.md (US1-US5) + Constitution + audits round 2 (session 2026-08-14)

## Architecture / Décisions techniques

### Backend (api/)
- **SSO (US1)** : implémenter la validation réelle dans `SSOService` :
  - OIDC : valider l'ID-token (signature via JWKS de l'issuer configuré, `iss`, `aud`,
    `exp`) — utiliser `firebase/php-jwt` (déjà présent ? sinon ajout léger) ou le
    client HTTP + clé publique. Fallback documenté si JWKS indisponible.
  - SAML : valider la réponse (parsable, issuer attendu, conditions) — sans lib
    complète, faire une validation structurelle stricte + signature si clé publique
    configurée ; sinon 422 explicite `SSO_VALIDATION_UNAVAILABLE` (jamais 501).
  - Mapper l'email/nameId vers un employé du tenant via `user_lookups` / `users`.
  - Tests `SSOCallbackTest` (valide/invalide/issuer inconnu/config absente).
- **SEPA (US1)** : `BankExportGenerator` — lire l'IBAN/BIC du débiteur depuis
  `companies.metadata` (clés documentées) ou `company_settings` ; 422
  `MISSING_COMPANY_IBAN` si absent ; remplacer `PLACEHOLDER_*`/`NOTPROVIDED` par les
  vraies valeurs (BIC employé optionnel, sinon BIC entreprise).
  Tests : 422 sans IBAN, valeurs réelles avec IBAN, format legacy intact.
- **ExportController::history (US2)** : implémenter sur une vraie source — audit trail
  (`audit_logs` sur les événements d'export) ou table d'exports existante ; paginé,
  tenant-scope ; test `ExportHistoryTest`.
- **Push FCM (US2)** : `NotificationDispatcher::dispatch()` appelle
  `PushNotificationService->sendToEmployee()` (best-effort, try/catch non bloquant) ;
  test `NotificationPushDispatchTest` avec mock du service push.
- **Dead file (US2)** : supprimer `routes/modules/notification.php` + le `require` dans
  `routes/api.php`.
- **Route dupliquée (US2)** : conserver `/payments/{payrollRun}/documents` (canonique,
  utilisé par la matrice frontend/API) et garder `/payroll-runs/{payrollRun}/payment-documents`
  en alias si des clients l'utilisent (vérifier `mobile-workflow-contracts.json`), sinon
  supprimer le doublon.

### Web (front/web)
- **/videos (US3)** : soit brancher `public/videos/product-demo.*` (fichiers commités)
  avec `<video controls>`, soit état « Bientôt disponible » honnête ; retirer IDs
  YouTube factices + thumbnails inexistants.
- **/mobile (US3)** : `androidHref`/`iosHref` → `/signup?source=download_<slug>_<platform>`
  (logique identique à `/download`).
- **/download (US3)** : CTA principal honnête — pointe vers un livrable réel s'il
  existe dans `public/`, sinon libellé « Être contacté » vers `/contact?topic=download`.
- **Case studies (US3)** : créer `src/app/(landing)/case-studies/[slug]/page.tsx` avec
  contenu générique par slug (startup/retail/factory/law-firm) ou pointer les cartes
  vers `/case-studies` (index existant) — choix : pages [slug] minimales (contenu des
  fiches déjà dans `content.ts`).
- **Contrats PDF (US3)** : `apiFetch('/contracts/${id}/generate-pdf')`.
- **Checkout (US3)** : traiter `response.ok` du POST `/api/forms/signup` ; en cas
  d'échec, afficher l'erreur et ne pas rediriger ; aligner la bannière login.
- **Ancres /docs (US5)** : renommer vers ids existants ou ajouter les ids manquants.
- **forms.ts (US5)** : supprimer `trackAnalytics`/`fetchCsrfToken` morts (routes
  inexistantes) — vérifier qu'aucun appel actif ne les consomme.
- **Dead code web (US5)** : supprimer `lib/dynamic-imports.tsx`,
  `components/SkeletonLoader.tsx` (si non utilisé ailleurs), `DemoForm.tsx`,
  `ContactForm.tsx` (non référencés), export dupliqué `HeroSection` (garder
  `QuickTrialEmailForm`), route `/api/downloads` inutilisée, réécrire ou supprimer
  `lib/constants.ts` contre les routes réelles.
- **FAQ (US5)** : aligner Starter (39 €/mois) et essai (30 jours) sur
  `modules/vitrine/data/pricing.ts`.
- **OnboardingWizard (US5)** : corriger la chaîne mojibake ; envoyer la complétion via
  un endpoint dédié (`/onboarding-setup/{step}/complete` ou similaire existant) au lieu
  de `PATCH /company/branding` ; appeler `onComplete()` dans le catch.

### Mobile (front/mobile_apps)
- **Manifeste (US4)** : aligner `MobileExperienceService` sur les routes GoRouter réelles :
  - `leopardo_hr` : `/hr/employees` → `/team` (écran existant), `/hr/team-overview` →
    `/organigramme` (existant) ou `/team`, `/invitations` → route à créer ou retirer
    (vérifier l'écran invitations dans l'app) ; tout module sans écran → servit sans
    `route` (status actif mais non navigable) ou retiré du manifeste.
  - `leopardo_manager` : `/company/team-roles` → route existante ou retirée ;
    `/dashboard/admin` → retirée.
  - Garde CI : script qui vérifie que toute `route:` du manifeste existe dans les
    routeurs des apps (étendre `dev-hub/tools/validate-mobile-workflow-contracts.ps1`
    ou nouveau script) ; mise à jour `mobile-workflow-contracts.json`.
- **Voice IA (US5)** : l'écran placeholder ne doit pas envoyer `Uint8List(0)` — soit
  désactiver le bouton d'envoi avec état « bientôt disponible », soit retirer l'entrée.
- **/manager/dashboard (US5)** : retirer la route placeholder ou la remplacer par un
  écran honnête (dashboard manager réel s'il existe).
- **Petits (US5)** : `onTap` noop → retirer l'action ; TODO signature manifeste →
  implémenter ou documenter ; route morte `/smart-attendance/background-permission` →
  retirer.

## Phases

### Phase 1 — Backend (US1 + US2)
- T001 SSO SAML/OIDC réel (validation + tests) — **P1**
- T002 SEPA IBAN/BIC réels (422 si absent, tests) — **P1**
- T003 `ExportController::history` réel (tests) — **P1**
- T004 Push FCM via `NotificationDispatcher` (test) — **P1**
- T005 Supprimer `routes/modules/notification.php` + require — **P2**
- T006 Route dupliquée payment-documents (canonique + alias vérifié) — **P2**

### Phase 2 — Web (US3 + US5)
- T007 `/videos` — lecteur réel ou état honnête — **P1**
- T008 `/mobile` ancres → `/signup?source=...` — **P1**
- T009 `/download` CTA honnête — **P1**
- T010 Case studies `[slug]` ou index — **P1**
- T011 Contrats PDF → `/generate-pdf` — **P1**
- T012 Checkout : gérer la réponse API (pas de faux succès) — **P1**
- T013 Ancres `/docs#*` corrigées — **P2**
- T014 Dead code web (forms.ts helpers, constants.ts, dynamic-imports, SkeletonLoader,
  DemoForm, ContactForm, HeroSection dup, /api/downloads) — **P2**
- T015 FAQ alignée pricing — **P2**
- T016 OnboardingWizard : chaîne + endpoint dédié + catch — **P2**

### Phase 3 — Mobile (US4 + US5)
- T017 Manifeste `MobileExperienceService` aligné routeurs (HR + Manager) + garde CI —
  **P1**
- T018 Voice IA placeholder honnête (pas d'envoi vide) — **P2**
- T019 `/manager/dashboard` retiré ou branché — **P2**
- T020 Petits : onTap noop, TODO manifeste, route morte — **P3**

### Convergence
- T021 `CHANGELOG.md` + `AGENTS.md` (leçons) + `.specify/memory/project-state.md` +
  cocher T001-T021 après merge — **P2**

## Fichiers touchés (référence)

- `api/app/Core/Auth/Infrastructure/Services/SSO/SSOService.php` + `SSOController.php`
- `api/app/Modules/Payroll/Infrastructure/Services/BankExportGenerator.php`
- `api/app/Modules/HR/Interfaces/Api/V1/Controllers/ExportController.php`
- `api/app/Modules/Notification/Infrastructure/Services/NotificationDispatcher.php`
- `api/routes/api.php`, `api/routes/modules/notification.php` (suppression),
  `api/routes/modules/payroll_engine.php`
- `api/tests/Feature/{SSOCallbackTest,BankExportTest,ExportHistoryTest,NotificationPushDispatchTest}.php`
- `front/web/src/app/(landing)/{videos,mobile,download,case-studies}/**`,
  `front/web/src/app/(dashboard)/contracts/page.tsx`,
  `front/web/src/app/(landing)/checkout/page.tsx`,
  `front/web/src/app/(landing)/docs/page.tsx`, `front/web/src/app/(landing)/faq/page.tsx`
- `front/web/src/modules/vitrine/lib/{forms.ts,constants.ts}`,
  `front/web/src/modules/onboarding/components/OnboardingWizard.tsx`,
  `front/web/src/lib/dynamic-imports.tsx`, `front/web/src/components/SkeletonLoader.tsx`,
  `front/web/src/app/api/downloads/route.ts` (suppression)
- `api/app/Modules/HR/Infrastructure/Services/MobileExperienceService.php`
- `front/mobile_apps/leopardo_hr/lib/app.dart`, `leopardo_manager/lib/app.dart`
- `dev-hub/tools/validate-mobile-workflow-contracts.ps1` (+ nouveau garde manifeste→routeurs)
- `dev-hub/tools/mobile-workflow-contracts.json`, `CHANGELOG.md`, `AGENTS.md`

## Contraintes

- Ne pas dupliquer : drift OpenAPI = issue #2181 (en cours) ; mojibake global = #2173 ;
  dead code admin = #2190 ; vagues 1 = #2175-#2188.
- Ne pas toucher aux branches/PR en cours (fix/2136, feat/2116, feat/2121, feat/2131,
  fix/openapi-*, phpstan-*, payroll GA/CG/BF/ML).
- Constitution §IV : PHPStan strict level 8 vert, Pint, tests, isolation tenant.
- Constitution §VII : PR par issue avec `Closes #N`, CHANGELOG, branche supprimée après
  merge ; s'assigner l'issue avant de coder ; anti-doublon `gh issue list --assignee @me`.
- Rétro-compat : ne pas casser `/me/trainings`, `/export/*`, `/payments/{id}/documents`,
  les routes GoRouter existantes, le fallback DZD documenté (dernier recours).
