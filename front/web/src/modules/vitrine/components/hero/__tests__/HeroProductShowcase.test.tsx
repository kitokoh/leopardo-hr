import React from 'react';
import { render, screen } from '@testing-library/react';
import { HeroProductShowcase } from '../HeroProductShowcase';

/**
 * Le héro doit montrer le produit réel (screenshot, pas la mascotte) et un
 * badge de réassurance « cloud ou auto-hébergé » — la promesse Business OS,
 * sans vocabulaire de licence.
 */
describe('HeroProductShowcase', () => {
  it('renders the real product screenshot with a localized alt', () => {
    render(<HeroProductShowcase locale="fr" />);
    const img = screen.getByRole('img');
    expect(img.getAttribute('src')).toContain('web-dashboard.png');
    expect(img.getAttribute('alt')).toMatch(/tableau de bord/i);
  });

  it('renders a static trust badge linking to pricing (cloud or self-hosted)', () => {
    render(<HeroProductShowcase locale="en" />);
    const badge = screen.getByTestId('hero-trust-badge');
    expect(badge).toHaveAttribute('href', '/pricing');
    expect(badge.textContent).toMatch(/cloud or self-hosted/i);
    expect(badge.textContent).not.toMatch(/open[ -]?source/i);
  });

  it('never mentions open source, whatever the locale', () => {
    for (const locale of ['fr', 'en', 'tr', 'ar'] as const) {
      const { unmount } = render(<HeroProductShowcase locale={locale} />);
      const badge = screen.getByTestId('hero-trust-badge');
      expect(badge.textContent).not.toMatch(/open[ -]?source|a[cç][iı]k kaynak|مفتوح المصدر/i);
      unmount();
    }
  });
});
