# ISSUE_3883 — Plan Free visible sur la vitrine

> Spec Kit : `.specify/features/3883-free-plan-vitrine/spec.md` · Tâches : `tasks.md`
> Branche : `fix/3883-free-plan-vitrine` · Issue : #3883

## Décision appliquée

**Option A** (funnel freemium) : le plan Free (0 €/mois, 5 employés) — créé par le
`PlanSeeder` (schéma canonique #2977) — est de nouveau affiché sur la vitrine, en
tête des 4 locales. Le CTA mène à l'essai guidé sans carte (`/signup?source=pricing_free`),
jamais au checkout (leçon #2907 : « Start for free » → paywall 24 €/mois).

## Changements

| Fichier | Changement |
|---|---|
| `front/web/src/modules/vitrine/data/pricing.ts` | Plan Free ajouté ×4 locales (price/annual 0, 5 employés) |
| `front/web/src/app/(landing)/pricing/page.tsx` | `getPlanHref` : `price === '0'` → `/signup?source=pricing_free` |
| `front/web/src/modules/vitrine/components/PricingSection.tsx` | CTA Free + grille 4 colonnes (home) |
| `front/web/src/modules/vitrine/data/pricing-faq.ts` | FAQ « Free » ×4 + plafonds 5/30/250/illimité |
| `front/web/src/modules/vitrine/components/__tests__/pricing-checkout-alignment.test.ts` | 4 plans, Free ↔ 0/0 |
| `CHANGELOG.md` | Entrée `[Unreleased]` |

## Pourquoi pas le checkout pour Free

Le checkout n'a pas de configuration Free facturable (`PLAN_ALIASES.free → pilot`) :
router le CTA Free vers `/checkout?plan=free` afficherait le paywall Pilot (bug #2907).
Le plan Free est le palier post-essai des TPE ; l'acquisition passe par l'essai guidé.
