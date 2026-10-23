/**
 * TrustedBrands — #8392 : exactement 3 secteurs visibles, rotation aléatoire
 * au lieu du ruban défilant.
 */

import React from 'react';
import { render, screen, act } from '@testing-library/react';
import { TrustedBrands, nextVisibleSectors } from '../TrustedBrands';

// `framer-motion` (via `motion-dom`) met en cache `prefersReducedMotion` au
// niveau MODULE, une seule fois par worker : si un autre fichier de test a déjà
// installé un `matchMedia` renvoyant `matches: true` (les tests `layout-*`),
// `hasReducedMotionListener` passe à `true` et la préférence n'est plus jamais
// relue — redéfinir `matchMedia` dans `beforeEach` ne suffit donc pas, et la
// rotation restait désactivée pour tout le worker. On neutralise le hook à la
// source : le test ne dépend plus de l'ordre d'exécution des fichiers.
jest.mock('framer-motion', () => {
  const actual = jest.requireActual('framer-motion');
  return { ...actual, useReducedMotion: () => false };
});

describe('TrustedBrands (#8392)', () => {
  beforeEach(() => {
    window.localStorage.setItem('preferred_locale', 'fr');
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
    jest.restoreAllMocks();
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

  it('la rotation aléatoire change le contenu après l’intervalle', () => {
    // Aléatoire figé : le test ne doit pas dépendre du tirage. `nextVisibleSectors`
    // garantit déjà le changement par construction ; on vérifie ici le câblage
    // composant → rotation.
    jest.spyOn(Math, 'random').mockReturnValue(0);

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

/**
 * La garantie « le trio change à chaque tick » est une propriété de la fonction
 * de rotation, pas du rendu : on l'éprouve sur 500 tirages, y compris les
 * valeurs extrêmes que `Math.random` peut renvoyer une fois stubé (0 et 1).
 */
describe('nextVisibleSectors (#8392 — rotation garantie)', () => {
  const TOTAL = 8;
  const asTrio = (indices: number[]) => [...indices].sort((a, b) => a - b).join(',');

  it('change toujours le trio, quel que soit le tirage', () => {
    let trio: number[] = [0, 1, 2];
    for (let i = 0; i < 500; i += 1) {
      const next = nextVisibleSectors(trio, TOTAL, Math.random);
      expect(asTrio(next)).not.toBe(asTrio(trio));
      trio = next;
    }
  });

  it('reste dans les bornes quand le tirage renvoie 0 puis 1', () => {
    for (const value of [0, 1, 0.999999, 0.5]) {
      const next = nextVisibleSectors([0, 1, 2], TOTAL, () => value);
      expect(next).toHaveLength(3);
      expect(new Set(next).size).toBe(3);
      next.forEach((index) => {
        expect(index).toBeGreaterThanOrEqual(0);
        expect(index).toBeLessThan(TOTAL);
      });
      expect(asTrio(next)).not.toBe('0,1,2');
    }
  });

  it('ne boucle pas quand tous les secteurs sont déjà affichés', () => {
    const all = [0, 1, 2, 3, 4, 5, 6, 7];
    expect(nextVisibleSectors(all, TOTAL, Math.random)).toBe(all);
  });
});
