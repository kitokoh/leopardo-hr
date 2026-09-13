import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { FREE_GUIDED_TRIAL_HREF } from '../checkout';

/**
 * #7312 — le CTA « essai guidé » du plan Free ne doit plus reperdre le
 * prospect.
 *
 * `/checkout?plan=free` affiche une page dédiée (#3883/#4195) dont le CTA
 * principal menait à `/signup?source=checkout_plan_free` — SANS `plan=`. Or
 * le middleware redirige tout `/signup` sans plan valide vers `/pricing#plans`
 * (vérifié en production : 307 → `/pricing`). Le prospect choisissait le plan
 * Free puis atterrissait sur la page tarifs.
 *
 * La cible vit désormais dans un constant unique ; ce test verrouille le fait
 * que `plan=free` reste dans l'URL ET que la page ne réintroduit pas l'ancien
 * lien littéral.
 */
describe('checkout — CTA essai guidé du plan Free (#7312)', () => {
  it('conserve le plan dans l’URL (sinon le middleware renvoie vers /pricing)', () => {
    expect(FREE_GUIDED_TRIAL_HREF.startsWith('/signup?')).toBe(true);

    const params = new URLSearchParams(FREE_GUIDED_TRIAL_HREF.split('?')[1] ?? '');
    expect(params.get('plan')).toBe('free');
    // Offres acceptées par le middleware (`SUPPORTED_PLANS`).
    expect(['free', 'pilot', 'operations', 'enterprise']).toContain(params.get('plan'));
    expect(params.get('source')).toBe('checkout_plan_free');
  });

  it('la page checkout n’utilise plus le lien littéral sans plan', () => {
    const source = readFileSync(
      join(__dirname, '../../../../app/(landing)/checkout/page.tsx'),
      'utf8',
    );

    expect(source).not.toContain('href="/signup?source=checkout_plan_free"');
    expect(source).toContain('href={FREE_GUIDED_TRIAL_HREF}');
  });
});
