# Findings Registry — QA Expert 6 (2026-08-15)

## Nouveaux constats

| ID | Surface | Sévérité | Constat | Statut |
|---|---|---|---|---|
| F6-01 | Admin | P2 | ESLint 4 warnings no-unused-vars (EditUserModal ×3 catch, UserDetailView retry) | #3220 → PR #3228 |
| F6-03 | Tooling | P3 | check-issues-left-open-by-merged-prs.sh : set non sérialisable + guillemets simples | #3225 → PR #3301 |
| F6-04 | Ops | P1 | API staging/prod stale v4.23.5 : api-explorer 500, i18n catalog 500, supported-countries 404, demo-users 404, /employees 302 HTML | déjà #2627/#2632 (réaffirmé, preuves live) |

## Constats déjà couverts (vérifiés, non dupliqués)

| ID | Constat | Couverture |
|---|---|---|
| F6-02 | Collisions migrations tenant 000019/000020 | Corrigé sur main (1e576375) → #3224 fermée |
| F6-05 | Drift garde manifeste mobile #2212 (11 routes manager) | #3205, PR #3209 |
| F6-06 | Issues #2597/#2605/#3111/#3158/#3163 non résolues malgré PRs mergées | Vérifié code — restent ouvertes (pas de fausse fermeture) |

## Tests effectués (preuves)

- Vitrine : `npm run build` OK, `npm run lint` OK, `tsc --noEmit` OK, 305 tests jest OK.
- Admin : `npm run build` OK, `npm run lint` → 4 warnings avant fix, 0 après.
- Checkers repo : env parity 271 clés OK, orphelins interfaces 0 nouveau, OpenAPI 0 drift,
  mojibake 0, migrations collisions → corrigé, manifest mobile → rouge (couvert #3209).
- Black-box : voir spec US4 (endpoints staging).
- Scans anti-régression : 0 dd/dump, 0 apiClient.dio, 0 withOpacity, 0 href="#", 0 await runApp.

---

# Session 2 (suite) — Audit global 360° 2026-08-15 après-midi

## Nouveaux constats

| ID | Surface | Sévérité | Constat | Issue | PR |
|---|---|---|---|---|---|
| F6-07 | Admin | P1 | Route morte `/users/:id` → `UserDetailView.vue` (vue supprimée en 17541e5c, réintroduite par un merge de conflit) — `vite build` admin cassé sur main | #3280 (rouverte par régression) | #3711 (mergé) |
| F6-08 | Admin | P1 | `AnalyticsView.vue` : `localeStore.current` utilisé sans `const localeStore = useLocaleStore()` (mergé via #3700) — ReferenceError runtime | — | #3711 (mergé) |
| F6-09 | API | P1 | Collisions préfixes migrations 2026-08-15 : public 000004/000006 ×2, tenant 000001 ×2 + doublon strict public 000007 (copie de 000004) — garde #1962 rouge | #1962 (relancée) | #3712 (mergé) |
| F6-10 | Ops | P1 | Prod API v4.23.5 stale : queue driver `sync`, `/api-explorer` 500, `/demo-users` 404 — confirmé live (déjà #2627/#2632/#3259/#3452/#3562) | réaffirmé | — |
| F6-11 | CI | P2 | Gardes hygiène dev-hub (app-version-sync, env-example-parity) jamais câblées en CI | #3708 (déjà couvert par PR #3713) | #3713 |

## Implémentations de cette session

| ID | Issue | PR | Statut |
|---|---|---|---|
| T-3284 | #3284 — 9 routes GoRouter mortes app HR | #3715 | ouverte |
| T-3149 | #3149 — SocialDeclarationService (556→503 l.) | #3720 | ouverte |

## Preuves de tests

- `vite build` admin : exit 0 après retrait route morte + déclaration localeStore.
- `npm run lint` admin : 0 erreur.
- `check-migration-basename-collisions.sh` : ✅ après renumérotation.
- `check-app-version-sync.sh` / `check-env-example-parity.sh` : ✅ sur main.
- Vitrine `next build` : exit 0 (73 pages SSG).
- OpenAPI : 0 nouveau drift (121 gaps, tous en allowlist).
- Anti-régression : 0 `href="#"`, 0 `leopardo.local`, 0 résidu « 30 jours », 0 `DZD` hardcodé.
