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
