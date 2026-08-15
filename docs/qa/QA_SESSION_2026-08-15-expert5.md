# QA Leopardo HR — Session Expert 5 du 2026-08-15

Mission : tester la plateforme dans tous les sens (vitrine, web, admin, mobiles,
workflows, API, logiques, onboarding, cohérence), formaliser chaque manquement
selon la méthode Spec Kit (issue + spec/plan/tasks), implémenter les manquements
et le max d'issues ouvertes, merger le max de branches, `main` VERT.

## Contexte

- Le swarm QA (agents/expert2/expert3/expert4) a déjà créé ~150 issues et mergé
  ~40 PRs pendant la journée ; la file CI est saturée.
- **Constats critiques de la session** : le swarm mergeait sur checks *pending*
  (bypass admin, `enforce_admins: false`) → un test rouge est passé sur `main`
  (`TestRtspSsidGuardTest`, #3324) — corrigé en session (PR #3344).
- Le sandbox de cette session dispose de **PHP 8.4 + PostgreSQL + Redis** →
  validation locale possible (unique parmi les agents).

## Environnement local (validation)

| Étape | Résultat |
|-------|----------|
| PHP 8.4.24 + extensions (pgsql, sqlite, redis, bcmath, intl, gd…) | ✅ |
| PostgreSQL 14 + Redis 7 (schémas public/shared_tenants + migrations) | ✅ |
| Composer install (api/) | ✅ |
| Suite Unit locale | ✅ 511 passed, 2 failed (SSRF test → corrigé #3324) |
| Suite Feature locale | en cours |

## Tests effectués

### Live (prod/staging)
- **API Render** (`gestionemployerbackend.onrender.com`) : `/api/v1/health` 200
  (v4.23.5 stale) ; `/api-explorer` **500** ; `/docs/openapi.yaml` 200 ;
  `/trial/signup` (guided_trial) répond sans `provisioning_token` → `/trial/status`
  impossible → flux guided-trial cassé en prod (staging stale, F-E4-01) ;
  throttle `trial/signup` opérationnel (429 après usage) ; `/trial/status` sans
  token → 404 (attendu).
- **Vitrine Vercel** (`gestionemployer-backend.vercel.app`) : `/`, `/pricing`,
  `/docs`, `/contact`, `/changelog`, `/sitemap.xml` 200 ; `/blog` **404** (mais
  publié dans le sitemap) ; `/api/sitemap` 404 ; `/contact` affiche le littéral
  `{copy.info.responseTime}` en FR → **#3352**.
- **DNS** : `leopardo-rh.com` (défaut `site.ts`) = NXDOMAIN → canonicals/sitemap
  sur domaine mort (couvert #3190/#3193).
- **Console admin** (`leo-admin.pages.dev`) : 200 (login).

### Audit statique (4 agents parallèles — rapports dans `docs/qa/audit-expert5-2026-08-15/`)
- **Web vitrine** (25 constats) : canonical homepage ×6, métriques fabriquées
  (50K+/500+), études de cas fictives, pricing 3 représentations contradictoires
  (Enterprise « Sur devis » vs 299/239 € vs 0 € sandbox), surcoût/employé absent
  du checkout, SW précache routes auth, 7 pages FR-only, badges non localisés,
  liens Enterprise morts.
- **Admin Vue** (17 constats) : 12 routes `requiresTenant` inaccessibles,
  recherche header cassée (useRouter hors setup), CSV sans anti-formule
  (PayrollView/LeavesView), composables orphelins ×3, modale TaxRates morte,
  bandeau maintenance jamais déclenchable, read-all 405, inbox super-admin vide,
  prompt() natifs.
- **Mobile Flutter** (20 constats) : navigation manager vers routes non
  enregistrées (/team, /tasks, /me/monthly), onboarding HR int/String, FCM
  placeholders commités ×4, marketing sans auth, imports après déclarations,
  retries POST non-idempotents, DateTime.parse non gardés, deep-linking absent,
  SyncService subscription jamais cancelée.
- **API Laravel** (23 constats) : IDOR leave-balances, OAuth tenantless, policy
  approbation morte, SSRF rtsp (fixé), clé QR fail-open, races bulk-pay/payout/
  magic-link 2×, trial_days incohérent, OTP avalé, exceptions brutes, N+1,
  per_page non bornés.

> ~85 % des constats sont déjà tracés en issues par le swarm (vagues expert2/3/4).
> Cette session a créé les issues manquantes : **#3324** (test SSRF rouge sur
> main) et **#3352** (placeholder FR /contact).

## Issues créées

- **#3324** [P1] TestRtspSsidGuardTest rouge sur main (TEST-NET-3 + DNS) → **PR #3344**
- **#3352** [P2] /contact — placeholder `{copy.info.responseTime}` en FR → **PR #3357**

## Implémentation (PRs de la session)

| Issue | Correctif | PR | Statut |
|-------|-----------|----|--------|
| #3324 | Test SSRF déterministe + cohérent garde (203.0.113.10 → private, cible IP publique) | #3344 | ⏳ CI |
| #3352 | /contact — chaîne FR réelle au lieu du placeholder | #3357 | ⏳ CI |
| — | Feature spec kit `qa-expert5-session-2026-08-15/` + docs session | (ce PR docs) | ⏳ CI |

## Merge campaign

- ~36 PRs ouvertes à l'arrivée (swarm), file CI saturée ; merge strict :
  5 checks requis verts uniquement (leçon #3324 : ne pas merger sur pending).
- PRs en échec réel : #3111 (PHPStan/module — branche stale, 121 commits derrière
  main → merge de main requis).

## Notes

- Déploiement staging stale (v4.23.5, api-explorer 500, guided-trial cassé) =
  action ops prioritaire (F-E4-01) : relancer les déploys Render/Pages/Vercel
  après la campagne de merge.
- `main` doit rester vert : tout correctif backend est validé localement avant
  push (tests + PHPStan + Pint).
