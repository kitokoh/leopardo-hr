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

## Phase 3 — PRs ouvertes par cet agent

| PR | Issue | Sujet | Validation locale |
|----|-------|-------|-------------------|
| #4472 | 4467 | sitemap `/blog` gated par `NEXT_PUBLIC_ENABLE_BLOG` | jest 5/5, tsc, eslint |
| #4475 | 4468 | `/mobile` — locale persistée dans l'URL (`?lang=`) | tsc, eslint, runtime vérifié |
| #4477 | 4327 | `/contact` — adresse/horaires/sujets localisés ×4 | tsc, eslint, runtime EN/AR+RTL + prefill `?topic=` |
| #4480 | 4323 | a11y — noms accessibles newsletter/docs-search/OTP | tsc, eslint, jest 19/19 |

## Recommandations

1. Merger les 4 PRs ci-dessus quand la file CI se libère (branches déjà à jour avec main).
2. Suivre #4393 (metadata locale) : le pattern « contenu suit le navigateur, metadata FR » touche toutes les pages vitrine.
3. Prochaines cibles P2 sans branche : #4303/#4304 (mobile i18n/deep links), #4305 (admin i18n lot 5).
