import { getPricingFaq, pricingFaqByLocale } from '@/modules/vitrine/data/pricing-faq';

/**
 * #4192 — source unique de la FAQ tarifs : pricing-faq.ts alimente à la fois
 * l'UI /pricing et le JSON-LD FAQPage (layout #3921). Ces gardes empêchent la
 * réintroduction d'une copie désynchronisée et garantissent que le schéma
 * suit exactement le contenu visible.
 */
describe('pricing-faq single source (#4192)', () => {
  const locales = ['fr', 'en', 'tr', 'ar'] as const;

  it.each(locales)('fournit une FAQ %s non vide avec des ids uniques', (locale) => {
    const items = getPricingFaq(locale);

    expect(items.length).toBeGreaterThanOrEqual(8);
    const ids = items.map((i) => i.id);
    expect(new Set(ids).size).toBe(ids.length);
    for (const item of items) {
      expect(item.question.length).toBeGreaterThan(10);
      expect(item.answer.length).toBeGreaterThan(10);
      expect(item.category.length).toBeGreaterThan(0);
    }
  });

  it('couvre les 4 locales avec le même jeu d\'ids (parité UI / JSON-LD)', () => {
    const idSets = locales.map((locale) => new Set(getPricingFaq(locale).map((i) => i.id)));
    for (const idSet of idSets) {
      expect(idSet).toEqual(idSets[0]);
    }
  });

  it('expose les mêmes données que le catalogue par locale', () => {
    for (const locale of locales) {
      expect(getPricingFaq(locale)).toEqual(pricingFaqByLocale[locale]);
    }
  });
});
