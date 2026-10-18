import React from 'react';
import { render, screen } from '@testing-library/react';
import { readFileSync } from 'fs';
import { join } from 'path';
import { HeroSection } from '../HeroSection';

/**
 * Vague 6 — direction artistique premium du héros « stage ».
 *
 * Ces tests verrouillent ce que la revue visuelle a validé, et rien de plus :
 *  · la PROFONDEUR est faite de trois plans nommés (ciel, halo, vignettage) ;
 *  · la PILE est celle demandée par le propriétaire — le titre passe DERRIÈRE
 *    la constellation (z-[5] < z-10) mais reste lisible (voile + ombre portée) ;
 *  · le CTA unique reste unique et l'indicateur de défilement ne le percute
 *    plus (il était superposé au bouton dans le layout `stage`).
 *
 * Les règles CSS pures (fond nuit, halos, vignettage, arc géographique) sont
 * vérifiées sur `globals.css` : elles n'ont pas d'équivalent DOM mesurable en
 * jsdom, et les figer ici évite qu'une refonte ultérieure les efface en
 * silence.
 */

jest.mock('next/navigation', () => ({
  useSearchParams: () => new URLSearchParams(''),
}));

jest.mock('framer-motion', () => {
  const actual = jest.requireActual('framer-motion');
  const passthrough = ({ children, ...props }: React.PropsWithChildren<Record<string, unknown>>) => {
    const { initial, animate, transition, style, ...rest } = props as Record<string, unknown>;
    void initial;
    void animate;
    void transition;
    void style;
    return <div {...rest}>{children}</div>;
  };
  return {
    ...actual,
    motion: new Proxy(actual.motion as Record<string, unknown>, {
      get: (target, key: string) => (key in target ? target[key] : passthrough),
    }),
    useScroll: () => ({ scrollYProgress: { get: () => 0 } }),
    useTransform: () => 0,
    useSpring: () => 0,
  };
});

const stageProps = {
  headline: 'Your whole business, in one single app.',
  animated: false,
  layout: 'stage' as const,
  visual: <div data-testid="stage-visual" />,
};

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

