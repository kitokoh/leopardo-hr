/**
 * TrustedBrands — #8392 : exactement 3 secteurs visibles, rotation aléatoire
 * au lieu du ruban défilant.
 */

import React from 'react';
import { render, screen, act } from '@testing-library/react';
import { TrustedBrands } from '../TrustedBrands';

describe('TrustedBrands (#8392)', () => {
  beforeEach(() => {
    window.localStorage.setItem('preferred_locale', 'fr');
    // `framer-motion` (motion-dom) met en cache `prefersReducedMotion` au
    // niveau MODULE, une seule fois par worker : si un autre fichier de test
    // a déjà installé un `matchMedia` renvoyant `matches: true` (les tests
    // `layout-*`), la rotation est désactivée pour tout le worker et ce test
    // devient flaky. On force donc un `matchMedia` déterministe
    // (`matches: false` = mouvement autorisé) — même approche que
    // `rtl-direction.test.tsx`.
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      configurable: true,
      value: jest.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: jest.fn(),
        removeListener: jest.fn(),
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
        dispatchEvent: jest.fn(),
      })),
    });
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('affiche exactement 3 secteurs à la fois', () => {
    render(<TrustedBrands locale="fr" />);
    // Les cartes portent les libellés secteurs (8 au catalogue, 3 visibles).
    const labels = [
      'Industrie',
      'Energie & BTP',
      'Finance & Services',
      'Aviation & Transport',
      'Telecoms',
      'Retail & Distribution',
      'Logistique',
      'Tech & IT',
    ];
    const visible = labels.filter((label) => screen.queryByText(label) !== null);
    expect(visible).toHaveLength(3);
  });

  it('la rotation aléatoire change le contenu après l\'intervalle', () => {
    const { container } = render(<TrustedBrands locale="fr" />);
    const avant = container.textContent;
    act(() => {
      // 3 intervalles de rotation écoulés.
      jest.advanceTimersByTime(11_000);
    });
    // De nouvelles cartes ont été montées (les sortantes restent en DOM sous
    // faux timers jsdom — elles disparaissent réellement en navigateur).
    expect(container.textContent).not.toBe(avant);
  });

  it('aucun ruban défilant (marquee) dans le markup', () => {
    const { container } = render(<TrustedBrands locale="fr" />);
    expect(container.querySelector('.marquee-scroll')).toBeNull();
  });
});
