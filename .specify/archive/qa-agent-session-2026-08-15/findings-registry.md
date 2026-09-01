# Registre des manquements — Session QA agent 2026-08-15

> Session de test de la plateforme Leopardo RH (repo kitokoh/leopardo-hr).
> Mission : tester la vitrine, le web, l'admin, les mobiles, les workflows, les APIs, les
> logiques, l'onboarding et la cohérence — tout manquement → spec + tasks + issues (méthode
> Spec Kit) puis implémentation. Anti-doublon : les constats déjà couverts par les vagues
> QA existantes (#2600–#2813, missions 2026-08-14/15) sont exclus ou référencés.

## A. Vérifications runtime effectuées (prod live)

- [x] API Render live (`gestionemployerbackend.onrender.com`, health v4.23.5) :
      `/health/live` 200 · login démo manager **200** (token + data) · login super-admin → 401
      `INVALID_CREDENTIALS` (démo désactivée en prod, cf. #2646) · `/i18n/catalog/fr` **500** ·
      `/supported-countries` **404** · `/api-explorer` **404** · `/dashboard/kpi` **500** ·
      `/employees/{id}` **500** · `POST /payroll/simulate` **404** (route présente sur main).
      → déploiement Render obsolète vs main (#2812/#2632 — les 500 sont déjà corrigés sur main).
- [x] Vitrine Vercel live : `/` 200 · `/pricing` 200 · `/blog` **404** · sitemap.xml contient
      **50 URLs `/blog/*`** → blog 404 + sitemap stale (#2813, fix #2943 pas encore déployé).
      `/robots.txt` OK · `/api/sitemap` **404** (legacy robots route supprimée sur main).
- [x] Admin `leo-admin.pages.dev` : 200 (login OK).

## B. Findings — Audit statique main (post-merges #2944/#2891/#2936/#2935)

| ID | Sév | Constat | Preuve | Statut |
|----|-----|---------|--------|--------|
| S1 | P1 | Checkout : clés fantômes `starter`/`business` RÉ-MERGÉES dans main par #2944 (résolution de conflits perdue) ; fallback plan `'business'` ; label Enterprise alias `scale` présent | `front/web/src/app/(landing)/checkout/page.tsx` sur main : `starter:`/`business:`/`scale:` | Corrigé sur `fix/2909-pricing-trial-copy` (PR #2972) |
| S2 | P3 | BOM U+FEFF en tête de `checkout/page.tsx` sur main | `od -tx1` 1er octet = `ef bb bf` | Corrigé (PR #2972) |
| S3 | P2 | `seo.pricing.description` : clé absente des catalogues i18n → le fallback FR (`t()` 3e arg) est toujours utilisé, meta en/tr/ar non traduites | grep catalogues : 0 occurrence | Ouvert → issue |
| S4 | P2 | Codes de plan incohérents backend/frontend : `FeaturePlanMatrixSeeder` (`trial/starter/business/enterprise`) vs checkout (`free/pilot/operations/enterprise`) | `api/database/seeders/FeaturePlanMatrixSeeder.php:13-20` | Ouvert → issue |
| S5 | P3 | `branding/page.tsx` : 3e schéma de nommage « Starter/Pro/Enterprise » (ni pricing ni matrice backend) | `front/web/src/app/(landing)/branding/page.tsx:106-121` | Ouvert → issue |
| S6 | P2 | `SignupForm.test.tsx` : 5 tests échouent sur main (flow OTP « vérifiez votre email ») — hors CI (jest ne tourne pas en CI vitrine) | `npm run test` → 15/16 suites, 5 failed | Ouvert → issue |
| S7 | P1 | Prod : `/dashboard/kpi` 500 + `/employees/{id}` 500 + `/payroll/simulate` 404 (corrigés sur main, non déployés) | smoke `scripts/qa_api_smoke.py` live | Traçé (#2812/#2632) |
| S8 | P1 | Locales en/tr/ar : « 14-day/14 gün/14 يوم » + stats hero `{ value: 14 }` (essai) — #2753 n'avait corrigé que le FR | grep vitrine | Corrigé (PR #2972) |
| S9 | P2 | `(dashboard)/billing/page.tsx` : `PLAN_LABELS` starter/business → labels faux pour codes canoniques | ligne 34-35 | Corrigé (PR #2972) |
| S10 | P2 | `src/app/api/robots/route.ts` legacy supprimée sur main ✅ mais issue #2608 ouverte (partie « ajouter /blog, /signup, /checkout à robots.txt » reste à faire) | robots.ts vs #2608 | Ouvert → issue reste |

## C. Actions session (branches/PRs)

- [x] PR #2944 mise à jour (résolution conflits plans canoniques + 30j) → mergée par le propriétaire
      SANS la résolution (conflits ré-apparus) → S1/S2.
- [x] PR #2891 mise à jour (résolution conflits, robots route + dead blog code supprimés) → mergée.
- [x] PR #2972 ouverte : `fix/2909-pricing-trial-copy` — S1, S2, S8, S9 + contenu #2909.
- [ ] Branches `fix/2604-2606-web-accents`, `fix/2607-2608-web-seo-cleanup`, `fix/2789-admin-supported-countries`
      à mettre à jour + PR (issues ouvertes #2604/#2606/#2607/#2608/#2789).
- [ ] PR #2306 (`qa-hardening-wave-2026-08-14`, draft) : à réconcilier avec main (298 commits de retard).
