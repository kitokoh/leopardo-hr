import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { readFileSync } from 'fs';
import { join } from 'path';
import { HeroOrbitCards } from '../HeroOrbitCards';
import { SOLUTIONS } from '../../../data/solution-catalog';

/**
 * Point 4 (proposition B) — les solutions métier sont des CARTES EN ORBITE
 * autour du noyau, et non plus des losanges anonymes projetés par le canvas.
 *
 * Ces tests verrouillent ce que la décision produit engage :
 *  · une carte par solution, chacune un VRAI bouton (clavier, lecteur d'écran) ;
 *  · un mouvement orbital en CSS PUR (`transform: rotate`), jamais un recalcul
 *    JavaScript par image — c'est l'exigence explicite du point 3 ;
 *  · une variance par étoile (rayon, période, sens, phase) : aucune carte ne
 *    tourne au même rythme, donc pas d'effet « rouage » ;
 *  · `prefers-reduced-motion` neutralise le mouvement sans retirer l'accès.
 *
 * Les règles CSS pures sont vérifiées sur `globals.css` : elles n'ont pas
 * d'équivalent DOM mesurable en jsdom, et les figer ici évite qu'une refonte
 * ultérieure les efface en silence.
 */

jest.mock('next/navigation', () => ({
  useSearchParams: () => new URLSearchParams(''),
}));

const globalsCss = readFileSync(join(process.cwd(), 'src/app/globals.css'), 'utf8');

/** Corps d'une règle CSS (première occurrence), commentaires retirés. */
function ruleBody(selector: string): string {
  const start = globalsCss.indexOf(`${selector} {`);
  if (start === -1) return '';
  const open = globalsCss.indexOf('{', start);
  const close = globalsCss.indexOf('}', open);
  if (open === -1 || close === -1) return '';
  return globalsCss
    .slice(open + 1, close)
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .trim();
}

