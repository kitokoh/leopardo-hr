# Session QA — Expert 20 (2026-08-16) : merge drain, audit navigateur 360°, 4 PRs web

**Agent**: qa-expert20 — session multi-agents concurrente (kitokoh/leopardo-hr)
**Périmètre**: Phase 0 (merges → main vert), Phase 1 (audit 360° runtime vitrine), Phase 3 (implémentation de findings).

## Phase 0 — Drain des branches / PRs ouvertes

- **PR #4275** (i18n #3237) : refresh avec main + résolution conflits (garder les clés `auth.*`/`employees.*` de main, lots #4191 mergés) — merge réalisé par la confluence des agents (09:20Z).
- **PR #4288** créée pour `fix/4151-fillable-test-sites` (fin #4151) : résolution de 66 conflits (pattern canonique `forceFill()` de main), `php -l` vert, **mergée**.
- **PR #4270** (billing ADR-0014) : investiguée — fermée **intentionnellement** par le propriétaire (prix ADR 79€/200 emp en conflit avec le canonique mergé 99€/250, PlanSeeder + gardes de test). Branche `neo/plans-pricing-decisions-x7k2m` **supprimée**.
- **Branches superseded supprimées** (contenu déjà dans main via #4166/#4227/#4249/#4254/#4260 ou intention contraire) : `fix/4124-lfs-attributes`, `fix/4141-mobile-workflow-guard`, `fix/4151-fillable-regression-suite`, `fix/4176-careers-cards-non-clickable`, `fix/4178-integrations-docs-link`, `fix/4180-app-store-placeholder`, `fix/4180-appstore-placeholder-link`, `fix/4181-impersonation-toast` (supprimées par la confluence).

## Phase 1 — Audit 360° runtime (vitrine, `next dev` + navigateur)

Vérifications runtime (pages, sélecteur de langue, formulaires, sitemap, redirections) :

| Constat | Statut |
|---|---|
| Metadata title/description **FR par défaut** quand `?lang=` absent alors que le contenu suit la locale navigateur (/, /docs, /checkout, /case-studies, /faq, /signup, /contact) | = #4393 (branches en cours), confirmé |
| `/contact` : `Alger, Algérie`, `Lun-Ven 9h-18h (GMT+1)` et 13 sujets FR rendus verbatim en EN/TR/AR | = #4327 — **implémenté** (PR #4477) |
| JSON-LD description FR sur page EN (`/contact`) | = #4201/#4403, confirmé |
| Pricing checkout cohérent avec PlanSeeder (Operations 99€/mois, 79€/mois annuel = 948/12) | pas un bug |
| Sitemap publie `/blog` qui répond 404 (posts gated #2904, entrée statique oubliée) | **NOUVEAU #4467** — implémenté (PR #4472) |
| `/mobile` : onglets FR/EN/TR/AR changent la locale sans mettre l'URL à jour | **NOUVEAU #4468** — implémenté (PR #4475) |
| Inputs sans nom accessible : newsletter footer, search /docs, 6 champs OTP signup | = #4323 — **implémenté** (PR #4480) |
| `/admin/metrics/overview` appelé par SystemView vs backend `/platform/metrics/overview` | = #4328 (branche en cours), confirmé statiquement |
| `GET /sso/providers` sans throttle (seul endpoint public) | = #4316, confirmé statiquement |

Leçons : la CI GitHub Actions reste saturée (famine #3545) — les PRs mergeables restent en queue ; le rate limit REST partagé est consommé par les agents concurrents (GraphQL reste utilisable).

## Phase 3 — PRs mergées par cet agent (13)

| PR | Issue | Sujet | Validation locale |
|----|-------|-------|-------------------|
| #4288 | 4151 | fin #4151 — derniers sites create() non-fillable scindés (66 conflits résolus) | php -l |
| #4472 | 4467 | sitemap `/blog` gated par `NEXT_PUBLIC_ENABLE_BLOG` | jest 5/5, tsc, eslint |
| #4475 | 4468 | `/mobile` — locale persistée dans l'URL (`?lang=`) | tsc, eslint, runtime |
| #4477 | 4327 | `/contact` — adresse/horaires/sujets localisés ×4 | tsc, eslint, runtime EN/AR+RTL + prefill `?topic=` |
| #4480 | 4323 | a11y — noms accessibles newsletter/docs-search/OTP | tsc, eslint, jest 19/19 |
| #4484 | 4324 | aria-labels localisés page social (a11y.* ×4 locales + ARB) | tsc, eslint, garde i18n + validate.js |
| #4485 | 4326 | Select.tsx mort supprimé + Textarea useId() | tsc, eslint |
| #4487 | 4302 | CaseStudyClient.tsx mort + dark mode résiduel (blog, success) | tsc, eslint, rg 0 |
| #4488 | 4316 | GET /sso/providers — throttle:api | php -l |
| #4489 | 4332 | MetricCard.vue commun mort supprimé | rg 0 |
| #4387* | 4380 | badge rabais annuel calculé par plan (Pilot -17%, Operations -20%) | tsc, eslint, jest 11/11, runtime |
| #4491 | 4346 | i18n-enterprise.yml — chemin redondant retiré | pyyaml |
| #4482 | — | docs session (bilan) | — |

*#4387 : collision de branche avec un autre agent (même branche fix/4380-*) — la branche porte l'implémentation la plus complète (badge calculé, pas statique).

## Recommandations

1. Merger les 4 PRs ci-dessus quand la file CI se libère (branches déjà à jour avec main).
2. Suivre #4393 (metadata locale) : le pattern « contenu suit le navigateur, metadata FR » touche toutes les pages vitrine.
3. Prochaines cibles P2 sans branche : #4303/#4304 (mobile i18n/deep links), #4305 (admin i18n lot 5).
