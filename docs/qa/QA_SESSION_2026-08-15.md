# QA Leopardo HR — Session du 2026-08-15 (audit externe complet)

Mission : tester la plateforme dans tous les sens (vitrine, web, admin, mobile,
workflows, API, logique, onboarding, cohérence), documenter chaque manquement, créer
les tickets/tasks/incidences selon la méthode spec kit, puis implémenter les correctifs.

## Méthode
1. Tests **live** des surfaces déployées : vitrine (Vercel), console admin (Cloudflare
   Pages), API (Render) — curl + navigateur.
2. Revue **statique** parallèle (4 agents) : API Laravel (auth/onboarding/tenancy),
   front/web Next.js, admin-dashboard Vue, apps Flutter (cross-check des 407 appels
   Dart vs routes backend).
3. Chaque constat confirmé → issue GitHub (`[QA][P#]`) + feature spec kit
   `.specify/features/qa-wave-2026-08-15/` (plan/spec/tasks).
4. Implémentation des correctifs (PR par issue, `Closes #X`).

## Constats confirmés (live + code)

### LIVE — Production
| ID | Sévérité | Constat | Preuve |
|----|----------|---------|--------|
| OPS-1 | CRITIQUE | `/api-explorer` → **500** en prod (fix #2287 merged 06:22Z non déployé — Render stale) | curl onrender |
| OPS-2 | CRITIQUE | Checkout prod → **`sandbox:true`**, « Aucune carte débitée », `provisioned:false` → faux succès de paiement, aucun compte créé | curl Vercel `/api/billing/checkout` |
| OPS-3 | MAJEUR | Admin login Pages → « Mot de passe oublié ? »/« Sécurité »/« Support » en `href="#"` (fix #2243/#2323 non déployés — build stale) | bundle LoginView-BXZeeAzT.js + navigateur |
| OPS-4 | MAJEUR | Déploiement Vercel Production 06:23:35Z en **failure** (sha d821326515) | GitHub deployments API |
| OPS-5 | INFO | `/api/v1/demo-users` → 404 (attendu, `DISABLE_DEMO_SEEDING=true`) | curl |

### API (code)
| ID | Sévérité | Constat | Réf |
|----|----------|---------|-----|
| API-1 | CRITIQUE | Login ignore `status` : `UserAuthService`, `PlatformAuthController`, callbacks Google ; suspension ne révoque pas les tokens | #2630 |
| API-2 | MAJEUR | `/auth/register` crée un employé sans `company_id` → login impossible + probing cross-tenant | #2636 |
| API-3 | MAJEUR | `/ai/*` et `/growth/partner/*` sans middleware tenant complet ; `/sso` management incomplet | #2635 |
| API-4 | MAJEUR | Trial guidé : magic link jamais envoyé (TODO), mot de passe manager jamais communiqué, `trial/status` dit `ready` sans credentials | #2629 |
| API-5 | MAJEUR | `UserInvitationService::accept()` sans check statut société | #2637 |
| API-6 | MOYEN | Drift OpenAPI : 119 chemins de code absents de la spec | #2638 |
| API-7 | MINEUR | `updateCountry` dupliqué (artefact merge, code mort) | #2644 |

### Mobile (apps Flutter)
| ID | Sévérité | Constat | Réf |
|----|----------|---------|-----|
| MOB-1 | MAJEUR | `POST /onboarding-setup/{id}/complete\|skip` (employee + HR) vs backend `PATCH` → **405** (manager OK) | #2631 |
| MOB-2 | MAJEUR | `GET /departments/{id}/hierarchy` (HR + Manager) → **route inexistante 404** | #2633 |
| MOB-3 | INFO | `leopardo.local:7878` (HTTP) hardcodé dans sync edge (confirmé non utilisé pour le trafic tenant release — à surveiller) | — |

### Web vitrine (Next.js)
| ID | Sévérité | Constat | Réf |
|----|----------|---------|-----|
| WEB-1 | MAJEUR | Checkout : sandbox en prod + carte test affichée en permanence | #2628 |
| WEB-2 | MINEUR | `app/api/robots/route.ts` annonce `/api/sitemap` (404) ; doublon avec `app/robots.ts` | #2643 |
| WEB-3 | MOYEN | ~15 pages FR-only (contact, docs, guides, about, checkout…) malgré sélecteur EN/TR/AR ; OnboardingWizard FR hardcodé | #2642 |
| WEB-4 | INFO | 3 routes API mortes supprimées (docs obsolètes uniquement) | — |

### Admin (Vue)
| ID | Sévérité | Constat | Réf |
|----|----------|---------|-----|
| ADM-1 | MAJEUR | 3/8 gaps QA-8 restants : training sessions/enrollments + webhooks CRUD/test sans `/admin` → 401 super-admin | #2634 |
| ADM-2 | MINEUR | Handlers factices EditUserModal (4 actions) + maintenance SystemAlertsOverlay (setTimeout+toast) | #2641 |
| ADM-3 | MINEUR | Palette : `/vehicles` → 404, 7 entrées tenant → `/`, settings → `/system` | #2640 |
| ADM-4 | MINEUR | 2 clés i18n manquantes + clés brutes dans `document.title` | #2639 |
| ADM-5 | MINEUR | Orphelins : useNotificationStream non branché, 16 composants non importés, Alt+R → route tenant | #2645 |

## Issues créées (spec-first)
#2628 checkout sandbox (P1) · #2629 trial guidé (P1) · #2630 auth statut (P1) · #2631
mobile onboarding 405 (P1) · #2632 déploiements stale (P1) · #2633 organigramme
hierarchy (P2) · #2634 admin training/webhooks /admin (P2) · #2635 middleware tenant
ai/growth (P2) · #2636 /auth/register orphelin (P2) · #2637 invitation société suspendue
(P2) · #2638 drift OpenAPI (P3) · #2639 i18n admin (P3) · #2640 command palette (P3) ·
#2641 handlers factices (P3) · #2642 pages FR-only (P3) · #2643 robots (P3) · #2644
updateCountry dupliqué (P3) · #2645 orphelins admin (P3)

Feature spec kit : `.specify/features/qa-wave-2026-08-15/` (plan.md, spec.md, tasks.md).

## Implémentation (PRs)
| Issue | PR | Statut |
|-------|----|--------|
| #2628 checkout sandbox | #2665 | ✅ mergé |
| #2629 trial guidé magic link | #2864 | ⏳ PR ouverte |
| #2630 auth statut (tokens + Google + super-admin) | #2860 | ⏳ PR ouverte (statut login déjà couvert par #2816) |
| #2631 mobile onboarding 405 | #2663 | ✅ mergé |
| #2632 déploiements stale | — | 📋 ops (documenté) |
| #2633 organigramme hierarchy | #2861 | ⏳ PR ouverte |
| #2634 admin training/webhooks /admin | #2862 | ⏳ PR ouverte |
| #2635 middleware tenant ai/growth/sso | #2863 | ⏳ PR ouverte (growth partiellement couvert par #2818) |
| #2636 /auth/register orphelin | — | ✅ couvert par #2617 (mergé) |
| #2637 invitation société suspendue | #2865 | ⏳ PR ouverte |
| #2638 drift OpenAPI | — | 📋 P3 (tasks spec kit) |
| #2639 i18n admin (clés) | #2866 | ⏳ PR ouverte (document.title via #2862) |
| #2640 command palette | #2866 | ⏳ PR ouverte |
| #2641 handlers factices admin | #2875 | ⏳ PR ouverte |
| #2642 pages vitrine FR-only | — | 📋 P3 (tasks spec kit) |
| #2643 robots /api/sitemap | #2664 | ✅ mergé |
| #2644 updateCountry dupliqué | #2871 | ⏳ PR ouverte |
| #2645 orphelins admin | — | 📋 P3 (tasks spec kit) |

13 PRs au total (3 mergées, 10 ouvertes) — détails : `git log` des branches `fix/<issue>-<slug>` / PRs #2663-#2876.

## Notes
- Pas de run backend local (pas de PHP/Postgres dans le sandbox) : les changements PHP
  sont validés par CI (tests + PHPStan) ; revue statique attentive avant push.
- Déploiement stale = incident ops prioritaire : les utilisateurs voient une version
  antérieure aux correctifs QA mergés.