describe('HeroOrbitCards — les solutions en orbite autour du noyau (point 4)', () => {
  it('rend une carte-bouton par solution du catalogue', () => {
    render(<HeroOrbitCards onSelect={jest.fn()} />);
    const cards = screen.getAllByRole('button');
    expect(cards).toHaveLength(SOLUTIONS.length);
    cards.forEach((card) => {
      // Un nom accessible est obligatoire : le libellé visible est court et
      // traduit, il ne suffit pas à décrire l'action.
      expect(card).toHaveAttribute('aria-label');
      expect(card.getAttribute('aria-label')!.length).toBeGreaterThan(0);
    });
  });

  it('ouvre la fiche de la solution cliquée (identifiant, pas index)', () => {
    const onSelect = jest.fn();
    render(<HeroOrbitCards onSelect={onSelect} />);
    fireEvent.click(screen.getByTestId(`hero-orbit-card-${SOLUTIONS[0].id}`));
    expect(onSelect).toHaveBeenCalledWith(SOLUTIONS[0].id);
  });

  it('marque la carte ouverte via aria-expanded (contrat de dialogue)', () => {
    const { rerender } = render(<HeroOrbitCards onSelect={jest.fn()} />);
    const first = screen.getByTestId(`hero-orbit-card-${SOLUTIONS[0].id}`);
    expect(first).toHaveAttribute('aria-expanded', 'false');
    expect(first).toHaveAttribute('aria-haspopup', 'dialog');

    rerender(<HeroOrbitCards onSelect={jest.fn()} selectedId={SOLUTIONS[0].id} />);
    expect(screen.getByTestId(`hero-orbit-card-${SOLUTIONS[0].id}`)).toHaveAttribute(
      'aria-expanded',
      'true',
    );
  });

  it('ne masque AUCUNE carte aux technologies d’assistance', () => {
    const { container } = render(<HeroOrbitCards onSelect={jest.fn()} />);
    // L'anneau porte les boutons : le marquer `aria-hidden` retirerait de vrais
    // contrôles de l'arbre d'accessibilité tout en les laissant focusables —
    // la violation exacte déjà corrigée une fois sur les étiquettes canvas.
    container.querySelectorAll('.hero-orbit-ring').forEach((ring) => {
      expect(ring).not.toHaveAttribute('aria-hidden');
    });
    // Les repères d'orbite et le noyau, eux, sont bien décoratifs.
    container.querySelectorAll('.hero-orbit-ring-guide, .hero-orbit-core').forEach((decor) => {
      expect(decor).toHaveAttribute('aria-hidden', 'true');
    });
  });

  it('varie rayon, période, sens et phase par carte (pas d’effet rouage)', () => {
    const { container } = render(<HeroOrbitCards onSelect={jest.fn()} />);
    const cards = screen.getAllByRole('button');
    // Le rayon est porté par la POSITION de la carte sur le cercle (left/top en
    // pourcentage), pas par une variable CSS : c'est ce qui garantit une
    // trajectoire exacte (cf. note dans globals.css).
    const radii = new Set(
      cards.map((c) => {
        const el = c as HTMLElement;
        const dx = parseFloat(el.style.left) - 50;
        const dy = parseFloat(el.style.top) - 50;
        return Math.round(Math.hypot(dx, dy));
      }),
    );
    const delays = new Set(
      cards.map((c) => (c as HTMLElement).style.getPropertyValue('--orbit-delay')),
    );
    // Les anneaux portent la période et le sens, et diffèrent entre eux.
    const rings = Array.from(container.querySelectorAll('.hero-orbit-ring')) as HTMLElement[];
    const durations = new Set(rings.map((r) => r.style.getPropertyValue('--orbit-dur')));
    const dirs = new Set(rings.map((r) => r.style.getPropertyValue('--orbit-dir')));
    // Au moins deux rayons, deux périodes, deux sens et plusieurs phases.
    expect(radii.size).toBeGreaterThan(1);
    expect(durations.size).toBe(2);
    expect(dirs.size).toBe(2);
    expect(delays.size).toBeGreaterThan(1);
  });

  it('anime l’orbite en CSS pur (rotate), jamais par recalcul JS', () => {
    // Le mouvement vient d'animations CSS sur `transform: rotate`.
    expect(globalsCss).toMatch(/@keyframes hero-orbit-spin/);
    expect(globalsCss).toMatch(/@keyframes hero-orbit-counter-spin/);
    expect(globalsCss).toMatch(/animation:\s*hero-orbit-spin/);
    // L'anneau tourne ; la contre-rotation vit sur l'élément INTERNE, ce qui
    // laisse le recentrage de la carte statique (donc son centre exactement sur
    // le cercle) — c'est la correction du défaut de dérive mesuré en navigateur.
    expect(globalsCss).toMatch(/\.hero-orbit-card-inner\s*\{[^}]*animation:\s*hero-orbit-counter-spin/);
    const card = ruleBody('.hero-orbit-card');
    expect(card).toMatch(/transform:\s*translate\(-50%,\s*-50%\)/);
    // Le recentrage de la carte n'est jamais animé.
    expect(card).not.toMatch(/animation:/);
  });

  it('neutralise le mouvement en `prefers-reduced-motion` sans retirer l’accès', () => {
    // Les animations ne sont déclarées QUE sous `no-preference`.
    const noPref = globalsCss.split('@media (prefers-reduced-motion: no-preference)').slice(1);
    expect(noPref.some((block) => block.slice(0, 600).includes('hero-orbit-spin'))).toBe(true);
    // Hors `no-preference`, la carte garde sa position statique sur l'orbite.
    const card = ruleBody('.hero-orbit-card');
    expect(card).toMatch(/transform:/);
    // Et les cartes restent des boutons focusables dans tous les cas.
    render(<HeroOrbitCards onSelect={jest.fn()} />);
    screen.getAllByRole('button').forEach((card) => {
      expect(card).not.toHaveAttribute('tabindex', '-1');
    });
  });
});
