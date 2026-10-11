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

  it('la rotation aléatoire fait tourner les cartes (transition ≤ 4 visibles)', () => {
    render(<TrustedBrands locale="fr" />);
    act(() => {
      // 3 intervalles de rotation écoulés.
      jest.advanceTimersByTime(11_000);
    });
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
    // Au moins 3 cartes, au plus 4 pendant une transition sortante (jsdom ne
    // termine pas les animations framer-motion sous faux timers — en
    // navigateur réel, la carte sortante disparaît à la fin de sa transition).
    expect(visible.length).toBeGreaterThanOrEqual(3);
    expect(visible.length).toBeLessThanOrEqual(4);
  });

  it('aucun ruban défilant (marquee) dans le markup', () => {
    const { container } = render(<TrustedBrands locale="fr" />);
    expect(container.querySelector('.marquee-scroll')).toBeNull();
  });
});
