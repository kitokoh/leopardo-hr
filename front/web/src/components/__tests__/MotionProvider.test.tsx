import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * BOS-B-05 — `prefers-reduced-motion` doit être respecté par toutes les
 * animations framer-motion de l'app. `MotionProvider` (#8063) pose
 * `MotionConfig reducedMotion="user"` : framer-motion neutralise alors les
 * transforms quand l'utilisateur demande moins de mouvement.
 *
 * On ne teste pas le rendu interne de framer-motion (déjà couvert par la
 * lib) : on vérifie le CONTRAT — le provider transmet bien `reducedMotion`
 * = "user" à `MotionConfig`, et rend ses enfants. Si quelqu'un retire la
 * prop, ce test échoue.
 */
const motionConfigSpy = jest.fn();
jest.mock('framer-motion', () => ({
  MotionConfig: (props: { reducedMotion?: string; children?: React.ReactNode }) => {
    motionConfigSpy(props.reducedMotion);
    return <div data-testid="motion-config">{props.children}</div>;
  },
}));

import { MotionProvider } from '../MotionProvider';

describe('MotionProvider (prefers-reduced-motion)', () => {
  beforeEach(() => {
    motionConfigSpy.mockClear();
  });

  it('renders its children', () => {
    render(
      <MotionProvider>
        <span>contenu</span>
      </MotionProvider>,
    );
    expect(screen.getByText('contenu')).toBeInTheDocument();
  });

  it('configures framer-motion with reducedMotion="user"', () => {
    render(
      <MotionProvider>
        <span>contenu</span>
      </MotionProvider>,
    );
    expect(motionConfigSpy).toHaveBeenCalledWith('user');
  });
});
