import React from 'react';
import { render, screen } from '@testing-library/react';
import { ToolCard } from '../ToolCard';

/**
 * Tests de la carte d'outil — lien, libellés, catégorie.
 */
describe('ToolCard', () => {
  it('rend un lien vers la page de l’outil', () => {
    render(
      <ToolCard
        href="/toolbox/imc"
        name="IMC — Indice de masse corporelle"
        description="Calculez votre IMC."
        categoryLabel="Santé"
        openLabel="Ouvrir l’outil"
      />,
    );
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/toolbox/imc');
    expect(screen.getByText('IMC — Indice de masse corporelle')).toBeInTheDocument();
    expect(screen.getByText('Santé')).toBeInTheDocument();
    expect(screen.getByText('Ouvrir l’outil')).toBeInTheDocument();
  });
});
