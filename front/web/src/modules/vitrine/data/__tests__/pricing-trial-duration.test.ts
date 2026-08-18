import { getPricingPlans } from '@/modules/vitrine/data/pricing';

/**
 * #4951 — durée d'essai unifiée : 14 jours partout (config `billing.trial_days`
 * = 14, épics #3012/#3218/#3516). Interdit la réintroduction d'une durée
 * différente (ex. résiduel « 30-day ») sur la même page /pricing.
 */
describe('pricing trial duration unifiée (#4951)', () => {
  const locales = ['fr', 'en', 'tr', 'ar'] as const;

  it.each(locales)('aucun plan %s n\'annonce une durée d\'essai autre que 14', (locale) => {
    const plans = getPricingPlans(locale);
    const notes = plans.map((p) => p.priceNote ?? '').filter((n) => n.length > 0);
    expect(notes.length).toBeGreaterThan(0);

    for (const note of notes) {
      // La mention d'essai (si présente) doit être « 14 » — jamais 30/7/15…
      const trialMatch = note.match(/\b(\d{1,2})\s*(?:day|jour|gün|يوم)/i);
      if (trialMatch) {
        expect(Number(trialMatch[1])).toBe(14);
      }
    }
  });
});
