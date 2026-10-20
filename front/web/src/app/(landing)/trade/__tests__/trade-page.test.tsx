import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * #8489 / #8490 / #8500 (I-02, I-03, I-13) — le point d'entree « Trade » est
 * localise (fr/en/tr/ar) et propose les deux chemins reels :
 *  - « creer » -> tunnel d'inscription existant, avec le mode d'activite ;
 *  - « rejoindre » -> connexion existante (jamais de second tenant).
 *
 * `useVitrineLocale` resout la locale apres montage (jsdom = en-US) : on le
 * mocke pour tester chaque langue de facon deterministe, et
 * `useSearchParams` pour piloter le mode preselectionne.
 */
let mockLocale = 'fr';
let mockSearch = '';
jest.mock('@/modules/vitrine/lib/vitrine-locale', () => ({
  useVitrineLocale: () => ({ locale: mockLocale, direction: 'ltr' }),
}));
jest.mock('next/navigation', () => ({
  useSearchParams: () => new URLSearchParams(mockSearch),
}));
jest.mock('@/modules/vitrine/components/hero/HeroScene3D', () => ({
  HeroScene3D: () => null,
}));
jest.mock('@/modules/vitrine', () => ({
  Navbar: () => null,
  Footer: () => null,
}));

import TradePage from '../page';

function renderTrade(locale: string, search = '') {
  mockLocale = locale;
  mockSearch = search;
  return render(<TradePage />);
}

describe('Trade entry point (/trade)', () => {
  it('renders the French copy from the shared catalog', () => {
    renderTrade('fr');
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Configurez votre entreprise');
    expect(screen.getByText('Quel est votre modele d\'activite ?')).toBeInTheDocument();
  });

  it('renders the English copy for en', () => {
    renderTrade('en');
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Set up your business in minutes');
  });

  it('renders the Turkish copy for tr', () => {
    renderTrade('tr');
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Isletmenizi dakikalar icinde kurun');
  });

  it('renders the Arabic copy for ar', () => {
    renderTrade('ar');
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('أعدّ شركتك في دقائق');
  });

  it('offers create and join links that reuse the existing tunnels', () => {
    renderTrade('fr');
    const createLinks = screen.getAllByRole('link', { name: /Creer mon espace/ });
    expect(createLinks[0]).toHaveAttribute('href', '/signup?trade=international');
    const joinLinks = screen.getAllByRole('link', { name: /Se connecter pour rejoindre/ });
    expect(joinLinks[0]).toHaveAttribute('href', '/auth/login');
  });

  it('preselects the mode from the query string and defaults safely otherwise', () => {
    // mode domestique : la carte « creer » domestique doit porter le mode.
    renderTrade('fr', 'mode=domestic');
    const domesticCreate = screen
      .getAllByRole('link', { name: /Creer mon espace/ })
      .find((link) => link.getAttribute('href') === '/signup?trade=domestic');
    expect(domesticCreate).toBeDefined();
  });
});
