# Implementation Plan: Vague QA Expert 5 — 2026-08-15

**Branches**: `fix/<issue>-<slug>` (une par issue, protocole #2400) | **Date**: 2026-08-15
**Spec**: `.specify/features/qa-expert5-2026-08-15/spec.md`

## Summary

41 manquements nouveaux vérifiés (8 API, 11 web, 8 admin, 7 mobile, 7 cohérence/tooling) → 41 issues
#3363-#3416. Implémentation par lots de surface, une PR par issue avec `Closes #N`, CHANGELOG sous
`[Unreleased]`, checks CI verts avant merge. Campagne merge parallèle sur les ~45 PRs ouvertes du
swarm : résolution des conflits, attente des checks, merge en cascade, garde de non-régression
(AGENTS.md : vérifier qu'une vieille branche n'écrase pas un fix plus récent).

## Technical Context

- **Backend**: Laravel 12 (PHP 8.3/8.4, PostgreSQL 16 shared `search_path`), DDD modulaire
  `api/app/Modules/{Module}/{Application,Domain,Infrastructure,Interfaces}`.
- **Auth/tenancy**: `public.user_lookups` (schema_name/company_id/employee_id) + `SET search_path`
  via `TenantManager`/`AuthService` — pattern canonique à réutiliser pour password reset.
- **Kiosk**: `KioskController` + `OnboardingQrService` (jetons signés `base64url(payload).signature`).
- **Admin**: Vue 3 + Vite (runtime-only build — pas de template string), routes `/admin/*` avec
  auth `super_admin_api` ; normalizeApiPath ne touche que `/v1/`.
- **Vitrine/dashboard**: Next.js (App Router), `useVitrineLocale` FR/EN/TR/AR, gating
  `client-features.ts`, middleware racine (13 prefixes protégés).
- **Mobile**: Flutter, `go_router`, convention `requestWithRetry` + `extractDataMap/List`.
- **CI**: GitHub Actions = source de vérité ; checks requis sur main : Backend Coverage,
  PHPStan Strict (level 8), Module Structure Validator, Frontend ESLint+TS, actionlint.
  Vercel check externe non bloquant (rate-limited).
- **Sandbox**: PHP 8.3 + PostgreSQL 14 + Redis installés localement (composer install, migrations,
  tests ciblés) ; pas de Flutter SDK → mobile validé par CI + gardes statiques.

## Constitution Check

- Spec-first ✓ (ce document + issues). Auto-assignation issue ✓. Marker branch ✓. Une PR par issue ✓.
- Multi-tenant : les fixes API préservent l'isolation (search_path, scopes company_id).
- Qualité : PHPStan strict vert, lint admin/web verts, gardes du repo verts.

## Lot API (issues #3363-#3370)

| Issue | Fix |
|---|---|
| #3363 P1 password reset | Résolution via `public.user_lookups` + `setTenantSearchPath` (pattern AuthService) avant forgot/reset ; test tenant à schéma ; fusion des 2 classes/test PasswordResetMail (#3370) |
| #3364 P2 register | `RegisterAction` : résoudre l'invitation → `setTenant` → MAJ employé existant (chemin OnboardingController::activate) ou fermer l'endpoint ; aligner le client mobile |
| #3365 P2 QR punch | Parser `base64url(payload).signature`, vérifier signature+expiration, résoudre par `employee_id` scopé kiosque ; rejeter payloads non signés |
| #3366 P3 rate limiter | Supprimer la double registration `trial-status` (garder clé `token\|IP`) |
| #3367 P3 kiosk-punch | `throttle:kiosk-punch` sur le groupe kiosque (integrations.php + rh.php) |
| #3368 P3 search_path kiosk | try/finally restore autour de `setTenantSearchPath` dans les 6 handlers |
| #3369 P3 syncTrips | Index unique `(company_id, traccar_trip_id)` + `insertOrIgnore` + bornage from/to (31 j) |

## Lot Web (issues #3372-#3382, #3410, #3416)

| Issue | Fix |
|---|---|
| #3372 checkout surcoût | Afficher priceNote/surcoût dans PlanSummaryCard + résumé paiement ; aligner sièges inclus |
| #3373 CTA pilote | Home CTA → `/signup?source=...` (alignement /pricing) |
| #3374 Enterprise | Retirer enterprise de PLAN_CONFIG/checkout → redirection `/contact?topic=enterprise` |
| #3375 robots | Disallow les 13 prefixes racine (miroir middleware) |
| #3376 sitemap | Gater /blog sur enableBlog ; retirer /share + /offline |
| #3377 checkout FR-only | Catalogues vitrine pour checkout/success (erreurs + validation + labels paiement) |
| #3378 dashboard FR-only | Étendre `src/lib/i18n.ts` (billing/reports/employees/...) + `getCopy(locale)` |
| #3379 gating fail-open | Défaut `'locked'` ; preuve positive pour available ; capabilities arrays dans valueFor |
| #3380 upgrade manual | Retirer les boutons manual ou router via checkout |
| #3381 footer mort | Ajouter /about + /videos aux 4 catalogues section 0 |
| #3382 carrières FR-only | Catalogues vitrine section careers + useVitrineLocale |
| #3410 versions fantômes | Régénérer changelog-public.ts depuis CHANGELOG.md |

## Lot Admin (issues #3388-#3395)

| Issue | Fix |
|---|---|
| #3388 OAuth template | Extraire `OAuthProviderCard` en SFC `.vue` |
| #3389 webhooks | GET /admin/webhooks/events pour les checkboxes ; mapper is_active ↔ active |
| #3390 chat 501 | Désactiver le composer + avis « chat IA plateforme indisponible » |
| #3391 read-all | `api.put('/v1/notifications/read-all')` |
| #3392 websocket URL | Injecter VITE_WEBSOCKET_URL (ou défaut wss origin API) ; aligner .env.example |
| #3393 raccourci Alt+R | Retirer la ligne obsolète du modal |
| #3394 growth dead code | Retirer l'affectation morte ; consommer commissions ou alléger la requête |
| #3395 exports catch | try/catch → état historyError + retry |

## Lot Mobile (issues #3400-#3406)

| Issue | Fix |
|---|---|
| #3400 manager routes + garde | Ajouter GoRoutes /tasks /team /me/monthly au manager (port HR) + aligner manifeste → garde verte |
| #3401 read-all verbes | Aligner hr/manager notification_repository sur PATCH/POST (dans le PR #3167 ou cohérent avec lui) |
| #3402 DateTime HR | `DateTime.tryParse` + nullable (miroir manager #3157) |
| #3403 ai_voice retries | `maxRetriesOverride: 0` sur transcribe/synthesize |
| #3404 route orpheline | Retirer /me/monthly employee ou la câbler |
| #3405 fr_FR dates | Locale dérivée de Localizations/preferredLanguage |
| #3406 casts directs | extractDataMap/List aux 8 sites |

## Lot Cohérence (issues #3409-#3414)

| Issue | Fix |
|---|---|
| #3409 CHANGELOG dup | Supprimer lignes 1207-1656 ; fusionner 13 lignes uniques ; dédup 4.22.x |
| #3411 matrix orphelins | Déplacer lignes 140-144 dans la table principale |
| #3412 RBAC dup | Fusionner la famille Payroll engine |
| #3413 refs PLAN_ACTION2 | Pointer vers docs/archive/PLAN_ACTION2/ ou retirer |
| #3414 allowlist morte | Retirer POST approve/reject |
| #3416 web-offline env | Ajouter .env.example |

## Validation

- `cd api && composer install && php artisan migrate:fresh --seed` (PostgreSQL local) + tests ciblés
  (PasswordReset tenant schéma, kiosk QR, syncTrips, rate limiters).
- `python3 dev-hub/tools/check-openapi-route-coverage.py` ; `bash dev-hub/tools/check-mobile-manifest-routes.sh`.
- `npm run lint && npm run build` (front/web, front/admin-dashboard).
- CI PR : checks requis verts avant merge (`gh pr checks`), puis merge `--merge --delete-branch`.
- Après merge : `gh run list --branch main` vert ; CHANGELOG à jour ; AGENTS.md leçons si nécessaire.
