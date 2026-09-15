import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { FREE_GUIDED_TRIAL_HREF } from '../checkout';

/**
 * #7312 — le CTA « essai guidé » du plan Free ne doit plus reperdre le
 * prospect.
 *
 * `/checkout?plan=free` affiche une page dédiée (#3883/#4195) dont le CTA
 * principal menait à `/signup?source=checkout_plan_free` — SANS `plan=`, ce
 * qui déclenchait alors la redirection middleware vers `/pricing#plans`.
 *
 * Depuis #7488 (décision #7487), `/signup` est accessible sans plan et la
 * redirection n'existe plus. Le `plan=free` est conservé dans l'URL pour le
 * rappel d'offre et le tracking campagne ; ce test verrouille le constant
 * unique et l'absence de l'ancien lien littéral.
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
