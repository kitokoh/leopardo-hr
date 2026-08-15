# Mini-spec — Issue #3919

## Problème

Triple schéma de prix contradictoire sur la vitrine :
1. `/pricing` (`data/pricing.ts`) affiche Starter 29 € / Business 79 € / Enterprise 199 € (schéma legacy).
2. Le checkout route backend (`api/billing/checkout`) applique le schéma canonique #2977 : Pilot 29/24 €, **Operations 99/79 €**, Enterprise sur devis.
3. `seo.pricing.description` (en.json) mélange les deux (« Pilot 29, Operations 99, Enterprise on quote »).

Conséquence P1 : le prix affiché sur `/pricing` (79 € pour le plan populaire) **change au moment de l'achat** (99 € facturés) ; les noms affichés divergent du backend (`PlanSeeder.php` migre Starter→Pilot, Business→Operations via LEGACY_NAMES).

## Contrat canonique (source : PlanSeeder.php + spec #2977)

| Plan | Mensuel | Annuel | Employés |
|---|---|---|---|
| Free | 0 € | 0 € | 5 |
| Pilot | 29 € | 24 € | 30 |
| Operations | 99 € | 79 € | 250 |
| Enterprise | Sur devis | Sur devis | Illimité |

## Correctif

- `data/pricing.ts` ×4 locales : noms canoniques (Pilot/Operations/Enterprise), prix 29/24, 99/79, Enterprise « Sur devis » (libellés localisés), plafonds 30/250.
- `pricing/page.tsx` : CTA populaire → `/checkout?plan=operations` ; FAQ ×4 locales alignées (noms, plafonds, support, API).
- `checkout/page.tsx` : `PLAN_CONFIG` renommé en clés canoniques pilot/operations/enterprise (prix 29/24, 99/79, Enterprise `null` → « Sur devis ») ; `PLAN_ALIASES` inversé (starter→pilot, business→operations, scale→enterprise, free→pilot) ; rendu « Sur devis » pour Enterprise.
- `checkout/success/page.tsx` : affiche le nom canonique du plan.
- `seo.ts` + `fr.json` : descriptions pricing/checkout alignées.
- `content.ts` : FAQ stockage alignée.
- Test `pricing-checkout-alignment.test.ts` : nom + prix vitrine = config checkout pour les 4 locales ; alias legacy routés vers la config canonique ; plan populaire → operations.

## Validation

`tsc --noEmit`, `eslint --max-warnings 0`, jest 471/471 verts, build Next.js OK, rendu runtime `/pricing` et `/checkout?plan=operations` OK (Pilot/Operations/Enterprise, plus aucun Starter/Business). Checksum i18n régénéré (`sync-backend.js`), validator 4 locales OK.

Closes #3919
