import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ResultPanel } from '../ResultPanel';

/**
 * Tests du panneau de résultats — affichage structuré et copie presse-papiers.
 */
describe('ResultPanel', () => {
  const rows = [
    { label: 'IMC', value: '22,9', emphasis: true },
    { label: 'Interprétation', value: 'Corpulence normale' },
  ];

  it('affiche le titre et toutes les lignes', () => {
    render(
      <ResultPanel title="Résultat" rows={rows} copyLabel="Copier" copiedLabel="Copié" copyText="IMC: 22,9" />,
    );
    expect(screen.getByText('Résultat')).toBeInTheDocument();
    expect(screen.getByText('IMC')).toBeInTheDocument();
    expect(screen.getByText('22,9')).toBeInTheDocument();
    expect(screen.getByText('Corpulence normale')).toBeInTheDocument();
  });

  it('copie le texte dans le presse-papiers et confirme', async () => {
    const writeText = jest.fn().mockResolvedValue(undefined);
    Object.assign(navigator, { clipboard: { writeText } });

    render(
      <ResultPanel title="Résultat" rows={rows} copyLabel="Copier" copiedLabel="Copié" copyText="IMC: 22,9" />,
    );
    fireEvent.click(screen.getByRole('button', { name: /Copier/ }));

    await waitFor(() => expect(writeText).toHaveBeenCalledWith('IMC: 22,9'));
    await waitFor(() => expect(screen.getByRole('button', { name: /Copié/ })).toBeInTheDocument());
  });

  it('n’échoue pas si le presse-papiers est indisponible', async () => {
    Object.assign(navigator, {
      clipboard: { writeText: jest.fn().mockRejectedValue(new Error('denied')) },
    });
    render(
      <ResultPanel title="Résultat" rows={rows} copyLabel="Copier" copiedLabel="Copié" copyText="x" />,
    );
    fireEvent.click(screen.getByRole('button', { name: /Copier/ }));
    // Le bouton reste sur « Copier » : aucune exception remontée.
    await waitFor(() => expect(screen.getByRole('button', { name: /Copier/ })).toBeInTheDocument());
  });
});
