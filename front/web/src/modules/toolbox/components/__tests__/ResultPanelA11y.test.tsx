import React from 'react';
import { render, screen } from '@testing-library/react';
import { ResultPanel } from '../ResultPanel';

/**
 * Tests d'accessibilité du panneau de résultats.
 *
 * Le résultat est recalculé à chaque frappe : sans région live, un lecteur
 * d'écran n'annonce jamais le changement. Ces tests verrouillent la présence
 * de la région ET l'exclusion des boutons d'action (sinon chaque recalcul
 * ré-annoncerait « Copier », « Partager »).
 */
describe('ResultPanel — accessibilité', () => {
  const rows = [
    { label: 'IMC', value: '22,9', emphasis: true },
    { label: 'Interprétation', value: 'Corpulence normale' },
  ];

  function renderPanel() {
    return render(
      <ResultPanel
        title="Résultat"
        rows={rows}
        copyLabel="Copier"
        copiedLabel="Copié"
        copyText="IMC: 22,9"
      />,
    );
  }

  it('expose les résultats dans une région live polie et atomique', () => {
    const { container } = renderPanel();
    const region = container.querySelector('dl');

    expect(region).not.toBeNull();
    expect(region).toHaveAttribute('aria-live', 'polite');
    expect(region).toHaveAttribute('aria-atomic', 'true');
  });

  it('garde les boutons d’action HORS de la région live', () => {
    const { container } = renderPanel();
    const region = container.querySelector('dl')!;

    expect(region.contains(screen.getByRole('button', { name: /Copier/ }))).toBe(false);
  });

  it('rend chaque ligne comme un couple terme/valeur', () => {
    renderPanel();
    expect(screen.getByText('IMC').tagName).toBe('DT');
    expect(screen.getByText('22,9').tagName).toBe('DD');
  });
});
