# Plan — QA Audit Expert v3 2026-08-15

## Objectif

Livrer les 18 manquements nouveaux (F-V3-01→F-V3-18) identifiés par la campagne de test
experte v3 (2026-08-15), en respectant la Constitution (spec-first, anti-doublon #2400,
branches `fix/<issue>-<slug>`, `Closes #` dans le body, CHANGELOG, gates CI).

## Ordre d'implémentation (risque décroissant, valeur croissante)

1. **P1 build admin** (#3114) — DÉJÀ FAIT (PR #3123 : CommandPalette).
2. **US1 console admin** (guard requiresTenant, TrainingView catalogue) — petit diff front,
   débloque 3 vues + endpoints #2634. Risque : faible. 
3. **US2 policy approvals** (backend authz) — diff moyen, test de régression requis.
4. **US4 flutter analyze vert** (cycle providers ×3 apps, platform_admin directives,
   marketing 44 erreurs, manager DateTime?) — diff structurel sur les providers partagés ;
   à faire dans UNE branche pour les 3 apps (le fix est identique), puis les apps isolées.
5. **US3 FCM** — retrait/gating de fichiers + doc ; diff faible.
6. **US5 SSRF + RBAC rh.php** — backend, garde `NotPrivateUrl` + regroupement middleware ;
   risque : régression de flux employee → vérifier chaque route déplacée.
7. **US6 SEO vitrine** — canonical par page + suppression orphelins ; diff faible, build requis.
8. **US7 N+1 + SocialDeclaration** — perf + refactor ; le refactor SocialDeclaration peut
   être scindé en issue de dette si le risque est trop élevé en fin de vague.
9. **Hygiène** (TaxRates modal, routes mortes RH, baseline PHPStan).

## Architecture des changements

- **Admin** : `router/index.js` (meta), `views/training/TrainingView.vue`, `views/settings/TaxRatesView.vue`, suppression de 2 composables.
- **API** : `ApprovalController` (+policy), `CameraService` (+garde réseau), `routes/modules/rh.php` (middleware), `PaymentBatchController`, `FleetController`, `SocialDeclarationController` (+service optionnel), `docs/security/RBAC_ROUTE_MATRIX.md`.
- **Mobile** : `leopardo_core` (pas de changement), providers `core_providers.dart` + `auth_provider.dart` ×3 apps, `platform_admin_app.dart`, marketing (calendar/social/main), `attendance_repository.dart` (manager), suppression routes mortes `leopardo_hr/lib/app.dart`.
- **Vitrine** : `seo.ts` + 8 layouts landing, suppression 3 fichiers orphelins.

## Validation

- Admin : `npm run lint` + `npm run build` (0 erreur).
- Vitrine : `npm run lint` + `npm run build`.
- Mobile : `flutter analyze` 0 erreur × 6 apps (Flutter 3.47 stable).
- API : `pint --test`, phpstan diff, tests ciblés (`ApprovalAuthzTest`, `CameraRtspSecurityTest`), puis suite complète.
- Chaque PR : `Closes #N` dans le body + entrée CHANGELOG `## [Unreleased]`.

## Risques & mitigations

- **Collision avec la vague expert #2 parallèle** : re-vérifier branches/PRs/issues avant
  chaque création d'issue et chaque PR (protocole #2400).
- **Cycle de providers** : le fix doit préserver le comportement #2737 (sortie de session
  au 401) — le handler `onUnauthorized` doit rester câblé sans cycle (provider de callback).
- **rh.php `api.manager`** : certains endpoints sont consommés par l'app employee (ex.
  POST /attendance/{log} correction ?) — auditer les consommateurs avant de restreindre.
- **Marketing** : si l'app n'est pas distribuée (#2661), privilégier le retrait documenté
  plutôt qu'un refactor long.
