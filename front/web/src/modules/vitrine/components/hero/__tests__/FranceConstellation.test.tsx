import React from 'react';
import { render } from '@testing-library/react';
import { FranceConstellation } from '../FranceConstellation';

/**
 * #8461 (vague 4) — la France en constellation derrière la scène 3D :
 * purement décorative (aria-hidden, aucun texte — rien à traduire),
 * silhouette + villes-nœuds + maillage, classes d'animation portées par le
 * parent (dérive) et les nœuds (scintillement).
 */
describe('FranceConstellation', () => {
  it('renders a decorative, text-free SVG', () => {
    const { container } = render(<FranceConstellation />);
    const svg = container.querySelector('svg');
    expect(svg).not.toBeNull();
    expect(svg).toHaveAttribute('aria-hidden', 'true');
    expect(svg).toHaveAttribute('focusable', 'false');
    // Aucun texte : rien à traduire (garde PA2-I18N-014), rien à lire.
    expect(container.textContent).toBe('');
  });

  it('draws the Hexagone outline, the city mesh and the glowing city nodes', () => {
    const { container } = render(<FranceConstellation />);
    // Contour + voile intérieur = 2 polygones.
    expect(container.querySelectorAll('polygon')).toHaveLength(2);
    // 12 liens du maillage intérieur.
    expect(container.querySelectorAll('line')).toHaveLength(12);
    // 10 villes × (halo + cœur) = 20 cercles.
    expect(container.querySelectorAll('circle')).toHaveLength(20);
    // Chaque ville porte la classe de scintillement (neutralisée en
    // prefers-reduced-motion côté CSS).
    expect(container.querySelectorAll('g.france-city-twinkle')).toHaveLength(10);
  });

  it('passes the parent className through (drift + sizing from HeroSection)', () => {
    const { container } = render(<FranceConstellation className="france-constellation-drift h-40" />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('class')).toContain('france-constellation-drift');
    expect(svg?.getAttribute('class')).toContain('h-40');
  });
});
