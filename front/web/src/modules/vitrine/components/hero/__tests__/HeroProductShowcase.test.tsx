import React from 'react';
import { render, screen } from '@testing-library/react';
import { HeroProductShowcase } from '../HeroProductShowcase';
import { GITHUB_REPO_URL } from '@/modules/vitrine/data/github-repo';

/**
 * #8067 — le héro doit montrer le produit réel (screenshot, pas la mascotte)
 * et un signal open-source cliquable, statique au build (pas d'appel réseau).
 */
describe('HeroProductShowcase', () => {
  it('renders the real product screenshot with a localized alt', () => {
    render(<HeroProductShowcase locale="fr" />);
    const img = screen.getByRole('img');
    expect(img.getAttribute('src')).toContain('web-dashboard.png');
    expect(img.getAttribute('alt')).toMatch(/tableau de bord/i);
  });

  it('renders a clickable static GitHub badge (stars, license, repo link)', () => {
    render(<HeroProductShowcase locale="en" />);
    const badge = screen.getByTestId('hero-github-badge');
    expect(badge).toHaveAttribute('href', GITHUB_REPO_URL);
    expect(badge.textContent).toMatch(/GitHub/);
    expect(badge.textContent).toMatch(/MIT/);
  });
});
