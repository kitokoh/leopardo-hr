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
