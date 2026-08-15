# QA Leopardo RH — Session Expert 6 (audit ciblé + merge campaign) du 2026-08-15

Mission (propriétaire) : tester la plateforme dans tous les sens, consigner chaque manquement
selon la méthode Spec Kit (issue + spec/plan/tasks), implémenter les manquements et le max
d'issues ouvertes, merger le max de branches (main VERT). Swarm multi-agents actif — protocole
anti-doublon #2400 appliqué sur chaque constat.

## Tests effectués (preuves)

- **Builds locaux** : vitrine Next.js ✅ (0 erreur), admin Vue ✅ (0 erreur) ; vitrine `lint` ✅, `tsc --noEmit` ✅, **305 tests jest ✅** ; admin `lint` : 4 warnings → corrigé (0).
- **Checkers repo** : env parity 271 clés ✅ ; orphelins interfaces 0 nouveau ✅ ; OpenAPI coverage 552/720 (0 drift nouveau) ✅ ; mojibake 0 ✅ ; **migrations tenant : collisions 000019/000020 constatées puis vérifiées déjà corrigées sur main (1e576375)** ; **garde manifeste mobile #2212 : rouge sur main depuis #3117 → déjà couvert #3205/PR #3209**.
- **Scans anti-régression** : 0 `dd/dump`, 0 `apiClient.dio`, 0 `withOpacity`, 0 `href="#"`, 0 `await runApp`.
- **Black-box staging/prod (Render + Vercel)** : API **v4.23.5 (stale)** — `/api-explorer` 500, `/api/v1/i18n/catalog/fr` 500, `/api/v1/supported-countries` 404, `/api/v1/demo-users` 404, `/api/v1/employees` → **302 HTML vers /login** (au lieu de 401 JSON), `/api/v1/hr/employees` → 401 JSON (correct), routes inconnues → 404 JSON (correct). Constats P1 déjà suivis : #2627/#2632/#2646.

## Constats & issues

| Constat | Issue | PR |
|---|---|---|
| Admin ESLint 4 warnings (catch + retry mort) | #3220 | #3228 |
| Collisions migrations tenant (déjà corrigé) | #3224 (close, preuve) | — |
| Script #2512 cassé (2 bugs) | #3225 | #3301 |
| Déploiements stale v4.23.5 (réaffirmé, preuves live) | #2627/#2632 | — |
| Drift manifeste mobile (vérifié, non dupliqué) | #3205 | #3209 |

## Cohérence post-merge (#2512)

Le script `check-issues-left-open-by-merged-prs.sh` (réparé en #3301) liste 7 issues
référencées par PRs mergées restées ouvertes (#2597, #2605, #2627, #2632, #3111, #3158,
#3163). **Vérification code individuelle** : #2597 (placeholders AI Voice toujours présents),
#2605 (page /about en français codé en dur), #2627/#2632 (API toujours v4.23.5), #3111
(issue liée à #2789 toujours ouverte), #3158 (baseline PHPStan non régénérée — vérif CI),
#3163 (tarifs toujours 29/79 en vitrine) → **aucune fermeture abusive** ; rapport transmis.

## Merge campaign

- **59 runs orphelins/supersédés annulés** (cancel-orphan-runs.sh, outil officiel #2413) pour désengorger la file (saturation #2488).
- Boucle de merge des PRs aux 5 checks requis verts (en cours au fil de la file).

## État final

- main : merges continus du swarm ; garde migrations ✅, garde env ✅, OpenAPI ✅.
- 2 PRs d'implémentation ouvertes par cette session : #3228 (admin lint), #3301 (tooling).
- Token propriétaire : à révoquer en fin de session (jamais persisté hors env).
