# Feature Specification: Session QA Expert 8 — Audit Multi-Surface 2026-08-15

**Feature Branch**: `qa-expert8-session-2026-08-15`
**Created**: 2026-08-15
**Status**: Draft

**Input**: Mission propriétaire — tester la plateforme dans tous les sens (vitrine, web, admin, mobiles, workflows, API, logiques, onboarding, cohérence), consigner chaque manquement selon la méthode Spec Kit (issue + spec/plan/tasks), implémenter en fin de test, merger le max de branches, main vert.

## Contexte

Session experte du repo kitokoh/leopardo-hr (2026-08-15). Le swarm d'agents a déjà créé ~210 issues ouvertes et ~60 PRs en vol pendant la session ; la règle anti-doublon (#2400) a été appliquée sur chaque constat (issues + branches + PRs vérifiées). Cette spec couvre **uniquement les manquements vérifiés localement et NON couverts** au moment de la rédaction.

## Validation effectuée (preuves)

- [x] **Suite backend locale** : PHP 8.4 + PostgreSQL 16 + Redis — tests Unit 497 passed / 4 skipped ; Feature en cours de complétion.
- [x] **PHPStan Strict level 8** : 15 erreurs hors baseline sur main (GenerateBankExportJob 3e arg, clés dupliquées PayrollCalculator, fixtures caméras non typées, TrialWelcomeMail ternaires) — PRs de fix en vol (#3415/#3398/#3455).
- [x] **Builds locaux** : vitrine Next.js ✅ (0 erreur) ; admin Vue ✅ (0 erreur).
- [x] **Checkers repo** : migrations 0 collision (après fix swarm) ; parité .env.example 272 clés OK ; 0 controller orphelin ; 20 interfaces orphelines toutes allowlistées ; catalogue pays OK ; **check-mobile-manifest-routes ÉCHEC** (11 routes manager manquantes — couvert #3205, PR #3209 en vol).
- [x] **Black-box prod** : API v4.23.5 (stale, couvert #2627/#2812) ; vitrine : /blog 404 malgré 50 URLs blog au sitemap (couvert #2906/#2813), médias LFS servis comme pointeurs (fix #2868 en code, prod stale), /share 405 (#3252), /pricing + /checkout noindex au sitemap (NOUVEAU #3486) ; admin pages.dev 200.
- [x] **Audits statiques par surface** (4 scouts) : vitrine 16 constats nouveaux / 14 couverts ; admin 17 nouveaux / 9 sains ; API 5 nouveaux / 15 couverts ; mobile 12 nouveaux / 15 couverts.

## Findings NOUVEAUX (issues créées #3485-#3500)

### F1 [P2][web] SSO SAML/OIDC vendu comme inclus Enterprise mais coming_soon sur /integrations (#3485)
### F2 [P3][web] Sitemap publie /signup + /checkout en noindex — contradiction sitemap/robots (#3486)
### F3 [P2][web] seo.ts force t('fr', seo.pricing.description) — meta FR sur EN/TR/AR malgré traductions (#3487)
### F4 [P2][web] MiniCaseStudies « Real results » — cas fictifs avec chiffres non sourcés (#3488)
### F5 [P3][web] 4 pages vitrine 100% FR non listées par #3248 : /branding /careers /mobile /testimonials (#3489)
### F6 [P3][admin] EditUserModal orphelin + emit edit mort + 3 clés i18n avatar absentes (#3490)
### F7 [P3][admin] États d'erreur silencieux : TrainingView/PredictionsView/ReportsView/TaxRatesView/CompaniesView (#3491)
### F8 [P3][admin] LeavesView + PayrollView actions sans retour utilisateur (#3492)
### F9 [P3][admin] GrowthDashboardView : confirm() natif + NaN% sur commission (#3493)
### F10 [P3][admin] WebhooksView confirm FR + ReportsView KPIs overtime/payroll jamais chargés (#3494)
### F11 [P3][admin] UsersView placeholders bruts + modale impersonation style plat (#3495)
### F12 [P3][api] PlanSeeder Enterprise trial_days=30 vs 14 (résiduel #3164) (#3496)
### F13 [P2][api] Throttles absents sur callbacks SSO SAML/OIDC publics (résiduel #3000 fermé à tort) (#3497)
### F14 [P3][mobile] Employee — 4 routes GoRouter mortes (/contracts /training /expenses /ai-voice) (#3498)
### F15 [P3][mobile] Sentry tracesSampleRate=1.0 dans hr + manager — politique #2766 (0.2) (#3499)
### F16 [P3][mobile] Marketing await avant runApp + smart_attendance cast direct + code mort core (#3500)

## Décisions consignées

- **D1** : durée d'essai = 14 jours (décision propriétaire 594c68f2, PRs #2944/#3135/#3396) — les résidus 30j (PlanSeeder Enterprise #3496, FAQ TR #3434 couvert) sont des bugs à corriger, pas des arbitrages.
- **D2** : les issues fermées sans fix effectif sur main (#3000, #3148, #3164) sont re-ouvertes via constats nouveaux (#3497, #3496) avec preuve code.

## User Stories & Testing

### US1 — La vitrine vend ce qu'elle livre (P1/P2)
Un visiteur EN/TR/AR voit des prix, features et métriques cohérents avec la réalité produit (SSO non vendu si coming_soon ; pas de chiffres fabriqués ; meta description dans sa langue).

**Independent Test** : crawl des pages /pricing /checkout /about /testimonials /integrations — absence de features coming_soon vendues, absence de métriques non sourcées, meta description localisée.

### US2 — L'admin ne ment pas à l'utilisateur (P2/P3)
Chaque action admin (approuver, calculer, exporter, charger) retourne un état succès/erreur visible ; aucun placeholder brut ; aucun composant mort.

**Independent Test** : navigation des 36 routes admin — aucune vue ne laisse un échec silencieux ; `rg` confirme la suppression des orphelins.

### US3 — L'API est bornée et honnête (P2/P3)
Throttles sur les callbacks publics SSO ; trial_days uniforme 14 ; pagination bornée.

**Independent Test** : curl POST /sso/saml/{companyId}/callback ×11 → 429 ; PlanSeeder → trial_days=14.

### US4 — Le mobile est propre (P3)
Pas de route morte navigable, pas de tracesSampleRate hors politique, pas d'await bloquant avant runApp.

**Independent Test** : `rg "tracesSampleRate"` → ≤0.2 ; `rg "await initializeDateFormatting"` hors StartupGate → 0 ; routes mortes retirées.
