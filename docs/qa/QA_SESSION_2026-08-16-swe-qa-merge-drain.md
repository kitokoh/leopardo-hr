# Session QA — Expert SWE/QA merge drain + implémentations (2026-08-16)

**Agent**: expert-swe-qa-2026-08-16 (session multi-agents concurrente — « merge-drain »)
**Périmètre**: Phase 0 (merge drain des PRs ouvertes), Phase 2 (dette + issues), Phase 1 (audit), Phase 3 (implémentations).

## Phase 0/2 — Merge drain

Au départ : **68 PRs ouvertes**, ~54 issues, ~100 branches. Traitées pendant la session
(merge API + auto-resolver de conflits, protocole Lock & Isolate) :

- **Docs** : #4361 #4423 #4432 #4438 #4465 #4479 #4544
- **API** : #4543 #4353 #4365 #4441 #4444 #4354 #4457 #4390 #4356 #4308(fermée par agent concurrent, reprise #4552)…
- **Web/admin/mobile/ci** : #4541 #4540 #4450 #4444 #4431 #4452 #4566 #4568 #4569 #4576 #4578 + le swarm a fusionné #4542 #4545 #4546 #4532 #4533 #4535 #4538 #4539 #4449 #4547 #4549 #4531 #4483 #4367 #4369 #4358 #4420 #4434 #4429 #4440 #4421 #4347 #4460 #4427 #4425 #4424 #4455 #4458 #4462 #4470 #4471 #4483…

Total : **~55 PRs fusionnées** (contribution directe : 14 merges API dont 6 avec
résolution de conflits automatique + union de catalogues i18n).

**Conflits résolus** : catalogues `api/lang/{fr,en,ar,tr}/errors.php` (union de clés,
déduplication), `CHANGELOG.md`, controllers PHP — via script `resolve-conflicts.py`
(guards anti-fragments ajoutés après incident, voir Leçon).

## Phase 3 — Implémentations livrées (11 issues, 11 PRs fusionnées)

| Issue | Fix | PR |
|-------|-----|----|
| #4509 web/seo | canonical guides localisé via les layouts (metadata de page statique retirée) | #4568 |
| #4508 web/tech-debt | 4 schémas JSON-LD morts + FAQJsonLd supprimés (seo.ts, JsonLd.tsx) | #4569 |
| #4516 admin/bug | préfixe `/v1/` retiré de ~22 appels API codés en dur | #4576 |
| #4514 admin/tech-debt | `requiresTenant` câblé sur les routes tenant-only fleet/exports | #4578 |
| #4563 web/bug (audit) | `aria-label` dupliqué docs/page.tsx (régression merge #4367×#4480) → tsc vert | #4566 |
| #4525 mobile/ios | `UIBackgroundModes` remote-notification sur les 4 apps FCM | #4581 |
| #4521 mobile/i18n | leopardo_marketing — delegates l10n + supportedLocales + locale | #4587 |
| #4528 mobile/ux | Settings — branche d'erreur + retry sur Career/CabinetStats (employee+manager) | #4589 |
| #4529 mobile/tech-debt | 8 fichiers morts supprimés + onboardingChecklistProvider dédupliqué (factory core) | #4595 |
| #4520 mobile/build | applicationId `com.leopardo.marketing` normalisé (AGP/Kotlin déjà alignés #4378) | #4596 |
| #4600 web/ops/seo (audit) | garde de build `NEXT_PUBLIC_SITE_URL` requis en prod (leopardo-rh.com NXDOMAIN) | #4603 |

Vérifications locales : `tsc --noEmit` vert (web), `npm run build` vert avec env var et
échec bruyant sans (garde #4600 prouvée), `node --check` stores admin, XML plist validé,
parité clés i18n admin ×4 locales (0 écart), brace-balance Dart.

## Phase 1 — Constats d'audit (nouveaux)

### #4563 — `docs/page.tsx` aria-label dupliqué → tsc rouge sur main (P1, web)
PRs #4367 et #4480 ont corrigé indépendamment le même input (recherche docs) → merge
avec double `aria-label` → TS17001 sur main, check Frontend requis rouge (toutes les PRs
web bloquées). Corrigé (#4566).

### #4600 — `DEFAULT_SITE_URL` pointe vers leopardo-rh.com en NXDOMAIN (P1, web/ops/seo)
Vérifié prod : `leopardo-rh.com` → 000 (NXDOMAIN, #3452 toujours ouverte) ; vitrine live
sur `gestionemployer-backend.vercel.app` (200, sitemap OK). Tout build sans
`NEXT_PUBLIC_SITE_URL` émet canonicals/sitemap/JSON-LD vers un domaine mort. Garde de
build ajoutée (#4603) — échec bruyant au lieu de canonicals invalides.

### Vérifié résolu en prod
- `GET /api/v1/health` → 200 ; `/supported-countries` → 200 ; `/i18n/catalog/fr` → 200
  (issues #2812 résolues — API re-déployée).
- `POST /api/v1/trial/signup` → 422 validation localisée (plus de 500 #3259).
- Mobile : 0 usage `apiClient.dio.*` hors download, 0 `.withOpacity(`, 0 `dd/dump`.
- CI : les 75 jobs GitHub Actions ont un `timeout-minutes` (#4455 confirmé sur main).
- i18n admin : parité parfaite fr/en/tr/ar (clés + placeholders).

## Leçon (incident résolu)

Résolution de conflits `errors.php` par script regex (commit 0669334f, branche #4441) a
laissé le littéral `, origin/main` → ParseError P0 sur main (#4565, corrigé par le swarm
#4584). Garde ajoutée au resolver : grep `origin/main`/marqueurs + vérification structure.
Ne jamais committer un merge auto-résolu sans contrôle structurel de chaque fichier résolu.

## État final

- ~55 PRs fusionnées, 11 issues implémentées par cette session, 2 nouvelles issues
  d'audit (#4563, #4600) créées ET corrigées.
- PRs restantes : ~4 (agents concurrents). Issues restantes : dette i18n connue (#2755,
  #4194, #4303…), épopée prod (#3765/#3766/#3452), features marketing/platform_admin
  (#3910/#3912).
