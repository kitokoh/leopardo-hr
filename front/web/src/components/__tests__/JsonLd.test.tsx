/**
 * #4403 — JSON-LD : toutes les Offers ont un prix machine valide, données
 * localisées par locale (plus de FR en dur sur les pages en/tr/ar).
 */
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { OrganizationJsonLd } from '../JsonLd';

function extractOffers(locale: string) {
  const html = renderToStaticMarkup(React.createElement(OrganizationJsonLd, { locale }));
  const json = JSON.parse(
    html.replace(/^<script[^>]*>/, '').replace(/<\/script>$/, ''),
  ) as { offers?: Array<{ '@type': string; name: string; description: string; price?: number; priceCurrency?: string }> };
  expect(json.offers).toBeDefined();
  return json.offers!;
}

describe('OrganizationJsonLd (#4403)', () => {
  it('emits only machine-priced plans as Offers (Enterprise « sur devis » excluded)', () => {
    const offers = extractOffers('fr');
    const names = offers.map((o) => o.name);
    expect(names).toContain('Free');
    expect(names).toContain('Pilot');
    expect(names).toContain('Operations');
    expect(names.some((n) => n.toLowerCase().includes('enterprise'))).toBe(false);
  });

  it('every Offer carries a finite price + priceCurrency', () => {
    for (const locale of ['fr', 'en', 'tr', 'ar']) {
      for (const offer of extractOffers(locale)) {
        expect(offer['@type']).toBe('Offer');
        expect(Number.isFinite(offer.price)).toBe(true);
        expect(offer.priceCurrency).toBe('EUR');
      }
    }
  });

  it('localizes descriptions per locale (no FR leak on en/tr/ar)', () => {
    const fr = extractOffers('fr').map((o) => o.description).join(' ');
    const en = extractOffers('en').map((o) => o.description).join(' ');
    expect(fr).not.toBe(en);
  });
});
