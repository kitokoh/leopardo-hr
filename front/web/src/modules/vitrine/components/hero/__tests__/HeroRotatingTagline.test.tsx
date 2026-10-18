import React from 'react';
import { act, render, screen } from '@testing-library/react';
import { HeroRotatingTagline } from '../HeroRotatingTagline';

/**
 * Passe Fondateur (#8703) — le petit texte secondaire du héros : plusieurs
 * phrases se succèdent AU MÊME ENDROIT, sous la phrase principale.
 *
 * Ces tests verrouillent le contrat demandé par le propriétaire :
 *  · les phrases tournent (l'une s'efface, la suivante prend sa place) ;
 *  · la rotation se fait SANS saut de mise en page (même cellule de grille) ;
 *  · `prefers-reduced-motion` fige la première phrase, contenu complet ;
 *  · les lecteurs d'écran ne subissent pas la rotation (sr-only unique).
 */

const PHRASES = ['Phrase une.', 'Phrase deux.', 'Phrase trois.'];

function mockMatchMedia(matches: boolean) {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: jest.fn().mockImplementation((query: string) => ({
      matches,
      media: query,
      onchange: null,
      addEventListener: jest.fn(),
      removeEventListener: jest.fn(),
      addListener: jest.fn(),
      removeListener: jest.fn(),
      dispatchEvent: jest.fn(),
    })),
  });
}

/** Cellules de la pile rotative (les spans aria-hidden, hors sr-only). */
function cells(root: HTMLElement): HTMLElement[] {
  return Array.from(root.querySelectorAll('span[aria-hidden="true"]')) as HTMLElement[];
}

describe('HeroRotatingTagline — texte secondaire rotatif (#8703)', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    mockMatchMedia(false);
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('empile toutes les phrases dans la MÊME cellule (aucun saut de mise en page)', () => {
    render(<HeroRotatingTagline phrases={PHRASES} />);
    const root = screen.getByTestId('hero-rotating-tagline');
    const stacked = cells(root);
    expect(stacked).toHaveLength(PHRASES.length);
    stacked.forEach((cell) => {
      expect(cell.className).toContain('col-start-1');
      expect(cell.className).toContain('row-start-1');
    });
    // La première phrase est visible, les autres effacées.
    expect(stacked[0].className).toContain('opacity-100');
    expect(stacked[1].className).toContain('opacity-0');
  });

  it('fait défiler les phrases au même endroit, puis boucle', () => {
    render(<HeroRotatingTagline phrases={PHRASES} intervalMs={1000} />);
    const root = screen.getByTestId('hero-rotating-tagline');

    act(() => {
      jest.advanceTimersByTime(1000);
    });
    expect(cells(root)[1].className).toContain('opacity-100');
    expect(cells(root)[0].className).toContain('opacity-0');

    act(() => {
      jest.advanceTimersByTime(2000);
    });
    // Boucle complète : retour à la première phrase.
    expect(cells(root)[0].className).toContain('opacity-100');
  });

  it('reste figé sur la première phrase quand le mouvement est réduit', () => {
    mockMatchMedia(true);
    render(<HeroRotatingTagline phrases={PHRASES} intervalMs={1000} />);
    const root = screen.getByTestId('hero-rotating-tagline');

    act(() => {
      jest.advanceTimersByTime(5000);
    });
    expect(cells(root)[0].className).toContain('opacity-100');
    expect(cells(root)[1].className).toContain('opacity-0');
  });

  it('annonce une seule phrase aux lecteurs d’écran (rotation aria-hidden)', () => {
    render(<HeroRotatingTagline phrases={PHRASES} />);
    const root = screen.getByTestId('hero-rotating-tagline');
    const srOnly = root.querySelector('.sr-only');
    expect(srOnly).not.toBeNull();
    expect(srOnly!.textContent).toBe(PHRASES[0]);
    // La pile rotative est décorative pour les technologies d'assistance.
    cells(root).forEach((cell) => expect(cell).toHaveAttribute('aria-hidden', 'true'));
  });

  it('ne rend rien sans phrase, et une phrase seule reste statique', () => {
    const { container } = render(<HeroRotatingTagline phrases={[]} />);
    expect(container.firstChild).toBeNull();

    render(<HeroRotatingTagline phrases={['Seule.']} intervalMs={1000} />);
    const root = screen.getByTestId('hero-rotating-tagline');
    act(() => {
      jest.advanceTimersByTime(3000);
    });
    expect(cells(root)[0].className).toContain('opacity-100');
  });
});
