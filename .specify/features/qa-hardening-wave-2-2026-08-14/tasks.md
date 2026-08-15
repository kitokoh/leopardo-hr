# Tasks: Vague QA Hardening 2 — Backend & Surfaces Web/Mobile (2026-08-14)

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

## Phase 1 — Backend (US1 + US2)

- [ ] T001 [P1] US1 SSO : implémenter la validation SAML/OIDC dans `SSOService`
      (signature/issuer/audience, mapping employé, erreurs 4xx explicites — jamais 501),
      tests `SSOCallbackTest` (valide, invalide, issuer inconnu, config absente).
- [ ] T002 [P1] US1 SEPA : `BankExportGenerator` lit IBAN/BIC entreprise
      (`companies.metadata`) ; 422 `MISSING_COMPANY_IBAN` si absent ; plus de
      `PLACEHOLDER_COMPANY_IBAN`/`NOTPROVIDED`. Tests `BankExportTest`.
- [ ] T003 [P1] US2 `ExportController::history` sur source réelle (audit exports),
      paginé tenant-scope. Tests `ExportHistoryTest`.
- [ ] T004 [P1] US2 `NotificationDispatcher::dispatch()` déclenche le push FCM via
      `PushNotificationService` (best-effort non bloquant). Test
      `NotificationPushDispatchTest` (mock push).
- [ ] T005 [P2] US2 Supprimer `routes/modules/notification.php` + `require` dans
      `routes/api.php`.
- [ ] T006 [P2] US2 Route dupliquée payment-documents : un chemin canonique
      (`/payments/{payrollRun}/documents`), vérifier les consommateurs
      (`mobile-workflow-contracts.json`) avant de garder/supprimer l'alias.

## Phase 2 — Web (US3 + US5)

- [ ] T007 [P1] US3 `/videos` : lecteur réel (`public/videos/product-demo.*`) ou état
      « Bientôt disponible » ; retirer IDs YouTube factices + thumbnails inexistants.
- [ ] T008 [P1] US3 `/mobile` : `androidHref`/`iosHref` →
      `/signup?source=download_<slug>_<platform>`.
- [ ] T009 [P1] US3 `/download` : CTA principal honnête (livrable réel ou
      « Être contacté »).
- [ ] T010 [P1] US3 Case studies : créer `case-studies/[slug]/page.tsx` (contenu depuis
      `content.ts`) ou pointer les cartes vers `/case-studies`.
- [ ] T011 [P1] US3 Contrats : `apiFetch('/contracts/${id}/generate-pdf')`.
- [ ] T012 [P1] US3 Checkout plan gratuit : traiter `response.ok`, afficher l'erreur,
      rediriger seulement en cas de succès réel ; bannière login alignée.
- [ ] T013 [P2] US5 Ancres `/docs#webhooks-security|security|mobile-install` →
      ids existants.
- [ ] T014 [P2] US5 Dead code web : supprimer helpers `forms.ts` morts
      (`/api/analytics/track`, `/api/csrf-token`), `lib/constants.ts` (ou réécrire
      routes réelles), `dynamic-imports.tsx`, `SkeletonLoader.tsx`, `DemoForm.tsx`,
      `ContactForm.tsx`, export dupliqué `HeroSection`, route `/api/downloads`.
- [ ] T015 [P2] US5 FAQ alignée sur `data/pricing.ts` (Starter 39 €, essai 30 jours).
- [ ] T016 [P2] US5 `OnboardingWizard` : chaîne mojibake corrigée ; complétion via
      endpoint dédié (au lieu de `PATCH /company/branding`) ; `onComplete()` dans le
      catch.

## Phase 3 — Mobile (US4 + US5)

- [ ] T017 [P1] US4 `MobileExperienceService` : aligner les routes du manifeste sur les
      routeurs GoRouter réels (HR : `/hr/employees`→`/team`, `/hr/team-overview`→
      `/organigramme`, `/invitations` branchée/retirée ; Manager : `/company/team-roles`,
      `/dashboard/admin` branchées/retirées) ; module sans écran = servi sans route ;
      garde CI manifeste→routeurs + `mobile-workflow-contracts.json` à jour.
- [ ] T018 [P2] US5 Voice IA : plus d'envoi `Uint8List(0)` — bouton désactivé avec état
      « bientôt disponible » ou entrée retirée.
- [ ] T019 [P2] US5 `/manager/dashboard` : retirer la route placeholder ou la brancher
      sur un écran réel.
- [ ] T020 [P3] US5 Petits : `onTap` noop retiré, TODO signature manifeste
      implémenté/documenté, route morte `/smart-attendance/background-permission` retirée.

## Convergence

- [ ] T021 Mettre à jour `CHANGELOG.md`, `AGENTS.md` (leçons), `.specify/memory/project-state.md`,
      cocher T001-T021 après merge.
