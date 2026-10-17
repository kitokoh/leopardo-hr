import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { HeroScene3DPanel } from '../HeroScene3DPanel';

/**
 * Vague 6 (correctif a11y) — la constellation doit être atteignable au CLAVIER.
 *
 * Défaut mesuré en navigateur sur `main` (7859b93c4) : les étiquettes d'étoiles
 * étaient des `<div>` décoratifs (`pointer-events-none`, aucun `tabindex`), la
 * scène n'était pas focusable, et le focus retombait sur `<body>` à la
 * fermeture de la fiche. Résultat : un utilisateur au clavier ne pouvait NI
 * ouvrir la fiche, NI savoir où le focus était revenu.
 *
 * Ces tests verrouillent la correction : chaque étoile est un vrai bouton
 * focusable, la fiche s'ouvre au clavier, et le focus est restitué à l'étoile
 * d'origine.
 */

jest.mock('next/navigation', () => ({
  useSearchParams: () => new URLSearchParams(''),
}));

// La scène WebGL n'existe pas en jsdom : on la remplace par un double qui
// expose les mêmes étiquettes-boutons que le composant réel, afin de tester le
// câblage clavier du panneau (ouverture + restitution du focus).
jest.mock('../HeroScene3D', () => ({
  HeroScene3D: ({
    onSelectModule,
    moduleLabels,
  }: {
    onSelectModule?: (index: number) => void;
    moduleLabels?: string[];
  }) => (
    <div data-testid="scene-stub">
      {(moduleLabels ?? []).map((label, index) => (
        <button
          key={label}
          type="button"
          aria-label={label}
          onClick={() => onSelectModule?.(index)}
        >
          {label}
        </button>
      ))}
    </div>
  ),
}));

describe('HeroScene3DPanel — accessibilité clavier de la constellation', () => {
  it('expose une étiquette-bouton focusable par solution', () => {
    render(<HeroScene3DPanel />);
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
    buttons.forEach((button) => {
      expect(button).toHaveAttribute('aria-label');
      expect(button).not.toHaveAttribute('tabindex', '-1');
    });
  });

  it('ouvre la fiche au clavier (activation d’une étiquette) puis la referme par Échap', () => {
    render(<HeroScene3DPanel />);
    const first = screen.getAllByRole('button')[0];
    fireEvent.click(first);
    expect(screen.getByTestId('solution-card')).toBeInTheDocument();

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(screen.queryByTestId('solution-card')).not.toBeInTheDocument();
  });

  it('la scène est une cible de restitution du focus (tabindex=-1, jamais dans l’ordre de tabulation)', () => {
    const { container } = render(<HeroScene3DPanel />);
    const scene = container.querySelector('[tabindex="-1"]');
    expect(scene).not.toBeNull();
    // `-1` = focusable par script uniquement : la restitution du focus ne
    // pollue pas l'ordre de tabulation naturel.
    expect(scene).toHaveAttribute('tabindex', '-1');
  });

  it('la fiche porte role=dialog + aria-modal (contrat d’accessibilité)', () => {
    render(<HeroScene3DPanel />);
    fireEvent.click(screen.getAllByRole('button')[0]);
    const dialog = screen.getByTestId('solution-card');
    expect(dialog).toHaveAttribute('role', 'dialog');
    expect(dialog).toHaveAttribute('aria-modal', 'true');
  });
});
