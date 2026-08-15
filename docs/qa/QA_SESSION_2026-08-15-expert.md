# QA Leopardo RH — Session expert du 2026-08-15

Mission (propriétaire) : tester la plateforme dans tous les sens — vitrine, web app, admin,
mobiles, workflows, API, logiques, onboarding, cohérence — consigner chaque manquement selon la
méthode Spec Kit (issue + spec/plan/tasks), puis implémenter les correctifs.

**Contexte** : le swarm d'agents avait déjà créé ~180 issues ouvertes (vagues QA 2026-08-14/15).
Règle anti-doublon (#2400) appliquée : chaque finding vérifié contre les issues ET branches
existantes avant création. Ce rapport couvre les manquements **nouveaux** (non couverts) + le
bilan des surfaces testées.

## Méthode
1. Revue statique : contrats API↔routes (checker Python), ancres/liens/mojibake vitrine,
   vues/boutons/mock admin, patterns interdits mobile, cohérence docs.
2. Exécution locale : PHP 8.4 + PostgreSQL 16 + Redis (composer install, 142 migrations),
   suite de tests complète, PHPStan strict, Pint, builds Next.js + Vite + lints.
3. Black-box prod : API Render (gestionemployerbackend.onrender.com), admin
   (leo-admin.pages.dev), vitrine (gestionemployer-backend.vercel.app) — crawl, formulaires,
   onboarding guidé, erreurs, médias, sitemap.

## Findings NOUVEAUX (issues créées dans cette session)

| Issue | Sév. | Surface | Sujet | Fix implémenté | PR |
|---|---|---|---|---|---|
| #2829 | P1 | Web | Médias Git LFS servis comme pointeurs en prod (5 assets : screenshots home + vidéo démo + poster) | Binaires réels commités, sortis du filtre LFS | #2868 |
| #2830 | P1 | Backend | 8 tests rouges — drift tests ↔ moteur (SnPayrollFixtures CSS 3 % vs 7 %, use manquant ×2, TG/GA, NotificationDispatcher) | 10 fichiers réalignés sur le moteur (vérifié à la main) | #2869 |
| #2831 | P2 | Backend | PayrollTenantIsolationTest cross-tenant tax-slab attend 403, reçoit 404 (Constitution §II = 404 anti-énumération) | assertNotFound ×2 + commentaire | #2869 |
| #2832 | P3 | Web | /docs : 3 ancres mortes résiduelles + 3 ids orphelins | TOC/sections réalignés (0 mort, 0 orphelin) | #2870 |
| #2833 | P3 | Mobile | apiClient.dio.options (pattern interdit) dans 3 user_auth_repository | Suppression (redondant : intercepteur gère Accept-Language) | #2872 |

Spec Kit : `.specify/features/qa-expert-2026-08-15/{spec,plan,tasks}.md`.

## Bilan des surfaces testées (findings déjà couverts par le swarm — non dupliqués)

### Vitrine (front/web) — live `gestionemployer-backend.vercel.app`
- ✅ Fonctionnelle : /, /pricing, /docs, /signup (parcours guidé OTP complet testé bout en bout),
  /demo, /contact, /download, /checkout, /faq, /guides, /employes, /documents, /comptabilite…
- ✅ Formulaires : signup/demo/contact → 200/201 avec lead id ; validation FR champs requis OK
- ⚠️ Déjà couverts : sitemap /blog 404 (#2647), plans incohérents (#2649), SignupForm FR-only (#2648/#2727),
  og:images absentes (#2722/#2752), icon-192 manquant (#2724/#2756), témoignages fabriqués (#2726),
  essai 14 vs 30 j (#2753), i18n FR-only (#2642/#2605/#2657), SEO/canonicals (#2656/#2607)
- ⚠️ Déploiements prod périmés : API v4.23.5 (main 4.24+) → /i18n/catalog/* 500, /health non routé
  (#2627/#2632/#2654)

### Admin (front/admin-dashboard) — live `leo-admin.pages.dev`
- ✅ Login + modale démo fonctionnels (erreur propre si démo désactivée)
- ⚠️ Déjà couverts : bouton Acces Demo inutilisable en prod (#2646/#2730), EditUserModal factice
  (#2610/#2641), composants orphelins (#2612/#2658), command palette (#2640/#2703), pagination
  users (#2698), UserTable bouton Éditer mort (#2697), enveloppe {data:[]} dashboard (#2747)

### Mobiles (Flutter ×5 + core)
- ✅ Patterns : requestWithRetry + extractDataList OK, withOpacity 0, casts directs 0, runApp OK,
  manifest routes OK (check-mobile-manifest-routes vert)
- ⚠️ Déjà couverts : mojibake (#2660/#2738), i18n hardcodée (#2740/#2755), double auth (#2739),
  cabinet navigation (#2735/#2748), devise DZD (#2741), leopardo_marketing orphelin (#2661)

### API backend (Laravel) — tests locaux + black-box
- ✅ 1917 tests : 1909 passent (hors 8 drift corrigés par #2830/#2831) ; openapi coverage 0 drift
  nouveau ; ZKTeco sécurisé (#2216 OK) ; login 401 propre ; onboarding invitation 404 propre ;
  rate limiting présent
- ⚠️ Déjà couverts : suspended login (#2618/#2630), register orphelin (#2617/#2636), OAuth state
  (#2619), webhooks fail-open (#2614/#2615/#2616), OpenAPI drift (#2662/#2675), middleware tenant
  /ai + /growth (#2622/#2635), Préavis jours (#2671), races solde/pointage (#2669/#2676)…

### CI / Ops
- ⚠️ File GitHub Actions saturée (runs pending, guard branch-protection rouge) — #2488/#2131
  (déjà couvert)
- ⚠️ PHPStan Strict : ~90 erreurs résiduelles majoritairement dans les tests (le swarm fixe en
  continu — #2587/#2594/#2770) ; 3 erreurs app mineures constatées (PlatformCompanyController
  unreachable, nullsafe Carbon ×2)

## Notes d'implémentation
- Les 5 correctifs sont de petits changements ciblés (tests, TOC, .gitattributes, suppression de
  lignes redondantes) — zéro changement de comportement applicatif, alignement sur l'existant
  documenté (Constitution, SN_COMPLIANCE.md, #2473/#2578/#2219).
- PRs : body avec `Closes #N`, CHANGELOG sous `## [Unreleased]`, garde anti-doublon vérifiée
  (aucune branche/PR existante sur ces issues au moment de la création).
