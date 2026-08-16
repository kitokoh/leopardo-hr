# Session QA — Audit 360° + implémentation (2026-08-16, matin)

**Agent**: Expert Software Engineer / Senior QA — `kitokoh`
**Périmètre**: Audit 360° (vitrine, admin-dashboard, API Laravel, mobile, CI),
consolidation des branches ouvertes, implémentation des findings d'audit.

## Phase 1 — Audit 360° & gap analysis

**14 issues spec-kit créées** (#4169 → #4182), toutes vérifiées sur le code ou
l'env live, format `## Constat` / `## Cause racine` / `## Fix attendu` /
`## Critères d'acceptation` + label `qa-audit-2026-08-16`.

| # | Surface | Sévérité | Sujet |
|---|---------|----------|-------|
| 4169 | API | P2 | Injection de formules CSV (OWASP) — `toCsv()` et 3 autres exports |
| 4170 | Admin | P1 | Routes tenant → 401 → session super-admin détruite (FleetView, ExportsView, VehicleDetailModal) |
| 4171 | API | P2 | Renderer DomainException — message brut en `localized_message` si code hors catalogue |
| 4172 | API | P3 | Kiosk web — punch authentifié par device_code seul (incohérent avec X-Kiosk-Token) |
| 4173 | Vitrine | P3 | `<html lang>` SSR ignore `?lang=` — crawlers reçoivent du HTML fr |
| 4174 | Vitrine | P3 | /branding — sélecteur de langue interne déconnecté du mécanisme global |
| 4175 | Vitrine | P3 | /docs servie 100 % FR (hors périmètre #3248) |
| 4176 | Vitrine | P3 | /careers — cartes d'offres cursor-pointer sans lien |
| 4177 | Vitrine | P3 | /offline sans metadata — canonical + hreflang vers la homepage |
| 4178 | Vitrine | P3 | /integrations — lien docs via variables serveur-only côté client |
| 4179 | Vitrine/API | P3 | GET /auth/google non documenté (vérifié : implémenté, drift #3885) → clos en duplicata |
| 4180 | API | P3 | Lien App Store placeholder `id000000000*` dans les e-mails d'invitation |
| 4181 | Admin | P3 | Token d'impersonation affiché en toast si échec clipboard |
| 4182 | Admin | P3 | Composants morts jamais importés (ApprovalWidget, KanbanBoard, ApplicantDetailModal) |

Constats live (prod v4.23.5, non nouveaux — déjà au backlog) : `/i18n/catalog`
500, `/supported-countries` 404, demo login INVALID_CREDENTIALS, prod figée
(#3767/#3545). La CI GitHub Actions était saturée toute la session (des
dizaines de runs queued, famine #3545 — le fix #4113 attend dans la file).

## Phase 2 — Consolidation des branches ouvertes

- **13 branches de PRs ouvertes synchronisées avec main** (merge main + push,
  zéro conflit la plupart du temps) : fix/4156, 4095, 4152, 3967, 4124, 4119,
  4087, 3545, 4103, 3957, 3949, 3600, 4091, docs/qa-expert17-session-v2.
- **Poller de merge v3** mis en place (1 appel API par cycle, merge quand
  `mergeable_state == clean`) — en attente de la file CI.
- **Doublons #4180** : #4234 et #4244 fermés avec commentaire de renvoi vers
  la PR canonique **#4227** (protocole anti-doublon AGENTS.md).

## Phase 3 — Implémentations (13 PRs ouvertes, issues audit)

| PR | Issue | Sujet | Validation |
|----|-------|-------|------------|
| #4224 | 4169 | `CsvCellSanitizer` partagé appliqué aux 4 exports | PHPStan strict OK, tests 4/4 |
| #4225 | 4170 | `_skipAuthRedirect` sur les 4 appels tenant admin + `downloadApiFile(options)` | node --check OK |
| #4226 | 4171 | Fallback `errors.SERVER_ERROR` + `report()` | PHPStan OK, tests feature 2/2 |
| #4227 | 4180 | Liens iOS env-driven (`LEOPARDO_IOS_APP_LINKS`), blade conditionnel | PHPStan strict OK, tests 4/4 |
| #4229 | 4182 | Suppression 3 composants morts | rg 0 référence |
| #4230 | 4177 | Metadata noindex sur /offline (layout serveur) | tsc OK |
| #4231 | 4176 | Cartes /careers → lien /contact locale-aware | tsc + eslint OK |
| #4232 | 4181 | Token d'impersonation masqué en cas d'échec clipboard | — |
| #4238 | 4173 | lang/dir SSR alignés sur `?lang=` + LocaleSync URL-first | tsc + eslint + garde i18n OK |
| #4239 | 4174 | /branding branché sur `useVitrineLocale()` | tsc + eslint OK |
| #4240 | 4175 | /docs localisée ×4 locales (data `docs-page.ts`, pattern guides) | tsc + eslint + garde i18n OK |
| #4246 | 4172 | Kiosk web : session gate + résolution compagnie (`PlatformCompanyLookup`) + bug latent `$kiosk->company` null | PHPStan strict OK, tests 2/2 |

Validation locale : PHP 8.4.24 + PostgreSQL (suite exécutée pour les tests
feature ciblés), Pint, PHPStan strict, tsc, eslint, garde i18n
`check-i18n-diff.js` (PA2-I18N-014) sur les branches web.

## Leçons

1. **`git stash` + changements de branches = perte de travail** : lors de la
   création de branches multiples, les modifications non commitées ont été
   stashées puis écrasées par `reset --hard`. Désormais : commit par branche
   immédiatement, ou une seule branche de travail.
2. **`Log::spy()` casse `Log::channel('audit')`** (retourne null → 500 dans le
   contrôleur). Pour tester un logger par canal, ne pas spy le facade entier.
3. **`Employee::create(['company_id' => ...])` abandonne silencieusement la
   clé** (non-fillable depuis #3677) — dans les tests, utiliser `forceCreate`.
4. **`AttendanceKiosk` n'a pas de relation `company()`** : `$kiosk->company`
   vaut toujours null → page web kiosk crashait (`$company->name`), search_path
   jamais posé. Résoudre via `PlatformCompanyLookup` (pattern API kiosk).
5. **La CI est la seule source de vérité backend** : la file GitHub Actions
   était saturée toute la session ; les merges ont dû attendre. Le fix de
   famine #3545 (PR #4113) est critique pour débloquer.
6. **Environnement local Sentry** : `SentryContextMiddleware::setTag(null)`
   fait 500 sur certains tests API en local (no-op en CI) — ne pas corriger le
   code applicatif pour ça, contourner dans le test (seed direct DB).
