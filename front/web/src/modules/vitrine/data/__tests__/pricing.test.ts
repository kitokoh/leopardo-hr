/**
 * Garde anti-régression #4951 : la durée d'essai doit être UNIFORME sur la
 * page pricing (14 jours, alignée sur config('billing.trial_days') = 14).
 * Un libellé « 30-day »/« 30 jours » qui traînerait sur une carte plan casse
 * ce test — même mécanique que checkout-i18n.test.ts (lecture de la source
 * de vérité des prix, pas de rendu DOM).
 */
import { pricing } from '../pricing';

describe('pricing trial duration consistency (#4951)', () => {
  const locales = Object.keys(pricing) as Array<keyof typeof pricing>;

  it('n\'annonce qu\'une seule durée d\'essai sur toute la page, toutes locales', () => {
    const durations = new Set<string>();

    for (const locale of locales) {
      for (const plan of pricing[locale]) {
        const note = plan.priceNote ?? '';
        // extrait la durée annoncée (14-day / 14 jours / 30-day / 30 jours…)
        const m = note.match(/(\d+)[- ]?(day|jours|gün|يوم)/i);
        expect(m).not.toBeNull();
        if (m) {
          durations.add(`${locale}:${m[1]}`);
        }
      }
    }

    // Une seule durée (14) sur l'ensemble des locales : le SET des durées
    // distinctes doit être exactement {14}. La forme initiale comparait le
    // tableau par-locale au tableau dédupliqué — structurellement impossible
    // à satisfaire dès que 2+ locales annoncent une durée (cf. #2755 lot 2).
    const distinctDurations = [...new Set([...durations].map((d) => d.split(':')[1]))];
    expect(distinctDurations).toEqual(['14']);
  });

  it('aucun libellé « 30-day » / « 30 jours » ne subsiste', () => {
    for (const locale of locales) {
      for (const plan of pricing[locale]) {
        expect(plan.priceNote).not.toMatch(/30[- ]?(day|jours)/i);
      }
    }
  });
});