describe('Héros « stage » — profondeur premium (vague 6)', () => {
  it('empile trois plans de profondeur, tous décoratifs', () => {
    const { container } = render(<HeroSection {...stageProps} />);
    ['hero-stage-base', 'hero-stage-halo', 'hero-stage-vignette'].forEach((cls) => {
      const nodes = container.querySelectorAll(`.${cls}`);
      expect(nodes.length).toBeGreaterThan(0);
      nodes.forEach((node) => expect(node).toHaveAttribute('aria-hidden', 'true'));
    });
  });

  it('place le halo DERRIÈRE la scène et le vignettage AU-DESSUS, sous le CTA', () => {
    const { container } = render(<HeroSection {...stageProps} />);
    const halo = container.querySelector('.hero-stage-halo')!;
    const scene = container.querySelector('.z-10')!;
    const vignette = container.querySelector('.hero-stage-vignette')!;
    // Le halo appartient au fond (z-0), la scène est au premier plan (z-10).
    expect(halo.className).toContain('z-0');
    expect(scene.className).toContain('z-10');
    // Le vignettage referme les bords SANS jamais passer devant la scène.
    expect(vignette.className).toContain('z-[11]');
  });

  it('ramène le titre dans le MÊME FLUX que les cartes (point 4, proposition B)', () => {
    const { container } = render(<HeroSection {...stageProps} />);
    // Le titre n'est plus un calque `absolute z-[5]` indépendant : c'était
    // cette indépendance de flux — pas le z-index — qui créait la « rupture de
    // profondeur » relevée par le propriétaire produit. Il vit désormais dans
    // le conteneur de flux, au-dessus des cartes, et partage donc leur plan.
    const legacyTitleLayer = Array.from(container.querySelectorAll('div')).find((el) =>
      el.className.includes('z-[5]'),
    );
    expect(legacyTitleLayer).toBeUndefined();

    const flow = Array.from(container.querySelectorAll('div')).find(
      (el) => el.className.includes('z-10') && el.className.includes('flex-col'),
    );
    expect(flow).toBeDefined();
    expect(flow!.textContent).toContain(stageProps.headline);
    // Les cartes sont DANS ce même flux, après le titre — un seul axe vertical.
    expect(flow!.querySelector('[data-testid="stage-visual"]')).not.toBeNull();

    // Lisibilité conservée : voile radial + liseré sombre.
    expect(container.querySelector('.hero-stage-title-scrim')).not.toBeNull();
    expect(container.querySelector('.hero-stage-title-text')).not.toBeNull();
  });

  it('ne rend plus l’indicateur de défilement dans le layout `stage`', () => {
    const { container } = render(<HeroSection {...stageProps} />);
    // Il occupait `bottom-8` centré — exactement la place du CTA unique.
    const scroller = Array.from(container.querySelectorAll('div')).find(
      (el) => el.className.includes('bottom-8') && el.className.includes('left-1/2'),
    );
    expect(scroller).toBeUndefined();
    expect(screen.getByTestId('stage-visual')).toBeInTheDocument();
  });

  it('définit un fond bleu nuit/graphite, un halo et un vignettage en CSS', () => {
    const base = ruleBody('.hero-stage-base');
    expect(base).toMatch(/linear-gradient/);
    // Bleu nuit : aucune teinte purement noire en aplat.
    expect(base).toMatch(/rgb\(8 14 26\)/);

    const halo = ruleBody('.hero-stage-halo');
    expect(halo).toMatch(/radial-gradient/);

    const vignette = ruleBody('.hero-stage-vignette');
    expect(vignette).toMatch(/radial-gradient/);
    expect(vignette).toMatch(/transparent/);
  });

  it('respiration du halo neutralisée en `prefers-reduced-motion`', () => {
    // L'animation n'est déclarée QUE sous `no-preference`.
    const noPref = globalsCss.split('@media (prefers-reduced-motion: no-preference)').slice(1);
    expect(noPref.some((block) => block.slice(0, 400).includes('hero-halo-breathe'))).toBe(true);
    expect(globalsCss).toMatch(/@keyframes hero-halo-breathe/);
  });
});

describe('Arc géographique — sommet sur la constellation (vague 6)', () => {
  it('cale le sommet de l’arc sur le cœur de la scène, pas sur le titre', () => {
    const arc = ruleBody('.geo-arc');
    expect(arc).toMatch(/--geo-apex:\s*54%/);
    // Rayon resserré : l'ancien `min(54vw, 40vh)` faisait passer l'arc 20 vh trop haut.
    expect(arc).toMatch(/--geo-r:\s*min\(30vw, 21vh\)/);
  });

  it('décrit un demi-cercle avec l’échelle demandée 0.68 → 1 → 0.68', () => {
    expect(globalsCss).toMatch(/@keyframes geo-arc-sweep/);
    const frames = globalsCss.match(/@keyframes geo-arc-sweep\s*\{[\s\S]*?\n  \}/)?.[0] ?? '';
    expect(frames).toMatch(/rotate\(-76deg\)[\s\S]*scale\(0\.68\)/);
    expect(frames).toMatch(/50%\s*\{[\s\S]*?scale\(1\)/);
    expect(frames).toMatch(/100%\s*\{[\s\S]*?scale\(0\.68\)/);
  });

  it('reste DERRIÈRE la constellation sur les trois formats', () => {
    // Le calque est posé en z-[1] par le composant ; la scène est en z-10.
    const arcZ = ruleBody('.geo-arc');
    expect(arcZ).not.toMatch(/z-index/);
    expect(globalsCss).toMatch(/@media \(max-width: 1023\.98px\)[\s\S]{0,120}\.geo-arc/);
    expect(globalsCss).toMatch(/@media \(max-width: 639\.98px\)[\s\S]{0,120}\.geo-arc/);
  });
});
