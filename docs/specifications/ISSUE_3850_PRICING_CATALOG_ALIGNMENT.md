# ISSUE 3850 — Alignement du catalogue de plans vitrine / checkout / backend

> Statut : **Audité** (2026-08-15) — prêt pour `/speckit-plan` / `/speckit-tasks`.
> Périmètre : `front/web` (checkout, pricing, JsonLd) — aucun changement backend prévu.

## 1. Problème

Régression introduite par le merge #3629 (`f1554c20`, 2026-08-15) : la branche,
créée avant le fix #3247 (`c6ab93d9`), a écrasé l'alignement du catalogue de
plans sur `PlanSeeder`. Trois sources de vérité divergentes coexistent sur `main` :

| Source | Fichier | Plans (mensuel) | Annuel/mois |
|---|---|---|---|
| Vitrine /pricing | `front/web/src/modules/vitrine/data/pricing.ts` | Starter 29 / Business 79 / Enterprise 199 | 24 / 66 / 166 |
| Checkout UI | `front/web/src/app/(landing)/checkout/page.tsx` (`PLAN_CONFIG`) | Free 0 / Pilot 29 / Operations 99 / Enterprise 299 | 0 / 24 / 79 / 239 |
| Backend (vérité billing) | `api/database/seeders/PlanSeeder.php` | Free 0 / Pilot 29 / Operations 99 / Enterprise sur devis | 0 / 290 / 948 / 0 |

Conséquence directe : le CTA « Business 79€ » de /pricing ouvre un checkout
« Operations 99€ », et « Enterprise 199€ » ouvre un checkout « 299€ » (montant
fictif, le backend facturant 0€ / sur devis).

## 2. Décisions de cadrage (issues closes antérieures)

- #2907 — clés canoniques checkout : `free / pilot / operations / enterprise`.
- #3247 — « single source » : les montants doivent être alignés sur `PlanSeeder`
  (Starter 29 / Business 79 / Enterprise 199, annuel 24/66/166, 14 j d'essai,
  suppression du plan Free et du « Sur devis » côté checkout).
- D-E4-01 / 594c68f2 — essai vitrine = **14 jours** (`config('billing.trial_days')`).
- #1775 — le domaine `gestionemployer-backend.vercel.app` est **interdit** dans
  les données structurées (entreprise tierce).

## 3. Solution cible

1. **Restaurer** le `PLAN_CONFIG` du checkout sur les valeurs de `c6ab93d9`
   (Starter 29 / Business 79 / Enterprise 199, annuel 24/66/166, trialDays 14)
   — ou, mieux, faire importer au checkout les données de `pricing.ts` pour ne
   plus avoir de copie locale des montants.
2. **Supprimer les alias** `PLAN_ALIASES` (starter→pilot, business→operations,
   scale→enterprise) ou les faire pointer vers les clés de `pricing.ts` de façon
   cohérente (business → Business 79, enterprise → Enterprise 199).
3. **JsonLd.tsx** : remplacer le fallback local par `getSiteUrl()` (#2656).
4. **Non-régression** : garde de diff (script ou test) qui échoue si le catalogue
   `Free 0 / Pilot 29 / Operations 99 / Enterprise 299` réapparaît dans
   `checkout/page.tsx`.

## 4. Critères d'acceptation

- `/checkout?plan=business` affiche Business 79€/mois ; `/checkout?plan=scale`
  (et enterprise) affiche Enterprise 199€ ou « Sur devis » cohérent avec /pricing.
- `npx tsc --noEmit`, `npm run lint`, `npx next build`, `npm test` verts.
- Aucune occurrence de `gestionemployer-backend.vercel.app` dans `front/web/src`.
- Entrée CHANGELOG.md sous `## [Unreleased]`.

## 5. Tests

- Jest : adapter `pricing-plan-routing.test.ts` au mapping corrigé.
- Playwright (preview locale) : pricing → checkout → montant cohérent.
