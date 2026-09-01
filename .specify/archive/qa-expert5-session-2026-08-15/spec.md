# Feature Specification: QA Expert 5 — Session 2026-08-15 (test exhaustif + formalisation + merge)

**Feature Branch**: `docs/qa-expert5-session-2026-08-15`

**Created**: 2026-08-15

**Status**: En cours

**Input**: Mission propriétaire — « tester l'app dans tous les sens (vitrine, web, admin, mobiles, workflows, API, logiques, onboarding, cohérence) ; tout manquement → spec/task/issue selon la méthode spec kit ; implémenter les manquements + le max d'issues ouvertes ; merger le max de branches ; main VERT ».

## User Stories

### US1 — Fiabiliser les tests backend (P1)
Tout correctif backend doit être validé localement (PHP 8.4 + PostgreSQL + Redis) avant merge pour protéger `main` vert (le swarm mergeait sur checks pending — un test rouge est passé sur main, #3324).

**Acceptance**:
1. Given un test unitaire rouge sur main, When on le corrige sur branche dédiée, Then la suite locale passe et le PR ferme l'issue (`Closes #N`).
2. Given un PR backend, When ses 5 checks requis sont verts, Then il peut être mergé.

### US2 — Tester les surfaces en conditions réelles (P1)
Tester les surfaces déployées (vitrine Vercel, console admin Cloudflare Pages, API Render) et documenter chaque manquement en issue GitHub `[QA][P#]`.

**Acceptance**: chaque constat vérifié (live ou code avec fichier:ligne) donne une issue + une entrée findings-registry.

### US3 — Campagne de merge avec main vert (P1)
Merger le maximum de PRs ouvertes en s'assurant que les 5 checks requis (Backend Coverage, PHPStan Strict, Module Structure, ESLint+TS, actionlint) sont verts, et que `main` reste vert.

**Acceptance**: `main` vert en fin de session ; les PRs mergées ferment leurs issues (`Closes #`).

## Constats (résumé)

### Live (prod/staging)
| ID | Sévérité | Constat |
|----|----------|---------|
| L-01 | P1 | `/api-explorer` → 500 en prod Render (fix mergé non déployé — staging stale v4.23.5) |
| L-02 | P1 | Guided-trial : `provisioning_token` absent de la réponse prod → `/trial/status` impossible (stale) |
| L-03 | P2 | `/contact` affiche le littéral `{copy.info.responseTime}` (FR) → issue #3352, fix #3357 |
| L-04 | P3 | `/blog` → 404 (sitemap le publie) — couvert par le swarm (web-blog-sitemap) |
| L-05 | P3 | `leopardo-rh.com` NXDOMAIN — canonicals/sitemap sur domaine mort (couvert #3190/#3193) |

### Statique (4 audits parallèles — rapports complets dans `docs/qa/audit-expert5-2026-08-15/`)
| Surface | Constats majeurs |
|---------|------------------|
| Web vitrine | canonical homepage ×6, métriques fabriquées, études de cas fictives, pricing incohérent (Enterprise 3 représentations, surcoût absent du checkout), SW précache routes auth, 7 pages FR-only |
| Admin Vue | recherche header cassée (useRouter hors setup), CSV sans anti-formule ×2, composables orphelins ×3, read-all 405, inbox super-admin vide |
| Mobile Flutter | navigation manager vers routes non enregistrées (/team /tasks /me/monthly), onboarding HR int/String, DateTime.parse non gardés, FCM placeholders, marketing sans auth, deep-linking absent |
| API Laravel | IDOR leave-balances, OAuth tenantless, policy approbation morte, SSRF rtsp (fixé #3147 — test corrigé #3324), clé QR fail-open, races bulk-pay/payout, exceptions brutes |

> La majorité des constats est déjà tracée en issues par le swarm (vagues expert2/expert3/expert4). Cette spec couvre les constats propres de la session expert 5 : #3324 (test SSRF rouge sur main) et #3352 (placeholder FR /contact).
