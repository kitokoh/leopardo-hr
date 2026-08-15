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

- **179 runs orphelins/supersédés annulés** au total (2 passes de cancel-orphan-runs.sh, outil officiel #2413) pour désengorger la file (saturation #2488 : 0 runner disponible, runs queued en boucle).
- Boucle de merge automatique en arrière-plan : merge des PRs aux 5 checks requis verts (en cours au fil de la file).

## Implémentation (issues fermées par la session)

| Issue | Correctif | PR |
|---|---|---|
| #3220 [P2] Admin | ESLint vert — catch bindings + retry mort supprimés | #3228 |
| #3225 [P3] Tooling | check-issues-left-open-by-merged-prs.sh réparé (3 bugs) | #3301 |
| #3326 [P2] Web | Checkout — fallback plan sûr 'free' (page blanche) | #3371 |
| #3331 [P3] Web | /offline — lien mort leopardo.local → repo GitHub | #3397 |
| #3332 [P3] Web | Sitemap — /share POST-only retirée | #3399 |
| #3321 [P3] API | per_page borné à 100 sur 8 endpoints + test | #3418 |
| #3340 [P3] Admin | Export CSV congés — anti-injection de formule | #3426 |
| #3224 [P2] API | Collisions migrations (déjà corrigé sur main) | fermée, preuve |
| #3323 [P3] API | OpenAPI /public-holidays (faux positif : routes tenant existent) | fermée, preuve |

## État final

- main : merges continus du swarm (30+ PRs mergées sur la fenêtre) ; gardes repo verts (migrations, env, OpenAPI).
- **11 PRs créées par cette session** : #3228 (lint admin), #3301 (tool script), #3351 (docs session), #3371 (checkout fallback), #3397 (offline — **mergée**), #3399 (sitemap /share — fermée comme doublon de #3355, fix déjà sur main), #3418 (per_page ×8), #3426 (CSV injection), #3451 (essai 14j TR), #3461 (CHANGELOG dédupliqué 1658→1172 lignes), #3469 (SEO case-studies), #3481 (per_page ×11 supplémentaires).
- **5 issues fermées avec preuve code** : #3224 (migrations déjà corrigées), #3323 (faux positif OpenAPI), #3332 (sitemap /share via #3355), #3331 (via #3397), #3252 (via #3355).
- Merge campaign : 179 runs orphelins annulés ; boucle de merge active.
- Token propriétaire : à révoquer en fin de session (jamais persisté hors env).

---

# Addendum — Vague constats expert 6 (issues #3427–#3437) — même session

## Constats NOUVEAUX (vérifiés anti-doublon sur les 210 issues ouvertes)

| Issue | Surface | Sev | Constat | Résolution |
|---|---|---|---|---|
| **#3427** | api | **P1** | Bootstrapping Edge node par tout employé → edge_token + pull PII/biométrie (face_encoding, biometric_id) | **PR #3444 MERGED** |
| **#3428** | api | P2 | CameraPermission : employee_id cible non scoped tenant (FK cross-tenant) | **PR #3447 MERGED** |
| **#3429** | api | P3 | SalaryAdvance markPaid TOCTOU → double ledger + double document | **PR #3450 MERGED** |
| **#3430** | api | P3 | Onboarding complete/skip par tout employé (conflit test T118) | Arbitrage demandé (commentaire #3430) |
| **#3431** | mobile | P2 | Statut avance `disputed` sans libellé mobile | Corrigé main — fermée avec preuve |
| **#3432** | mobile | P2 | Contract.fromJson start_date nullable → TypeError | Corrigé main — fermée |
| **#3433** | mobile | P3 | DateTime.parse résiduels core | Corrigé main — fermée |
| **#3434** | web | P2 | FAQ TR « 30 tam gün » | Fix déjà main — PR fermée anti-doublon |
| **#3435** | web | P2 | /case-studies/[slug] sans metadata ni sitemap | PR #3483 (swarm) |
| **#3436** | admin | P3 | CSV PayrollView sans anti-injection | Fix déjà main — PR fermée |
| **#3437** | admin | P3 | Libellés features bruts | **PR #3465 MERGED** |

## Implémentation depuis le backlog (issues existantes)

| Issue | Fix | PR |
|---|---|---|
| #3487 | Meta /pricing localisée via ?lang= | #3526 (CI) |
| #3485 | SSO vendu inclus mais coming_soon → « bientôt disponible » ×4 | **#3547 MERGED** |
| #3497 | Throttles callbacks SSO publics (XML non authentifié) | #3556 (CI) |
| #3488 | MiniCaseStudies chiffres fictifs → disclaimer ×4 | **#3558 MERGED** |
| #3523 | Proxy /api/v1 sans try/catch → 502 JSON + timeout 15 s | #3569 (CI) |
| #3520/#3521 | Credentials démo en clair → variables + échec explicite | #3572 (CI) |
| #3565 | sync_models_example.dart (12 print) → supprimé | #3575 (CI) |
| #3496 | trial_days Enterprise 30 vs 14 | Fermée avec preuve (déjà #3516) |

## Bilan

- 9 PRs mergées (#3209, #3444, #3447, #3450, #3465, #3547, #3558 + swarm), 4 fermées anti-doublon avec renvoi, 5 en file CI.
- Garde manifeste mobile réparée (#3209) ; OpenAPI 0 drift ; builds web/admin verts.
- Points d'arbitrage : #3430 (onboarding), essai 14 vs 30 j (verrouiller via config billing.trial_days), #3452 (vitrine NXDOMAIN).
