/**
 * #4951 (audit PM 2026-08-17) — la durée d'essai doit être UNIQUE sur une
 * même page (14 jours, aligné sur config('billing.trial_days') = 14).
 * Un plan affichant 30 jours à côté de plans à 14 jours est une
 * contradiction produit (épics #3012/#3218/#3516).
 */
import { getPricingPlans } from '../pricing';

const TRIAL_14 = /14[- ](jours|jour|days|day|gün|يومًا|يوم)/i;

describe('pricing trial duration consistency (#4951)', () => {
  it('aucun plan ne mentionne un essai de 30 jours', () => {
    for (const locale of ['fr', 'en', 'tr', 'ar'] as const) {
      for (const plan of getPricingPlans(locale)) {
        expect(plan.priceNote).not.toMatch(/30 (jours|day|days|gün|يوم)/i);
      }
    }
  });

  it('tous les plans annoncent 14 jours d’essai', () => {
    for (const locale of ['fr', 'en', 'tr', 'ar'] as const) {
      for (const plan of getPricingPlans(locale)) {
        expect(plan.priceNote).toMatch(TRIAL_14);
      }
    }
  });
});
