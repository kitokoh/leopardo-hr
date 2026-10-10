import React from 'react';
import { render } from '@testing-library/react';
import { HeroScene3D } from '../HeroScene3D';

/**
 * En environnement sans WebGL ni matchMedia (jsdom), le héro doit servir le
 * repli 2D (ParticleField) sans erreur — jamais d'écran cassé.
 */
describe('HeroScene3D', () => {
  it('renders the 2D particle fallback when WebGL detection cannot run', () => {
    const { container } = render(<HeroScene3D />);
    expect(container.querySelector('canvas')).not.toBeNull();
  });

  it('does not load the WebGL scene synchronously (idle-deferred enhancement)', () => {
    const { container } = render(<HeroScene3D />);
    // Pas d'hôte 3D au premier rendu : la scène n'arrive qu'après le
    // requestIdleCallback, et uniquement si WebGL est disponible.
    expect(container.querySelector('div[aria-hidden="true"]')).toBeNull();
  });
});
